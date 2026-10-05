<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_cannot_manage_catalog_or_users_or_reports(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $category = Category::factory()->create();
        $product = Product::factory()->create();

        $this->postJson('/api/products', ['category_id' => $category->id, 'sku' => 'A-1', 'name' => 'A', 'price' => 1000, 'stock' => 1])->assertForbidden();
        $this->deleteJson("/api/products/{$product->id}")->assertForbidden();
        $this->postJson("/api/products/{$product->id}/stock-movements", ['type' => 'in', 'quantity' => 5])->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/reports/sales')->assertForbidden();
        $this->getJson('/api/products')->assertOk();
    }

    public function test_admin_can_create_product_with_initial_stock_movement(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create();

        $this->postJson('/api/products', ['category_id' => $category->id, 'sku' => 'KOP-9', 'name' => 'Kopi', 'price' => 15000, 'stock' => 20])
            ->assertCreated()->assertJsonPath('data.stock', 20);

        $this->assertDatabaseHas('stock_movements', ['type' => 'in', 'quantity' => 20, 'stock_after' => 20]);
    }

    public function test_product_list_supports_search_filter_and_pagination(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Product::factory()->count(20)->create();
        Product::factory()->create(['name' => 'Croissant Spesial', 'stock' => 1, 'min_stock' => 5]);

        $this->getJson('/api/products?per_page=5')->assertOk()
            ->assertJsonCount(5, 'data')->assertJsonPath('meta.total', 21);
        $this->getJson('/api/products?search=Croissant')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/products?low_stock=1')->assertOk()->assertJsonPath('data.0.name', 'Croissant Spesial');
    }
}
