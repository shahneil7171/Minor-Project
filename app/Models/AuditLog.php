<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PHASE 4 — one immutable record of a significant administrative action.
 *
 * Written exclusively through App\Services\AuditLogService, which redacts
 * sensitive values and stamps the acting user + IP. The model is read/query
 * only: there is no update() path in the application, and `context` is
 * treated as an already-sanitised array.
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'action',
        'target_type',
        'target_id',
        'description',
        'context',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    /**
     * The administrator (or staff member) who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Only entries for one action (or every action when null).
     */
    public function scopeAction(Builder $query, ?string $action): Builder
    {
        if ($action !== null && $action !== '' && $action !== 'all') {
            $query->where('action', $action);
        }

        return $query;
    }

    /**
     * Only entries written by one user.
     */
    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query;
    }

    /**
     * Only entries inside an inclusive [from, to] window.
     */
    public function scopeBetweenDates(Builder $query, ?\DateTimeInterface $from, ?\DateTimeInterface $to): Builder
    {
        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Distinct action names, for the filter dropdown.
     *
     * @return array<int, string>
     */
    public static function actionList(): array
    {
        return static::query()
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();
    }

    /**
     * "Product created" style label for the action key.
     */
    public function actionLabel(): string
    {
        return self::labelFor($this->action);
    }

    /**
     * Turn "product.created" into "Product Created".
     */
    public static function labelFor(string $action): string
    {
        $words = str_replace(['.', '_'], ' ', $action);

        return ucwords($words);
    }
}
