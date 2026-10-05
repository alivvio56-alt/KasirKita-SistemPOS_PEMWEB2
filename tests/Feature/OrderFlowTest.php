<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(Product $product, int $qty = 2): int
    {
        return $this->postJson('/api/orders', [
            'type' => 'take_away',
            'items' => [['product_id' => $product->id, 'quantity' => $qty]],
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
    }

    public function test_full_business_process_from_order_to_history(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['price' => 15000, 'stock' => 10]);

        $id = $this->createOrder($product, 2);
        $this->assertSame(10, $product->fresh()->stock, 'stok belum dipotong saat pending');

        $this->patchJson("/api/orders/{$id}/status", ['status' => 'processing'])->assertOk();
        $this->assertSame(8, $product->fresh()->stock, 'stok otomatis berkurang saat diproses');

        $this->patchJson("/api/orders/{$id}/status", ['status' => 'ready'])->assertOk();
        $this->patchJson("/api/orders/{$id}/status", ['status' => 'completed'])->assertStatus(409); // belum bayar

        $this->postJson("/api/orders/{$id}/payment", ['payment_method' => 'cash', 'paid_amount' => 50000])
            ->assertCreated()->assertJsonPath('data.change_amount', 20000);

        $this->patchJson("/api/orders/{$id}/status", ['status' => 'completed'])
            ->assertOk()->assertJsonPath('data.status', 'completed');

        $this->getJson('/api/orders?status=completed')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_insufficient_stock_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['stock' => 1]);

        $this->postJson('/api/orders', ['type' => 'dine_in', 'items' => [['product_id' => $product->id, 'quantity' => 5]]])
            ->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_invalid_status_transition_returns_409(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->createOrder(Product::factory()->create(['stock' => 10]));

        $this->patchJson("/api/orders/{$id}/status", ['status' => 'completed'])->assertStatus(409);
    }

    public function test_cancel_after_processing_restores_stock_and_requires_admin(): void
    {
        $kasir = User::factory()->create();
        Sanctum::actingAs($kasir);
        $product = Product::factory()->create(['stock' => 10]);
        $id = $this->createOrder($product, 3);
        $this->patchJson("/api/orders/{$id}/status", ['status' => 'processing'])->assertOk();

        $this->patchJson("/api/orders/{$id}/status", ['status' => 'cancelled', 'reason' => 'x'])->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->patchJson("/api/orders/{$id}/status", ['status' => 'cancelled', 'reason' => 'Pelanggan batal'])->assertOk();
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['type' => 'return', 'quantity' => 3]);
    }

    public function test_preorder_requires_customer_and_pickup_time(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['stock' => 0]);

        $this->postJson('/api/orders', ['type' => 'preorder', 'items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertStatus(422)->assertJsonValidationErrors(['customer_id', 'pickup_at']);

        $this->postJson('/api/orders', [
            'type' => 'preorder',
            'customer_id' => Customer::factory()->create()->id,
            'pickup_at' => now()->addDay()->toDateTimeString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('data.type', 'preorder');
    }

    public function test_only_pending_orders_can_be_edited(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = Product::factory()->create(['stock' => 10, 'price' => 10000]);
        $id = $this->createOrder($product, 1);

        $this->putJson("/api/orders/{$id}", ['items' => [['product_id' => $product->id, 'quantity' => 3]]])
            ->assertOk()->assertJsonPath('data.total', 30000);

        $this->patchJson("/api/orders/{$id}/status", ['status' => 'processing'])->assertOk();
        $this->putJson("/api/orders/{$id}", ['notes' => 'ubah'])->assertStatus(409);
    }
}
