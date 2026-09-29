<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AuditLogService — PHASE 4.
 *
 * The single writer for `audit_logs`. Controllers call record() after a
 * meaningful administrative action; nothing else writes to the table.
 *
 * DESIGN RULES
 * ------------
 * 1. REDACTION FIRST. Context values are never stored raw. A denylist of key
 *    names (password, token, secret, key, account_number, upi, qr, ...)
 *    replaces the value with '[redacted]' — and the replacement is recursive,
 *    so a nested payload cannot smuggle a secret past the filter.
 * 2. NO REQUEST PAYLOADS. Callers describe what happened in their own words;
 *    passing $request->all() is never required and is discouraged.
 * 3. NEVER THROWS. An audit failure must never roll back or break the action
 *    the administrator actually performed; it is logged to the app log only.
 * 4. EXPLICIT TARGETS. Target type/id describe the affected record so the UI
 *    can link back to it without storing a snapshot of sensitive columns.
 */
class AuditLogService
{
    /**
     * Context key fragments that must never be persisted. Matched
     * case-insensitively as substrings so "seller_account_number",
     * "MAIL_PASSWORD" and "password_confirmation" are all caught.
     *
     * @var array<int, string>
     */
    public const REDACTED_KEYS = [
        'password',
        'passwd',
        'secret',
        'token',
        'api_key',
        'apikey',
        'access_key',
        'app_key',
        'private',
        'credential',
        'account_number',
        'accountnumber',
        'bank_account',
        'ifsc',
        'upi',
        'qr',
        'cvv',
        'card_number',
        'remember_token',
        'authorization',
    ];

    /**
     * The canonical action keys this application records. Kept as constants so
     * a typo can never silently create an unfilterable action.
     */
    public const PRODUCT_CREATED = 'product.created';
    public const PRODUCT_UPDATED = 'product.updated';
    public const PRODUCT_DELETED = 'product.deleted';
    public const PRODUCT_APPROVED = 'product.approved';
    public const SELLER_APPROVED = 'seller.approved';
    public const SELLER_REJECTED = 'seller.rejected';
    public const ORDER_APPROVED = 'order.approved';
    public const ORDER_STATUS_CHANGED = 'order.status_changed';
    public const DELIVERY_ASSIGNED = 'delivery.assigned';
    public const RETURN_APPROVED = 'return.approved';
    public const RETURN_REJECTED = 'return.rejected';
    public const PAYMENT_PROFILE_VERIFIED = 'payment_profile.verified';
    public const PAYMENT_PROFILE_REJECTED = 'payment_profile.rejected';
    public const COUPON_CREATED = 'coupon.created';
    public const COUPON_UPDATED = 'coupon.updated';
    public const COUPON_DELETED = 'coupon.deleted';
    public const USER_ROLE_CHANGED = 'user.role_changed';
    public const USER_STATUS_CHANGED = 'user.status_changed';
    public const BACKUP_CREATED = 'backup.created';
    public const BACKUP_RESTORED = 'backup.restored';

    /**
     * Every action this service can write, with its human label. Used to build
     * the audit-log filter dropdown without a second source of truth.
     *
     * @return array<string, string>
     */
    public static function catalogue(): array
    {
        return [
            self::PRODUCT_CREATED          => 'Product Created',
            self::PRODUCT_UPDATED          => 'Product Edited',
            self::PRODUCT_DELETED          => 'Product Deleted',
            self::PRODUCT_APPROVED         => 'Product Approved',
            self::SELLER_APPROVED          => 'Seller Approved',
            self::SELLER_REJECTED          => 'Seller Rejected',
            self::ORDER_APPROVED           => 'Order Approved',
            self::ORDER_STATUS_CHANGED     => 'Order Status Changed',
            self::DELIVERY_ASSIGNED        => 'Delivery Partner Assigned',
            self::RETURN_APPROVED          => 'Return Approved',
            self::RETURN_REJECTED          => 'Return Rejected',
            self::PAYMENT_PROFILE_VERIFIED => 'Payment Profile Verified',
            self::PAYMENT_PROFILE_REJECTED => 'Payment Profile Rejected',
            self::COUPON_CREATED           => 'Coupon Created',
            self::COUPON_UPDATED           => 'Coupon Updated',
            self::COUPON_DELETED           => 'Coupon Deleted',
            self::USER_ROLE_CHANGED        => 'User Role Changed',
            self::USER_STATUS_CHANGED      => 'User Status Changed',
            self::BACKUP_CREATED           => 'Backup Created',
            self::BACKUP_RESTORED          => 'Backup Restored',
        ];
    }

    /**
     * Write one audit entry.
     *
     * @param  array<string, mixed>  $context  Extra safe detail (redacted here).
     */
    public function record(
        string $action,
        string $description,
        ?string $targetType = null,
        int|string|null $targetId = null,
        array $context = [],
        ?User $actor = null,
    ): ?AuditLog {
        try {
            $actor ??= auth()->user();

            return AuditLog::create([
                'user_id'     => $actor?->id,
                'user_name'   => $actor?->name,
                'user_role'   => $actor?->account_type,
                'action'      => $action,
                'target_type' => $targetType,
                'target_id'   => $targetId === null ? null : (string) $targetId,
                'description' => $this->truncate($description, 2000),
                'context'     => $this->sanitize($context),
                'ip_address'  => request()?->ip(),
                'user_agent'  => $this->truncate((string) request()?->userAgent(), 255) ?: null,
            ]);
        } catch (Throwable $e) {
            // An audit write must never break the administrator's action.
            Log::error('Audit log write failed for action ' . $action . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Convenience wrapper for a model target.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordFor(
        string $action,
        string $description,
        ?object $target,
        array $context = [],
        ?User $actor = null,
    ): ?AuditLog {
        return $this->record(
            $action,
            $description,
            $target === null ? null : class_basename($target),
            $target === null ? null : ($target->id ?? $target->getKey() ?? null),
            $context,
            $actor,
        );
    }

    /**
     * Recursively redact sensitive values out of a context array.
     *
     * Values that survive are cast to a safe scalar so the JSON column can
     * never receive an object graph or an unbounded blob.
     *
     * @param  array<mixed>  $context
     * @return array<string, mixed>
     */
    public function sanitize(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $clean[(string) $key] = '[redacted]';

                continue;
            }

            $clean[(string) $key] = is_array($value)
                ? $this->sanitize($value)
                : $this->safeScalar($value);
        }

        return $clean;
    }

    /**
     * Whether a context key must be redacted (substring, case-insensitive).
     */
    public function isSensitiveKey(string $key): bool
    {
        $needle = mb_strtolower($key);

        foreach (self::REDACTED_KEYS as $fragment) {
            if (str_contains($needle, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Coerce a context value into something safe to persist.
     */
    private function safeScalar(mixed $value): string|int|float|bool|null
    {
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return round($value, 4);
        }

        if (is_scalar($value)) {
            return $this->truncate((string) $value, 500);
        }

        // Objects/arrays are represented by their class name, never dumped.
        return $this->truncate(is_object($value) ? class_basename($value) : '[omitted]', 500);
    }

    private function truncate(string $value, int $limit): string
    {
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 1) . '…' : $value;
    }
}
