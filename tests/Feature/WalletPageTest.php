<?php

namespace Tests\Feature;

use App\Models\SmartWallet;
use App\Models\User;
use App\Services\SmartCoinsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Customer "View Wallet" entry point from the My Account page.
 *
 * Covers the required guarantees: the sidebar link on /profile, buyer and
 * seller access to their OWN customer-facing wallet, strict per-user
 * isolation (a tampered user_id in the URL is ignored), guest redirect to
 * login, ledger-matching balances, correct sidebar active-state markup, and
 * that the surrounding profile/returns pages keep working.
 */
class WalletPageTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer']);
    }

    private function seller(): User
    {
        return User::factory()->create(['account_type' => 'seller']);
    }

    private function admin(): User
    {
        return User::factory()->create(['account_type' => 'admin']);
    }

    // ------------------------------------------------------------------
    // 1 + 6 — MY ACCOUNT SIDEBAR
    // ------------------------------------------------------------------

    public function test_my_account_sidebar_shows_view_wallet_between_profile_and_returns(): void
    {
        $buyer = $this->buyer();

        $html = $this->actingAs($buyer)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('View Wallet')
            ->getContent();

        // The item links to the real (existing) wallet page.
        $this->assertStringContainsString(
            'href="' . route('wallet.index') . '" class="menu-item',
            $html,
            'Sidebar must link to the working wallet route.'
        );

        // Order check runs ONLY inside the sidebar nav — the header dropdown
        // legitimately repeats some of these labels earlier in the document.
        $this->assertSame(1, preg_match('/<nav class="profile-menu">(.*?)<\/nav>/s', $html, $sidebar),
            'Profile sidebar must render.');
        $menu = $sidebar[1];

        // Required order: Profile Info -> View Wallet -> Returns & Refunds
        // -> Addresses -> Change Password.
        $order = [
            'Profile Info',
            'View Wallet',
            'Returns &amp; Refunds',
            'Addresses',
            'Change Password',
        ];
        $positions = array_map(fn (string $label) => strpos($menu, $label), $order);
        $this->assertNotContains(false, $positions, 'Every sidebar label must render.');
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($positions, $sorted, 'Sidebar items must appear in the required order.');
    }

    public function test_view_wallet_sidebar_item_is_inactive_on_profile_and_profile_info_is_active(): void
    {
        $buyer = $this->buyer();

        $html = $this->actingAs($buyer)
            ->get(route('profile.show'))
            ->assertOk()
            ->getContent();

        // On /profile the Profile Info item carries the active class...
        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*\/profile" class="menu-item active">/',
            $html,
            'Profile Info must be the active sidebar item on /profile.'
        );

        // ...while the View Wallet item renders in its normal (inactive) state.
        // The wallet itself opens as its own page — same pattern as Returns &
        // Refunds — and its active state is driven by routeIs("wallet.*").
        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*\/wallet" class="menu-item\s*">/',
            $html,
            'View Wallet must render with the standard menu-item class and no premature active state.'
        );
    }

    // ------------------------------------------------------------------
    // 1 + 2 — BUYER / SELLER / ADMIN ACCESS TO THEIR OWN WALLET
    // ------------------------------------------------------------------

    public function test_buyer_can_open_view_wallet_from_my_account(): void
    {
        $buyer = $this->buyer();
        app(SmartCoinsService::class)->credit(
            $buyer, 150, 'refund_credit', 'refund', 1, 'wp-buyer-150', 'Refund for order KDP-1'
        );

        // The sidebar link on /profile points at the wallet route.
        $this->actingAs($buyer)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('href="' . route('wallet.index') . '"', false);

        // Following it opens the real wallet page with the real balance.
        $this->actingAs($buyer)
            ->get(route('wallet.index'))
            ->assertOk()
            ->assertSee('Smart Coins')
            ->assertSee('150.00 coins')
            ->assertSee('Refund for order KDP-1');
    }

    public function test_seller_can_open_their_own_customer_facing_wallet(): void
    {
        $seller = $this->seller();
        app(SmartCoinsService::class)->credit(
            $seller, 250, 'admin_adjustment', 'admin', 1, 'wp-seller-250', 'Seller goodwill credit'
        );

        $this->actingAs($seller)
            ->get(route('wallet.index'))
            ->assertOk()
            ->assertSee('Smart Coins')
            ->assertSee('250.00 coins')
            ->assertSee('Seller goodwill credit');
    }

    public function test_admin_can_open_their_own_customer_facing_wallet(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('wallet.index'))
            ->assertOk()
            ->assertSee('Smart Coins')
            ->assertSee('0.00 coins');
    }

    // ------------------------------------------------------------------
    // 3 + 4 — STRICT PER-USER ISOLATION AND GUEST REDIRECT
    // ------------------------------------------------------------------

    public function test_authenticated_user_cannot_view_another_users_wallet(): void
    {
        $alice = $this->buyer();
        $bob = $this->buyer();

        $coins = app(SmartCoinsService::class);
        $coins->credit($alice, 111, 'refund_credit', 'refund', 11, 'iso-alice', 'alice-unique-ledger-entry');
        $coins->credit($bob, 999, 'refund_credit', 'refund', 22, 'iso-bob', 'bob-unique-ledger-entry');

        // Alice sees only her own wallet — never Bob's balance or ledger rows.
        $this->actingAs($alice)->get(route('wallet.index'))
            ->assertOk()
            ->assertSee('111.00 coins')
            ->assertSee('alice-unique-ledger-entry')
            ->assertDontSee('bob-unique-ledger-entry')
            ->assertDontSee('999.00 coins');

        // A tampered user_id in the query string must be ignored entirely.
        $this->actingAs($alice)->get(route('wallet.index', ['user_id' => $bob->id]))
            ->assertOk()
            ->assertSee('111.00 coins')
            ->assertDontSee('bob-unique-ledger-entry');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/wallet')->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------
    // 5 — LEDGER-ACCURATE BALANCE / TOTALS / RATE / PAGINATION
    // ------------------------------------------------------------------

    public function test_wallet_balance_and_totals_match_the_existing_ledger(): void
    {
        $buyer = $this->buyer();
        $coins = app(SmartCoinsService::class);

        $coins->credit($buyer, 500, 'refund_credit', 'refund', 7, 'bal-credit-500', 'Return refund credit');
        $coins->debit($buyer, 200, 'checkout_redemption', 'order', 9, 'bal-debit-200', 'Redeemed at checkout');

        // The page must render persisted ledger values, never demo data.
        $response = $this->actingAs($buyer)->get(route('wallet.index'));
        $response
            ->assertOk()
            ->assertSee('300.00 coins')   // balance = 500 - 200
            ->assertSee('500.00')         // total earned
            ->assertSee('200.00')         // total spent
            ->assertSee('Return refund credit')
            ->assertSee('Redeemed at checkout')
            ->assertSee('Refund #7')
            ->assertSee('Order #9')
            // Conversion rate from config (smart_coins.coin_to_inr = 1).
            ->assertSee('1 Smart Coin = ₹' . number_format(
                (float) config('smart_coins.coin_to_inr', 1), 2
            ));

        // Cards on the page agree with the wallet row in the database.
        $wallet = SmartWallet::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame(300.0, (float) $wallet->balance);
        $this->assertSame(500.0, (float) $wallet->total_earned);
        $this->assertSame(200.0, (float) $wallet->total_spent);
        $this->assertSame(500.0, (float) $wallet->total_refund_credited);
        $response->assertSee(number_format((float) $wallet->total_refund_credited, 2));
    }

    public function test_long_transaction_history_is_paginated(): void
    {
        $buyer = $this->buyer();
        $coins = app(SmartCoinsService::class);

        for ($i = 1; $i <= 16; $i++) {
            $coins->credit($buyer, 10, 'refund_credit', 'refund', $i, 'page-credit-' . $i, 'Ledger entry ' . $i);
        }

        $this->actingAs($buyer)->get(route('wallet.index'))
            ->assertOk()
            ->assertSee('Ledger entry 16')  // newest first on page 1
            ->assertSee('page=2');          // second page link for the 16th row
    }

    // ------------------------------------------------------------------
    // 7 — SURROUNDING ACCOUNT PAGES STILL WORK
    // ------------------------------------------------------------------

    public function test_existing_profile_addresses_password_and_returns_pages_still_work(): void
    {
        $buyer = $this->buyer();

        $this->actingAs($buyer)->get(route('profile.show'))->assertOk()->assertSee('My Account');
        $this->actingAs($buyer)->get(route('profile.change-password'))->assertOk();
        $this->actingAs($buyer)->get(route('profile.addresses.index'))->assertOk();
        $this->actingAs($buyer)->get(route('returns.index'))->assertOk();
    }
}





