<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $sellers = [1, 13, 3];
        $base = '/uploads/products/';

        $items = [
            ['main' => 'Electronics', 'title' => 'Bluetooth Speaker', 'price' => 2499, 'image' => 'jbl-flip-6.webp', 'sub' => 'Speakers'],
            ['main' => 'Electronics', 'title' => 'Wireless Keyboard', 'price' => 1299, 'image' => 'logitech-mx-master-3s.webp', 'sub' => 'Computer Accessories'],
            ['main' => 'Fashion', 'title' => "Men's Casual Shirt", 'price' => 799, 'image' => 'adidas-running-shoes.webp', 'sub' => 'Men\'s Clothing'],
            ['main' => 'Fashion', 'title' => "Women's Handbag", 'price' => 1499, 'image' => 'american-tourister-backpack.webp', 'sub' => 'Bags & Accessories'],
            ['main' => 'Home & Kitchen', 'title' => 'Stainless Steel Cookware Set', 'price' => 2999, 'image' => 'philips-airfryer-xl.webp', 'sub' => 'Cookware & Utensils'],
            ['main' => 'Home & Kitchen', 'title' => 'Electric Kettle', 'price' => 1199, 'image' => 'philips-airfryer-xl.webp', 'sub' => 'Kitchen Appliances'],
            ['main' => 'Beauty & Personal Care', 'title' => 'Face Moisturiser', 'price' => 499, 'image' => 'ourpureplanet-prd-opp137-05.webp', 'sub' => 'Skincare'],
            ['main' => 'Beauty & Personal Care', 'title' => 'Hair Dryer', 'price' => 1599, 'image' => 'ourpureplanet-prd-opp137-05.webp', 'sub' => 'Haircare'],
            ['main' => 'Grocery & Food', 'title' => 'Basmati Rice 5kg', 'price' => 649, 'image' => '900838001-g2.webp', 'sub' => 'Packaged Foods'],
            ['main' => 'Grocery & Food', 'title' => 'Premium Dry Fruits', 'price' => 899, 'image' => '900838001-g2.webp', 'sub' => 'Dry Fruits & Nuts'],
            ['main' => 'Sports & Fitness', 'title' => 'Adjustable Dumbbells', 'price' => 2499, 'image' => 'adidas-running-shoes.webp', 'sub' => 'Fitness Equipment'],
            ['main' => 'Sports & Fitness', 'title' => 'Yoga Mat', 'price' => 699, 'image' => 'adidas-running-shoes.webp', 'sub' => 'Yoga & Exercise'],
            ['main' => 'Books & Education', 'title' => 'Programming Fundamentals Book', 'price' => 499, 'image' => 's-l500.webp', 'sub' => 'Academic Books'],
            ['main' => 'Books & Education', 'title' => 'Engineering Mathematics Book', 'price' => 599, 'image' => 's-l500.webp', 'sub' => 'Educational Materials'],
            ['main' => 'Toys & Games', 'title' => 'Building Blocks Set', 'price' => 799, 'image' => 'kpzptljbcshx37sgqcjxt8.webp', 'sub' => 'Educational Toys'],
            ['main' => 'Toys & Games', 'title' => 'Board Game', 'price' => 999, 'image' => 'kpzptljbcshx37sgqcjxt8.webp', 'sub' => 'Board Games'],
            ['main' => 'Automotive', 'title' => 'Car Phone Holder', 'price' => 399, 'image' => '900838001-g2.webp', 'sub' => 'Car Accessories'],
            ['main' => 'Automotive', 'title' => 'Tyre Inflator', 'price' => 1299, 'image' => '900838001-g2.webp', 'sub' => 'Tools & Maintenance'],
            ['main' => 'Health & Wellness', 'title' => 'Digital Thermometer', 'price' => 349, 'image' => 'ourpureplanet-prd-opp137-05.webp', 'sub' => 'Personal Health Devices'],
            ['main' => 'Health & Wellness', 'title' => 'Heating Pad', 'price' => 899, 'image' => 'ourpureplanet-prd-opp137-05.webp', 'sub' => 'Wellness Accessories'],
            ['main' => 'Furniture', 'title' => 'Study Desk', 'price' => 4999, 'image' => 'hp-pavilion-15-laptop.webp', 'sub' => 'Tables & Desks'],
            ['main' => 'Furniture', 'title' => 'Ergonomic Study Stool', 'price' => 2499, 'image' => 'hp-pavilion-15-laptop.webp', 'sub' => 'Office Chairs'],
            ['main' => 'Pet Supplies', 'title' => 'Pet Feeding Bowl', 'price' => 299, 'image' => 'kpzptljbcshx37sgqcjxt8.webp', 'sub' => 'Dog Supplies'],
            ['main' => 'Pet Supplies', 'title' => 'Adjustable Dog Leash', 'price' => 499, 'image' => 'kpzptljbcshx37sgqcjxt8.webp', 'sub' => 'Dog Supplies'],
            ['main' => 'Tools & Hardware', 'title' => 'Screwdriver Set', 'price' => 599, 'image' => 'logitech-mx-master-3s.webp', 'sub' => 'Hand Tools'],
            ['main' => 'Tools & Hardware', 'title' => 'Digital Measuring Tape', 'price' => 899, 'image' => 'logitech-mx-master-3s.webp', 'sub' => 'Hand Tools'],
            ['main' => 'Outdoor & Travel', 'title' => 'Travel Duffel Bag', 'price' => 1799, 'image' => 'american-tourister-backpack.webp', 'sub' => 'Travel Bags'],
            ['main' => 'Outdoor & Travel', 'title' => 'Camping Lantern', 'price' => 699, 'image' => 'american-tourister-backpack.webp', 'sub' => 'Camping Equipment'],
            ['main' => 'Baby Products', 'title' => 'Baby Feeding Bottle', 'price' => 399, 'image' => 'ourpureplanet-prd-opp137-05.webp', 'sub' => 'Feeding Accessories'],
            ['main' => 'Baby Products', 'title' => 'Baby Blanket', 'price' => 699, 'image' => 'ourpureplanet-prd-opp137-05.webp', 'sub' => 'Baby Care'],
            ['main' => 'Office & Business', 'title' => 'Office Paper Pack', 'price' => 349, 'image' => 's-l500.webp', 'sub' => 'Office Stationery'],
            ['main' => 'Office & Business', 'title' => 'Desk Organiser', 'price' => 599, 'image' => 's-l500.webp', 'sub' => 'Desk Accessories'],
            ['main' => 'Gaming', 'title' => 'Gaming Mouse', 'price' => 1299, 'image' => 'logitech-mx-master-3s.webp', 'sub' => 'Gaming Accessories'],
            ['main' => 'Gaming', 'title' => 'Gaming Headset', 'price' => 2499, 'image' => 'sony-wh-1000xm5.webp', 'sub' => 'Gaming Accessories'],
            ['main' => 'Garden & Outdoor Living', 'title' => 'Garden Hose', 'price' => 799, 'image' => 'adidas-running-shoes.webp', 'sub' => 'Gardening Tools'],
            ['main' => 'Garden & Outdoor Living', 'title' => 'Gardening Tool Set', 'price' => 999, 'image' => 'adidas-running-shoes.webp', 'sub' => 'Gardening Tools'],
            ['main' => 'Religious & Spiritual', 'title' => 'Prayer Beads', 'price' => 299, 'image' => 's-l500.webp', 'sub' => 'Prayer Accessories'],
            ['main' => 'Religious & Spiritual', 'title' => 'Decorative Puja Thali', 'price' => 799, 'image' => 's-l500.webp', 'sub' => 'Festival & Worship Supplies'],
            ['main' => 'Gifts & Collectibles', 'title' => 'Decorative Gift Box', 'price' => 499, 'image' => 'kpzptljbcshx37sgqcjxt8.webp', 'sub' => 'Decorative Gifts'],
            ['main' => 'Gifts & Collectibles', 'title' => 'Collectible Figurine', 'price' => 899, 'image' => 'kpzptljbcshx37sgqcjxt8.webp', 'sub' => 'Collectible Figures'],
        ];

        $i = 0;
        foreach ($items as $item) {
            $main = Category::where('name', $item['main'])->whereNull('parent_id')->first();
            if (! $main) continue;
            $sub = $main->children()->where('name', $item['sub'])->first();
            if (! $sub) $sub = $main->children()->first();
            if (! $sub) continue;

            $sku = 'CAT3-' . strtoupper(str_replace(' ', '', $item['title'])) . '-' . $main->id . '-' . $i;
            $sellerId = $sellers[$i % count($sellers)];

            Product::firstOrCreate(
                ['sku' => $sku],
                [
                    'slug' => strtolower(str_replace(' ', '-', $item['title'])) . '-' . $i,
                    'title' => $item['title'],
                    'subtitle' => $item['title'] . ' for everyday use.',
                    'description' => 'A reliable ' . $item['title'] . ' for daily use.',
                    'image' => $base . $item['image'],
                    'images' => [],
                    'details' => ['Category' => $item['main']],
                    'price' => $item['price'],
                    'special_price' => null,
                    'quantity' => 15,
                    'reserved' => 0,
                    'low_stock_threshold' => 5,
                    'stock_status' => 'in-stock',
                    'category_id' => $sub->id,
                    'category_name' => $item['main'],
                    'subcategory' => $sub->name,
                    'brand' => 'KDP Mart',
                    'tax' => 18,
                    'status' => 1,
                    'tags' => [$sub->name],
                    'options' => [],
                    'variants' => [],
                    'is_seed' => true,
                    'seller_id' => $sellerId,
                    'owner_type' => $sellerId === 1 ? 'store' : 'seller',
                ]
            );
            $i++;
        }
    }
}
