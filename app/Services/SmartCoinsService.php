<?php

namespace App\Services;

use App\Models\SmartCoinTransaction;
use App\Models\SmartWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Smart Coins wallet — secure store-credit ledger.
 *
 * 1 Smart Coin = Rs 1 (config smart_coins.rate, default 1).
 * Refund-originated coins never expire. All balance math happens
 * server-side inside row-locked transactions; every mutation is
 * idempotent via idempotency_key so retries can never double-credit.
 */
class SmartCoinsService
{
    /**
     * 1 Smart Coin = Rs 1 by default (config smart_coins.coin_to_inr).
     * A coin is internal store credit, never a claim of cash.
     */
    public static function rate(): float
    {
        $rate = (float) config('smart_coins.coin_to_inr', 1);
        return $rate > 0 ? $rate : 1.0;
    }

    /** Store-credit value (INR) of a coin amount. */
    public static function valueOf(float $coins): float { return round($coins * self::rate(), 2); }

    /** Coins equivalent to a cash amount. */
    public static function coinsForAmount(float $amount): float { return round(max(0, $amount) / self::rate(), 2); }

    /** Minimum coins that may be redeemed in one checkout. */
    public static function minRedeem(): float { return (float) config('smart_coins.min_redeem', 1); }

    /** Max percentage of a payable amount that may be paid with coins. */
    public static function maxPercent(): int { return (int) config('smart_coins.max_percent', 100); }

    /**
     * The coins a buyer MAY apply to a payable amount, honouring the
     * available balance, the configured minimum and the max-percent cap.
     * Always recomputed server-side — never trusted from the browser.
     */
    public function redeemableFor(User $user, float $payable): float
    {
        $payable = max(0.0, $payable);
        $balance = $this->balance($user);
        $cap = round($payable * (self::maxPercent() / 100), 2);
        $allowed = min($balance, $cap);

        // Below the configured minimum, no coins are applied at all.
        if ($allowed < self::minRedeem()) {
            return 0.0;
        }

        return round($allowed, 2);
    }

    public function walletFor(User $user): SmartWallet
    {
        return SmartWallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'total_earned' => 0, 'total_spent' => 0, 'total_refund_credited' => 0]
        );
    }

    public function balance(User $user): float
    {
        return (float) $this->walletFor($user)->balance;
    }

    /** Credit coins (refund path). Idempotent on $idempotencyKey. */
    public function credit(User $user, float $coins, string $type, ?string $refType, ?int $refId, string $idempotencyKey, ?string $description = null, ?int $actorId = null): SmartCoinTransaction
    {
        $coins = round($coins, 2);
        if ($coins <= 0) throw ValidationException::withMessages(['amount' => 'Credit amount must be positive.']);

        return DB::transaction(function () use ($user, $coins, $type, $refType, $refId, $idempotencyKey, $description, $actorId) {
            $existing = SmartCoinTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) return $existing;

            $wallet = SmartWallet::where('user_id', $user->id)->lockForUpdate()->first()
                ?? $this->walletFor($user);

            $tx = SmartCoinTransaction::create([
                'wallet_id' => $wallet->id, 'user_id' => $user->id, 'type' => $type,
                'direction' => 'credit', 'amount' => $coins, 'reference_type' => $refType,
                'reference_id' => $refId, 'idempotency_key' => $idempotencyKey,
                'description' => $description, 'actor_id' => $actorId, 'status' => 'completed',
                'expires_at' => $type === 'refund_credit' ? null : null,
            ]);
            $wallet->increment('balance', $coins);
            $wallet->increment('total_earned', $coins);
            if ($type === 'refund_credit') $wallet->increment('total_refund_credited', $coins);
            return $tx;
        });
    }

    /** Debit coins (checkout path). Row-locked, never negative. Idempotent. */
    public function debit(User $user, float $coins, string $type, ?string $refType, ?int $refId, string $idempotencyKey, ?string $description = null): SmartCoinTransaction
    {
        $coins = round($coins, 2);
        if ($coins <= 0) throw ValidationException::withMessages(['amount' => 'Debit amount must be positive.']);

        return DB::transaction(function () use ($user, $coins, $type, $refType, $refId, $idempotencyKey, $description) {
            $existing = SmartCoinTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) return $existing;

            $wallet = SmartWallet::where('user_id', $user->id)->lockForUpdate()->first()
                ?? $this->walletFor($user);

            if ((float) $wallet->balance < $coins) {
                throw ValidationException::withMessages(['coins' => 'Insufficient Smart Coins balance.']);
            }
            $tx = SmartCoinTransaction::create([
                'wallet_id' => $wallet->id, 'user_id' => $user->id, 'type' => $type,
                'direction' => 'debit', 'amount' => $coins, 'reference_type' => $refType,
                'reference_id' => $refId, 'idempotency_key' => $idempotencyKey,
                'description' => $description, 'status' => 'completed',
            ]);
            $wallet->decrement('balance', $coins);
            $wallet->increment('total_spent', $coins);
            return $tx;
        });
    }

    /** Max coins redeemable for a payable amount. */
    public function maxRedeemable(User $user, float $payable): float
    {
        return round(min($this->balance($user), max(0, $payable)), 2);
    }

    public function history(User $user, int $perPage = 15)
    {
        return SmartCoinTransaction::where('user_id', $user->id)->latest()->paginate($perPage);
    }

    /** Admin adjustment — creates compensating entry, never edits history. */
    public function adjust(User $user, float $coins, User $admin, ?string $note = null): SmartCoinTransaction
    {
        $key = 'admin-adjust-' . $user->id . '-' . now()->format('YmdHis') . '-' . $admin->id . '-' . abs((int) ($coins * 100));
        if ($coins >= 0) {
            return $this->credit($user, $coins, 'admin_adjustment', 'admin', $admin->id, $key, $note ?? 'Admin credit adjustment.', $admin->id);
        }
        return $this->debit($user, abs($coins), 'admin_adjustment', 'admin', $admin->id, $key, $note ?? 'Admin debit adjustment.');
    }
}
