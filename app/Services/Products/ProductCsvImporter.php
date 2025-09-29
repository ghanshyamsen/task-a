<?php

namespace App\Services\Products;

use App\DataTransferObjects\ProductImportSummary;
use App\Enums\UploadStatus;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use League\Csv\Reader;
use RuntimeException;

class ProductCsvImporter
{
    private const REQUIRED_HEADERS = ['sku', 'name', 'price'];
    private const REQUIRED_VALUES = ['sku', 'name', 'price'];

    public function import(string $path): ProductImportSummary
    {
        if (! is_readable($path)) {
            throw new RuntimeException("CSV file not readable at {$path}");
        }

        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        $headers = array_map(
            fn ($header) => Str::of((string) $header)->trim()->lower()->__toString(),
            $csv->getHeader() ?? []
        );

        $this->assertHeaders($headers);

        $summary = new ProductImportSummary();
        $seenSkus = [];

        foreach ($csv->getRecords() as $index => $record) {
            $rowNumber = $index + 2; // Account for header row.

            $normalized = $this->normalizeRecord($record);
            if ($this->isEmptyRow($normalized)) {
                continue;
            }

            $summary->total++;

            if (! $this->hasRequiredValues($normalized)) {
                $summary->invalid++;
                $summary->recordError($rowNumber, 'Missing required column value.');
                continue;
            }

            $sku = strtoupper(trim((string) $normalized['sku']));
            if (isset($seenSkus[$sku])) {
                $summary->duplicates++;
                continue;
            }
            $seenSkus[$sku] = true;

            if (! is_numeric($normalized['price'])) {
                $summary->invalid++;
                $summary->recordError($rowNumber, 'Invalid price value.');
                continue;
            }

            $price = number_format((float) $normalized['price'], 2, '.', '');
            $description = $normalized['description'] ?? null;
            $uploadId = trim((string) ($normalized['upload_id'] ?? ''));
            $metadata = $this->extractMetadata($normalized);

            $upload = null;
            $originalImage = null;

            if ($uploadId !== '') {
                $upload = Upload::with('images')->find($uploadId);
                if ($upload === null || $upload->status !== UploadStatus::Completed) {
                    $summary->invalid++;
                    $summary->recordError($rowNumber, 'Upload missing or incomplete.');
                    continue;
                }

                $originalImage = $upload->images->firstWhere('variant', 'original');
                if ($originalImage === null) {
                    $summary->invalid++;
                    $summary->recordError($rowNumber, 'Original image variant missing.');
                    continue;
                }
            }

            try {
                DB::transaction(function () use (
                    $sku,
                    $normalized,
                    $price,
                    $description,
                    $metadata,
                    $upload,
                    $originalImage,
                    $summary
                ): void {
                    $product = Product::query()->where('sku', $sku)->lockForUpdate()->first();

                    if ($upload !== null) {
                        $currentOwnerId = $upload->images->first()?->product_id;
                        if ($currentOwnerId !== null && ($product === null || $currentOwnerId !== $product->getKey())) {
                            throw new RuntimeException('Upload already linked to a different product.');
                        }
                    }

                    $payload = [
                        'sku' => $sku,
                        'name' => (string) $normalized['name'],
                        'description' => $description,
                        'price' => $price,
                        'metadata' => $metadata ?: null,
                    ];

                    if ($product === null) {
                        $product = Product::create($payload);
                        $summary->created++;
                    } else {
                        $product->fill($payload)->save();
                        $summary->updated++;
                    }

                    if ($upload !== null) {
                        foreach ($upload->images as $image) {
                            if ($image->product_id !== $product->getKey()) {
                                $image->product()->associate($product);
                                $image->save();
                            }
                        }

                        if ($originalImage !== null && $product->primary_image_id !== $originalImage->getKey()) {
                            $product->primary_image_id = $originalImage->getKey();
                            $product->save();
                        }
                    }
                });
            } catch (RuntimeException $exception) {
                $summary->invalid++;
                $summary->recordError($rowNumber, $exception->getMessage());
            }
        }

        return $summary;
    }

    private function assertHeaders(array $headers): void
    {
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);
        if ($missing !== []) {
            throw new RuntimeException('CSV missing required headers: ' . implode(', ', $missing));
        }
    }

    private function normalizeRecord(array $record): array
    {
        $normalized = [];
        foreach ($record as $header => $value) {
            $normalized[Str::of((string) $header)->trim()->lower()->__toString()] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }

    private function extractMetadata(array $row): array
    {
        $metadata = [];
        foreach ($row as $key => $value) {
            if (in_array($key, self::REQUIRED_HEADERS, true) || $key === 'description') {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $metadata[$key] = $value;
        }

        return $metadata;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    private function hasRequiredValues(array $row): bool
    {
        foreach (self::REQUIRED_VALUES as $header) {
            if (! isset($row[$header]) || $row[$header] === null || $row[$header] === '') {
                return false;
            }
        }

        return true;
    }
}