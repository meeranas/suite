<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', '');
    }

    public function generate(string $model, string $prompt, array $config = []): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key not configured');
        }

        // Check if config contains messages array (for function calling)
        if (!empty($config['messages']) && is_array($config['messages'])) {
            $messages = $config['messages'];
        } else {
            // Parse messages from prompt (support system/user format)
            $messages = $this->parseMessages($prompt);
        }

        // Build request payload
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $config['temperature'] ?? 0.7,
            'max_tokens' => $config['max_tokens'] ?? 2000,
        ];

        // Add tools if provided
        if (!empty($config['tools'])) {
            $payload['tools'] = $config['tools'];
            $payload['tool_choice'] = $config['tool_choice'] ?? 'auto';
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/chat/completions", $payload);

        if (!$response->successful()) {
            Log::error('OpenAI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('OpenAI API request failed: ' . $response->body());
        }

        $data = $response->json();
        $message = $data['choices'][0]['message'] ?? [];

        // Extract tool calls if present (preserve original format for OpenAI)
        $toolCalls = null;
        if (!empty($message['tool_calls'])) {
            $toolCalls = array_map(function ($toolCall) {
                // Return in format expected by AiModelService
                return [
                    'id' => $toolCall['id'] ?? null,
                    'type' => $toolCall['type'] ?? 'function',
                    'function' => [
                        'name' => $toolCall['function']['name'] ?? null,
                        'arguments' => json_decode($toolCall['function']['arguments'] ?? '{}', true),
                    ],
                ];
            }, $message['tool_calls']);
        }

        return [
            'content' => $message['content'] ?? '',
            'tokens_used' => [
                'input' => $data['usage']['prompt_tokens'] ?? 0,
                'output' => $data['usage']['completion_tokens'] ?? 0,
            ],
            'tool_calls' => $toolCalls,
        ];
    }

    /**
     * Parse prompt into messages array
     * Supports format: "System: ...\n\nUser: ..." or just user message
     */
    protected function parseMessages(string $prompt): array
    {
        $messages = [];

        // Check if prompt contains system/user separation
        if (preg_match('/^System:\s*(.+?)(?:\n\nUser:|$)/is', $prompt, $systemMatch)) {
            $messages[] = [
                'role' => 'system',
                'content' => trim($systemMatch[1]),
            ];

            // Extract user message
            if (preg_match('/\n\nUser:\s*(.+)$/is', $prompt, $userMatch)) {
                $messages[] = [
                    'role' => 'user',
                    'content' => trim($userMatch[1]),
                ];
            }
        } else {
            // Single user message
            $messages[] = [
                'role' => 'user',
                'content' => $prompt,
            ];
        }

        return $messages;
    }
}

