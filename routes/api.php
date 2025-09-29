<?php

\Illuminate\Support\Facades\Route::prefix('uploads')->group(function (): void {
    \Illuminate\Support\Facades\Route::post('init', [\App\Http\Controllers\Api\ChunkedUploadController::class, 'init']);
    \Illuminate\Support\Facades\Route::post('{upload}/chunk', [\App\Http\Controllers\Api\ChunkedUploadController::class, 'chunk']);
    \Illuminate\Support\Facades\Route::get('{upload}', [\App\Http\Controllers\Api\ChunkedUploadController::class, 'status']);
    \Illuminate\Support\Facades\Route::post('{upload}/complete', [\App\Http\Controllers\Api\ChunkedUploadController::class, 'complete']);
});

\Illuminate\Support\Facades\Route::post('products/import', [\App\Http\Controllers\Api\ProductImportController::class, 'import']);
