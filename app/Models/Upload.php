<?php

namespace App\Models;

use App\Enums\UploadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Upload extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'original_filename',
        'mime_type',
        'total_size',
        'chunk_size',
        'total_chunks',
        'received_chunks',
        'checksum',
        'storage_disk',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'status' => UploadStatus::class,
    ];

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function markCompleted(): void
    {
        $this->forceFill([
            'status' => UploadStatus::Completed,
            'completed_at' => now(),
        ])->save();
    }
}