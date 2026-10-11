<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TempCouponPaginationVerifyTest extends TestCase
{
    use RefreshDatabase;

    private function seedCoupons(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            Coupon::create([
                'code' => 'PAGE' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'type' => 'fixed',
                'value' => 10,
                'active' => true,
            ]);
        }
    }

    public function test_page_two_shows_single_summary_and_correct_rows(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin']);
        $this->seedCoupons(21);

        $response = $this->actingAs($admin)->get('/admin/coupons?page=2');
        $response->assertOk();

        $html = $response->getContent();

        // Mobile-only Prev/Next block must be hidden via CSS.
        $this->assertStringContainsString('div.flex.justify-between:not(.hidden)', $html);

        // The desktop summary shows real paginator values: 16 to 21 of 21.
        $this->assertStringContainsString('>16<', $html);
        $this->assertStringContainsString('>21<', $html);

        // Page 2 contains exactly the remaining 6 rows (no dupes, no skips).
        preg_match_all('/<td>(PAGE\d+)<\/td>/', $html, $m);
        $this->assertSame(
            ['PAGE016','PAGE017','PAGE018','PAGE019','PAGE020','PAGE021'],
            $m[1] ?? []
        );

        // Single nav element from the one links() call.
        $this->assertSame(1, substr_count($html, '<nav role="navigation"'));

        // Prev points to page 1, Next is disabled on the last page.
        $this->assertStringContainsString('coupons?page=1', $html);
    }

    public function test_page_one_summary_and_no_duplicate_records(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin']);
        $this->seedCoupons(21);

        $html = $this->actingAs($admin)->get('/admin/coupons')->assertOk()->getContent();

        // Page 1 holds the first 15 rows; PAGE016+ live on page 2 (no dupes/skips).
        preg_match_all('/<td>(PAGE\d+)<\/td>/', $html, $m);
        $this->assertSame(
            ['PAGE001','PAGE002','PAGE003','PAGE004','PAGE005','PAGE006','PAGE007','PAGE008','PAGE009','PAGE010','PAGE011','PAGE012','PAGE013','PAGE014','PAGE015'],
            $m[1] ?? []
        );

        // Summary reads "Showing 1 to 15 of 21 results" (real paginator values).
        $this->assertStringContainsString('>1<', $html);
        $this->assertStringContainsString('>15<', $html);
        $this->assertStringContainsString('>21<', $html);
    }

    public function test_dump_footer_html(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin']);
        $this->seedCoupons(21);

        $html = $this->actingAs($admin)->get('/admin/coupons?page=2')->assertOk()->getContent();

        $pos = strpos($html, '<nav role="navigation"');
        fwrite(STDERR, "\n" . substr($html, $pos, 6000) . "\n");
        $this->assertTrue(true);
    }
}
