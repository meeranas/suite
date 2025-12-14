<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Suite extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'status',
        'subscription_tiers',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'subscription_tiers' => 'array',
        'metadata' => 'array',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class)->orderBy('order');
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(AgentWorkflow::class);
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForTier($query, string $tier)
    {
        return $query->where(function ($q) use ($tier) {
            $q->whereJsonContains('subscription_tiers', $tier)
                ->orWhereNull('subscription_tiers');
        });
    }
}

