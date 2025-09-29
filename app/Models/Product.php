<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'price',
        'metadata',
        'primary_image_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'price' => 'decimal:2',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function primaryImage(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'primary_image_id');
    }
}