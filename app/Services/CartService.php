<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;

/**
 * CartService
 *
 * Single access point for the shopper's cart storage.
 *
 * - Authenticated shoppers: rows in the carts/cart_items tables keyed by
 *   user_id, so each customer's cart is isolated and survives logout.
 * - Guests: the legacy session cart (merged into the database cart on login).
 *
 * Admins get their own empty cart exactly like any other user — carts are
 * never shared between accounts.
 */
class CartService
{
    /**
     * The persistent cart for a user (created on first use).
     */
    public function forUser(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * All cart lines for the current shopper, keyed by cart key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function lines(): array
    {
        $user = auth()->user();

        if ($user) {
            return $this->forUser($user)->toLines();
        }

        return session()->get('cart', []);
    }

    /**
     * Persist the full set of lines for the current shopper.
     *
     * @param array<string, array<string, mixed>> $lines
     */
    public function save(array $lines): void
    {
        $user = auth()->user();

        if (! $user) {
            session(['cart' => $lines]);

            return;
        }

        $cart = $this->forUser($user);
        $keptKeys = [];

        foreach ($lines as $key => $line) {
            $keptKeys[] = $key;

            CartItem::updateOrCreate(
                [
                    'cart_id'     => $cart->id,
                    'product_key' => $key,
                ],
                [
                    'product_slug'    => (string) ($line['product'] ?? $key),
                    'title'           => (string) ($line['title'] ?? 'Product'),
                    'image'           => $line['image'] ?? null,
                    'price'           => (float) ($line['price'] ?? 0),
                    'quantity'        => max(1, (int) ($line['quantity'] ?? 1)),
                    'selected_options' => $line['selected_options'] ?? [],
                    'options_text'    => $line['options_text'] ?? null,
                    'sku'             => $line['sku'] ?? null,
                    'variant_id'      => $line['variant_id'] ?? null,
                ]
            );
        }

        // Drop lines that are no longer part of the cart.
        $cart->items()->whereNotIn('product_key', $keptKeys)->delete();
    }

    /**
     * Remove every line from the current shopper's cart.
     */
    public function clear(): void
    {
        $user = auth()->user();

        if ($user) {
            $this->forUser($user)->items()->delete();

            return;
        }

        session()->forget('cart');
    }

    /**
     * Total quantity of items currently in the shopper's cart.
     */
    public function count(): int
    {
        return array_sum(array_column($this->lines(), 'quantity'));
    }

    /**
     * Merge a guest session cart into a user's persistent cart.
     *
     * Called right after sign-in: quantities for matching products are added
     * together instead of creating duplicate lines, and the session cart is
     * consumed so it is never shown or inherited anywhere else.
     */
    public function mergeGuestCartIntoUserCart(User $user): void
    {
        $guestLines = session()->pull('cart', []);

        if (empty($guestLines)) {
            return;
        }

        $cart = $this->forUser($user);

        foreach ($guestLines as $key => $line) {
            $item = CartItem::where('cart_id', $cart->id)
                ->where('product_key', $key)
                ->first();

            if ($item) {
                $item->quantity += max(1, (int) ($line['quantity'] ?? 1));
                $item->save();

                continue;
            }

            CartItem::create([
                'cart_id'         => $cart->id,
                'product_key'     => $key,
                'product_slug'    => (string) ($line['product'] ?? $key),
                'title'           => (string) ($line['title'] ?? 'Product'),
                'image'           => $line['image'] ?? null,
                'price'           => (float) ($line['price'] ?? 0),
                'quantity'        => max(1, (int) ($line['quantity'] ?? 1)),
                'selected_options' => $line['selected_options'] ?? [],
                'options_text'    => $line['options_text'] ?? null,
                'sku'             => $line['sku'] ?? null,
                'variant_id'      => $line['variant_id'] ?? null,
            ]);
        }
    }

    /**
     * Remove every line of a product (including variant lines) from ALL
     * carts. Used when a product is deleted from the catalog.
     */
    public static function forgetProductEverywhere(string $slug): void
    {
        CartItem::where(function ($query) use ($slug) {
            $query->where('product_slug', $slug)
                ->orWhere('product_key', 'like', $slug . '::%')
                ->orWhere('product_key', $slug);
        })->delete();
    }
}
