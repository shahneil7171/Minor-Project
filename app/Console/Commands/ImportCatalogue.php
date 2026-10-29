<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;

class ImportCatalogue extends Command
{
    protected $signature = 'catalogue:import';
    protected $description = 'Import catalogue from CSV';

    public function handle(): int
    {
        $path = storage_path('app/catalogue.csv');
        if (!file_exists($path)) {
            $this->error('CSV not found');
            return 1;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        // remove BOM
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        $sellerMap = [
            'Admin (ID 1)' => 1,
            'Sadie Sink (ID 13)' => 13,
            'Manav Rajput (ID 3)' => 3,
        ];

        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row[1])) continue;
            $data = array_combine($header, $row);

            $mainName = trim($data['Main Category']);
            $subName = trim($data['Subcategory']);
            $main = Category::where('name', $mainName)->whereNull('parent_id')->first();
            if (!$main) {
                $this->warn("Main not found: $mainName");
                continue;
            }
            $sub = $main->children()->where('name', $subName)->first();
            if (!$sub) {
                $sub = $main->children()->first();
            }

            $sellerId = $sellerMap[trim($data['Suggested Seller Account'])] ?? 1;

            $sku = trim($data['Model / SKU']);
            $product = Product::firstOrNew(['sku' => $sku]);
            if (!$product->exists) {
                // new product
            }

            $product->title = trim($data['Product Name']);
            $product->subtitle = trim($data['Subtitle']);
            $product->brand = trim($data['Brand / Manufacturer']);
            $product->description = trim($data['Description']);
            $product->price = (float) $data['Price (₹)'];
            $sp = trim($data['Special Price (₹)']);
            $product->special_price = $sp !== '' ? (float) $sp : null;
            $product->quantity = (int) $data['Quantity'];
            $product->stock_status = (trim($data['Stock Status']) === 'In Stock') ? 'in-stock' : 'out-of-stock';
            $product->tax = (float) $data['Tax (%)'];
            $product->status = (trim($data['Status']) === 'Enabled') ? 1 : 0;
            $product->category_id = $sub->id;
            $product->category_name = $mainName;
            $product->subcategory = $sub->name;
            $product->slug = trim($data['SEO Slug']);
            $tags = array_map('trim', explode(',', $data['Tags (comma-separated)']));
            $product->tags = $tags;
            $details = array_filter(array_map('trim', explode("\n", $data['Product Details (one per line)'])));
            $product->details = $details;
            $product->seller_id = $sellerId;
            $product->owner_type = $sellerId === 1 ? 'store' : 'seller';
            $product->is_seed = true;

            // image: try to find a matching file in uploads/products by keyword
            // leave image as-is if already set and file exists
            if (empty($product->image) || !file_exists(public_path(ltrim($product->image, '/')))) {
                // try to find an image containing a word from title
                $words = explode(' ', strtolower($product->title));
                $found = null;
                foreach (scandir(public_path('uploads/products')) as $f) {
                    if ($f === '.' || $f === '..') continue;
                    foreach ($words as $w) {
                        if (strlen($w) > 3 && stripos($f, $w) !== false) {
                            $found = '/uploads/products/' . $f;
                            break 2;
                        }
                    }
                }
                if ($found) {
                    $product->image = $found;
                }
            }

            $product->save();
            $count++;
        }
        fclose($handle);

        $this->info("Imported/updated $count products.");
        return 0;
    }
}
