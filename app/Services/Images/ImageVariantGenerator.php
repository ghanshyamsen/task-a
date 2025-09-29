<?php

namespace App\Services\Images;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageVariantGenerator
{
    private ImageManager $images;

    public function __construct(?ImageManager $images = null)
    {
        $this->images = $images ?? new ImageManager(new Driver());
    }

    /**
     * Generate image variants preserving aspect ratio.
     *
     * @return array<string, array{path: string, width: int, height: int, size: int, checksum: string}>
     */
    public function generate(string $disk, string $sourcePath, string $destinationDir, array $sizes = [256, 512, 1024]): array
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($sourcePath)) {
            throw new \InvalidArgumentException("Source image not found at {$sourcePath}");
        }

        $variants = [];

        $originalStream = $storage->readStream($sourcePath);
        if ($originalStream === false) {
            throw new \RuntimeException('Unable to read image stream for variants.');
        }

        $originalContents = stream_get_contents($originalStream);
        if ($originalContents === false) {
            throw new \RuntimeException('Unable to read image contents.');
        }
        fclose($originalStream);

        $image = $this->images->read($originalContents);
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) ?: 'jpg';

        // Ensure destination dir exists.
        $storage->makeDirectory($destinationDir);

        $originalVariantPath = trim($destinationDir, '/') . '/original.' . $extension;
        $storage->put($originalVariantPath, $originalContents);

        $variants['original'] = [
            'path' => $originalVariantPath,
            'width' => $image->width(),
            'height' => $image->height(),
            'size' => strlen($originalContents),
            'checksum' => hash('sha256', $originalContents),
        ];

        foreach ($sizes as $size) {
            $variants[(string) $size] = $this->createVariant(
                storage: $storage,
                sizeLimit: (int) $size,
                destinationPath: trim($destinationDir, '/') . '/' . $size . '.' . $extension,
                extension: $extension,
                originalContents: $originalContents,
            );
        }

        return $variants;
    }

    /**
     * @return array{path: string, width: int, height: int, size: int, checksum: string}
     */
    private function createVariant(
        $storage,
        int $sizeLimit,
        string $destinationPath,
        string $extension,
        string $originalContents
    ): array {
        $variant = $this->images->read($originalContents);
        $variant->scaleDown($sizeLimit, $sizeLimit);

        $encoded = $variant->encodeByPath($destinationPath);
        $binary = $encoded->toString();

        $storage->put($destinationPath, $binary);

        return [
            'path' => $destinationPath,
            'width' => $variant->width(),
            'height' => $variant->height(),
            'size' => strlen($binary),
            'checksum' => hash('sha256', $binary),
        ];
    }
}