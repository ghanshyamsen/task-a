<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('upload_id')->constrained('uploads')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('variant', 32);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->string('path');
            $table->timestamps();
            $table->unique(['upload_id', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};