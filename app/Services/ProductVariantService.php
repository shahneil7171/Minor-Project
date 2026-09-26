<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * ProductVariantService
 * --------------
 * Central helpers for the product options / variants system.
 *
 * This project stores the OpenCart-style structure in the product row's two
 * JSON columns (`products.options` / `products.variants`), shaped as:
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
     * The result is ALWAYS grouped by option name (see groupOptions()): one
     * option name produces exactly one option holding all of its values, never
     * one option per value. That is what makes the Cartesian product produce
     * 2 x 2 = 4 combinations instead of 1 x 1 x 1 x 1 = 1.
     *
     * @return array<int, array{name:string, values:array<int,string>}>
     */
    public static function normalizeOptions(array $options): array
    {
        $result = [];

        foreach (self::groupOptions($options) as $option) {
            if (empty($option['values'])) {
                throw ValidationException::withMessages([
                    'options' => "Option \"{$option['name']}\" must have at least one value.",
                ]);
            }

            $result[] = [
                'name'   => $option['name'],
                'values' => $option['values'],
            ];
        }

        return $result;
    }

    /**
     * Group ANY option data by option name — the single canonical shape used by
     * the product form, the storefront picker and every generated variant.
     *
     * Accepts every shape this feature has ever produced or received:
     *
     *   1. parallel form arrays  ['name' => ['Size','Colour'],
     *                              'values' => ['S, M', 'Red, Blue']]
     *   2. a list of option rows  [['name'=>'Size','values'=>['S','M']], …]
     *   3. flat name/value pairs  [['name'=>'Storage','value'=>'1TB'], …]
     *
     * Rows that share a name (compared case-insensitively) are MERGED into one
     * option and their values unioned, keeping the first spelling of each value
     * and the order in which values were first seen. This is what repairs legacy
     * data that was stored one-value-per-row:
     *
     *   [Storage:512GB] [Colour:Silver] [Storage:1TB] [Colour:Orange]
     *        becomes
     *   Storage: [512GB, 1TB]   Colour: [Silver, Orange]
     *
     * Blank rows (an option the seller added but never filled in) are dropped.
     *
     * @return array<int, array{name:string, values:array<int,string>}>
     */
    public static function groupOptions(mixed $options): array
    {
        $groups = [];
        $order  = [];

        foreach (self::optionRows($options) as $row) {
            $name = trim((string) $row['name']);

            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name);

            if (! isset($groups[$key])) {
                $groups[$key] = ['name' => $name, 'values' => [], 'seen' => []];
                $order[] = $key;
            }

            foreach ($row['values'] as $value) {
                $valueKey = mb_strtolower($value);

                // "512GB" and "512gb" are the same option value.
                if (isset($groups[$key]['seen'][$valueKey])) {
                    continue;
                }

                $groups[$key]['seen'][$valueKey] = true;
                $groups[$key]['values'][] = $value;
            }
        }

        $result = [];

        foreach ($order as $key) {
            $result[] = [
                'name'   => $groups[$key]['name'],
                'values' => $groups[$key]['values'],
            ];
        }

        return $result;
    }

    /**
     * Flatten any accepted option input into a list of
     * ['name' => string, 'values' => string[]] rows.
     *
     * @return array<int, array{name:string, values:array<int,string>}>
     */
    private static function optionRows(mixed $options): array
    {
        if (! is_array($options) || $options === []) {
            return [];
        }

        // Shape 1: the parallel arrays the form posts.
        if (array_key_exists('name', $options) && ! self::isNestedList($options['name'] ?? null)) {
            $names  = array_values((array) $options['name']);
            $groups = array_values((array) ($options['values'] ?? []));
            $count  = max(count($names), count($groups));
            $rows   = [];

            for ($i = 0; $i < $count; $i++) {
                $rows[] = [
                    'name'   => is_array($names[$i] ?? null) ? '' : ($names[$i] ?? ''),
                    'values' => self::splitValues($groups[$i] ?? null),
                ];
            }

            return $rows;
        }

        // Shapes 2 and 3: a list of rows.
        $rows = [];

        foreach ($options as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'name'   => $row['name'] ?? ($row['option'] ?? ''),
                'values' => array_merge(
                    self::splitValues($row['values'] ?? null),
                    self::splitValues($row['value'] ?? null),
                ),
            ];
        }

        return $rows;
    }

    /**
     * Split a raw option-value input into individual values.
     *
     * Accepts an array of values, or a single string whose values are separated
     * by commas and/or new lines.
     *
     * @return array<int, string>
     */
    private static function splitValues(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            $values = [];

            foreach ($raw as $entry) {
                $values = array_merge($values, self::splitValues($entry));
            }

            return $values;
        }

        $parts = preg_split('/[\r\n,]+/', (string) $raw) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($v) => $v !== ''));
    }

    /**
     * Whether a value is a list whose first element is itself a list.
     */
    private static function isNestedList(mixed $value): bool
    {
        return is_array($value) && isset($value[0]) && is_array($value[0]);
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
    public static function normalizeVariants(array $options, array $variants, $basePrice, $baseStock, ?string $baseSku = null): array
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

        return self::finalizeSkus($out, $baseSku);
    }

    /**
     * Fill in sensible SKUs for every variant left blank and guarantee the
     * resulting SKUs are unique inside this product.
     *
     * Admin-entered SKUs are always preserved verbatim; only the missing ones
     * are generated (e.g. "S26U-256GB-BLACK"). A duplicated SKU is a
     * validation error so nothing ever silently overwrites another variant's
     * identifier.
     *
     * @param array<int, array<string, mixed>> $variants
     */
    private static function finalizeSkus(array $variants, ?string $baseSku): array
    {
        $used = [];

        // Reserve admin-entered SKUs first so generated ones cannot collide.
        foreach ($variants as $variant) {
            $sku = trim((string) ($variant['sku'] ?? ''));

            if ($sku === '') {
                continue;
            }

            if (isset($used[mb_strtolower($sku)])) {
                throw ValidationException::withMessages([
                    'variants' => "The variant SKU \"{$sku}\" is used more than once. Variant SKUs must be unique.",
                ]);
            }

            $used[mb_strtolower($sku)] = true;
        }

        foreach ($variants as $index => $variant) {
            $sku = trim((string) ($variant['sku'] ?? ''));

            if ($sku !== '') {
                continue;
            }

            $candidate = self::generateSku($baseSku, is_array($variant['values'] ?? null) ? $variant['values'] : []);
            $suffix    = 2;

            while (isset($used[mb_strtolower($candidate)])) {
                $candidate .= '-' . $suffix++;
            }

            $used[mb_strtolower($candidate)]   = true;
            $variants[$index]['sku']           = $candidate;
        }

        return $variants;
    }

    /**
     * Build a human-friendly SKU from the product SKU/slug plus the selected
     * option values, e.g. "S26U" + [Storage => 256GB, Color => Black]
     * becomes "S26U-256GB-BLACK".
     *
     * @param array<string, string> $values
     */
    public static function generateSku(?string $baseSku, array $values): string
    {
        $sanitize = function ($value): string {
            $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', (string) $value));

            return mb_substr($clean, 0, 12);
        };

        $prefix = $sanitize($baseSku);

        if ($prefix === '') {
            $prefix = 'VAR';
        }

        $parts = [$prefix];

        foreach ($values as $value) {
            $part = $sanitize($value);

            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode('-', $parts);
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