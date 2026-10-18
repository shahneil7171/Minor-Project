<?php

namespace App\Services;

use App\Models\Order;
use App\Services\Gateways\CashfreeGateway;
use App\Services\Gateways\RazorpayGateway;
use Illuminate\Validation\ValidationException;

class PaymentGatewayFactory
{
    /**
     * Return the gateway for a provider name.
     *
     * @throws ValidationException
     */
    public static function for(string $provider): PaymentGateway
    {
        $provider = strtolower(trim($provider));

        return match ($provider) {
            'razorpay' => new RazorpayGateway(),
            'cashfree' => new CashfreeGateway(),
            default => throw ValidationException::withMessages([
                'payment_provider' => 'Unknown payment provider: ' . $provider . '.',
            ]),
        };
    }

    /**
     * Return the gateway for a local order.
     *
     * COD is not a provider, so an order without a provider either has no
     * provider fields set (COD) or is a gateway order. Callers that expect a
     * gateway should first check `payment_provider` is 'razorpay' or 'cashfree'.
     */
    public static function forOrder(Order $order): PaymentGateway
    {
        return self::for($order->payment_provider ?? '');
    }
}
