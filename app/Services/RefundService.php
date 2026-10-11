<?php

namespace App\Services;

use App\Mail\RefundProcessedMail;
use App\Models\CodRefundPayout;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Notifications\StoreAlert;
use App\Support\ReturnPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefundService
{
    public function __construct(private SmartCoinsService $coins) {}

    public function refundFor(ReturnRequest $r): ?Refund
    {
        return Refund::where('return_request_id', $r->id)->first();
    }

    public function amountFor(ReturnRequest $r): float
    {
        $r->loadMissing(['order', 'orderItem']);
        $b = ReturnPolicy::refundBreakdown($r->order, $r->orderItem, (int) $r->quantity);
        return round((float) ($b['total'] ?? $r->refund_amount ?? 0), 2);
    }

    public function initiate(ReturnRequest $return, User $admin, string $method): Refund
    {
        $return->loadMissing(['order', 'orderItem', 'customer']);
        if (! in_array($method, Refund::METHODS, true)) {
            throw ValidationException::withMessages(['method' => 'Unknown refund method.']);
        }
        return DB::transaction(function () use ($return, $admin, $method) {
            $locked = ReturnRequest::whereKey($return->id)->lockForUpdate()->first();
            if ($ex = Refund::where('return_request_id', $locked->id)->first()) return $ex;
            $amount = $this->amountFor($locked);
            $provider = $method === 'smart_coins' ? 'smart_coins' : ($method === 'cod_cash' ? 'cod_cash' : 'simulated');
            $refund = Refund::create([
                'return_request_id' => $locked->id, 'order_id' => $locked->order_id,
                'order_item_id' => $locked->order_item_id, 'user_id' => $locked->user_id,
                'amount' => $amount, 'currency_code' => $locked->order?->currency_code ?? 'INR',
                'method' => $method, 'provider' => $provider, 'status' => 'pending',
                'idempotency_key' => 'refund-' . $locked->id,
                'note' => ($provider === 'simulated' ? 'SIMULATED refund, no real bank transfer. ' : '') . 'Initiated by ' . $admin->name,
            ]);
            $locked->forceFill([
                'resolution' => $locked->resolution ?? ($method === 'smart_coins' ? 'refund_coins' : ($method === 'cod_cash' ? 'refund_cod_cash' : 'refund_original')),
                'refund_method' => $method, 'refund_status' => 'pending', 'refund_amount' => $amount,
            ])->save();
            if ($method === 'cod_cash') {
                CodRefundPayout::firstOrCreate(['refund_id' => $refund->id], [
                    'return_request_id' => $locked->id, 'assigned_by' => $admin->id,
                    'amount' => $amount, 'status' => 'assigned',
                ]);
            }
            return $refund;
        });
    }

    public function processSmartCoins(Refund $refund, User $admin): Refund
    {
        return DB::transaction(function () use ($refund, $admin) {
            $r = Refund::whereKey($refund->id)->lockForUpdate()->first();
            if ($r->status === 'completed') return $r;
            if ($r->method !== 'smart_coins') throw ValidationException::withMessages(['method' => 'Not a Smart Coins refund.']);
            $customer = $r->user ?? $r->returnRequest?->customer;
            if (! $customer) throw ValidationException::withMessages(['return' => 'Return has no customer.']);
            $r->forceFill(['status' => 'processing'])->save();
            $this->coins->credit($customer, (float) $r->amount, 'refund_credit', 'refund', $r->id, 'refund-credit-' . $r->id, 'Smart Coins refund for return ' . ($r->returnRequest?->return_number ?? $r->return_request_id) . ' (store credit, not cash).', $admin->id);
            $r->forceFill(['status' => 'completed', 'completed_at' => now(), 'provider_reference' => 'COINS-' . $r->id])->save();
            $this->syncReturn($r, true);
            $this->notify($r, $customer, 'Smart Coins credited', ((float) $r->amount) . ' Smart Coins credited.');
            return $r;
        });
    }

    public function processOnlineRefund(Refund $refund, User $admin, ?string $ref = null): Refund
    {
        return DB::transaction(function () use ($refund, $admin, $ref) {
            $r = Refund::whereKey($refund->id)->lockForUpdate()->first();
            if ($r->status === 'completed') return $r;
            $r->forceFill(['status' => 'processing'])->save();
            $r->forceFill(['status' => 'completed', 'completed_at' => now(), 'provider_reference' => $ref ?: ('SIMULATED-' . $r->id), 'note' => trim(($r->note ? $r->note . ' ' : '') . 'SIMULATED completion by ' . $admin->name . ' - no real bank transfer.')])->save();
            $this->syncReturn($r, true);
            $customer = $r->user ?? $r->returnRequest?->customer;
            if ($customer) $this->notify($r, $customer, 'Refund completed (simulated)', 'Simulated refund of Rs ' . number_format((float) $r->amount, 2) . ' completed. No real bank transfer.');
            return $r;
        });
    }

    public function markFailed(Refund $refund, User $admin, ?string $reason = null): Refund
    {
        $refund->forceFill(['status' => 'failed', 'note' => trim(($refund->note ? $refund->note . ' ' : '') . ($reason ?? 'Marked failed.'))])->save();
        return $refund;
    }

    public function retry(Refund $refund, User $admin): Refund
    {
        $refund->forceFill(['status' => 'pending'])->save();
        if ($refund->method === 'smart_coins') return $this->processSmartCoins($refund, $admin);
        if ($refund->method === 'original_simulated') return $this->processOnlineRefund($refund, $admin, $refund->provider_reference);
        return $refund;
    }

    private function syncReturn(Refund $r, bool $done): void
    {
        $ret = $r->returnRequest ?? ReturnRequest::find($r->return_request_id);
        if (! $ret) return;
        $ret->forceFill(['refund_status' => $done ? 'refunded' : 'processing', 'refund_amount' => $r->amount, 'refund_reference' => $r->provider_reference ?? $ret->refund_reference, 'refunded_at' => $done ? now() : $ret->refunded_at])->save();
    }

    private function notify(Refund $r, User $customer, string $title, string $body): void
    {
        try { $customer->notify(new StoreAlert($title, $body, route('returns.show', ['return' => $r->return_request_id]), ['type' => 'refund', 'refund_id' => $r->id])); }
        catch (Throwable $e) { Log::error('Refund notify failed: ' . $e->getMessage()); }
        try { $ret = $r->returnRequest ?? ReturnRequest::find($r->return_request_id); if ($ret && $customer->email) Mail::to($customer->email)->send(new RefundProcessedMail($ret)); }
        catch (Throwable $e) { Log::error('Refund mail failed: ' . $e->getMessage()); }
    }
}
