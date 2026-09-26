<?php

namespace App\Services;

/**
 * ProductImageService
 * -------------------
 * Single source of truth for building a product's image gallery.
 *
 * Historical background: products were persisted as
 *
 *     image  = main photo
 *     images = [main photo, ...additional photos]   (main duplicated at [0])
 *
 * and the product-detail page rendered the large hero image AND every entry
 * of `images`, so the main photo was always displayed twice. Editing a
 * product and re-uploading the same picture also accumulated visually
 * identical copies (new timestamped filename each time) because the paths
 * differed and could never be collapsed by value comparison alone.
 *
 * The pipeline this service implements:
 *
 *     main image + additional images
 *         -> normalize references
 *         -> drop empty/null values
 *         -> remove duplicate references (same file)
 *         -> unique, order-preserved gallery collection
 *
 * Duplicate detection works on two levels:
 *
 *  1. Reference level  - equivalent references to the same stored file are
 *     recognised (`uploads/products/a.webp`, `/uploads/products/a.webp` and
 *     `http://<own-host>/uploads/products/a.webp` all point at one file).
 *  2. Content level    - local files that exist under `public/` are compared
 *     by SHA-256 content hash, so genuine re-upload copies of the same
 *     picture collapse to a single gallery entry. Different files (different
 *     content) are NEVER treated as duplicates, and nothing is ever deleted
 *     from disk or from the database by this service - it only decides what
 *     should be displayed/stored.
 */
class ProductImageService
{
    /**
     * Per-process memo of sha256 fingerprints for local files, keyed by
     * normalized relative path. Purely an optimisation; results are also
     * correct without it.
     *
     * @var array<string, string>
     */
    private static array $hashMemo = [];

    /**
     * A valid product image reference.
     *
     * A product's main image is stored as EITHER an absolute URL
     * (https://images.unsplash.com/...) OR a project-local path such as
     * /uploads/products/1234-photo.webp (what the "Upload main photo" field
     * produces). A strict `url` rule is therefore WRONG for this field: it
     * rejects every locally stored image, which is the majority of them.
     *
     * Accepted:
     *   - http(s) absolute URLs (with optional query string)
     *   - project-local paths under uploads/ or storage/ (leading slash optional)
     *   - any absolute local path that points at a file (/some/dir/photo.png)
     *
     * Rejected: free text that is not a URL and not a path.
     *
     * NOTE: deliberately contains no `|` so it can be used inside a
     * pipe-delimited Laravel rule string.
     */
    public const REFERENCE_REGEX = '~^(https?://\S+|/?((public/)?(uploads|storage))/\S+|/\S+\.\w{2,5}(?:\?\S*)?)$~';

    /**
     * The validation rule for a submitted main-image reference.
     *
     * `nullable` is what makes EDIT work: submitting an EMPTY image field means
     * "no new image was provided" and the stored image is kept. It is never
     * interpreted as "delete the image" - removing an image is an explicit
     * action (see the `remove_image` flag in the product routes).
     *
     * @return array<int, string>
     */
    public static function referenceRule(): array
    {
        return ['nullable', 'string', 'max:1000', 'regex:' . self::REFERENCE_REGEX];
    }

    /**
     * A short, seller-facing explanation of the accepted formats.
     */
    public static function referenceHint(): string
    {
        return 'Paste a full image URL (https://...) or keep the existing /uploads/... path.';
    }

    /**
     * Build the final display gallery: unique([main_image, ...additional_images]).
     *
     * The main image is always first (it doubles as the initial hero image),
     * every following entry is unique, empty/null values are ignored and the
     * original order is preserved.
     *
     * @param  mixed  $main    Main image reference (string|null).
     * @param  mixed  $images  Additional image references (array|mixed).
     * @return list<string>
     */
    public static function uniqueGallery(mixed $main, mixed $images): array
    {
        $refs = [(string) ($main ?? '')];

        foreach ((array) ($images ?? []) as $image) {
            $refs[] = (string) ($image ?? '');
        }

        return self::uniqueList($refs);
    }

    /**
     * De-duplicate any list of image references (order preserved, empties
     * ignored, original reference strings returned untouched).
     *
     * @param  array<mixed>  $refs
     * @return list<string>
     */
    public static function uniqueList(array $refs): array
    {
        $unique = [];
        $seen   = [];

        foreach ($refs as $ref) {
            $trimmed = trim((string) ($ref ?? ''));

            if ($trimmed === '') {
                continue; // Ignore empty/null image values.
            }

            $fingerprint = self::fingerprint($trimmed);

            if ($fingerprint === null || isset($seen[$fingerprint])) {
                continue; // Same stored file (or same reference) - skip.
            }

            $seen[$fingerprint] = true;
            $unique[] = $trimmed;
        }

        return array_values($unique);
    }

    /**
     * ONLY the genuinely additional images of a product: the de-duplicated
     * gallery minus the main image and minus any placeholder/default image.
     *
     * Placeholders can NEVER be legitimate additional images - they exist
     * only as bootstrap/seed defaults or as the "no image at all" hero.
     * This is used for storage (clean representation: the main photo lives
     * in `image`, never inside `images`) and for the edit form's
     * "Additional image URLs" prefill.
     *
     * @param  mixed  $main    Main image reference (string|null).
     * @param  mixed  $images  Raw stored/form image list (may contain the main ref).
     * @return list<string>
     */
    public static function additionalImages(mixed $main, mixed $images): array
    {
        $all       = self::uniqueGallery($main, $images);
        $mainPrint = self::fingerprint(trim((string) ($main ?? '')));

        return array_values(array_filter(
            $all,
            fn (string $ref): bool => self::fingerprint($ref) !== $mainPrint
                && ! self::isPlaceholder($ref)
        ));
    }

    /**
     * The application's canonical "product has no image" placeholder.
     * Single source of truth - previously duplicated inline in the store
     * route, update route and the product-detail view.
     */
    public static function defaultImage(): string
    {
        return 'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?auto=format&fit=crop&w=800&q=80';
    }

    /**
     * Normalized keys of every known placeholder/default image reference:
     * the shared default plus each seed-catalog product's bootstrap image
     * (config/catalog.php). Seed placeholders were historically merged into
     * product galleries by the edit flow and appeared as phantom second
     * images that no admin ever added.
     *
     * @return list<string>
     */
    public static function placeholderKeys(): array
    {
        $keys = [self::normalize(self::defaultImage())];

        foreach ((array) config('catalog.seed_products', []) as $row) {
            $keys[] = self::normalize((string) (($row ?? [])['image'] ?? ''));
        }

        return array_values(array_unique(array_filter(
            array_map(fn (?string $key) => $key ?? '', $keys)
        )));
    }

    /**
     * Whether a reference is one of the application's placeholder/default
     * images (shared default or a seed-catalog bootstrap image).
     */
    public static function isPlaceholder(string $ref): bool
    {
        $key = self::normalize($ref);

        return $key !== null && in_array($key, self::placeholderKeys(), true);
    }

    /**
     * The gallery as it may be DISPLAYED: unique([main, ...stored extras])
     * with every placeholder removed as long as at least one real image
     * remains. A placeholder may only ever represent a product that has
     * nothing else - never sit next to a real photo as a phantom second
     * gallery entry.
     *
     * @param  mixed  $main    Main image reference (string|null).
     * @param  mixed  $images  Stored additional image references.
     * @return list<string>
     */
    public static function galleryForDisplay(mixed $main, mixed $images): array
    {
        $gallery   = self::uniqueGallery($main, $images);
        $realOnly  = array_values(array_filter(
            $gallery,
            fn (string $ref): bool => ! self::isPlaceholder($ref)
        ));

        return $realOnly !== [] ? $realOnly : $gallery;
    }

    /**
     * Comparable identity of an image reference.
     *
     * Local files that actually exist get a content fingerprint (sha256);
     * everything else (remote URLs, missing files) is identified purely by
     * its normalized reference, so different remote images can never be
     * confused with each other.
     */
    public static function fingerprint(string $ref): ?string
    {
        $key = self::normalize($ref);

        if ($key === null) {
            return null;
        }

        // Remote references are only comparable by reference.
        if (self::isRemote($ref) && ! self::pointsAtOwnHost($ref)) {
            return 'ref:' . $key;
        }

        $path = self::localAbsolutePath($key);

        if ($path !== null && is_file($path) && is_readable($path)) {
            if (! isset(self::$hashMemo[$key])) {
                $hash = @hash_file('sha256', $path);

                self::$hashMemo[$key] = $hash !== false ? $hash : '';
            }

            if (self::$hashMemo[$key] !== '') {
                return 'sha256:' . self::$hashMemo[$key];
            }
        }

        return 'ref:' . $key;
    }

    /**
     * Normalize an image reference into a comparable key.
     *
     *  - unifies backslashes / repeated slashes,
     *  - strips leading slashes so `uploads/products/a.webp` equals
     *    `/uploads/products/a.webp`,
     *  - reduces absolute URLs pointing at this app's own host down to their
     *    local path,
     *  - drops query strings/fragments for locally stored files (cache
     *    busters must not split one file into two identities),
     *  - lower-cases remote hosts only (paths stay case-sensitive because
     *    file systems are).
     *
     * Returns null for empty references.
     */
    public static function normalize(string $ref): ?string
    {
        $ref = trim(str_replace('\\', '/', $ref));

        if ($ref === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $ref) === 1) {
            $host = strtolower((string) (parse_url($ref, PHP_URL_HOST) ?? ''));
            $path = self::collapseSlashes((string) (parse_url($ref, PHP_URL_PATH) ?? ''));

            // A URL served by this app IS the local file behind it.
            if ($host !== '' && self::isOwnHost($host)) {
                return ltrim($path, '/');
            }

            $query = (string) (parse_url($ref, PHP_URL_QUERY) ?? '');

            return $host . $path . ($query !== '' ? '?' . $query : '');
        }

        // Local-style reference: drop cache-busting query/fragment.
        $path = self::collapseSlashes(preg_replace('/[?#].*$/', '', $ref) ?? $ref);

        return ltrim($path, '/');
    }

    /**
     * Whether a raw reference points somewhere other than this app.
     */
    private static function isRemote(string $ref): bool
    {
        return preg_match('#^(https?:)?//#i', $ref) === 1;
    }

    /**
     * Whether a raw reference is an absolute URL aimed at this app itself.
     */
    private static function pointsAtOwnHost(string $ref): bool
    {
        if (! self::isRemote($ref)) {
            return false;
        }

        $host = strtolower((string) (parse_url($ref, PHP_URL_HOST) ?? ''));

        return $host !== '' && self::isOwnHost($host);
    }

    /**
     * Whether the given host is this app's own host (APP_URL or the current
     * request host).
     */
    private static function isOwnHost(string $host): bool
    {
        $appUrlHost = strtolower((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?? ''));

        if ($appUrlHost !== '' && hash_equals($appUrlHost, $host)) {
            return true;
        }

        try {
            $requestHost = strtolower((string) request()->getHost());
        } catch (\Throwable) {
            $requestHost = '';
        }

        return $requestHost !== '' && $requestHost === $host;
    }

    /**
     * Absolute filesystem path for a normalized local reference, or null
     * when the reference does not look like a local public asset.
     */
    private static function localAbsolutePath(string $key): ?string
    {
        // Normalized remote keys carry a host prefix ("example.com/img.png");
        // they can never resolve under public_path().
        if (! str_starts_with($key, 'uploads/') && ! str_starts_with($key, 'public/uploads/')) {
            return null;
        }

        $path = public_path(str_starts_with($key, 'public/') ? substr($key, 7) : $key);

        return str_starts_with($path, public_path()) ? $path : null;
    }

    /**
     * Collapse backslashes to forward slashes and repeated slashes to one.
     */
    private static function collapseSlashes(string $path): string
    {
        return preg_replace('#/{2,}#', '/', str_replace('\\', '/', $path)) ?? $path;
    }
}

