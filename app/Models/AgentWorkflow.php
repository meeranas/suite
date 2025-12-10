<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentWorkflow extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'suite_id',
        'name',
        'description',
        'agent_sequence',
        'workflow_config',
        'is_active',
    ];

    protected $casts = [
        'agent_sequence' => 'array',
        'workflow_config' => 'array',
        'is_active' => 'boolean',
    ];

    public function suite(): BelongsTo
    {
        return $this->belongsTo(Suite::class);
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    public function getAgentsAttribute()
    {
        if (!$this->agent_sequence) {
            return collect();
        }

        // Fetch agents and order by sequence using PostgreSQL-compatible method
        $agents = Agent::whereIn('id', $this->agent_sequence)->get();

        // Order by the sequence array using collection
        return $agents->sortBy(function ($agent) {
            return array_search($agent->id, $this->agent_sequence);
        })->values();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

