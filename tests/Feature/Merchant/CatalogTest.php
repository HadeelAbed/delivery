<?php

namespace Tests\Feature\Merchant;

use App\Models\Category;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function makeApprovedMerchant(): array
    {
        $merchant = User::factory()->merchant()->create(['status' => 'active']);
        $profile = Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => 'Test Cafe',
            'address' => 'Gaza, Al-Rimal',
        ]);

        return [$merchant, $profile];
    }

    public function test_merchant_can_create_category_and_product(): void
    {
        [$merchant, $profile] = $this->makeApprovedMerchant();

        $this->actingAs($merchant)->post(route('merchant.categories.store'), [
            'name' => 'Pizza',
        ])->assertSessionHas('status', 'Category created.');

        $category = Category::where('merchant_id', $profile->id)->firstOrFail();

        $this->actingAs($merchant)->post(route('merchant.products.store'), [
            'category_id' => $category->id,
            'name' => 'Margherita',
            'description' => 'Classic tomato and mozzarella',
            'price' => 12.50,
        ])->assertRedirect(route('merchant.products'));

        $this->assertDatabaseHas('products', [
            'category_id' => $category->id,
            'name' => 'Margherita',
            'price' => 12.50,
            'is_available' => true,
        ]);
    }

    public function test_product_availability_toggle_hides_from_customers(): void
    {
        [$merchant, $profile] = $this->makeApprovedMerchant();
        $category = Category::create(['merchant_id' => $profile->id, 'name' => 'Pizza']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Margherita',
            'price' => 12.50,
        ]);

        $this->actingAs($merchant)->put(route('merchant.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Margherita',
            'price' => 12.50,
            'is_available' => false,
        ])->assertRedirect(route('merchant.products'));

        $this->assertFalse($product->fresh()->is_available);
    }

    public function test_merchant_cannot_manage_another_merchants_product(): void
    {
        [$merchantA, $profileA] = $this->makeApprovedMerchant();
        [, $profileB] = $this->makeApprovedMerchant();

        $categoryB = Category::create(['merchant_id' => $profileB->id, 'name' => 'Pizza']);
        $productB = Product::create([
            'category_id' => $categoryB->id,
            'name' => 'Margherita',
            'price' => 12.50,
        ]);

        $response = $this->actingAs($merchantA)->put(route('merchant.products.update', $productB), [
            'category_id' => $categoryB->id,
            'name' => 'Hacked',
            'price' => 0.01,
        ]);

        $response->assertStatus(403);
        $this->assertSame('Margherita', $productB->fresh()->name);
    }

    public function test_approved_merchant_appears_in_customer_browse(): void
    {
        [$merchant, $profile] = $this->makeApprovedMerchant();

        $response = $this->actingAs(User::factory()->create())->get(route('customer.browse'));

        $response->assertStatus(200);
        $response->assertSee('Test Cafe');
    }

    public function test_pending_merchant_does_not_appear_in_browse(): void
    {
        $merchant = User::factory()->merchant()->create(['status' => 'pending']);
        Merchant::create([
            'user_id' => $merchant->id,
            'business_name' => 'Pending Cafe',
            'address' => 'Gaza',
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('customer.browse'));

        $response->assertStatus(200);
        $response->assertDontSee('Pending Cafe');
    }
}
