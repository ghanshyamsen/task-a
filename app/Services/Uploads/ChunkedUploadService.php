<?php

namespace App\Services\Uploads;

use App\Enums\UploadStatus;
use App\Models\Image;
use App\Models\Upload;
use App\Services\Images\ImageVariantGenerator;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ChunkedUploadService
{
    public function __construct(
        private readonly ImageVariantGenerator $variantGenerator,
    ) {
    }

    public function initiate(array $payload): Upload
    {
        return DB::transaction(function () use ($payload): Upload {
            $id = (string) Str::ulid();

            return Upload::create([
                'id' => $id,
                'original_filename' => $payload['filename'],
                'mime_type' => $payload['mime_type'] ?? null,
                'total_size' => (int) $payload['size'],
                'chunk_size' => (int) $payload['chunk_size'],
                'total_chunks' => (int) $payload['total_chunks'],
                'checksum' => strtolower($payload['checksum']),
                'storage_disk' => config('filesystems.default', 'local'),
                'status' => UploadStatus::Pending,
            ]);
        });
    }

    public function storeChunk(Upload $upload, int $chunkIndex, UploadedFile $chunk): array
    {
        if ($chunkIndex < 1 || $chunkIndex > $upload->total_chunks) {
            throw new RuntimeException('Chunk index out of bounds.');
        }

        $expectedSize = $upload->chunk_size;
        $actualSize = $chunk->getSize() ?? 0;
        if ($chunkIndex < $upload->total_chunks && $actualSize !== $expectedSize) {
            throw new RuntimeException('Chunk size mismatch.');
        }
        if ($chunkIndex === $upload->total_chunks && $actualSize > $expectedSize) {
            throw new RuntimeException('Final chunk larger than expected.');
        }

        return DB::transaction(function () use ($upload, $chunkIndex, $chunk): array {
            /** @var Upload $locked */
            $locked = Upload::whereKey($upload->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === UploadStatus::Completed) {
                return $this->buildStatusPayload($locked);
            }

            $disk = $this->disk($locked);
            $chunkDir = $this->chunkDirectory($locked);
            $disk->makeDirectory($chunkDir);

            $chunkPath = $chunkDir . '/' . $chunkIndex . '.part';
            $stream = fopen($chunk->getRealPath(), 'rb');
            if ($stream === false) {
                throw new RuntimeException('Unable to open chunk stream.');
            }
            $disk->put($chunkPath, $stream);
            fclose($stream);

            $receivedIndexes = $this->gatherChunkIndexes($disk, $chunkDir);
            $locked->forceFill([
                'received_chunks' => count($receivedIndexes),
                'status' => UploadStatus::InProgress,
            ])->save();

            return $this->buildStatusPayload($locked->refresh(), $receivedIndexes);
        });
    }

    public function status(Upload $upload): array
    {
        $fresh = $upload->fresh();
        if ($fresh === null) {
            throw new RuntimeException('Upload not found.');
        }
        $upload = $fresh;

        $disk = $this->disk($upload);
        $chunkDir = $this->chunkDirectory($upload);

        $indexes = $this->gatherChunkIndexes($disk, $chunkDir);

        if ($upload->received_chunks !== count($indexes)) {
            $upload->forceFill(['received_chunks' => count($indexes)])->save();
        }

        return $this->buildStatusPayload($upload, $indexes);
    }

    /**
     * @return array{images: array<int, Image>, variants: array<string, mixed>}
     */
    public function complete(Upload $upload): array
    {
        return DB::transaction(function () use ($upload): array {
            /** @var Upload $locked */
            $locked = Upload::whereKey($upload->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === UploadStatus::Completed) {
                return [
                    'images' => $locked->images()->get()->all(),
                    'variants' => [],
                ];
            }

            $disk = $this->disk($locked);
            $chunkDir = $this->chunkDirectory($locked);
            $chunkIndexes = $this->gatherChunkIndexes($disk, $chunkDir);

            if (count($chunkIndexes) !== $locked->total_chunks) {
                throw new RuntimeException('Upload incomplete. Missing chunks detected.');
            }

            $assembledPath = $this->assembleChunks($locked, $disk, $chunkIndexes);

            $checksum = hash_file('sha256', $disk->path($assembledPath));
            if ($checksum !== $locked->checksum) {
                $locked->forceFill(['status' => UploadStatus::Failed])->save();
                $disk->delete($assembledPath);

                throw new RuntimeException('Checksum mismatch. Upload aborted.');
            }

            $destinationDir = $this->variantDirectory($locked);
            $variants = $this->variantGenerator->generate(
                $locked->storage_disk,
                $assembledPath,
                $destinationDir,
                [256, 512, 1024]
            );

            $images = [];
            foreach ($variants as $variant => $data) {
                $image = Image::firstOrNew([
                    'upload_id' => $locked->getKey(),
                    'variant' => $variant,
                ]);

                if (! $image->exists) {
                    $image->id = (string) Str::ulid();
                }

                $image->fill([
                    'path' => $data['path'],
                    'width' => $data['width'],
                    'height' => $data['height'],
                    'size' => $data['size'],
                    'checksum' => $data['checksum'],
                ])->save();

                $images[] = $image;
            }

            $locked->markCompleted();

            $disk->delete($assembledPath);
            $disk->deleteDirectory($chunkDir);
            $disk->deleteDirectory('uploads/' . $locked->getKey() . '/assembled');

            return [
                'images' => $images,
                'variants' => $variants,
            ];
        });
    }

    private function disk(Upload $upload): FilesystemAdapter
    {
        return Storage::disk($upload->storage_disk);
    }

    private function chunkDirectory(Upload $upload): string
    {
        return 'uploads/' . $upload->getKey() . '/chunks';
    }

    private function variantDirectory(Upload $upload): string
    {
        return 'uploads/' . $upload->getKey() . '/variants';
    }

    private function gatherChunkIndexes(FilesystemAdapter $disk, string $chunkDir): array
    {
        if (! $disk->exists($chunkDir)) {
            return [];
        }

        $files = collect($disk->files($chunkDir));

        return $files
            ->map(function (string $path): int {
                return (int) preg_replace('/[^\d]/', '', basename($path));
            })
            ->filter(fn (int $index): bool => $index > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function assembleChunks(Upload $upload, FilesystemAdapter $disk, array $chunkIndexes): string
    {
        $assembledDir = 'uploads/' . $upload->getKey() . '/assembled';
        $disk->makeDirectory($assembledDir);

        $extension = strtolower(pathinfo($upload->original_filename, PATHINFO_EXTENSION)) ?: 'bin';
        $basename = Str::slug(pathinfo($upload->original_filename, PATHINFO_FILENAME)) ?: Str::lower(Str::ulid());
        $assembledPath = $assembledDir . '/' . $basename . '.' . $extension;

        $assembledFullPath = $disk->path($assembledPath);
        $handle = fopen($assembledFullPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open destination file for assembly.');
        }

        foreach ($chunkIndexes as $index) {
            $chunkPath = $this->chunkDirectory($upload) . '/' . $index . '.part';
            $chunkFullPath = $disk->path($chunkPath);
            $chunkHandle = fopen($chunkFullPath, 'rb');
            if ($chunkHandle === false) {
                fclose($handle);
                throw new RuntimeException("Failed to open chunk {$index}.");
            }
            stream_copy_to_stream($chunkHandle, $handle);
            fclose($chunkHandle);
        }

        fclose($handle);

        return $assembledPath;
    }

    private function buildStatusPayload(Upload $upload, ?array $receivedIndexes = null): array
    {
        return [
            'upload_id' => $upload->getKey(),
            'status' => $upload->status->value,
            'received_chunks' => $upload->received_chunks,
            'total_chunks' => $upload->total_chunks,
            'received_indexes' => $receivedIndexes ?? [],
        ];
    }
}