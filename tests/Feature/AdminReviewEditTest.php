<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin review management — edit page, update flow and authorization.
 */
class AdminReviewEditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer', 'status' => 'active']);
    }

    private function pendingReview(User $author): Review
    {
        return Review::create([
            'user_id'      => $author->id,
            'product_slug' => 'smart-watch-pro',
            'rating'       => 3,
            'comment'      => 'Decent battery life.',
            'status'       => 'pending',
        ]);
    }

    public function test_edit_page_renders_for_admin_with_all_fields(): void
    {
        $review = $this->pendingReview($this->buyer());

        $response = $this->actingAs($this->admin())
            ->get(route('admin.reviews.edit', $review));

        $response->assertOk();
        $response->assertSee('Smart Watch Pro');   // product context
        $response->assertSee('Decent battery life.'); // review text
        $response->assertSee('Pending');           // status field
        // Update form posts with CSRF + correct method.
        $response->assertSee('method="POST"', false);
        $response->assertSee('_method" value="PUT"', false);
    }

    public function test_admin_can_update_a_review(): void
    {
        $review = $this->pendingReview($this->buyer());

        $response = $this->actingAs($this->admin())
            ->put(route('admin.reviews.update', $review), [
                'rating'  => '5',
                'comment' => 'Updated after longer use — excellent.',
                'status'  => 'approved',
            ]);

        $response->assertRedirect(route('admin.reviews.index'));
        $response->assertSessionHas('success');

        $review->refresh();
        $this->assertSame(5, (int) $review->rating);
        $this->assertSame('approved', $review->status);
        $this->assertSame('Updated after longer use — excellent.', $review->comment);
    }

    public function test_update_validates_input(): void
    {
        $review = $this->pendingReview($this->buyer());

        $this->actingAs($this->admin())
            ->put(route('admin.reviews.update', $review), [
                'status'  => 'not-a-status',
                'rating'  => '9',
            ])
            ->assertSessionHasErrors(['status', 'rating']);
    }

    public function test_customers_cannot_access_review_editing(): void
    {
        $review = $this->pendingReview($this->buyer());

        // Customers are blocked by policy (403), authorization is preserved.
        $this->actingAs($this->buyer())
            ->get(route('admin.reviews.edit', $review))
            ->assertForbidden();

        $this->actingAs($this->buyer())
            ->put(route('admin.reviews.update', $review), [
                'status' => 'approved',
            ])
            ->assertForbidden();
    }
}
