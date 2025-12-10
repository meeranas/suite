<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', '');
    }

    public function generate(string $model, string $prompt, array $config = []): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API key not configured');
        }

        $response = Http::post("{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}", [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $config['temperature'] ?? 0.7,
                'maxOutputTokens' => $config['max_tokens'] ?? 2000,
            ],
        ]);

        if (!$response->successful()) {
            Log::error('Gemini API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Gemini API request failed: ' . $response->body());
        }

        $data = $response->json();

        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return [
            'content' => $content,
            'tokens_used' => [
                'input' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                'output' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
            ],
        ];
    }
}

