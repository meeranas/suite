<?php

namespace App\Services\AI;

use App\Models\Agent;
use App\Models\UsageLog;
use App\Services\AI\Providers\OpenAiProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\MistralProvider;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\PromptBuilder;
use App\Services\Tool\ToolExecutionService;
use Illuminate\Support\Facades\Log;

class AiModelService
{
    protected array $providers = [];
    protected PromptBuilder $promptBuilder;
    protected ?ToolExecutionService $toolExecutionService = null;

    public function __construct(PromptBuilder $promptBuilder, ?ToolExecutionService $toolExecutionService = null)
    {
        $this->promptBuilder = $promptBuilder;
        $this->toolExecutionService = $toolExecutionService;
        $this->providers = [
            'openai' => new OpenAiProvider(),
            // 'gemini' => new GeminiProvider(),
            // 'mistral' => new MistralProvider(),
            // 'claude' => new ClaudeProvider(),
        ];
    }

    /**
     * Generate AI response using agent configuration
     * Supports tool calling with automatic execution loop
     */
    public function generateResponse(
        Agent $agent,
        string $userPrompt,
        array $context = [],
        ?string $previousAgentOutput = null,
        ?array $tools = null,
        ?array $availableApiConfigs = null
    ): array {
        $provider = $this->getProvider($agent->model_provider);

        if (!$provider) {
            throw new \Exception("Unsupported model provider: {$agent->model_provider}");
        }

        // Build system prompt using PromptBuilder (pass tools if available)
        $systemPrompt = $this->promptBuilder->buildSystemPrompt($agent, $previousAgentOutput, $tools);

        // Build user prompt with context (pass hasTools flag)
        $hasTools = !empty($tools);
        $fullUserPrompt = $this->promptBuilder->buildUserPrompt(
            $userPrompt,
            $context['rag'] ?? [],
            $context['web_search'] ?? [],
            $context['external_data'] ?? [],
            $context['chat_history'] ?? [],
            $hasTools
        );

        // Get model configuration
        $config = array_merge([
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ], $agent->model_config ?? []);

        // Add tools if provided
        if ($tools !== null) {
            $config['tools'] = $tools;
        }

        $totalTokensUsed = ['input' => 0, 'output' => 0];
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        $messages[] = ['role' => 'user', 'content' => $fullUserPrompt];

        $maxIterations = 5; // Prevent infinite loops
        $iteration = 0;
        $finalContent = '';

        try {
            while ($iteration < $maxIterations) {
                // Combine messages into prompt for providers that need it
                $fullPrompt = $this->formatMessagesForProvider($messages);

                // For OpenAI with tools, pass messages in config
                if ($agent->model_provider === 'openai' && !empty($config['tools'])) {
                    $config['messages'] = $messages;
                    $response = $provider->generate($agent->model_name, '', $config);
                } else {
                    $response = $provider->generate($agent->model_name, $fullPrompt, $config);
                }

                $totalTokensUsed['input'] += $response['tokens_used']['input'] ?? 0;
                $totalTokensUsed['output'] += $response['tokens_used']['output'] ?? 0;

                // Add assistant response to messages
                $assistantMessage = [
                    'role' => 'assistant',
                    'content' => $response['content'] ?? '',
                ];

                // Add tool calls if present
                if (!empty($response['tool_calls'])) {
                    // Convert tool calls to OpenAI format (arguments must be JSON strings)
                    $formattedToolCalls = array_map(function ($toolCall) {
                        return [
                            'id' => $toolCall['id'] ?? null,
                            'type' => $toolCall['type'] ?? 'function',
                            'function' => [
                                'name' => $toolCall['function']['name'] ?? null,
                                'arguments' => json_encode($toolCall['function']['arguments'] ?? []), // Convert back to JSON string
                            ],
                        ];
                    }, $response['tool_calls']);

                    $assistantMessage['tool_calls'] = $formattedToolCalls;
                    $messages[] = $assistantMessage;

                    // Execute tools
                    if ($this->toolExecutionService && $availableApiConfigs) {
                        $toolResults = [];
                        foreach ($response['tool_calls'] as $toolCall) {
                            $toolName = $toolCall['function']['name'] ?? '';
                            $arguments = $toolCall['function']['arguments'] ?? [];

                            $result = $this->toolExecutionService->executeTool(
                                $toolName,
                                $arguments,
                                $availableApiConfigs
                            );

                            // Format tool result for AI
                            $toolContent = $this->formatToolResult($toolName, $result);

                            $toolResults[] = [
                                'role' => 'tool',
                                'tool_call_id' => $toolCall['id'] ?? null,
                                'content' => $toolContent,
                            ];
                        }

                        // Add tool results to messages
                        $messages = array_merge($messages, $toolResults);

                        // Continue loop to get AI response with tool results
                        $iteration++;
                        continue;
                    }
                }

                // No tool calls or tools not available - return final response
                $messages[] = $assistantMessage;
                $finalContent = $response['content'] ?? '';
                break;
            }

            return [
                'content' => $finalContent,
                'tokens_used' => $totalTokensUsed,
                'model' => $agent->model_name,
            ];
        } catch (\Exception $e) {
            Log::error('AI generation failed', [
                'agent_id' => $agent->id,
                'provider' => $agent->model_provider,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Format messages array into prompt string
     */
    protected function formatMessagesForProvider(array $messages): string
    {
        $parts = [];
        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $content = $message['content'] ?? '';

            if ($role === 'system') {
                $parts[] = "System: {$content}";
            } elseif ($role === 'user') {
                $parts[] = "User: {$content}";
            } elseif ($role === 'assistant') {
                $parts[] = "Assistant: {$content}";
            } elseif ($role === 'tool') {
                $toolName = $message['name'] ?? 'tool';
                $parts[] = "Tool ({$toolName}): {$content}";
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Format tool result for AI consumption
     */
    protected function formatToolResult(string $toolName, array $result): string
    {
        if (isset($result['error'])) {
            return "Error: " . $result['error'];
        }

        $data = $result['data'] ?? null;
        if (empty($data)) {
            return "No data returned from tool.";
        }

        // Format FDA drug data specially
        if (str_contains($toolName, 'fda') && isset($data['data']) && is_array($data['data'])) {
            $drugs = $data['data'];
            $formatted = "FDA Drug Data (" . count($drugs) . " records):\n\n";

            foreach (array_slice($drugs, 0, 10) as $index => $drug) {
                $formatted .= "Drug " . ($index + 1) . ":\n";
                if (!empty($drug['brand_name'])) {
                    $formatted .= "  Brand Name: " . $drug['brand_name'] . "\n";
                }
                if (!empty($drug['generic_name'])) {
                    $formatted .= "  Generic Name: " . $drug['generic_name'] . "\n";
                }
                if (!empty($drug['substance_name'])) {
                    $formatted .= "  Substance: " . $drug['substance_name'] . "\n";
                }
                if (!empty($drug['indications_and_usage']) && is_array($drug['indications_and_usage'])) {
                    $formatted .= "  Indications: " . implode('; ', array_slice($drug['indications_and_usage'], 0, 2)) . "\n";
                }
                if (!empty($drug['description']) && is_array($drug['description'])) {
                    $desc = implode(' ', array_slice($drug['description'], 0, 1));
                    $formatted .= "  Description: " . substr($desc, 0, 200) . "...\n";
                }
                $formatted .= "\n";
            }

            if (count($drugs) > 10) {
                $formatted .= "... and " . (count($drugs) - 10) . " more drug records.\n";
            }

            return $formatted;
        }

        // For other tools, return JSON
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }


    /**
     * Get provider instance
     */
    protected function getProvider(string $providerName): ?AiProviderInterface
    {
        return $this->providers[$providerName] ?? null;
    }

    /**
     * Log usage
     */
    public function logUsage(Agent $agent, array $tokensUsed, ?int $userId = null, ?int $chatId = null): void
    {
        $cost = $this->calculateCost($agent->model_provider, $agent->model_name, $tokensUsed);

        UsageLog::create([
            'user_id' => $userId ?? auth()->id(),
            'suite_id' => $agent->suite_id,
            'agent_id' => $agent->id,
            'chat_id' => $chatId,
            'action' => 'chat',
            'model_provider' => $agent->model_provider,
            'model_name' => $agent->model_name,
            'input_tokens' => $tokensUsed['input'] ?? 0,
            'output_tokens' => $tokensUsed['output'] ?? 0,
            'cost_usd' => $cost,
        ]);
    }

    /**
     * Calculate cost based on model and tokens
     */
    protected function calculateCost(string $provider, string $model, array $tokensUsed): float
    {
        // Pricing per 1M tokens (input/output)
        $pricing = [
            'openai' => [
                'gpt-4' => ['input' => 30.0, 'output' => 60.0],
                'gpt-4-turbo' => ['input' => 10.0, 'output' => 30.0],
                'gpt-3.5-turbo' => ['input' => 0.5, 'output' => 1.5],
            ],
            'gemini' => [
                'gemini-pro' => ['input' => 0.5, 'output' => 1.5],
            ],
            'mistral' => [
                'mistral-large' => ['input' => 8.0, 'output' => 24.0],
            ],
            'claude' => [
                'claude-3-opus' => ['input' => 15.0, 'output' => 75.0],
            ],
        ];

        $modelPricing = $pricing[$provider][$model] ?? ['input' => 1.0, 'output' => 1.0];

        $inputCost = (($tokensUsed['input'] ?? 0) / 1_000_000) * $modelPricing['input'];
        $outputCost = (($tokensUsed['output'] ?? 0) / 1_000_000) * $modelPricing['output'];

        return $inputCost + $outputCost;
    }
}

