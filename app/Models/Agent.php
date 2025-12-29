<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'suite_id',
        'name',
        'description',
        'slug',
        'model_provider',
        'model_name',
        'system_prompt',
        'prompt_template',
        'model_config',
        'external_api_configs',
        'enable_rag',
        'enable_web_search',
        'enable_external_apis',
        'order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'prompt_template' => 'array',
        'model_config' => 'array',
        'external_api_configs' => 'array',
        'enable_rag' => 'boolean',
        'enable_web_search' => 'boolean',
        'enable_external_apis' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function suite(): BelongsTo
    {
        return $this->belongsTo(Suite::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}

