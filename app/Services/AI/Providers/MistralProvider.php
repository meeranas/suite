<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MistralProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.mistral.ai/v1';

    public function __construct()
    {
        $this->apiKey = config('services.mistral.api_key', '');
    }

    public function generate(string $model, string $prompt, array $config = []): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Mistral API key not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => $config['temperature'] ?? 0.7,
                    'max_tokens' => $config['max_tokens'] ?? 2000,
                ]);

        if (!$response->successful()) {
            Log::error('Mistral API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Mistral API request failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'tokens_used' => [
                'input' => $data['usage']['prompt_tokens'] ?? 0,
                'output' => $data['usage']['completion_tokens'] ?? 0,
            ],
        ];
    }
}

