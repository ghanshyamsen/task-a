<?php

namespace App\Http\Controllers\Api;

use App\Enums\UploadStatus;
use App\Http\Controllers\Controller;
use App\Models\Upload;
use App\Services\Uploads\ChunkedUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ChunkedUploadController extends Controller
{
    public function __construct(
        private readonly ChunkedUploadService $uploads,
    ) {
    }

    public function init(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filename' => 'required|string',
            'size' => 'required|integer|min:1',
            'chunk_size' => 'required|integer|min:1',
            'total_chunks' => 'required|integer|min:1',
            'checksum' => 'required|string|min:32',
            'mime_type' => 'nullable|string',
        ]);

        $upload = $this->uploads->initiate($data);

        return response()->json([
            'upload_id' => $upload->getKey(),
            'status' => $upload->status->value,
        ], 201);
    }

    public function chunk(Request $request, Upload $upload): JsonResponse
    {
        $data = $request->validate([
            'index' => 'required|integer|min:1',
            'chunk' => 'required|file',
        ]);

        try {
            $status = $this->uploads->storeChunk($upload, (int) $data['index'], $data['chunk']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($status);
    }

    public function status(Upload $upload): JsonResponse
    {
        try {
            $status = $this->uploads->status($upload);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 404);
        }

        return response()->json($status);
    }

    public function complete(Upload $upload): JsonResponse
    {
        try {
            $result = $this->uploads->complete($upload);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'upload_id' => $upload->getKey(),
            'status' => UploadStatus::Completed->value,
            'images' => array_map(fn ($image) => [
                'id' => $image->getKey(),
                'variant' => $image->variant,
                'path' => $image->path,
                'width' => $image->width,
                'height' => $image->height,
                'size' => $image->size,
                'checksum' => $image->checksum,
            ], $result['images']),
        ]);
    }
}