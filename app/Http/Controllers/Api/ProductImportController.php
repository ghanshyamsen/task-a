<?php

namespace App\Http\Controllers\Api;

use App\DataTransferObjects\ProductImportSummary;
use App\Http\Controllers\Controller;
use App\Services\Products\ProductCsvImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProductImportController extends Controller
{
    public function __construct(
        private readonly ProductCsvImporter $importer,
    ) {
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $data['file']->store('imports');

        try {
            $summary = $this->importer->import(Storage::path($path));
        } catch (RuntimeException $exception) {
            Storage::delete($path);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        Storage::delete($path);

        return response()->json($summary->toArray());
    }
}