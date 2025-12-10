<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VectorEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'chat_id',
        'user_id',
        'content',
        'content_hash',
        'chunk_index',
        'embedding',
        'vector_id',
        'metadata',
    ];

    protected $casts = [
        'embedding' => 'array',
        'metadata' => 'array',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForChat($query, $chatId)
    {
        return $query->where('chat_id', $chatId);
    }
}

