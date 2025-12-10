<?php

namespace App\Services\AI;

interface AiProviderInterface
{
    /**
     * Generate AI response
     *
     * @param string $model Model name
     * @param string $prompt Full prompt including system message
     * @param array $config Additional configuration (temperature, max_tokens, tools, etc.)
     * @return array ['content' => string, 'tokens_used' => ['input' => int, 'output' => int], 'tool_calls' => array|null]
     */
    public function generate(string $model, string $prompt, array $config = []): array;
}

