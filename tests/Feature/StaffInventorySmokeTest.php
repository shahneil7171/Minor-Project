<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffInventorySmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_inventory(): void
    {
        $staff = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $this->actingAs($staff)->get(route('staff.inventory.index'))->assertOk();
        $this->actingAs($staff)->get(route('staff.inventory.history'))->assertOk();
    }

    public function test_buyer_cannot_access_staff_inventory(): void
    {
        $buyer = User::factory()->create(['account_type' => 'buyer']);
        $this->actingAs($buyer)->get(route('staff.inventory.index'))->assertStatus(403);
    }

    public function test_admin_cannot_access_staff_inventory(): void
    {
        $admin = User::factory()->create(['account_type' => 'admin']);
        $this->actingAs($admin)->get(route('staff.inventory.index'))->assertStatus(403);
    }

    public function test_staff_can_restock_a_product(): void
    {
        $staff = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $product = \App\Models\Product::create(['slug' => 'staff-restock-product', 'title' => 'Staff Restock Product', 'sku' => 'STAFF-001', 'description' => 'x', 'price' => 10, 'quantity' => 5, 'stock_status' => 'in-stock', 'tax' => 0, 'status' => 1, 'tags' => [], 'options' => [], 'variants' => [], 'is_seed' => false]);
        $before = (int) $product->quantity;

        $this->actingAs($staff)->post(route('staff.inventory.restock', ['product' => $product->id]), [
            'quantity' => 3,
            'note' => 'Restock test',
        ])->assertRedirect();

        $this->assertSame($before + 3, (int) $product->fresh()->quantity);
    }

    public function test_staff_restock_rejects_zero_or_negative_quantity(): void
    {
        $staff = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $product = \App\Models\Product::create(['slug' => 'staff-restock-product', 'title' => 'Staff Restock Product', 'sku' => 'STAFF-001', 'description' => 'x', 'price' => 10, 'quantity' => 5, 'stock_status' => 'in-stock', 'tax' => 0, 'status' => 1, 'tags' => [], 'options' => [], 'variants' => [], 'is_seed' => false]);

        $this->actingAs($staff)->post(route('staff.inventory.restock', ['product' => $product->id]), [
            'quantity' => 0,
            'note' => 'Bad',
        ])->assertSessionHasErrors('quantity');

        $this->actingAs($staff)->post(route('staff.inventory.restock', ['product' => $product->id]), [
            'quantity' => -2,
            'note' => 'Bad',
        ])->assertSessionHasErrors('quantity');
    }
}

