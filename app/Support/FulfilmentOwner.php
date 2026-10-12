<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;

/**
 * SINGLE SOURCE OF TRUTH for "who is responsible for preparing this stock".
 *
 * KDP MART is a multi-vendor marketplace, so every order line belongs to
 * exactly one fulfilment owner:
 *
 *   A. SELLER  — the product was added by a registered seller. That seller
 *                packs it and marks it Ready for Pickup.
 *   B. STORE   — the product was added/managed directly by the Admin store
 *                (seed catalog or admin-created). The ADMIN store packs it
 *                and marks it Ready for Pickup.
 *   C. UNKNOWN — a legacy row whose ownership could not be established. It is
 *                never silently guessed as admin-owned; it is surfaced as
 *                "Unassigned" so an administrator can review it. Fulfilment
 *                responsibility still falls to the store so such an order can
 *                never dead-end.
 *
 * The resolver reads the explicit `owner_type` marker first (added by the
 * additive fulfilment-owner migration) and only falls back to the historical
 * "seller_id IS NULL means platform-owned" convention, which is the meaning
 * the schema and every existing caller already rely on.
 *
 * EVERY user-facing fulfilment label in the application (customer order page,
 * admin orders, admin store fulfilment, seller orders, delivery partner pages,
 * notifications and emails) is derived from this class, so all surfaces can
 * never disagree about who is preparing an order.
 */
class FulfilmentOwner
{
    /** Prepared and dispatched by the Admin store (platform stock). */
    public const STORE = 'store';

    /** Prepared and dispatched by a registered seller account. */
    public const SELLER = 'seller';

    /** Ownership could not be established (legacy / needs review). */
    public const UNKNOWN = 'unknown';

    /** @var array<int, string> */
    public const OWNER_TYPES = [self::STORE, self::SELLER, self::UNKNOWN];

    /**
     * The fulfilment owner of an ORDER LINE.
     *
     * `order_items.seller_id` is the snapshot taken at checkout, so it stays
     * correct even if the product's ownership changes later.
     */
    public static function of(?OrderItem $item): string
    {
        if ($item === null) {
            return self::UNKNOWN;
        }

        return self::resolve($item->owner_type, $item->seller_id);
    }

    /**
     * The fulfilment owner of a CATALOG PRODUCT.
     */
    public static function ofProduct(?Product $product): string
    {
        if ($product === null) {
            return self::UNKNOWN;
        }

        return self::resolve($product->owner_type, $product->seller_id);
    }

    /**
     * Resolve an owner type from the explicit marker, then from the seller id.
     *
     * A null `seller_id` means platform/store-owned throughout this codebase
     * (product page, delivery pages, admin order items, checkout snapshot), so
     * that remains the documented fallback. Only an EXPLICIT unknown marker
     * stays unknown.
     */
    public static function resolve(?string $ownerType, ?int $sellerId): string
    {
        if (is_string($ownerType) && in_array($ownerType, self::OWNER_TYPES, true)) {
            return $ownerType;
        }

        if ($sellerId !== null) {
            return self::SELLER;
        }

        return self::STORE;
    }

    /**
     * Whether the given order line is prepared by the Admin store.
     */
    public static function isStore(?OrderItem $item): bool
    {
        return self::of($item) === self::STORE;
    }

    /**
     * Whether the given order line is prepared by a registered seller.
     */
    public static function isSeller(?OrderItem $item): bool
    {
        return self::of($item) === self::SELLER;
    }

    /**
     * Whether the given order line's owner could not be established.
     */
    public static function isUnknown(?OrderItem $item): bool
    {
        return self::of($item) === self::UNKNOWN;
    }

    /**
     * The store's display name (admin-configurable; never hardcoded).
     */
    public static function storeName(): string
    {
        $name = trim((string) Setting::get('store_name'));

        return $name !== '' ? $name : 'KDP MART';
    }

    /**
     * The store as a fulfilment owner, e.g. "KDP MART Store".
     */
    public static function storeOwnerLabel(): string
    {
        return self::storeName() . ' Store';
    }

    /**
     * Human-readable name of the owner that prepares this line:
     * "KDP MART Store", the seller's name, or "Unassigned".
     */
    public static function ownerName(?OrderItem $item): string
    {
        return match (self::of($item)) {
            self::SELLER  => $item->seller?->name ?? ('Seller #' . ($item->seller_id ?? 0)),
            self::UNKNOWN => 'Unassigned',
            default       => self::storeOwnerLabel(),
        };
    }

    /**
     * "Preparing by KDP MART Store" / "Preparing by Sadie Sink".
     *
     * Replaces the old hardcoded "Preparing by Seller" label, which was shown
     * even for platform-owned products that no seller is responsible for.
     */
    public static function preparingLabel(?OrderItem $item): string
    {
        return 'Preparing by ' . self::ownerName($item);
    }

    /**
     * "Waiting for KDP MART Store" / "Waiting for Sadie Sink".
     */
    public static function waitingLabel(?OrderItem $item): string
    {
        return 'Waiting for ' . self::ownerName($item);
    }
}
