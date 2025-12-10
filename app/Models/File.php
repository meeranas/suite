<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'chat_id',
        'original_name',
        'stored_name',
        'path',
        'mime_type',
        'size',
        'type',
        'is_processed',
        'is_embedded',
        'metadata',
        'signed_url_token',
        'signed_url_expires_at',
    ];

    protected $casts = [
        'is_processed' => 'boolean',
        'is_embedded' => 'boolean',
        'metadata' => 'array',
        'signed_url_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function embeddings(): HasMany
    {
        return $this->hasMany(VectorEmbedding::class);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeProcessed($query)
    {
        return $query->where('is_processed', true);
    }

    public function scopeEmbedded($query)
    {
        return $query->where('is_embedded', true);
    }
}

