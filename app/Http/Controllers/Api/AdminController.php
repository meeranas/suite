<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalApiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Get available AI providers
     */
    public function getProviders(): JsonResponse
    {
        $providers = [
            [
                'id' => 'openai',
                'name' => 'OpenAI',
                'models' => [
                    'gpt-4o',
                    'gpt-4-turbo',
                    'gpt-4',
                    'gpt-3.5-turbo-0125',
                    'gpt-3.5-turbo',
                    'text-embedding-3-small',
                    'text-embedding-3-large',
                ],
            ],
            [
                'id' => 'gemini',
                'name' => 'Google Gemini',
                'models' => [
                    'gemini-pro',
                    'gemini-pro-vision',
                    'gemini-1.5-pro',
                    'gemini-1.5-flash',
                ],
            ],
            [
                'id' => 'mistral',
                'name' => 'Mistral AI',
                'models' => [
                    'mistral-small',
                    'mistral-medium',
                    'mistral-large',
                    'mistral-tiny',
                ],
            ],
            [
                'id' => 'claude',
                'name' => 'Anthropic Claude',
                'models' => [
                    'claude-3-opus',
                    'claude-3-sonnet',
                    'claude-3-haiku',
                    'claude-2.1',
                    'claude-2.0',
                ],
            ],
        ];

        return response()->json($providers);
    }

    /**
     * Get available models for a specific provider
     */
    public function getModels(string $provider): JsonResponse
    {
        $models = match ($provider) {
            'openai' => [
                'gpt-4o',
                'gpt-4-turbo',
                'gpt-4',
                'gpt-3.5-turbo-0125',
                'gpt-3.5-turbo',
                'text-embedding-3-small',
                'text-embedding-3-large',
            ],
            'gemini' => [
                'gemini-pro',
                'gemini-pro-vision',
                'gemini-1.5-pro',
                'gemini-1.5-flash',
            ],
            'mistral' => [
                'mistral-small',
                'mistral-medium',
                'mistral-large',
                'mistral-tiny',
            ],
            'claude' => [
                'claude-3-opus',
                'claude-3-sonnet',
                'claude-3-haiku',
                'claude-2.1',
                'claude-2.0',
            ],
            default => [],
        };

        return response()->json([
            'provider' => $provider,
            'models' => $models,
        ]);
    }

    /**
     * Get available external API providers from database
     */
    public function getExternalApis(): JsonResponse
    {
        // Get active external API configurations from database
        $apis = ExternalApiConfig::where('is_active', true)
            ->orderBy('provider')
            ->orderBy('name')
            ->get()
            ->map(function ($config) {
                return [
                    'id' => $config->id, // Use database ID
                    'name' => $config->name, // Use name from database
                    'provider' => $config->provider, // Include provider
                    'type' => $config->type ?? 'data_api', // Include type (web_search or data_api)
                    'api_type' => $config->api_type ?? 'rest', // Include API type
                    'description' => $config->config['description'] ?? $config->name, // Use description from config or fallback to name
                ];
            })
            ->values()
            ->toArray();

        return response()->json($apis);
    }

    /**
     * Get subscription tiers
     */
    public function getSubscriptionTiers(): JsonResponse
    {
        $tiers = [
            ['id' => 'free', 'name' => 'Free'],
            ['id' => 'tier1', 'name' => 'Tier 1'],
            ['id' => 'tier2', 'name' => 'Tier 2'],
            ['id' => 'tier3', 'name' => 'Tier 3'],
        ];

        return response()->json($tiers);
    }
}





