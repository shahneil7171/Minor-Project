<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Readability of the catalog "All categories" filter dropdown (dark theme).
 *
 * The category data itself is covered by CategoryCatalogExpansionTest. This
 * suite guards the presentation contract of the same widget, which is what
 * broke: the products page renders a dark theme, so the opened option list must
 * not fall back to the platform's light popup panel (light panel plus the light
 * text inherited from the dark control = unreadable white-on-white), while the
 * dropdown must still be populated from the database, keep the parent/child
 * hierarchy and keep filtering working.
 */
class CategoryDropdownThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::disk('local')->delete('custom_products_test.json');

        // The dropdown is populated from the database, so the real tree is seeded.
        $this->seed(\Database\Seeders\CategorySeeder::class);
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer']);
    }

    private function seller(): User
    {
        return User::factory()->create(['account_type' => 'seller']);
    }

    /** Full HTML of the catalog page, optionally with a filter query string. */
    private function catalogPage(string $query = ''): string
    {
        $url = '/products'.($query !== '' ? '?'.$query : '');

        return $this->actingAs($this->buyer())->get($url)->assertOk()->getContent();
    }

    /** Only the category <select> markup, isolated from the rest of the page. */
    private function categorySelect(string $query = ''): string
    {
        $html = $this->catalogPage($query);

        $start = strpos($html, 'id="category-select"');
        $this->assertNotFalse($start, 'The catalog page must render the database-driven category filter.');

        $end = strpos($html, '</select>', $start);
        $this->assertNotFalse($end, 'The category filter must render a closed <select>.');

        return substr($html, $start, $end - $start);
    }

    /** Visible text of a top-level option, e.g. ">Electronics</option>". */
    private function mainOption(string $name, bool $selected = false): string
    {
        return ($selected ? 'selected>' : '>').e($name).'</option>';
    }

    /** Visible text of a subcategory option: indented and dash-prefixed, e.g.
     *  ">&nbsp;&nbsp;— Mobiles</option>". */
    private function subOption(string $name, bool $selected = false): string
    {
        return ($selected ? 'selected>' : '>').'&nbsp;&nbsp;— '.e($name).'</option>';
    }

    /* --------------------------------------------- database-driven options */

    public function test_all_categories_dropdown_lists_every_database_category(): void
    {
        $select = $this->categorySelect();

        $this->assertStringContainsString('>All categories</option>', $select);

        $mainCount = Category::query()->whereNull('parent_id')->count();
        $childCount = Category::query()->whereNotNull('parent_id')->count();

        // Every main category and every subcategory is rendered once - no
        // duplicates, no hard-coded subset of the tree.
        $this->assertGreaterThanOrEqual(20, $mainCount);
        $this->assertGreaterThanOrEqual(300, $childCount);
        $this->assertSame($mainCount, substr_count($select, 'class="cat-main"'));
        $this->assertSame($childCount, substr_count($select, 'class="cat-sub"'));

        // Spot-check a full branch: the parent plus all of its children.
        $electronics = Category::query()->where('slug', 'electronics')->firstOrFail();
        $this->assertStringContainsString($this->mainOption($electronics->name), $select);

        foreach ($electronics->children as $child) {
            $this->assertStringContainsString(
                $this->subOption($child->name),
                $select,
                "Subcategory {$child->name} is missing from the dropdown."
            );
        }
    }

    public function test_all_categories_dropdown_keeps_parent_child_hierarchy(): void
    {
        $select = $this->categorySelect();

        $electronics = Category::query()->where('slug', 'electronics')->firstOrFail();
        $fashion = Category::query()->where('slug', 'fashion')->firstOrFail();
        $mobiles = Category::query()
            ->where('name', 'Mobiles')
            ->where('parent_id', $electronics->id)
            ->firstOrFail();

        $electronicsPos = strpos($select, $this->mainOption($electronics->name));
        $fashionPos = strpos($select, $this->mainOption($fashion->name));
        $mobilesPos = strpos($select, $this->subOption($mobiles->name));

        $this->assertNotFalse($electronicsPos);
        $this->assertNotFalse($fashionPos);
        $this->assertNotFalse($mobilesPos);

        // Subcategories stay nested inside their parent's block: every
        // Electronics subcategory sits after Electronics and before Fashion,
        // so the tree is never flattened into a flat list.
        foreach ($electronics->children as $child) {
            $childPos = strpos($select, $this->subOption($child->name));
            $this->assertNotFalse($childPos, "Subcategory {$child->name} is missing.");
            $this->assertLessThan($fashionPos, $childPos, "{$child->name} leaked out of the Electronics block.");
            $this->assertLessThan($childPos, $electronicsPos, "{$child->name} appeared before Electronics.");
        }

        // Every subcategory row keeps its indentation marker.
        $this->assertSame(substr_count($select, 'class="cat-sub"'), substr_count($select, '&nbsp;&nbsp;'));
    }

    /* ------------------------------------------------------- dark theme CSS */

    public function test_all_categories_dropdown_is_styled_for_the_dark_theme(): void
    {
        $page = $this->catalogPage();

        // The catalog page is a dark page...
        $this->assertStringContainsString('color-scheme: dark', $page);

        // ...and the popup list explicitly opts into dark rows with light text
        // instead of inheriting the platform's light popup panel.
        $this->assertStringContainsString('#category-select option.cat-all', $page);
        $this->assertStringContainsString('#category-select option.cat-main', $page);
        $this->assertStringContainsString('#category-select option.cat-sub', $page);
        $this->assertStringContainsString('#category-select option:checked', $page);
        $this->assertStringContainsString('#category-select:focus', $page);

        // Dark track/thumb scrollbar and the shared accent colour.
        $this->assertStringContainsString('scrollbar-color: #2563eb #111827', $page);
        $this->assertStringContainsString('background-color: #0f172a', $page);
        $this->assertStringContainsString('background-color: #111827', $page);
        $this->assertStringContainsString('background-color: #1e293b', $page);
        $this->assertStringContainsString('color: #f8fafc', $page);
        $this->assertStringContainsString('color: #ffffff', $page);

        // Every dropdown row rule must define its own readable text colour and
        // must never fall back to a white/light panel.
        preg_match_all('/#category-select\s+option[^{]*\{([^}]*)\}/', $page, $matches);
        $this->assertNotEmpty($matches[1], 'The dropdown rows must carry explicit dark-popup styling.');

        $forbidden = ['#fff', '#ffffff', 'white', '#f8f9fa', 'transparent'];

        foreach ($matches[1] as $body) {
            $this->assertMatchesRegularExpression(
                '/color:\s*#[0-9a-f]{6}/i',
                $body,
                'Every dropdown row rule must set a readable text colour.'
            );

            if (preg_match('/background(?:-color)?:\s*([^;]+);/i', $body, $background)) {
                $value = strtolower(trim($background[1]));
                $this->assertNotContains(
                    $value,
                    $forbidden,
                    "The opened dropdown panel must not use a light background ({$value})."
                );
            }
        }

        // The sibling sort dropdown keeps its existing dark options (no regression).
        $this->assertStringContainsString('id="sort-select"', $page);
        $this->assertStringContainsString('#sort-select option', $page);
    }

    public function test_selected_category_stays_selected_after_filtering(): void
    {
        $electronics = Category::query()->where('slug', 'electronics')->firstOrFail();
        $fashion = Category::query()->where('slug', 'fashion')->firstOrFail();
        $homeKitchen = Category::query()->where('slug', 'home-kitchen')->firstOrFail();
        $mobiles = Category::query()
            ->where('name', 'Mobiles')
            ->where('parent_id', $electronics->id)
            ->firstOrFail();

        // Main category, subcategory and an '&'-named category all round-trip
        // through the slug-based filter, so a refresh keeps the same selection.
        $this->assertStringContainsString(
            $this->mainOption($electronics->name, true),
            $this->categorySelect('category=electronics')
        );
        $this->assertStringContainsString(
            $this->subOption($mobiles->name, true),
            $this->categorySelect('category=mobiles')
        );
        $this->assertStringContainsString(
            $this->mainOption($homeKitchen->name, true),
            $this->categorySelect('category=home-kitchen')
        );

        // An unrelated category is not marked selected at the same time.
        $this->assertStringNotContainsString(
            $this->mainOption($fashion->name, true),
            $this->categorySelect('category=electronics')
        );
    }

    /* ------------------------------------------------------------ filtering */

    public function test_category_and_subcategory_filtering_still_works_after_the_theme_fix(): void
    {
        $seller = $this->seller();

        $electronics = Category::query()->where('slug', 'electronics')->firstOrFail();
        $mobiles = Category::query()
            ->where('name', 'Mobiles')
            ->where('parent_id', $electronics->id)
            ->firstOrFail();

        $this->actingAs($seller)
            ->post('/products', [
                'title'        => 'Dropdown Theme Phone',
                'description'  => 'Verifies filtering after the dropdown theme fix.',
                'price'        => 199,
                'quantity'     => 2,
                'stock_status' => 'in-stock',
                'status'       => 1,
                'brand'        => 'TestBrand',
                'category'     => (string) $electronics->id,
                'subcategory'  => (string) $mobiles->id,
            ])
            ->assertRedirect('/products');

        // Parent category includes its subcategory's product...
        $this->actingAs($seller)
            ->get('/products?category=electronics')
            ->assertOk()
            ->assertSee('Dropdown Theme Phone');

        // ...and the exact subcategory filter does too...
        $this->actingAs($seller)
            ->get('/products?category=mobiles')
            ->assertOk()
            ->assertSee('Dropdown Theme Phone');

        // ...while sibling subcategories and unrelated categories stay clean.
        $this->actingAs($seller)
            ->get('/products?category=laptops')
            ->assertOk()
            ->assertDontSee('Dropdown Theme Phone');
        $this->actingAs($seller)
            ->get('/products?category=fashion')
            ->assertOk()
            ->assertDontSee('Dropdown Theme Phone');

        // The stored relationship is exactly the seller's explicit selection.
        $this->assertSame($mobiles->id, (int) Product::where('slug', 'dropdown-theme-phone')->value('category_id'));

        // The active filter is still reflected in the (dark) dropdown itself.
        $select = $this->categorySelect('category=mobiles');
        $this->assertStringContainsString($this->subOption($mobiles->name, true), $select);
    }
}

