<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ProductCatalogService;
use App\Services\ProductVariantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    private const SHIPPING_METHODS = [
        'standard' => [
            'label' => 'Standard Delivery',
            'cost' => 50.00,
            'estimate' => '3-5 Days',
        ],
    ];

    private const PAYMENT_METHODS = [
        'cod' => 'Cash on Delivery',
        'upi' => 'UPI',
        'card' => 'Card',
    ];

    public function cart(): View
    {
        return view('cart', ['cart' => session()->get('cart', [])]);
    }

    public function addToCart(Request $request, string $product): RedirectResponse
    {
        if ($this->isSeller()) {
            return redirect()->route('products')->with('error', 'Sellers cannot add items to cart.');
        }

        $catalog = app(ProductCatalogService::class)->all();
        $prod = $catalog[$product] ?? null;

        if (! $prod || ! $this->isProductEnabled($prod)) {
            abort(404);
        }

        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'variant_id' => ['nullable', 'string', 'max:100'],
        ]);

        $quantity = (int) ($data['quantity'] ?? 1);
        $variant = null;
        $variantId = $data['variant_id'] ?? null;

        if (! empty($prod['options'] ?? [])) {
            $variant = ProductVariantService::findVariant($prod, $variantId);

            if (! $variant) {
                return redirect()->route('product.show', ['product' => $product])
                    ->with('error', 'Please select all the required options before adding this product to your cart.');
            }
        }

        $cartKey = ProductVariantService::cartKey($product, $variant ? $variant['id'] : null);
        $cart = session()->get('cart', []);
        $currentQty = (int) ($cart[$cartKey]['quantity'] ?? 0);
        $availabilityError = $this->availabilityError($prod, $variant, $currentQty + $quantity);

        if ($availabilityError) {
            return redirect()->route('cart.index')->with('error', $availabilityError);
        }

        $line = $this->cartLineFromProduct($product, $prod, $variant, $quantity);

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] += $quantity;
            $cart[$cartKey]['price'] = $line['price'];
            $cart[$cartKey]['title'] = $line['title'];
            $cart[$cartKey]['image'] = $line['image'];
        } else {
            $cart[$cartKey] = $line;
        }

        session(['cart' => $cart]);

        return redirect()->route('cart.index')->with('success', 'Product added to cart.');
    }

    public function buyNow(Request $request, string $product): RedirectResponse
    {
        if ($this->isSeller()) {
            return redirect()->route('products')->with('error', 'Sellers cannot purchase items.');
        }

        $catalog = app(ProductCatalogService::class)->all();
        $prod = $catalog[$product] ?? null;

        if (! $prod || ! $this->isProductEnabled($prod)) {
            abort(404);
        }

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'variant_id' => ['nullable', 'string', 'max:100'],
        ]);

        $quantity = (int) $data['quantity'];
        $variant = null;
        $variantId = $data['variant_id'] ?? null;

        if (! empty($prod['options'] ?? [])) {
            $variant = ProductVariantService::findVariant($prod, $variantId);

            if (! $variant) {
                return redirect()->route('product.show', ['product' => $product])
                    ->with('error', 'Please select all the required options before buying this product.');
            }
        }

        $availabilityError = $this->availabilityError($prod, $variant, $quantity);

        if ($availabilityError) {
            return redirect()->route('cart.index')->with('error', $availabilityError);
        }

        $cartKey = ProductVariantService::cartKey($product, $variant ? $variant['id'] : null);
        $cart = session()->get('cart', []);
        $cart[$cartKey] = $this->cartLineFromProduct($product, $prod, $variant, $quantity);

        session([
            'cart' => $cart,
            'buy_now_item' => ['product' => $cartKey, 'quantity' => $quantity],
        ]);

        return redirect()->route('checkout.review');
    }

    public function review(): View|RedirectResponse
    {
        $cart = $this->cartForCheckout();

        if (empty($cart)) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        [$lines, $errors] = $this->normalizedCart($cart);

        if (! empty($errors)) {
            return redirect()->route('cart.index')->with('error', implode(' ', $errors));
        }

        $summary = $this->totals($lines, session('checkout_coupon_code'), 'standard');

        return view('checkout-review', [
            'cart' => $cart,
            'lines' => $lines,
            'summary' => $summary,
            'total' => $summary['subtotal'],
        ]);
    }

    public function increaseCartItem(string $product): RedirectResponse
    {
        $cart = session()->get('cart', []);

        if (! isset($cart[$product])) {
            return redirect()->route('cart.index');
        }

        $cart[$product]['quantity'] = (int) ($cart[$product]['quantity'] ?? 1) + 1;
        [$lines, $errors] = $this->normalizedCart([$product => $cart[$product]]);

        if (! empty($errors)) {
            return redirect()->route('cart.index')->with('error', implode(' ', $errors));
        }

        $cart[$product]['quantity'] = $lines[0]['quantity'];
        $cart[$product]['price'] = $lines[0]['unit_price'];
        $cart[$product]['title'] = $lines[0]['title'];
        $cart[$product]['image'] = $lines[0]['image'];
        session(['cart' => $cart]);

        return redirect()->route('cart.index');
    }

    public function decreaseCartItem(string $product): RedirectResponse
    {
        $cart = session()->get('cart', []);

        if (isset($cart[$product])) {
            if ((int) $cart[$product]['quantity'] > 1) {
                $cart[$product]['quantity'] -= 1;
            } else {
                unset($cart[$product]);
            }

            session(['cart' => $cart]);
        }

        return redirect()->route('cart.index');
    }

    public function removeCartItem(string $product): RedirectResponse
    {
        $cart = session()->get('cart', []);

        if (isset($cart[$product])) {
            unset($cart[$product]);
            session(['cart' => $cart]);
        }

        return redirect()->route('cart.index');
    }

    public function buyNowCartItem(Request $request, string $product): RedirectResponse
    {
        if ($this->isSeller()) {
            return redirect()->route('products')->with('error', 'Sellers cannot purchase items.');
        }

        $cart = session()->get('cart', []);

        if (! isset($cart[$product])) {
            return redirect()->route('cart.index');
        }

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        session(['buy_now_item' => [
            'product' => $product,
            'quantity' => (int) $data['quantity'],
        ]]);

        return redirect()->route('checkout.review');
    }

    public function index(): View|RedirectResponse
    {
        if ($this->isSeller()) {
            return redirect()->route('products')->with('error', 'Sellers cannot checkout.');
        }

        $cart = $this->cartForCheckout();

        if (empty($cart)) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        [$lines, $errors] = $this->normalizedCart($cart);

        if (! empty($errors)) {
            return redirect()->route('cart.index')->with('error', implode(' ', $errors));
        }

        $user = auth()->user();
        $addresses = $user ? $user->addresses()->latest()->get() : collect();
        $summary = $this->totals($lines, session('checkout_coupon_code'), 'standard');

        return view('checkout', [
            'cart' => $cart,
            'lines' => $lines,
            'summary' => $summary,
            'shippingMethods' => self::SHIPPING_METHODS,
            'paymentMethods' => self::PAYMENT_METHODS,
            'addresses' => $addresses,
            'defaultAddress' => $user?->defaultShippingAddress,
            'appliedCoupon' => $summary['coupon'],
        ]);
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'coupon_code' => ['required', 'string', 'max:50'],
        ]);

        [$lines, $errors] = $this->normalizedCart($this->cartForCheckout());

        if (empty($lines)) {
            return redirect()->route('cart.index')->with('error', 'Add products before applying a coupon.');
        }

        if (! empty($errors)) {
            return redirect()->route('cart.index')->with('error', implode(' ', $errors));
        }

        $couponCode = strtoupper(trim($data['coupon_code']));
        $subtotal = array_sum(array_column($lines, 'subtotal'));
        $coupon = Coupon::active()->where('code', $couponCode)->first();

        if (! $coupon || ! $coupon->isValidFor((float) $subtotal)) {
            session()->forget('checkout_coupon_code');

            return redirect()->route('checkout.index')
                ->withInput()
                ->with('error', 'That coupon is not valid for this order.');
        }

        session(['checkout_coupon_code' => $coupon->code]);

        return redirect()->route('checkout.index')->with('success', 'Coupon applied.');
    }

    public function removeCoupon(): RedirectResponse
    {
        session()->forget('checkout_coupon_code');

        return redirect()->route('checkout.index')->with('success', 'Coupon removed.');
    }

    public function submit(Request $request): RedirectResponse
    {
        if ($this->isSeller()) {
            return redirect()->route('products')->with('error', 'Sellers cannot checkout.');
        }

        $data = $this->validateCheckout($request);
        $cart = $this->cartForCheckout();

        if (empty($cart)) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        [$lines, $errors] = $this->normalizedCart($cart);

        if (! empty($errors)) {
            return redirect()->route('cart.index')->with('error', implode(' ', $errors));
        }

        $couponCode = session('checkout_coupon_code') ?: ($data['coupon_code'] ?? null);
        $summary = $this->totals($lines, $couponCode, $data['shipping_method']);

        $order = DB::transaction(function () use ($data, $lines, $summary, $request) {
            $shipping = $this->shippingSnapshot($request, $data);
            $user = auth()->user();

            do {
                $orderNumber = 'KDP-' . date('ymd') . '-' . strtoupper(Str::random(6));
            } while (Order::where('order_number', $orderNumber)->exists());

            $order = Order::create([
                'user_id' => $user?->id,
                'customer_email' => $user?->email ?? $shipping['email'],
                'order_number' => $orderNumber,
                'status' => 'pending',
                'subtotal' => $summary['subtotal'],
                'tax' => $summary['tax'],
                'shipping_method' => self::SHIPPING_METHODS[$data['shipping_method']]['label'],
                'shipping_cost' => $summary['shipping'],
                'discount_amount' => $summary['discount'],
                'coupon_code' => $summary['coupon']?->code,
                'total' => $summary['total'],
                'payment_method' => self::PAYMENT_METHODS[$data['payment_method']],
                'shipping_name' => $shipping['name'],
                'shipping_phone' => $shipping['phone'],
                'shipping_address' => $shipping['address'],
                'shipping_city' => $shipping['city'],
                'shipping_state' => $shipping['state'],
                'shipping_pincode' => $shipping['pincode'],
                'shipping_country' => $shipping['country'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_slug' => $line['product_slug'],
                    'product_title' => $line['title'],
                    'product_image' => $line['image'],
                    'sku' => $line['sku'],
                    'price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'subtotal' => $line['subtotal'],
                    'options_text' => $line['options_text'],
                ]);
            }

            if ($summary['coupon']) {
                $summary['coupon']->increment('used_count');
            }

            return $order->load('items');
        });

        $this->clearPurchasedCartItems(array_column($lines, 'cart_key'));

        session([
            'checkout_order_id' => $order->id,
            'order_id' => $order->id,
            'order_total' => (float) $order->total,
            'order_discount' => (float) $order->discount_amount,
            'order_coupon' => $order->coupon_code,
            'order_payment' => $order->payment_method,
        ]);
        session()->forget('checkout_coupon_code');

        return redirect()->route('checkout.complete');
    }

    public function complete(): View|RedirectResponse
    {
        $orderId = session('checkout_order_id');
        $order = $orderId ? Order::with('items')->find($orderId) : null;

        if (! $order) {
            return redirect()->route('cart.index');
        }

        if ($order->user_id && auth()->id() !== $order->user_id) {
            abort(403);
        }

        return view('checkout-complete', compact('order'));
    }

    private function validateCheckout(Request $request): array
    {
        $user = auth()->user();
        $addressOptions = $user ? ['saved', 'new'] : ['guest'];

        $rules = [
            'address_option' => ['required', Rule::in($addressOptions)],
            'shipping_method' => ['required', Rule::in(array_keys(self::SHIPPING_METHODS))],
            'payment_method' => ['required', Rule::in(array_keys(self::PAYMENT_METHODS))],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        if (in_array($request->input('payment_method'), ['upi', 'card'], true)) {
            $rules['payment_confirmation'] = ['required', 'accepted'];
        }

        if (! $user) {
            $rules = array_merge($rules, [
                'customer_name' => ['required', 'string', 'max:255'],
                'customer_email' => ['required', 'email', 'max:255'],
                'customer_phone' => ['required', 'string', 'max:20'],
                'shipping_address' => ['required', 'string', 'max:500'],
                'shipping_city' => ['required', 'string', 'max:100'],
                'shipping_state' => ['required', 'string', 'max:100'],
                'shipping_pincode' => ['required', 'string', 'max:20'],
                'shipping_country' => ['required', 'string', 'max:100'],
            ]);
        } elseif ($request->input('address_option') === 'saved') {
            $rules['address_id'] = [
                'required',
                Rule::exists('addresses', 'id')->where('user_id', $user->id),
            ];
        } else {
            $rules = array_merge($rules, [
                'new_address.full_name' => ['required', 'string', 'max:255'],
                'new_address.phone' => ['required', 'string', 'max:20'],
                'new_address.house_number' => ['required', 'string', 'max:100'],
                'new_address.street_address' => ['required', 'string', 'max:255'],
                'new_address.city' => ['required', 'string', 'max:100'],
                'new_address.state' => ['required', 'string', 'max:100'],
                'new_address.pincode' => ['required', 'string', 'max:20'],
                'new_address.country' => ['required', 'string', 'max:100'],
                'new_address.additional_info' => ['nullable', 'string', 'max:500'],
                'new_address.is_default_shipping' => ['nullable', 'boolean'],
            ]);
        }

        return $request->validate($rules);
    }

    private function shippingSnapshot(Request $request, array $data): array
    {
        $user = auth()->user();

        if (! $user) {
            return [
                'name' => $data['customer_name'],
                'email' => $data['customer_email'],
                'phone' => $data['customer_phone'],
                'address' => $data['shipping_address'],
                'city' => $data['shipping_city'],
                'state' => $data['shipping_state'],
                'pincode' => $data['shipping_pincode'],
                'country' => $data['shipping_country'],
            ];
        }

        if ($data['address_option'] === 'saved') {
            $address = $user->addresses()->whereKey($data['address_id'])->firstOrFail();

            return $this->snapshotFromAddress($address, $user->email);
        }

        $addressData = $data['new_address'];
        $isFirstAddress = $user->addresses()->count() === 0;
        $addressData['user_id'] = $user->id;
        $addressData['is_default_shipping'] = $isFirstAddress || $request->boolean('new_address.is_default_shipping');
        $addressData['is_default_billing'] = $isFirstAddress;

        if ($addressData['is_default_shipping']) {
            $user->addresses()->where('is_default_shipping', true)->update(['is_default_shipping' => false]);
        }

        $address = Address::create($addressData);

        return $this->snapshotFromAddress($address, $user->email);
    }

    private function snapshotFromAddress(Address $address, ?string $email): array
    {
        return [
            'name' => $address->full_name,
            'email' => $email,
            'phone' => $address->phone,
            'address' => trim($address->house_number . ', ' . $address->street_address, ', '),
            'city' => $address->city,
            'state' => $address->state,
            'pincode' => $address->pincode,
            'country' => $address->country,
        ];
    }

    private function cartForCheckout(): array
    {
        $cart = session()->get('cart', []);
        $buyNow = session()->get('buy_now_item');

        if (! $buyNow) {
            return $cart;
        }

        $cartKey = $buyNow['product'] ?? null;

        if (! $cartKey || ! isset($cart[$cartKey])) {
            return [];
        }

        $line = $cart[$cartKey];
        $line['quantity'] = (int) ($buyNow['quantity'] ?? $line['quantity'] ?? 1);

        return [$cartKey => $line];
    }

    private function normalizedCart(array $cart): array
    {
        $catalog = app(ProductCatalogService::class)->all();
        $lines = [];
        $errors = [];

        foreach ($cart as $cartKey => $item) {
            $baseSlug = explode('::', (string) ($item['product'] ?? $cartKey))[0];
            $product = $catalog[$baseSlug] ?? null;
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if (! $product || ! $this->isProductEnabled($product)) {
                $errors[] = 'One item in your cart is no longer available.';
                continue;
            }

            $variant = null;
            $variantId = $item['variant_id'] ?? null;

            if ($variantId) {
                $variant = ProductVariantService::findVariant($product, $variantId);

                if (! $variant) {
                    $errors[] = ($product['title'] ?? 'A product') . ' has an unavailable option selected.';
                    continue;
                }
            } elseif (! empty($product['options'] ?? [])) {
                $errors[] = 'Please reselect options for ' . ($product['title'] ?? 'one product') . '.';
                continue;
            }

            $availabilityError = $this->availabilityError($product, $variant, $quantity);

            if ($availabilityError) {
                $errors[] = $availabilityError;
                continue;
            }

            $unitPrice = $variant ? (float) $variant['price'] : $this->effectivePrice($product);
            $lineSubtotal = round($unitPrice * $quantity, 2);
            $taxRate = max(0.0, (float) ($product['tax'] ?? 0));
            $tax = round($lineSubtotal * ($taxRate / 100), 2);

            $lines[] = [
                'cart_key' => (string) $cartKey,
                'product_slug' => $baseSlug,
                'title' => $product['title'] ?? ($item['title'] ?? 'Product'),
                'image' => $product['image'] ?? ($item['image'] ?? null),
                'sku' => $variant ? ($variant['sku'] ?? null) : ($item['sku'] ?? ($product['sku'] ?? null)),
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'subtotal' => $lineSubtotal,
                'tax_rate' => $taxRate,
                'tax' => $tax,
                'options_text' => $variant ? ProductVariantService::describeVariant($variant) : ($item['options_text'] ?? null),
            ];
        }

        return [$lines, $errors];
    }

    private function totals(array $lines, ?string $couponCode, string $shippingMethod): array
    {
        $subtotal = round(array_sum(array_column($lines, 'subtotal')), 2);
        $tax = round(array_sum(array_column($lines, 'tax')), 2);
        $shipping = self::SHIPPING_METHODS[$shippingMethod]['cost'] ?? 0.0;
        $coupon = null;
        $discount = 0.0;

        if ($couponCode) {
            $coupon = Coupon::active()->where('code', strtoupper(trim($couponCode)))->first();

            if ($coupon && $coupon->isValidFor((float) $subtotal)) {
                $discount = min($coupon->discountFor((float) $subtotal), $subtotal + $shipping + $tax);
            } else {
                $coupon = null;
            }
        }

        return [
            'subtotal' => $subtotal,
            'shipping' => round($shipping, 2),
            'tax' => $tax,
            'discount' => round($discount, 2),
            'coupon' => $coupon,
            'total' => max(0.0, round($subtotal + $shipping + $tax - $discount, 2)),
        ];
    }

    private function clearPurchasedCartItems(array $cartKeys): void
    {
        $buyNow = session()->get('buy_now_item');

        if ($buyNow) {
            $cart = session()->get('cart', []);

            foreach ($cartKeys as $cartKey) {
                unset($cart[$cartKey]);
            }

            session(['cart' => $cart]);
            session()->forget('buy_now_item');

            return;
        }

        session()->forget(['cart', 'buy_now_item']);
    }

    private function cartLineFromProduct(string $slug, array $product, ?array $variant, int $quantity): array
    {
        return [
            'product' => $slug,
            'title' => $product['title'],
            'image' => $product['image'] ?? null,
            'price' => $variant ? (float) $variant['price'] : $this->effectivePrice($product),
            'quantity' => $quantity,
            'selected_options' => $variant ? $variant['values'] : [],
            'options_text' => $variant ? ProductVariantService::describeVariant($variant) : '',
            'sku' => $variant ? ($variant['sku'] ?? null) : ($product['sku'] ?? null),
            'variant_id' => $variant ? $variant['id'] : null,
        ];
    }

    private function availabilityError(array $product, ?array $variant, int $quantity): ?string
    {
        $title = $product['title'] ?? 'This product';

        if ($variant) {
            $stock = (int) ($variant['stock'] ?? 0);

            if ($stock <= 0) {
                return $title . ' is out of stock.';
            }

            if ($quantity > $stock) {
                return 'Sorry, only ' . $stock . ' unit(s) of ' . $title . ' are available.';
            }

            return null;
        }

        if (($product['stock_status'] ?? 'in-stock') === 'out-of-stock') {
            return $title . ' is out of stock.';
        }

        $stock = (int) ($product['quantity'] ?? 0);

        if ($stock > 0 && $quantity > $stock) {
            return 'Sorry, only ' . $stock . ' unit(s) of ' . $title . ' are available.';
        }

        return null;
    }

    private function effectivePrice(array $product): float
    {
        $base = $this->priceFloat($product['price'] ?? 0);
        $special = isset($product['special_price']) && $product['special_price'] !== ''
            ? $this->priceFloat($product['special_price'])
            : 0;

        return ($special > 0 && $special < $base) ? $special : $base;
    }

    private function priceFloat(mixed $price): float
    {
        return (float) filter_var((string) ($price ?? 0), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    private function isProductEnabled(array $product): bool
    {
        return ! isset($product['status']) || (int) $product['status'] === 1;
    }

    private function isSeller(): bool
    {
        return auth()->check() && auth()->user()->account_type === 'seller';
    }
}
