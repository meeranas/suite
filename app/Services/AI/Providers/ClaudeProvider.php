<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.anthropic.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key', '');
    }

    public function generate(string $model, string $prompt, array $config = []): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Claude API key not configured');
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/messages", [
                    'model' => $model,
                    'max_tokens' => $config['max_tokens'] ?? 2000,
                    'temperature' => $config['temperature'] ?? 0.7,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

        if (!$response->successful()) {
            Log::error('Claude API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Claude API request failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'tokens_used' => [
                'input' => $data['usage']['input_tokens'] ?? 0,
                'output' => $data['usage']['output_tokens'] ?? 0,
            ],
        ];
    }
}

