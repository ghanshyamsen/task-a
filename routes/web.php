<?php

use App\Http\Controllers\ProductBrowserController;
use App\Http\Controllers\ProductImportPageController;
use App\Http\Controllers\UploadBrowserController;
use Illuminate\Support\Facades\Route;

Route::get('/', ProductImportPageController::class)->name('products.import');
Route::get('/uploads', [UploadBrowserController::class, 'index'])->name('uploads.index');
Route::get('/uploads/{upload}/{image}', [UploadBrowserController::class, 'showImage'])->name('uploads.image');
Route::post('/uploads/generate-csv', [UploadBrowserController::class, 'generateCsv'])->name('uploads.generate');
Route::get('/products', [ProductBrowserController::class, 'index'])->name('products.index');