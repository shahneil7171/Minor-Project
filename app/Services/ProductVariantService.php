<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * ProductVariantService
 * --------------
 * Central helpers for the product options / variants system.
 *
 * This project stores products as JSON records (not a relational products
 * table), so the OpenCart-style structure is materialised inside each
 * product record under the keys:
 *
 *   'options'  => [ ['name' => 'Size', 'values' => ['S','M','L']], ... ]
 *   'variants' => [ ['id' => 'v...', 'values' => ['Size'=>'M'], 'sku'=>…,
 *                    'price'=>…, 'stock'=>…], ... ]
 *
 * These parallel the product_options / product_option_values /
 * product_variants / product_variant_values tables. `values` on a variant is
 * the pivot (which option value belongs to which option for that variant).
 */
class ProductVariantService
{
    /**
     * Normalise the raw form input for options.
     *
     * Form side posts parallel arrays:
     *   options[name][]   - option names (e.g. "Size")
     *   options[values][] - comma/newline separated value strings (e.g. "S, M, L")
     *
     * @return array<int, array{name:string, values:array<int,string>}>
     */
    public static function normalizeOptions(array $options): array
    {
        $names   = $options['name']   ?? [];
        $valueGroups = $options['values'] ?? [];

        $result   = [];
        $seenNames = [];

        $count = max(count($names), count($valueGroups));

        for ($i = 0; $i < $count; $i++) {
            $name = trim((string) ($names[$i] ?? ''));

            if ($name === '') {
                continue;
            }

            $raw = trim((string) ($valueGroups[$i] ?? ''));
            $values = [];
            if ($raw !== '') {
                // Allow values separated by comma and/or new lines.
                $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
                $values = array_values(array_unique(array_filter(array_map('trim', $parts), fn ($v) => $v !== '')));
            }

            if (empty($values)) {
                throw ValidationException::withMessages([
                    'options' => "Option \"{$name}\" must have at least one value.",
                ]);
            }

            if (isset($seenNames[$name])) {
                throw ValidationException::withMessages([
                    'options' => "The option \"{$name}\" was added more than once. Please use unique option names.",
                ]);
            }

            $seenNames[$name] = true;

            $result[] = [
                'name'   => $name,
                'values' => $values,
            ];
        }

        return $result;
    }

    /**
     * Normalise variants from the form and reconcile them with the options.
     *
     * Form side posts per-variant parallel arrays where each index represents
     * one generated combination:
     *   variants[data][i]   - JSON object mapping option name => value
     *   variants[price][i]  - variant price (empty => base price)
     *   variants[stock][i]  - variant stock (empty => base quantity)
     *   variants[sku][i]    - optional variant SKU
     *
     * @param array<int, array{name:string, values:array<int,string>}> $options
     * @return array<int, array{id:string, values:array<string,string>, sku:?string, price:float, stock:int}>
     */
    public static function normalizeVariants(array $options, array $variants, $basePrice, $baseStock): array
    {
        if (empty($options)) {
            return [];
        }

        $flat   = $variants['data']   ?? [];
        $prices = $variants['price']  ?? [];
        $stocks = $variants['stock']  ?? [];
        $skus   = $variants['sku']    ?? [];

        $out  = [];
        $seen = [];

        $count = count($flat);

        for ($i = 0; $i < $count; $i++) {
            $sel = json_decode((string) ($flat[$i] ?? '{}'), true);
            if (! is_array($sel)) {
                $sel = [];
            }

            $normalized = [];
            foreach ($options as $opt) {
                $name  = $opt['name'];
                $given = isset($sel[$name]) ? trim((string) $sel[$name]) : '';

                if ($given === '' || ! in_array($given, $opt['values'], true)) {
                    throw ValidationException::withMessages([
                        'variants' => "Every variant must include a valid value for the option \"{$name}\".",
                    ]);
                }

                $normalized[$name] = $given;
            }

            $id = self::variantId($normalized);
            if (isset($seen[$id])) {
                continue; // identical combination submitted twice
            }
            $seen[$id] = true;

            $price = trim((string) ($prices[$i] ?? ''));
            $stock = trim((string) ($stocks[$i] ?? ''));
            $sku   = trim((string) ($skus[$i] ?? ''));

            $out[] = [
                'id'     => $id,
                'values' => $normalized,
                'sku'    => $sku !== '' ? $sku : null,
                'price'  => $price !== '' ? (float) $price : (float) $basePrice,
                'stock'  => $stock !== '' ? (int) $stock : (int) $baseStock,
            ];
        }

        // When options exist but the seller did not post any explicit variants,
        // generate every combination with the base price/stock as defaults.
        if (empty($out)) {
            foreach (self::generateVariantSelections($options) as $normalized) {
                $out[] = [
                    'id'     => self::variantId($normalized),
                    'values' => $normalized,
                    'sku'    => null,
                    'price'  => (float) $basePrice,
                    'stock'  => (int) $baseStock,
                ];
            }
        }

        return $out;
    }
/**
     * Build every combination from a list of options.
     *
     * @param array<int, array{name:string, values:array<int,string>}> $options
     * @return array<int, array<string, string>>
     */
    public static function generateVariantSelections(array $options): array
    {
        $result = [[]];

        foreach ($options as $opt) {
            $next = [];
            foreach ($result as $partial) {
                foreach ($opt['values'] as $value) {
                    $copy              = $partial;
                    $copy[$opt['name']] = $value;
                    $next[]            = $copy;
                }
            }
            $result = $next;
        }

        return $result;
    }

    /**
     * Deterministic, stable unique id for a combination of option values.
     */
    public static function variantId(array $values): string
    {
        ksort($values, SORT_STRING);

        return 'v' . substr(md5(json_encode($values)), 0, 12);
    }
/**
     * Locate a variant inside a product record by its id.
     *
     * @return array|null
     */
    public static function findVariant(array $product, $variantId)
    {
        if ($variantId === null || $variantId === '') {
            return null;
        }

        foreach ($product['variants'] ?? [] as $variant) {
            if (($variant['id'] ?? null) === $variantId) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Human readable description of a variant's selections,
     * e.g. "Size: M | Color: Black".
     */
    public static function describeVariant(array $variant): string
    {
        $parts = [];

        foreach (($variant['values'] ?? []) as $name => $value) {
            $parts[] = $name . ': ' . $value;
        }

        return implode(' | ', $parts);
    }

    /**
     * The unique key used inside the session cart for a line.
     *
     * Plain products keep the historic slug key. Variant products use
     * "slug::variantId" so every combination is an independent cart line.
     */
    public static function cartKey(string $slug, ?string $variantId = null): string
    {
        return $variantId !== null && $variantId !== ''
            ? $slug . '::' . $variantId
            : $slug;
    }

    /**
     * Whether a cart line key refers to a variant line of the given product.
     */
    public static function isVariantLine(string $cartKey, string $slug): bool
    {
        return strpos($cartKey, $slug . '::') === 0;
    }
}