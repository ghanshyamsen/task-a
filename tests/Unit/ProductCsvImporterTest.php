<?php

namespace Tests\Unit;

use App\Enums\UploadStatus;
use App\Models\Image;
use App\Models\Product;
use App\Models\Upload;
use App\Services\Products\ProductCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_and_updates_products(): void
    {
        $importer = app(ProductCsvImporter::class);

        [$uploadOne, $originalOne] = $this->makeUpload();
        [$uploadTwo, $originalTwo] = $this->makeUpload();

        Product::create([
            'sku' => 'SKU100',
            'name' => 'Legacy Product',
            'description' => 'Old description',
            'price' => 10.00,
        ]);

        $rows = [
            ['sku', 'name', 'price', 'description', 'upload_id'],
            ['SKU100', 'Updated Product', '19.99', 'Fresh description', $uploadOne->getKey()],
            ['SKU200', 'New Product', '29.99', 'Brand new', $uploadTwo->getKey()],
            ['SKU200', 'Duplicate Product', '39.99', 'Duplicate row', $uploadTwo->getKey()],
            ['SKU300', 'Broken Product', '', 'Missing price', $uploadTwo->getKey()],
        ];

        $csvPath = $this->writeCsv($rows);

        $summary = $importer->import($csvPath);

        $this->assertSame(4, $summary->total);
        $this->assertSame(1, $summary->created);
        $this->assertSame(1, $summary->updated);
        $this->assertSame(1, $summary->invalid);
        $this->assertSame(1, $summary->duplicates);
        $this->assertNotEmpty($summary->errors);

        $product100 = Product::where('sku', 'SKU100')->firstOrFail();
        $this->assertSame('Updated Product', $product100->name);
        $this->assertSame($originalOne, $product100->primary_image_id);

        $product200 = Product::where('sku', 'SKU200')->firstOrFail();
        $this->assertSame($originalTwo, $product200->primary_image_id);

        $this->assertTrue(Image::where('upload_id', $uploadOne->getKey())->pluck('product_id')->unique()->contains($product100->id));
        $this->assertTrue(Image::where('upload_id', $uploadTwo->getKey())->pluck('product_id')->unique()->contains($product200->id));
    }

    public function test_import_allows_rows_without_uploads(): void
    {
        $importer = app(ProductCsvImporter::class);

        $rows = [
            ['sku', 'name', 'price', 'description', 'upload_id'],
            ['SKU500', 'No Image Product', '15.00', 'No upload provided', ''],
        ];

        $csvPath = $this->writeCsv($rows);

        $summary = $importer->import($csvPath);

        $this->assertSame(1, $summary->total);
        $this->assertSame(1, $summary->created);
        $this->assertSame(0, $summary->updated);
        $this->assertSame(0, $summary->invalid);
        $this->assertSame(0, $summary->duplicates);
        $this->assertEmpty($summary->errors);

        $product = Product::where('sku', 'SKU500')->firstOrFail();
        $this->assertNull($product->primary_image_id);
    }

    private function makeUpload(): array
    {
        $uploadId = (string) Str::ulid();
        $upload = Upload::create([
            'id' => $uploadId,
            'original_filename' => 'example.jpg',
            'mime_type' => 'image/jpeg',
            'total_size' => 1024,
            'chunk_size' => 512,
            'total_chunks' => 2,
            'received_chunks' => 2,
            'checksum' => str_repeat('a', 64),
            'status' => UploadStatus::Completed,
        ]);

        $originalImageId = (string) Str::ulid();
        $variants = [
            'original' => $originalImageId,
            '256' => (string) Str::ulid(),
            '512' => (string) Str::ulid(),
            '1024' => (string) Str::ulid(),
        ];

        foreach ($variants as $variant => $id) {
            Image::create([
                'id' => $id,
                'upload_id' => $uploadId,
                'product_id' => null,
                'variant' => $variant,
                'width' => 100,
                'height' => 100,
                'size' => 1000,
                'checksum' => str_repeat('b', 64),
                'path' => "uploads/{$uploadId}/variants/{$variant}.jpg",
            ]);
        }

        return [$upload, $originalImageId];
    }

    private function writeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'products_');
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }
}