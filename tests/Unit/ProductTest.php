<?php

namespace Tests\Unit;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_be_created_with_metadata(): void
    {
        $product = Product::create([
            'sku' => 'SKU-123',
            'name' => 'Sample Product',
            'description' => 'Demo item for unit testing.',
            'price' => 49.99,
            'metadata' => [
                'category' => 'testing',
                'weight_g' => 250,
                'in_stock' => true,
            ],
        ]);

        $this->assertNotNull($product->getKey());
        $this->assertSame('Sample Product', $product->name);
        $this->assertSame('SKU-123', $product->sku);
        $this->assertSame(49.99, (float) $product->price);
        $this->assertEquals([
            'category' => 'testing',
            'weight_g' => 250,
            'in_stock' => true,
        ], $product->metadata);

        $this->assertDatabaseHas('products', [
            'sku' => 'SKU-123',
            'name' => 'Sample Product',
        ]);
    }
}