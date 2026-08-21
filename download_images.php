<?php

/**
 * download_images.php
 *
 * Downloads reliable external product images, converts them to optimised
 * WebP files under public/uploads/products/, then regenerates
 * storage/app/private/custom_products.json with the new catalogue entries
 * pointing at the freshly downloaded local assets.
 *
 * Usage:
 *   php -d max_execution_time=180 download_images.php
 */

use Illuminate\Support\Facades\Storage;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/* ---------------------------------------------------------------------
 | Configuration
 * ------------------------------------------------------------------ */

$cwebpPath = __DIR__ . '/libwebp_tools/libwebp-1.4.0-windows-x64/bin/cwebp.exe';

if (! file_exists($cwebpPath)) {
    fwrite(STDERR, "ERROR: cwebp binary not found at {$cwebpPath}\n");
    exit(1);
}

/*
 * Product slug => definition + remote image URL.
 * Every URL below was verified live (HTTP 200 + image/* content-type).
 */
$productDefinitions = [];

$productDefinitions['adidas-running-shoes'] = [
    'url'          => 'https://images.pexels.com/photos/10351325/pexels-photo-10351325.jpeg',
    'title'        => 'Adidas Ultraboost Running Shoes',
    'sku'          => 'AD-RUN-001',
    'subtitle'     => 'Lightweight knit upper with responsive Boost cushioning for every stride.',
    'description'  => 'Engineered for daily runs and long-distance comfort, the Adidas Ultraboost combines a breathable Primeknit upper with responsive Boost midsole cushioning. The Continental rubber outsole delivers superior grip on wet and dry surfaces.',
    'details'      => [
        'Primeknit+ upper adapts to your foot shape',
        'Boost midsole returns energy with every step',
        'Continental rubber outsole for all-condition grip',
        'Torsion System for midfoot integrity',
        'Machine-washable construction',
    ],
    'price'         => 189,
    'special_price' => 149,
    'quantity'      => 24,
    'stock_status'  => 'in-stock',
    'category'      => 'Sports',
    'subcategory'   => 'Shoes',
    'brand'         => 'Adidas',
    'tax'           => 18,
    'status'        => 1,
    'slug'          => 'adidas-running-shoes',
    'tags'          => ['shoes', 'running', 'sportswear', 'boost'],
    'options'       => [],
    'variants'      => [],
];

$productDefinitions['american-tourister-backpack'] = [
    'url'          => 'https://images.pexels.com/photos/7673339/pexels-photo-7673339.jpeg',
    'title'        => 'American Tourister Urban Groove Backpack',
    'sku'          => 'AT-BPK-002',
    'subtitle'     => 'Water-resistant travel backpack with padded laptop sleeve and USB pass-through.',
    'description'  => 'Built for commuters and weekend travellers alike, the American Tourister Urban Groove offers a dedicated 15.6" laptop compartment, multiple organiser pockets, and a water-resistant polyester shell that keeps gear safe in unpredictable weather.',
    'details'      => [
        'Fits laptops up to 15.6 inches',
        'Water-resistant recycled polyester shell',
        'Ergonomic Air-Mesh back panel and straps',
        'Quick-access front pocket with RFID protection',
        'USB charging port pass-through (power bank not included)',
    ],
    'price'         => 79,
    'special_price' => 59,
    'quantity'      => 35,
    'stock_status'  => 'in-stock',
    'category'      => 'Travel',
    'subcategory'   => 'Bags',
    'brand'         => 'American Tourister',
    'tax'           => 12,
    'status'        => 1,
    'slug'          => 'american-tourister-backpack',
    'tags'          => ['backpack', 'travel', 'laptop-bag', 'commuter'],
    'options'       => [],
    'variants'      => [],
];

$productDefinitions['apple-watch-se'] = [
    'url'          => 'https://images.pexels.com/photos/4064301/pexels-photo-4064301.jpeg',
    'title'        => 'Apple Watch SE (2nd Gen) GPS 44mm',
    'sku'          => 'APL-WCH-003',
    'subtitle'     => 'Crash Detection, sleep tracking, and all-day battery in a lighter aluminium case.',
    'description'  => 'Apple Watch SE pairs essential health features with the speed of the S8 SiP chip. Track workouts, monitor heart rate, receive notifications, and stay connected — all wrapped in a durable Ion-X strengthened glass and aluminium design.',
    'details'      => [
        'S8 SiP chip with 64-bit dual-core processor',
        'Crash Detection and Fall Detection',
        'Comprehensive sleep tracking with Sleep Stages',
        'Water resistant to 50 metres',
        'Up to 18 hours of battery life',
    ],
    'price'         => 279,
    'special_price' => 229,
    'quantity'      => 15,
    'stock_status'  => 'in-stock',
    'category'      => 'Electronics',
    'subcategory'   => 'Wearables',
    'brand'         => 'Apple',
    'tax'           => 18,
    'status'        => 1,
    'slug'          => 'apple-watch-se',
    'tags'          => ['smartwatch', 'wearable', 'fitness-tracker', 'apple'],
    'options'       => [],
    'variants'      => [],
];

$productDefinitions['hp-pavilion-15-laptop'] = [
    'url'          => 'https://images.pexels.com/photos/3974810/pexels-photo-3974810.jpeg',
    'title'        => 'HP Pavilion 15 Laptop (Intel i5 / 16GB / 512GB SSD)',
    'sku'          => 'HP-LPT-005',
    'subtitle'     => 'Thin-and-light everyday performer with a 15.6" FHD IPS display and backlit keyboard.',
    'description'  => 'The HP Pavilion 15 balances productivity and entertainment with an Intel Core i5 processor, 16GB DDR4 memory, and a fast 512GB PCIe NVMe SSD. A narrow-bezel 15.6-inch FHD IPS display and Bang & Olufsen audio make it equally suited for work sessions and movie nights.',
    'details'      => [
        'Intel Core i5-1235U (10-core) processor',
        '16GB DDR4 RAM with 512GB PCIe NVMe SSD',
        '15.6" FHD IPS micro-edge anti-glare display',
        'Backlit keyboard with numeric keypad',
        'Windows 11 Home pre-installed',
    ],
    'price'         => 899,
    'special_price' => 779,
    'quantity'      => 12,
    'stock_status'  => 'in-stock',
    'category'      => 'Electronics',
    'subcategory'   => 'Computers',
    'brand'         => 'HP',
    'tax'           => 18,
    'status'        => 1,
    'slug'          => 'hp-pavilion-15-laptop',
    'tags'          => ['laptop', 'notebook', 'intel', 'windows-11'],
    'options'       => [],
    'variants'      => [],
];

$productDefinitions['jbl-flip-6'] = [
    'url'          => 'https://images.pexels.com/photos/7245198/pexels-photo-7245198.jpeg',
    'title'        => 'JBL Flip 6 Portable Bluetooth Speaker',
    'sku'          => 'JBL-SPEAKER-006',
    'subtitle'     => 'Bold JBL Pro Sound, IP67 waterproof, and 12 hours of playtime in a rugged build.',
    'description'  => 'The JBL Flip 6 delivers surprisingly big sound from a compact body. A separate tweeter produces crisp highs while dual bass radiators deliver punchy lows. With IP67 dust/water resistance it is ready for pool parties, beach days, and everything between.',
    'details'      => [
        'JBL Pro Sound with separate tweeter and dual bass radiators',
        'IP67 waterproof and dustproof rating',
        'Up to 12 hours of playtime per charge',
        'PartyBoost pairing for multi-speaker setups',
        'USB-C charging with quick-charge support',
    ],
    'price'         => 139,
    'special_price' => 109,
    'quantity'      => 30,
    'stock_status'  => 'in-stock',
    'category'      => 'Electronics',
    'subcategory'   => 'Audio',
    'brand'         => 'JBL',
    'tax'           => 18,
    'status'        => 1,
    'slug'          => 'jbl-flip-6',
    'tags'          => ['speaker', 'bluetooth', 'portable-audio', 'waterproof'],
    'options'       => [],
    'variants'      => [],
];

$productDefinitions['logitech-mx-master-3s'] = [
    'url'          => 'https://images.unsplash.com/photo-1516574187841-cb9cc2ca948b?auto=format&fit=crop&w=800&q=80',
    'title'        => 'Logitech MX Master 3S Wireless Mouse',
    'sku'          => 'LOGI-MS-007',
    'subtitle'     => '8K DPI tracking on any surface, quiet clicks, and MagSpeed scrolling.',
    'description'  => 'The MX Master 3S redefines precision and comfort. Its 8000-DPI Darkfield sensor tracks flawlessly even on glass, while near-silent clicks keep shared spaces peaceful. The electromagnetic MagSpeed scroll wheel zips through 1000 lines per second.',
    'details'      => [
        '8K DPI Darkfield high-precision tracking on any surface',
        'Quiet Clicks — 90% less click noise',
        'MagSpeed electromagnetic scroll wheel (1000 lines/sec)',
        'Multi-device connectivity via Bluetooth LE and Logi Bolt',
        'Up to 70 days of battery life on a single charge',
    ],
    'price'         => 119,
    'special_price' => 99,
    'quantity'      => 42,
    'stock_status'  => 'in-stock',
    'category'      => 'Electronics',
    'subcategory'   => 'Accessories',
    'brand'         => 'Logitech',
    'tax'           => 18,
    'status'        => 1,
    'slug'          => 'logitech-mx-master-3s',
    'tags'          => ['mouse', 'wireless-peripherals', 'ergonomic', 'logitech'],
    'options'       => [],
    'variants'      => [],
];

$productDefinitions['philips-airfryer-xl'] = [
    'url'          => 'https://cdn.pixabay.com/photo/2025/03/16/06/48/air-fryer-9473336_1280.jpg',
    'title'        => 'Philips Premium Airfryer XXL',
    'sku'          => 'PHI-AFR-008',
    'subtitle'     => 'Rapid Air technology fries with up to 90% less fat — no oil required.',
    'description'  => 'Cook healthier versions of family favourites with the Philips Premium Airfryer XXL. Its Rapid Air technology circulates hot air around food for crispy results with little to no oil, and the 7.3L family-sized basket handles whole chickens and generous portions with ease.',
    'details'      => [
        'Rapid Air technology — up to 90% less fat than deep frying',
        '7.3L capacity fits a whole chicken or 1.4kg of fries',
        'Smart presets with automatic time & temperature',
        'Dishwasher-safe removable basket and drawer',
        'Keep-warm function ready when you are',
    ],
    'price'         => 299,
    'special_price' => 249,
    'quantity'      => 18,
    'stock_status'  => 'in-stock',
    'category'      => 'Home Appliances',
    'subcategory'   => 'Kitchen',
    'brand'         => 'Philips',
    'tax'           => 18,
    'status'        => 1,
    'slug'          => 'philips-airfryer-xl',
    'tags'          => ['air-fryer', 'kitchen-appliance', 'cooking', 'healthy-eating'],
    'options'       => [],
    'variants'      => [],
];

$productDefinitions['canon-eos-1500d'] = [
    'url'          => 'https://images.pexels.com/photos/1591057/pexels-photo-1591057.jpeg',
    'title'        => 'Canon EOS 1500D DSLR Camera Kit',
    'sku'          => 'CAN-EOS-004',
    'subtitle'     => 'Entry-level DSLR with 24.1MP sensor, Full HD video, and built-in Wi-Fi/NFC.',
    'description'  => 'Step up to DSLR image quality with the Canon EOS 1500D. Its 24.1-megapixel APS-C CMOS sensor captures richly detailed stills while the DIGIC 4+ processor enables smooth Full HD 1080p recording at 30fps. Scene Intelligent Auto mode makes great shots effortless for beginners.',
    'details'      => [
        '24.1MP APS-C CMOS sensor with optical viewfinder',
        'DIGIC 4+ image processor for fast performance',
        'Full HD 1080p video at up to 30 fps',
        'Wi-Fi and NFC for easy sharing to smart devices',
        'ISO range of 100–12800 for low-light shooting',
    ],
    'price'         => 549,
    'special_price' => 479,
    'quantity'      => 8,
    'stock_status'  => 'in-stock',
    'category'      => 'Electronics',
    'subcategory'   => 'Cameras',
    'brand'         => 'Canon',
    'tax'           => 18,
    'status'        => 1,
    'slug'          => 'canon-eos-1500d',
    'tags'          => ['dslr', 'camera', 'photography', 'canon'],
    'options'       => [],
    'variants'      => [],
];

$productDefinitions['samsung-galaxy-a56'] = [
    'url'          => 'https://images.pexels.com/photos/3375859/pexels-photo-3375859.jpeg',
    'title'        => 'Samsung Galaxy A56 5G (256GB)',
    'sku'          => 'SMG-A56-009',
    'subtitle'     => 'Super AMOLED 120Hz display, 50MP OIS camera, and all-day 5000mAh battery.',
    'description'  => 'The Galaxy A56 brings flagship-grade features to a mid-range price point. Enjoy buttery-smooth scrolling on its 6.7-inch Super AMOLED 120Hz display, capture sharp photos day or night with the 50MP main sensor featuring optical image stabilisation, and stay powered through two days of typical use.',
    'details'      => [
        '6.7" Super AMOLED, 120Hz adaptive refresh rate',
        'Exynos 1580 chipset with 8GB RAM',
        '50MP main camera with OIS + 12MP ultra-wide',
        '5000mAh battery with 45W super-fast charging',
        'IP67 water and dust resistant build',
    ],
    'price'         => 449,
    'special_price' => 399,
    'quantity'      => 26,
    'stock_status'  => 'in-stock',
    'category'      => 'Electronics',
    'subcategory'   => 'Mobile Phones',
    'brand'         => 'Samsung',
    'tax'           => 18,
    'status'        => 1,
    'slug'          => 'samsung-galaxy-a56',
    'tags'          => ['smartphone', 'android', '5g', 'samsung'],
    'options'       => [],
    'variants'      => [],
];

$productDefinitions['sony-wh-1000xm5'] = [
    'url'          => 'https://images.pexels.com/photos/10482758/pexels-photo-10482758.jpeg',
    'title'        => 'Sony WH-1000XM5 Wireless Noise Cancelling Headphones',
    'sku'          => 'SNY-HDPH-010',
    'subtitle'     => 'Industry-leading noise cancellation, 30-hour battery, and crystal-clear calls.',
    'description'  => 'Two processors and eight microphones work together to deliver Sony\'s best-ever noise cancellation. The lightweight design keeps listening comfortable for hours while Adaptive Sound Control adjusts ambient sound automatically.',
    'details'      => [
        'Dual processor noise cancellation (V1 + QN1)',
        'Up to 30 hours of playback with ANC on',
        'Precise Voice Pickup with four beamforming mics',
        'Multipoint connection to two devices simultaneously',
        'Speak-to-Chat pauses music when you start talking',
    ],
    'price'         => 399,
    'special_price' => 349,
    'quantity'      => 14,
    'stock_status'  => 'in-stock',
    'category'      => 'Electronics',
    'subcategory'   => 'Audio',
    'brand'         => 'Sony',
    'tax'           => 18,
    'status'        => 1,
    'slug'          => 'sony-wh-1000xm5',
    'tags'          => ['headphones', 'anc', 'wireless-audio', 'noise-cancelling'],
    'options'       => [],
    'variants'      => [],
];


/* ---------------------------------------------------------------------
 | Helpers
 * ------------------------------------------------------------------ */

/** Download a remote image; returns [binary|null, contentType, httpCode, error]. */
function fetchImage(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
    ]);
    $body        = curl_exec($ch);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $httpCode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error       = curl_error($ch);
    curl_close($ch);

    return [$body !== false ? $body : null, $contentType, $httpCode, $error];
}

/** Convert a JPEG/PNG file into an optimised WebP file. Returns bool. */
function convertToWebp(string $cwebp, string $sourceFile, string $destFile): bool
{
    // -q 82 balances size vs fidelity; -resize 1600 0 caps width.
    $cmd = sprintf(
        '"%s" -quiet -q 82 -resize 1600 0 "%s" -o "%s"',
        $cwebp,
        $sourceFile,
        $destFile
    );

    exec($cmd . ' 2>&1', $outputLines, $exitCode);

    return $exitCode === 0 && file_exists($destFile) && filesize($destFile) > 1024;
}

/* ---------------------------------------------------------------------
 | Main routine
 * ------------------------------------------------------------------ */

$outputDir = __DIR__ . '/public/uploads/products';
if (! is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

$tempDir = rtrim(sys_get_temp_dir(), '/\\');
$results = [];
$builtProducts = [];

foreach ($productDefinitions as $slug => $def) {
    echo "── Processing {$slug}\n";

    [$imageData, $contentType, $httpCode, $curlError] = fetchImage($def['url']);

    if ($httpCode !== 200 || $imageData === null || stripos($contentType, 'image') === false) {
        echo "   FAIL  http={$httpCode} type={$contentType} err={$curlError}\n";
        $results[] = ['slug' => $slug, 'ok' => false];
        continue;
    }

    $ext     = str_contains($contentType, 'png') ? 'png' : 'jpg';
    $tempSrc = $tempDir . DIRECTORY_SEPARATOR . $slug . '.' . $ext;
    file_put_contents($tempSrc, $imageData);

    $destFile = $outputDir . DIRECTORY_SEPARATOR . $slug . '.webp';
    if (! convertToWebp($cwebpPath, $tempSrc, $destFile)) {
        echo "   FAIL  WebP conversion for {$slug}\n";
        @unlink($tempSrc);
        $results[] = ['slug' => $slug, 'ok' => false];
        continue;
    }

    @unlink($tempSrc);

    $sizeKb = round(filesize($destFile) / 1024, 1);
    echo "   OK    saved {$slug}.webp ({$sizeKb} KB)\n";
    $results[] = ['slug' => $slug, 'ok' => true];

    $localPath = "/uploads/products/{$slug}.webp";

    $builtProducts[$slug] = [
        'title'          => $def['title'],
        'sku'            => $def['sku'],
        'subtitle'       => $def['subtitle'],
        'description'    => $def['description'],
        'image'          => $localPath,
        'images'         => [$localPath],
        'details'        => $def['details'],
        'price'          => $def['price'],
        'special_price'  => $def['special_price'],
        'quantity'       => $def['quantity'],
        'stock_status'   => $def['stock_status'],
        'category'       => $def['category'],
        'subcategory'    => $def['subcategory'],
        'brand'          => $def['brand'],
        'tax'            => $def['tax'],
        'status'         => $def['status'],
        'slug'           => $def['slug'],
        'tags'           => $def['tags'],
        'options'        => [],
        'variants'       => [],
    ];
}

$successCount = count(array_filter($results, fn ($r) => $r['ok']));
echo "\nDownloaded & converted {$successCount}/" . count($results) . " images\n";

if ($successCount === 0) {
    fwrite(STDERR, "Nothing downloaded — leaving custom_products.json untouched.\n");
    exit(1);
}

$existing = Storage::disk('local')->exists('custom_products.json')
    ? json_decode(Storage::disk('local')->get('custom_products.json'), true) ?? []
    : [];

$merged = array_merge($existing, $builtProducts);

Storage::disk('local')->put(
    'custom_products.json',
    json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

echo "custom_products.json updated — " . count($merged) . " total custom products.\n";

