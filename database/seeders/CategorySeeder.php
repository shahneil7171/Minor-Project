<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the full KDP MART category tree into the EXISTING `categories`
 * table (parent_id / name / slug / sort_order / is_active).
 *
 * Idempotent and non-destructive — safe to run repeatedly
 * (`php artisan db:seed` or `php artisan db:seed --class=CategorySeeder`):
 *
 *  - Every main category and subcategory is looked up by its STABLE SLUG
 *    first. Existing rows (notably `electronics` and `fashion`) are REUSED
 *    so their IDs — and every product relationship pointing at them — are
 *    preserved. No duplicates are ever created.
 *  - Nothing is deleted, renamed, re-parented or re-enabled here. Admin
 *    edits (renames, disabled state, sort order of existing rows) survive
 *    re-seeding.
 *  - categories.slug is globally unique, while some subcategory names
 *    legitimately appear under more than one parent (e.g. "Backpacks"
 *    under Fashion AND Outdoor & Travel). The first parent keeps the plain
 *    slug; later parents get a deterministic parent-scoped slug
 *    (e.g. `outdoor-travel-backpacks`), so every re-run finds the same row.
 */
class CategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The complete tree: 20 main categories in display order, each with its
     * subcategories in display order. Slugs are derived from the names with
     * Str::slug() ("Home & Kitchen" -> "home-kitchen").
     *
     * @var array<int, array{name: string, slug: string, children: array<int, string>}>
     */
    private const TREE = [
        ['name' => 'Electronics', 'slug' => 'electronics', 'children' => [
            'Mobiles',
            'Mobile Accessories',
            'Laptops',
            'Laptop Accessories',
            'Tablets',
            'Computers',
            'Computer Accessories',
            'Monitors',
            'Printers & Scanners',
            'Cameras',
            'Camera Accessories',
            'Headphones & Earphones',
            'Speakers',
            'Home Audio',
            'Televisions',
            'Streaming Devices',
            'Smart Home',
            'Smart Watches',
            'Wearable Technology',
            'Power Banks',
            'Chargers & Cables',
            'Networking Devices',
            'Storage Devices',
            'Computer Components',
            'Gaming Accessories',
            'Electronic Gadgets',
        ]],
        ['name' => 'Fashion', 'slug' => 'fashion', 'children' => [
            'Men\'s Clothing',
            'Women\'s Clothing',
            'Kids Clothing',
            'Boys Clothing',
            'Girls Clothing',
            'Men\'s Footwear',
            'Women\'s Footwear',
            'Kids Footwear',
            'Watches',
            'Handbags',
            'Backpacks',
            'Wallets',
            'Belts',
            'Sunglasses',
            'Jewelry',
            'Fashion Accessories',
            'Ethnic Wear',
            'Western Wear',
            'Sportswear',
            'Winter Wear',
            'Innerwear',
            'Luggage & Travel Bags',
        ]],
        ['name' => 'Home & Kitchen', 'slug' => 'home-kitchen', 'children' => [
            'Kitchen Appliances',
            'Cookware',
            'Bakeware',
            'Kitchen Tools',
            'Dining',
            'Dinnerware',
            'Glassware',
            'Home Decor',
            'Wall Decor',
            'Clocks',
            'Curtains',
            'Rugs & Carpets',
            'Bedding',
            'Pillows',
            'Blankets',
            'Bathroom Accessories',
            'Storage & Organization',
            'Cleaning Supplies',
            'Home Improvement',
            'Home Appliances',
            'Lighting',
            'Lamps',
            'Fans',
            'Air Coolers',
        ]],
        ['name' => 'Beauty & Personal Care', 'slug' => 'beauty-personal-care', 'children' => [
            'Makeup',
            'Skincare',
            'Face Care',
            'Hair Care',
            'Hair Styling',
            'Fragrances',
            'Perfumes',
            'Bath & Body',
            'Grooming',
            'Shaving & Hair Removal',
            'Oral Care',
            'Beauty Tools',
            'Personal Care Appliances',
            'Nail Care',
            'Beauty Accessories',
        ]],
        ['name' => 'Grocery & Food', 'slug' => 'grocery-food', 'children' => [
            'Snacks',
            'Beverages',
            'Tea & Coffee',
            'Packaged Food',
            'Cooking Essentials',
            'Spices',
            'Rice & Grains',
            'Pulses',
            'Flour',
            'Breakfast Foods',
            'Bakery Products',
            'Chocolates & Sweets',
            'Dry Fruits',
            'Nuts & Seeds',
            'Instant Food',
            'Sauces & Condiments',
            'Canned Food',
            'Organic Food',
        ]],
        ['name' => 'Sports & Fitness', 'slug' => 'sports-fitness', 'children' => [
            'Fitness Equipment',
            'Gym Accessories',
            'Yoga',
            'Running',
            'Sportswear',
            'Cricket',
            'Football',
            'Basketball',
            'Badminton',
            'Tennis',
            'Table Tennis',
            'Cycling',
            'Swimming',
            'Outdoor Sports',
            'Team Sports',
            'Sports Accessories',
            'Fitness Trackers',
        ]],
        ['name' => 'Books & Education', 'slug' => 'books-education', 'children' => [
            'Fiction',
            'Non-Fiction',
            'Academic Books',
            'Engineering Books',
            'Computer Science Books',
            'Competitive Exam Books',
            'School Books',
            'Children\'s Books',
            'Educational Materials',
            'Stationery',
            'Notebooks',
            'Writing Instruments',
            'Art Supplies',
            'School Supplies',
            'Office Stationery',
            'Educational Toys',
        ]],
        ['name' => 'Toys & Games', 'slug' => 'toys-games', 'children' => [
            'Educational Toys',
            'Board Games',
            'Card Games',
            'Puzzles',
            'Action Figures',
            'Dolls',
            'Remote Control Toys',
            'Building Sets',
            'Outdoor Toys',
            'Musical Toys',
            'Baby Toys',
            'Collectible Toys',
            'Party Games',
            'Video Games',
            'Gaming Accessories',
        ]],
        ['name' => 'Automotive', 'slug' => 'automotive', 'children' => [
            'Car Accessories',
            'Bike Accessories',
            'Car Electronics',
            'Bike Electronics',
            'Car Care',
            'Cleaning & Detailing',
            'Car Interior Accessories',
            'Car Exterior Accessories',
            'Motorcycle Accessories',
            'Helmets',
            'Vehicle Lighting',
            'Tyres & Accessories',
            'Tools',
            'Safety Accessories',
            'Travel Accessories',
        ]],
        ['name' => 'Health & Wellness', 'slug' => 'health-wellness', 'children' => [
            'Healthcare Devices',
            'Fitness & Wellness',
            'First Aid',
            'Personal Care',
            'Health Monitoring',
            'Massage & Relaxation',
            'Sleep & Wellness',
            'Medical Accessories',
            'Mobility Accessories',
            'Wellness Products',
        ]],
        ['name' => 'Furniture', 'slug' => 'furniture', 'children' => [
            'Living Room Furniture',
            'Sofas',
            'Chairs',
            'Tables',
            'Coffee Tables',
            'TV Units',
            'Bedroom Furniture',
            'Beds',
            'Wardrobes',
            'Dressers',
            'Office Furniture',
            'Office Chairs',
            'Desks',
            'Bookshelves',
            'Storage Furniture',
            'Outdoor Furniture',
            'Kids Furniture',
        ]],
        ['name' => 'Pet Supplies', 'slug' => 'pet-supplies', 'children' => [
            'Dog Supplies',
            'Cat Supplies',
            'Pet Food',
            'Pet Toys',
            'Pet Beds',
            'Pet Grooming',
            'Pet Accessories',
            'Pet Bowls & Feeders',
            'Aquarium Supplies',
            'Bird Supplies',
            'Small Animal Supplies',
        ]],
        ['name' => 'Tools & Hardware', 'slug' => 'tools-hardware', 'children' => [
            'Hand Tools',
            'Power Tools',
            'Tool Sets',
            'Hardware',
            'Electrical Tools',
            'Plumbing Supplies',
            'Measuring Tools',
            'Workshop Equipment',
            'Safety Equipment',
            'Fasteners',
            'Adhesives',
            'Locks & Security',
            'Building Supplies',
        ]],
        ['name' => 'Outdoor & Travel', 'slug' => 'outdoor-travel', 'children' => [
            'Luggage',
            'Suitcases',
            'Backpacks',
            'Travel Bags',
            'Travel Accessories',
            'Camping',
            'Hiking',
            'Trekking',
            'Outdoor Gear',
            'Tents',
            'Sleeping Bags',
            'Travel Organizers',
            'Travel Safety',
            'Picnic Accessories',
        ]],
        ['name' => 'Baby Products', 'slug' => 'baby-products', 'children' => [
            'Baby Clothing',
            'Baby Footwear',
            'Diapers',
            'Baby Feeding',
            'Baby Bottles',
            'Baby Care',
            'Baby Bath',
            'Baby Grooming',
            'Baby Toys',
            'Nursery',
            'Baby Furniture',
            'Strollers',
            'Baby Safety',
            'Maternity Products',
        ]],
        ['name' => 'Office & Business', 'slug' => 'office-business', 'children' => [
            'Office Electronics',
            'Printers',
            'Scanners',
            'Projectors',
            'Office Furniture',
            'Office Chairs',
            'Desks',
            'Stationery',
            'Filing & Storage',
            'Presentation Supplies',
            'Business Supplies',
            'Packaging Supplies',
            'Computer Accessories',
            'Office Organization',
        ]],
        ['name' => 'Gaming', 'slug' => 'gaming', 'children' => [
            'Gaming PCs',
            'Gaming Laptops',
            'Gaming Consoles',
            'Video Games',
            'Controllers',
            'Gaming Headsets',
            'Gaming Keyboards',
            'Gaming Mice',
            'Gaming Monitors',
            'Gaming Chairs',
            'Gaming Accessories',
            'Console Accessories',
            'PC Gaming Accessories',
            'Streaming Equipment',
        ]],
        ['name' => 'Garden & Outdoor Living', 'slug' => 'garden-outdoor-living', 'children' => [
            'Gardening Tools',
            'Garden Equipment',
            'Plants',
            'Seeds',
            'Planters',
            'Pots',
            'Soil & Fertilizers',
            'Garden Decor',
            'Outdoor Lighting',
            'Outdoor Furniture',
            'Watering Equipment',
            'Irrigation',
            'Lawn Care',
            'Garden Storage',
        ]],
        ['name' => 'Religious & Spiritual', 'slug' => 'religious-spiritual', 'children' => [
            'Pooja Items',
            'Religious Books',
            'Idols & Statues',
            'Incense',
            'Diyas & Lamps',
            'Prayer Accessories',
            'Spiritual Decor',
            'Meditation Accessories',
            'Religious Accessories',
            'Festival Items',
        ]],
        ['name' => 'Gifts & Collectibles', 'slug' => 'gifts-collectibles', 'children' => [
            'Gift Items',
            'Personalized Gifts',
            'Greeting Cards',
            'Party Supplies',
            'Collectibles',
            'Hobby Items',
            'Handmade Products',
            'Souvenirs',
            'Decorative Gifts',
            'Celebration Accessories',
        ]],
    ];

    /**
     * Seed the category tree (main categories + subcategories).
     */
    public function run(): void
    {
        foreach (self::TREE as $index => $parent) {
            $parentCategory = $this->ensureMainCategory(
                $parent['name'],
                $parent['slug'],
                $index + 1
            );

            foreach ($parent['children'] as $childIndex => $childName) {
                $this->ensureSubcategory($parentCategory, $childName, $childIndex + 1);
            }
        }
    }

    /**
     * Reuse an existing main category looked up by its stable slug (IDs are
     * preserved so existing product relationships keep working), or create
     * it when missing. Existing rows are left untouched — no rename, no
     * re-activation, no sort-order overwrite.
     */
    private function ensureMainCategory(string $name, string $slug, int $sortOrder): Category
    {
        return Category::firstOrCreate(
            ['slug' => $slug],
            [
                'name'       => $name,
                'sort_order' => $sortOrder,
                'is_active'  => true,
            ]
        );
    }

    /**
     * Reuse or create a subcategory under the given parent.
     *
     * Slug resolution is deterministic so repeated seeding always maps the
     * same (parent, name) pair to the same row:
     *
     *  1. the plain slug (`backpacks`) when free — or when it already
     *     belongs to THIS parent (reused, ID preserved);
     *  2. a parent-scoped slug (`fashion backpacks` -> `fashion-backpacks`)
     *     when the plain slug is owned by a different parent;
     *  3. a numeric suffix as a last resort.
     */
    private function ensureSubcategory(Category $parent, string $name, int $sortOrder): Category
    {
        $base = Str::slug($name) ?: 'category';
        $scoped = Str::slug($parent->name . ' ' . $name) ?: $base;

        foreach ([$base, $scoped] as $candidate) {
            $existing = Category::where('slug', $candidate)->first();

            if ($existing === null) {
                return $this->createChild($parent, $name, $candidate, $sortOrder);
            }

            if ((int) $existing->parent_id === (int) $parent->id) {
                // Already seeded under this parent — reuse it as-is.
                return $existing;
            }

            // Slug owned by another parent — try the next candidate.
        }

        $counter = 1;
        $candidate = $scoped . '-' . $counter;

        while (true) {
            $existing = Category::where('slug', $candidate)->first();

            if ($existing === null) {
                return $this->createChild($parent, $name, $candidate, $sortOrder);
            }

            if ((int) $existing->parent_id === (int) $parent->id) {
                return $existing;
            }

            $candidate = $scoped . '-' . (++$counter);
        }
    }

    private function createChild(Category $parent, string $name, string $slug, int $sortOrder): Category
    {
        return Category::create([
            'parent_id'  => $parent->id,
            'name'       => $name,
            'slug'       => $slug,
            'sort_order' => $sortOrder,
            'is_active'  => true,
        ]);
    }
}