<?php

namespace App\Services\Tool;

use App\Models\ExternalApiConfig;
use App\Services\ExternalApi\ExternalApiService;
use Illuminate\Support\Facades\Log;

class ToolExecutionService
{
    protected ExternalApiService $externalApiService;

    public function __construct(ExternalApiService $externalApiService)
    {
        $this->externalApiService = $externalApiService;
    }

    /**
     * Execute a tool call
     * Returns the result data that can be passed back to the AI
     */
    public function executeTool(string $toolName, array $arguments, array $availableApiConfigs): array
    {
        // Parse tool name to extract provider and method
        // Format: {provider}_{method} (e.g., "fda_searchDrug", "openfda_getRecallInfo")
        $parts = explode('_', $toolName, 2);

        if (count($parts) < 2) {
            \Illuminate\Support\Facades\Log::error('Tool name parsing failed', [
                'tool_name' => $toolName,
                'parts' => $parts,
            ]);
            return [
                'error' => "Invalid tool name format: {$toolName}",
                'data' => null,
            ];
        }

        $providerBase = $parts[0]; // e.g., "fda", "openfda"
        $method = $parts[1]; // e.g., "searchDrug", "getAllDrugs"

        \Illuminate\Support\Facades\Log::info('Executing tool', [
            'tool_name' => $toolName,
            'provider_base' => $providerBase,
            'method' => $method,
            'arguments' => $arguments,
            'available_configs' => $availableApiConfigs,
        ]);

        // Find matching API config
        $apiConfig = $this->findApiConfig($providerBase, $availableApiConfigs);

        if (!$apiConfig) {
            \Illuminate\Support\Facades\Log::error('API config not found', [
                'provider_base' => $providerBase,
                'available_configs' => $availableApiConfigs,
            ]);
            return [
                'error' => "API configuration not found for provider: {$providerBase}. Available configs: " . implode(', ', $availableApiConfigs),
                'data' => null,
            ];
        }

        \Illuminate\Support\Facades\Log::info('API config found', [
            'config_id' => $apiConfig->id,
            'config_name' => $apiConfig->name,
            'config_provider' => $apiConfig->provider,
        ]);

        // Execute the specific tool method
        try {
            $result = $this->executeToolMethod($apiConfig, $method, $arguments);
            return [
                'tool' => $toolName,
                'data' => $result,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Tool execution failed', [
                'tool' => $toolName,
                'arguments' => $arguments,
                'error' => $e->getMessage(),
            ]);

            return [
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Find API config by provider name
     */
    protected function findApiConfig(string $providerBase, array $availableApiConfigIds): ?ExternalApiConfig
    {
        // Normalize provider base for matching
        $providerBaseLower = strtolower($providerBase);

        // Map common variations
        $providerMap = [
            'openfda' => 'fda',
            'fda' => 'fda',
        ];

        $normalizedProvider = $providerMap[$providerBaseLower] ?? $providerBaseLower;

        // Try exact match first
        $config = ExternalApiConfig::whereIn('id', $availableApiConfigIds)
            ->where('is_active', true)
            ->where(function ($query) use ($providerBase, $providerBaseLower, $normalizedProvider) {
                $query->where('provider', $providerBase)
                    ->orWhere('provider', $providerBaseLower)
                    ->orWhere('provider', $normalizedProvider)
                    ->orWhere('provider', ucfirst($providerBase))
                    ->orWhereRaw('LOWER(provider) = ?', [$providerBaseLower])
                    ->orWhereRaw('LOWER(provider) = ?', [$normalizedProvider])
                    ->orWhere('name', 'like', "%{$providerBase}%")
                    ->orWhereRaw('LOWER(name) LIKE ?', ["%{$providerBaseLower}%"]);
            })
            ->first();

        return $config;
    }

    /**
     * Execute a specific tool method
     */
    protected function executeToolMethod(ExternalApiConfig $config, string $method, array $arguments): array
    {
        $provider = strtolower($config->provider);

        // Handle FDA/OpenFDA tools
        if (in_array($provider, ['fda', 'openfda'])) {
            return $this->executeFdaTool($config, $method, $arguments);
        }

        // Handle other providers
        return match ($provider) {
            'crunchbase' => $this->executeCrunchbaseTool($config, $method, $arguments),
            'patents', 'google_patents' => $this->executePatentsTool($config, $method, $arguments),
            'news', 'newsapi' => $this->executeNewsTool($config, $method, $arguments),
            default => $this->executeGenericTool($config, $method, $arguments),
        };
    }

    /**
     * Execute FDA tool methods
     */
    protected function executeFdaTool(ExternalApiConfig $config, string $method, array $arguments): array
    {
        switch ($method) {
            case 'searchDrug':
                $query = $arguments['query'] ?? '';
                // Build query string for FDA API
                $fdaQuery = $query;
                if (isset($arguments['year'])) {
                    $fdaQuery .= " approved in {$arguments['year']}";
                }
                $results = $this->externalApiService->fetchData([$config->id], $fdaQuery);
                return $results[0] ?? ['status' => 'FAILED_OR_EMPTY', 'data' => null];

            case 'getAllDrugs':
                $limit = $arguments['limit'] ?? 20;
                $results = $this->externalApiService->fetchData([$config->id], "fetch all drugs limit {$limit}");
                return $results[0] ?? ['status' => 'FAILED_OR_EMPTY', 'data' => null];

            case 'getRecallInfo':
                $product = $arguments['product'] ?? '';
                // FDA recall endpoint would need to be implemented
                // For now, use search with recall context
                $results = $this->externalApiService->fetchData([$config->id], "recall {$product}");
                return $results[0] ?? ['status' => 'FAILED_OR_EMPTY', 'data' => null];

            case 'searchDevice':
                $query = $arguments['query'] ?? '';
                $results = $this->externalApiService->fetchData([$config->id], "device {$query}");
                return $results[0] ?? ['status' => 'FAILED_OR_EMPTY', 'data' => null];

            default:
                throw new \Exception("Unknown FDA tool method: {$method}");
        }
    }

    /**
     * Execute Crunchbase tool methods
     */
    protected function executeCrunchbaseTool(ExternalApiConfig $config, string $method, array $arguments): array
    {
        switch ($method) {
            case 'searchCompany':
                $query = $arguments['query'] ?? '';
                $results = $this->externalApiService->fetchData([$config->id], $query);
                return $results[0] ?? ['status' => 'FAILED_OR_EMPTY', 'data' => null];

            default:
                throw new \Exception("Unknown Crunchbase tool method: {$method}");
        }
    }

    /**
     * Execute Patents tool methods
     */
    protected function executePatentsTool(ExternalApiConfig $config, string $method, array $arguments): array
    {
        switch ($method) {
            case 'searchPatent':
                $query = $arguments['query'] ?? '';
                $results = $this->externalApiService->fetchData([$config->id], $query);
                return $results[0] ?? ['status' => 'FAILED_OR_EMPTY', 'data' => null];

            default:
                throw new \Exception("Unknown Patents tool method: {$method}");
        }
    }

    /**
     * Execute News tool methods
     */
    protected function executeNewsTool(ExternalApiConfig $config, string $method, array $arguments): array
    {
        switch ($method) {
            case 'searchNews':
                $query = $arguments['query'] ?? '';
                $results = $this->externalApiService->fetchData([$config->id], $query);
                return $results[0] ?? ['status' => 'FAILED_OR_EMPTY', 'data' => null];

            default:
                throw new \Exception("Unknown News tool method: {$method}");
        }
    }

    /**
     * Execute generic tool methods
     */
    protected function executeGenericTool(ExternalApiConfig $config, string $method, array $arguments): array
    {
        switch ($method) {
            case 'call':
                $query = $arguments['query'] ?? '';
                $results = $this->externalApiService->fetchData([$config->id], $query);
                return $results[0] ?? ['status' => 'FAILED_OR_EMPTY', 'data' => null];

            default:
                throw new \Exception("Unknown generic tool method: {$method}");
        }
    }
}

