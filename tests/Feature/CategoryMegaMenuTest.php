<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * KDP MART Categories mega menu (single shared, database-driven component).
 *
 * Covers the presentation contract of the navigation: two columns on desktop,
 * a drill-down panel on mobile, viewport-safe sizing, no clipped names, no
 * horizontal scrolling, accessible markup, real category links, and — most
 * importantly — that the menu stays driven by the `categories` table and never
 * touches the existing category/product data.
 */
class CategoryMegaMenuTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Names that stress long-text handling (requirement: nothing may be
     * clipped or ellipsised).
     */
    private const LONG_NAMES = [
        'Electronics',
        'Fashion',
        'Home & Kitchen',
        'Beauty & Personal Care',
        'Garden & Outdoor Living',
        'Religious & Spiritual',
        'Gifts & Collectibles',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // The menu is populated from the database, so the real tree is seeded.
        $this->seed(\Database\Seeders\CategorySeeder::class);
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer']);
    }

    /** Source of the shared component (CSS/JS/a11y contracts live there). */
    private function componentSource(): string
    {
        return (string) file_get_contents(resource_path('views/components/category-mega-menu.blade.php'));
    }

    /** Only the rendered mega menu markup, isolated from the rest of the page. */
    private function menu(string $html): string
    {
        $start = strpos($html, '<nav class="kdp-mega"');
        $this->assertNotFalse($start, 'The Categories mega menu must be rendered.');

        $end = strpos($html, '</nav>', $start);
        $this->assertNotFalse($end, 'The Categories mega menu must be a closed <nav> element.');

        return substr($html, $start, $end - $start);
    }

    private function homePage(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }

    /** A page rendered through the shared layout (uses the $navCategories composer). */
    private function layoutPage(): string
    {
        return $this->get('/login')->assertOk()->getContent();
    }

    /* ------------------------------------------------------- rendering */

    public function test_mega_menu_renders_on_the_storefront_header(): void
    {
        $menu = $this->menu($this->homePage());

        // Database-driven: every main category and their subcategories.
        foreach (self::LONG_NAMES as $name) {
            $this->assertStringContainsString(e($name), $menu, "{$name} is missing from the mega menu.");
        }

        $electronics = Category::query()->where('slug', 'electronics')->firstOrFail();

        foreach ($electronics->children as $child) {
            $this->assertStringContainsString(
                e($child->name),
                $menu,
                "Electronics subcategory {$child->name} is missing from the mega menu."
            );
        }

        // "All Categories" stays a link to the existing all-products route.
        $this->assertStringContainsString('All Categories', $menu);
        $this->assertStringContainsString('href="'.route('products').'"', $menu);
    }

    public function test_mega_menu_renders_in_the_shared_layout_header(): void
    {
        $menu = $this->menu($this->layoutPage());

        foreach (self::LONG_NAMES as $name) {
            $this->assertStringContainsString(e($name), $menu, "{$name} is missing from the layout mega menu.");
        }

        // Every main category gets its own pre-rendered subcategory panel, so
        // switching panels needs no request.
        $this->assertSame(20, substr_count($menu, 'data-kdp-cat="'));
        $this->assertSame(20, substr_count($menu, 'data-kdp-sub="'));
    }

    public function test_only_one_categories_menu_implementation_remains(): void
    {
        foreach ([$this->homePage(), $this->layoutPage()] as $html) {
            // The old nested dropdowns are gone…
            $this->assertStringNotContainsString('dropdown-submenu', $html);
            $this->assertStringNotContainsString('submenu-arrow', $html);
            $this->assertStringNotContainsString('nav-dropdown-menu', $html);
            $this->assertStringNotContainsString('nav-dropdown-toggle', $html);

            // …and exactly one mega menu is rendered per page (the attribute
            // below only counts the markup, not the script's selector).
            $this->assertSame(1, substr_count($html, 'data-kdp-mega>'));
            $this->assertSame(1, substr_count($html, 'id="kdpMega-panel"'));
            $this->assertSame(1, substr_count($html, '<nav class="kdp-mega"'));
        }
    }

    public function test_component_is_reusable_and_contains_no_hard_coded_categories(): void
    {
        $source = $this->componentSource();

        // No category name may be hard-coded in the component: it renders
        // whatever the caller loaded from the `categories` table.
        foreach (['Electronics', 'Fashion', 'Home &amp; Kitchen', 'Mobiles', 'Beauty &amp; Personal Care'] as $name) {
            $this->assertStringNotContainsString($name, $source, "{$name} must not be hard-coded in the menu component.");
        }

        // Both headers consume the same component (one implementation).
        $this->assertStringContainsString(
            '<x-category-mega-menu',
            (string) file_get_contents(resource_path('views/home.blade.php'))
        );
        $this->assertStringContainsString(
            '<x-category-mega-menu',
            (string) file_get_contents(resource_path('views/layouts/app.blade.php'))
        );
    }

    /* --------------------------------------------------- accessibility */

    public function test_mega_menu_exposes_accessible_markup_and_behaviour(): void
    {
        $source = $this->componentSource();
        $html = $this->homePage();

        // Semantic toggle wired to the panel.
        $this->assertStringContainsString('<nav class="kdp-mega" id="kdpMega" aria-label="Categories" data-kdp-mega>', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-controls="kdpMega-panel"', $html);
        $this->assertStringContainsString('aria-haspopup="true"', $html);
        $this->assertStringContainsString('id="kdpMega-panel"', $html);
        $this->assertStringContainsString('role="region"', $html);

        // Keyboard support: Escape closes and restores focus; arrows navigate.
        $this->assertStringContainsString("event.key === 'Escape'", $source);
        $this->assertStringContainsString('toggle.focus()', $source);
        $this->assertStringContainsString("event.key !== 'ArrowDown'", $source);
        $this->assertStringContainsString(':focus-visible', $source);
    }


    /* ------------------------------------------- layout / responsiveness */

    public function test_two_column_desktop_layout_fits_the_viewport(): void
    {
        $html = $this->homePage();

        // Column split: ~30% main categories, remaining space for subcategories.
        $this->assertStringContainsString(
            'grid-template-columns: minmax(220px, 30%) minmax(0, 1fr)',
            $html
        );

        // Responsive width that can never leave the viewport.
        $this->assertStringContainsString('width: min(90vw, 950px)', $html);
        $this->assertStringContainsString('max-width: calc(100vw - 30px)', $html);

        // Subcategories are a two-column grid on desktop, so 20+ entries do not
        // create a very long list.
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr))', $html);

        // Desktop and mobile breakpoints exist.
        $this->assertStringContainsString('@media (min-width: 992px)', $html);
        $this->assertStringContainsString('@media (max-width: 991.98px)', $html);

        // Panel is layered above page content (hero, cards, …) but scoped.
        $this->assertStringContainsString('--kdp-mega-z: 70', $html);
        $this->assertStringContainsString('z-index: var(--kdp-mega-z)', $html);

        // Existing KDP MART look and feel: dark surface, subtle border, radius,
        // shadow and the site accent.
        $this->assertStringContainsString('--kdp-mega-surface: #0b1220', $html);
        $this->assertStringContainsString('--kdp-mega-accent: #2563eb', $html);
        $this->assertStringContainsString('border-radius: 14px', $html);
        $this->assertStringContainsString('box-shadow: 0 18px 44px rgba(0, 0, 0, 0.45)', $html);
    }

    public function test_menu_cannot_scroll_horizontally_or_clip_category_names(): void
    {
        $source = $this->componentSource();

        // Never a horizontal scrollbar inside the menu…
        $this->assertStringNotContainsString('overflow-x: auto', $source);
        $this->assertStringNotContainsString('overflow-x: scroll', $source);

        // …no ellipsis/truncation of category names (declaration form)…
        $this->assertStringNotContainsString('text-overflow:', $source);

        // …only the panel itself clips its rounded corners, and only once.
        $this->assertSame(1, substr_count($source, 'overflow: hidden'));

        // Both columns scroll vertically and hide any horizontal overflow.
        $this->assertSame(2, substr_count($source, 'overflow-x: hidden'));
        $this->assertStringContainsString('overflow-wrap: anywhere', $source);

        // Long names wrap instead of overflowing their row.
        $this->assertMatchesRegularExpression(
            '/\.kdp-mega__name,\s*\.kdp-mega__subname\s*\{[^}]*min-width:\s*0[^}]*overflow-wrap:\s*anywhere/',
            $source
        );
    }

    public function test_desktop_height_is_capped_so_the_header_stays_compact(): void
    {
        $html = $this->homePage();

        // Columns are capped and scroll internally; the script shrinks the cap
        // further when there is little room below the toggle.
        $this->assertStringContainsString('max-height: 500px', $html);
        $this->assertStringContainsString('max-height: var(--kdp-mega-max-h, 560px)', $html);
        $this->assertStringContainsString('--kdp-mega-max-h', $html);

        // No fixed desktop-only width that could overflow small viewports.
        $this->assertStringNotContainsString('width: 950px', $html);
        $this->assertStringNotContainsString('min-width: 950px', $html);
    }


    public function test_mobile_uses_a_drill_down_panel_with_back_control(): void
    {
        $source = $this->componentSource();
        $html = $this->homePage();

        // Drill-down state + back button exist in the markup…
        $this->assertStringContainsString('data-kdp-back', $html);
        $this->assertStringContainsString('class="kdp-mega__back"', $html);
        $this->assertStringContainsString('Back to categories', $html);
        $this->assertStringContainsString('data-kdp-has-children', $html);

        // …and are driven by the responsive rules/behaviour.
        $this->assertStringContainsString('.kdp-mega.is-drilled .kdp-mega__col-main', $source);
        $this->assertStringContainsString('.kdp-mega.is-drilled .kdp-mega__col-subs', $source);
        $this->assertStringContainsString('max-height: 62vh', $source);
        $this->assertStringContainsString("classList.add('is-drilled')", $source);
        $this->assertStringContainsString("classList.remove('is-drilled')", $source);

        // Mobile panel is full width, so it always fits the screen.
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 991\.98px\)[\s\S]*?\.kdp-mega\s*\{[^}]*width:\s*100%/',
            $source
        );
    }

    public function test_hover_click_and_close_behaviour_is_handled_client_side(): void
    {
        $source = $this->componentSource();

        // Hover/focus swaps the subcategory panel…
        $this->assertStringContainsString("addEventListener('mouseover'", $source);
        $this->assertStringContainsString("addEventListener('focusin'", $source);

        // …and the menu is pre-rendered, so hovering never issues a request.
        $this->assertStringNotContainsString('fetch(', $source);
        $this->assertStringNotContainsString('XMLHttpRequest', $source);

        // Click toggles; outside click and Escape close.
        $this->assertStringContainsString("toggle.addEventListener('click'", $source);
        $this->assertStringContainsString("document.addEventListener('click'", $source);
        $this->assertStringContainsString('!nav.contains(event.target)', $source);
        $this->assertStringContainsString('close(true)', $source);

        // Viewport clamping + hover tolerance bridge.
        $this->assertStringContainsString('getBoundingClientRect()', $source);
        $this->assertStringContainsString('window.innerWidth - VIEWPORT_GAP', $source);
        $this->assertStringContainsString('kdp-mega__bridge', $source);
    }


    /* -------------------------------------------------- categories / links */

    public function test_electronics_branch_links_every_subcategory_to_a_real_route(): void
    {
        $menu = $this->menu($this->homePage());
        $electronics = Category::query()->where('slug', 'electronics')->firstOrFail();

        // The parent itself.
        $this->assertStringContainsString('href="'.route('categories.show', 'electronics').'"', $menu);

        // Every database subcategory of Electronics is listed and linked to its
        // existing slug-based category page.
        foreach ($electronics->children as $child) {
            $this->assertStringContainsString(
                'href="'.route('categories.show', $child->slug).'"',
                $menu,
                "Electronics / {$child->name} must link to its existing category URL."
            );
        }

        // Subcategory listing is never mixed across parents: the Electronics
        // panel contains its own children only.
        $panelStart = (int) strpos($menu, 'data-kdp-sub="'.$electronics->id.'"');
        $nextPanel = strpos($menu, 'data-kdp-sub="', $panelStart + 1);
        $electronicsPanel = substr($menu, $panelStart, $nextPanel === false ? null : $nextPanel - $panelStart);

        $this->assertStringContainsString('href="'.route('categories.show', 'mobiles').'"', $electronicsPanel);
        $this->assertStringNotContainsString('href="'.route('categories.show', 'mens-clothing').'"', $electronicsPanel);
    }

    public function test_long_subcategory_names_are_rendered_in_full(): void
    {
        $menu = $this->menu($this->homePage());

        $longSubcategories = [
            'Mobile Accessories',
            'Computer Accessories',
            'Printers & Scanners',
            'Headphones & Earphones',
            'Garden Storage',
        ];

        foreach ($longSubcategories as $name) {
            $this->assertStringContainsString(
                e($name),
                $menu,
                "Long subcategory name '{$name}' must be rendered in full."
            );
        }
    }

    public function test_category_without_subcategories_shows_the_empty_state(): void
    {
        $lonely = Category::create([
            'parent_id'  => null,
            'name'       => 'Test Lonely Category',
            'slug'       => 'test-lonely-category',
            'sort_order' => 999,
            'is_active'  => true,
        ]);

        $menu = $this->menu($this->homePage());

        // The new category shows up (proof the menu is data driven)…
        $this->assertStringContainsString('Test Lonely Category', $menu);
        $this->assertStringContainsString('data-kdp-sub="'.$lonely->id.'"', $menu);

        // …with the empty state instead of a blank panel.
        $this->assertStringContainsString('No subcategories available.', $menu);
        $this->assertStringContainsString('View all products', $menu);

        $lonely->delete();
    }

    public function test_disabled_categories_disappear_from_the_menu_without_duplicates(): void
    {
        $toys = Category::query()->where('slug', 'toys-games')->firstOrFail();
        $mobiles = Category::query()->where('slug', 'mobiles')->firstOrFail();

        $before = $this->menu($this->homePage());
        $this->assertSame(1, substr_count($before, 'data-kdp-cat="'.$toys->id.'"'));
        $this->assertStringContainsString(e($toys->name), $before);

        // Disable a main category…
        $toys->update(['is_active' => false]);
        // …and one subcategory of an active parent.
        $mobiles->update(['is_active' => false]);

        $after = $this->menu($this->homePage());

        $this->assertSame(0, substr_count($after, 'data-kdp-cat="'.$toys->id.'"'));
        $this->assertStringNotContainsString(e($toys->name), $after);
        $this->assertSame(0, substr_count($after, 'href="'.route('categories.show', 'mobiles').'"'));

        // Still exactly one menu (no duplicate replacement menu was created).
        $this->assertSame(1, substr_count($after, 'data-kdp-mega'));

        $toys->update(['is_active' => true]);
        $mobiles->update(['is_active' => true]);
    }


    /* ------------------------------------------- performance / data safety */

    public function test_menu_adds_no_per_category_queries(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->homePage();

        $categoryQueries = array_values(array_filter(
            DB::getQueryLog(),
            fn ($entry) => str_contains($entry['query'], 'categories')
        ));

        DB::disableQueryLog();

        // One query for the parent categories + one eager-load for all of their
        // children: the menu never queries inside a loop (no N+1), and hovering
        // a category issues no request at all.
        $this->assertLessThanOrEqual(
            3,
            count($categoryQueries),
            'The multi-category menu must load categories with eager loading, not per category.'
        );
    }

    public function test_existing_category_and_product_data_is_untouched(): void
    {
        $electronics = Category::query()->where('slug', 'electronics')->firstOrFail();
        $fashion = Category::query()->where('slug', 'fashion')->firstOrFail();

        $before = [
            'rows'        => Category::query()->count(),
            'mains'       => Category::query()->whereNull('parent_id')->count(),
            'subs'        => Category::query()->whereNotNull('parent_id')->count(),
            'electronics' => $electronics->id,
            'fashion'     => $fashion->id,
            'products'    => Product::query()->count(),
            'categorised' => Product::query()->whereNotNull('category_id')->count(),
            'assignments' => Product::query()->orderBy('id')->pluck('category_id', 'id')->all(),
            'slugs'       => Category::query()->orderBy('id')->pluck('slug')->all(),
        ];

        // Render every header that shows the menu.
        $this->homePage();
        $this->layoutPage();

        $this->assertSame(20, $before['mains']);
        $this->assertGreaterThanOrEqual(300, $before['subs']);

        $this->assertSame($before['rows'], Category::query()->count());
        $this->assertSame($before['mains'], Category::query()->whereNull('parent_id')->count());
        $this->assertSame($before['subs'], Category::query()->whereNotNull('parent_id')->count());
        $this->assertSame($before['slugs'], Category::query()->orderBy('id')->pluck('slug')->all());

        // Existing category ids (and therefore product assignments) are preserved.
        $this->assertSame($before['electronics'], Category::query()->where('slug', 'electronics')->value('id'));
        $this->assertSame($before['fashion'], Category::query()->where('slug', 'fashion')->value('id'));

        // No product was re-categorised, created or deleted by rendering the menu.
        $this->assertSame($before['products'], Product::query()->count());
        $this->assertSame($before['categorised'], Product::query()->whereNotNull('category_id')->count());
        $this->assertSame($before['assignments'], Product::query()->orderBy('id')->pluck('category_id', 'id')->all());

        // No duplicate menus/categories from the component.
        $this->assertSame(
            $before['mains'] + $before['subs'],
            Category::query()->count()
        );
    }
}
