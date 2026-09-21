<?php

use App\Mail\OrderConfirmationMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderShippedMail;
use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetOtpMail;
use App\Mail\RegistrationMail;
use App\Models\Order;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Place a real checkout for the given buyer (Smart Watch Pro, qty 1) using the
 * same flow as OrderHistoryTest, then return the created order.
 *
 * Expected totals: 199 (subtotal) + 35.82 (18% tax) + 50 (shipping) = 284.82
 */
function emailTestPlaceOrder(\Tests\TestCase $case, User $user): Order
{
    $case->actingAs($user);

    app(CartService::class)->save([
        'smart-watch-pro' => [
            'product'  => 'smart-watch-pro',
            'title'    => 'Smart Watch Pro',
            'price'    => 199.0,
            'quantity' => 1,
            'sku'      => 'KDP-SMW-001',
            'image'    => 'https://images.unsplash.com/photo-1518444209757-9ae0b9eb3734?auto=format&fit=crop&w=800&q=80',
        ],
    ]);

    $case->post('/checkout', [
        'address_option'  => 'new',
        'shipping_method' => 'standard',
        'payment_method'  => 'cod',
        'new_address'     => [
            'full_name'      => 'Jane Doe',
            'phone'          => '1234567890',
            'house_number'   => '123',
            'street_address' => 'Main Street',
            'city'           => 'New York',
            'state'          => 'NY',
            'pincode'        => '10001',
            'country'        => 'India',
        ],
    ])->assertRedirect(route('checkout.complete'));

    return Order::where('user_id', $user->id)->sole();
}

test('registration sends a welcome email to the new customer', function () {
    Mail::fake();

    $this->post('/register', [
        'name'                  => 'Ravi Kumar',
        'email'                 => 'ravi@example.com',
        'password'              => 'Sup3rSecret!',
        'password_confirmation' => 'Sup3rSecret!',
        'account_type'          => 'buyer',
    ])->assertRedirect('/');

    $captured = null;

    Mail::assertSent(RegistrationMail::class, function (RegistrationMail $mail) use (&$captured) {
        $captured = $mail;

        return $mail->hasTo('ravi@example.com');
    });

    Mail::assertSentCount(1);

    expect($captured->envelope()->subject)->toBe('Welcome to KDP MART!');

    // Contains the useful details…
    $html = $captured->render();
    expect($html)->toContain('Ravi Kumar');
    expect($html)->toContain('ravi@example.com');

    // …but never the plain-text password or a password hash.
    expect($html)->not->toContain('Sup3rSecret!');
    expect($html)->not->toContain('$2y$');
});

test('a password reset request emails the registered address via the existing OTP flow', function () {
    Mail::fake();

    User::factory()->create(['email' => 'reset-me@example.com']);

    $this->post('/forgot-password', ['email' => 'reset-me@example.com'])
        ->assertRedirect(route('password.verify'));

    // Exactly one email (the OTP) — no duplicate notifications.
    Mail::assertSentCount(1);

    Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) {
        return $mail->hasTo('reset-me@example.com');
    });
});

test('completing a password reset sends a password-changed security email', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'reset-done@example.com']);

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect(route('password.verify'));

    $otp = null;

    Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) use (&$otp) {
        $otp = $mail->build()->viewData['otp'];

        return true;
    });

    $this->post('/forgot-password/verify', ['email' => $user->email, 'otp' => $otp])
        ->assertRedirect(route('password.reset'));

    $this->post('/reset-password', [
        'email'                 => $user->email,
        'password'              => 'BrandNew1!',
        'password_confirmation' => 'BrandNew1!',
    ])->assertRedirect(route('login'));

    $captured = null;

    Mail::assertSent(PasswordChangedMail::class, function (PasswordChangedMail $mail) use (&$captured, $user) {
        $captured = $mail;

        return $mail->hasTo($user->email);
    });

    // The new password is never included in the security email.
    $html = $captured->render();
    expect($html)->toContain($user->name);
    expect($html)->not->toContain('BrandNew1!');
    expect($html)->not->toContain('$2y$');
});

test('changing the password from the profile sends a password-changed security email', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email'    => 'profile-pw@example.com',
        'password' => Hash::make('OldPass1!'),
    ]);

    $this->actingAs($user)->post(route('profile.update-password'), [
        'current_password'      => 'OldPass1!',
        'password'              => 'FreshPass1!',
        'password_confirmation' => 'FreshPass1!',
    ])->assertRedirect(route('profile.show'));

    Mail::assertSentCount(1);

    $captured = null;

    Mail::assertSent(PasswordChangedMail::class, function (PasswordChangedMail $mail) use (&$captured, $user) {
        $captured = $mail;

        return $mail->hasTo($user->email);
    });

    // Never the new or the old password.
    $html = $captured->render();
    expect($html)->not->toContain('FreshPass1!');
    expect($html)->not->toContain('OldPass1!');
});

test('placing an order sends an order confirmation email with the order details', function () {
    Mail::fake();

    $buyer = User::factory()->create(['email' => 'buyer-confirm@example.com', 'account_type' => 'buyer']);

    $order = emailTestPlaceOrder($this, $buyer);

    $captured = null;

    Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) use (&$captured, $order) {
        $captured = $mail;

        return $mail->hasTo('buyer-confirm@example.com')
            && $mail->order->is($order);
    });

    $subject = $captured->envelope()->subject;
    expect($subject)->toContain('Order Confirmation');
    expect($subject)->toContain('#' . $order->order_number);

    $html = $captured->render();
    expect($html)->toContain($order->order_number);
    expect($html)->toContain('Smart Watch Pro');
    // 199 subtotal + 35.82 tax + 50 shipping = 284.82
    expect($html)->toContain(number_format(284.82, 2));
    expect($html)->toContain('Jane Doe');
});

test('an empty cart checkout never sends an order confirmation email', function () {
    Mail::fake();

    $buyer = User::factory()->create(['account_type' => 'buyer']);

    $this->actingAs($buyer)->post('/checkout', [
        'address_option'  => 'new',
        'shipping_method' => 'standard',
        'payment_method'  => 'cod',
        'new_address'     => [
            'full_name'      => 'Jane Doe',
            'phone'          => '1234567890',
            'house_number'   => '123',
            'street_address' => 'Main Street',
            'city'           => 'New York',
            'state'          => 'NY',
            'pincode'        => '10001',
            'country'        => 'India',
        ],
    ])->assertRedirect(route('cart.index'));

    Mail::assertNothingSent();
});

test('shipped status transition emails the customer exactly once', function () {
    Mail::fake();

    $buyer = User::factory()->create(['email' => 'ship-me@example.com', 'account_type' => 'buyer']);
    $order = emailTestPlaceOrder($this, $buyer);

    $admin = User::factory()->create(['account_type' => 'admin']);

    // Real transition: pending -> shipped.
    $this->actingAs($admin)
        ->post('/admin/orders/' . $order->id . '/status', ['status' => 'out_for_delivery'])
        ->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe('out_for_delivery');

    $captured = null;

    Mail::assertSent(OrderShippedMail::class, function (OrderShippedMail $mail) use (&$captured) {
        $captured = $mail;

        return $mail->hasTo('ship-me@example.com');
    });

    expect($captured->envelope()->subject)->toContain('#' . $order->order_number);

    $html = $captured->render();
    expect($html)->toContain('Smart Watch Pro');
    expect($html)->toContain('Jane Doe');

    // No-op re-save of the same status must not send a duplicate email.
    $this->actingAs($admin)
        ->post('/admin/orders/' . $order->id . '/status', ['status' => 'out_for_delivery'])
        ->assertRedirect();

    Mail::assertSent(OrderShippedMail::class, 1);
    Mail::assertNotSent(OrderDeliveredMail::class);
});

test('delivered status transition emails the customer exactly once', function () {
    Mail::fake();

    $buyer = User::factory()->create(['email' => 'deliver-me@example.com', 'account_type' => 'buyer']);
    $order = emailTestPlaceOrder($this, $buyer);

    $admin = User::factory()->create(['account_type' => 'admin']);

    // Real transition: pending -> delivered.
    $this->actingAs($admin)
        ->post('/admin/orders/' . $order->id . '/status', ['status' => 'delivered'])
        ->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe('delivered');

    $captured = null;

    Mail::assertSent(OrderDeliveredMail::class, function (OrderDeliveredMail $mail) use (&$captured) {
        $captured = $mail;

        return $mail->hasTo('deliver-me@example.com');
    });

    expect($captured->envelope()->subject)->toContain('Has Been Delivered');

    // The final order amount appears in the delivery email.
    $html = $captured->render();
    expect($html)->toContain(number_format(284.82, 2));

    // No-op re-save of the same status must not send a duplicate email.
    $this->actingAs($admin)
        ->post('/admin/orders/' . $order->id . '/status', ['status' => 'delivered'])
        ->assertRedirect();

    Mail::assertSent(OrderDeliveredMail::class, 1);
});

test('customers cannot trigger shipped or delivered notification emails', function () {
    Mail::fake();

    $buyer = User::factory()->create(['account_type' => 'buyer']);
    $order = emailTestPlaceOrder($this, $buyer);

    $this->actingAs($buyer)
        ->post('/admin/orders/' . $order->id . '/status', ['status' => 'out_for_delivery'])
        ->assertStatus(403);

    Mail::assertNotSent(OrderShippedMail::class);
    Mail::assertNotSent(OrderDeliveredMail::class);
});


