<?php

namespace App\Services\Tool;

use App\Models\ExternalApiConfig;
use Illuminate\Support\Str;

class ToolDefinitionService
{
    /**
     * Generate tool definitions from external API configs
     * Returns array of tool definitions in OpenAI function calling format
     */
    public function generateTools(array $apiConfigIds): array
    {
        $tools = [];

        foreach ($apiConfigIds as $apiConfigId) {
            $config = ExternalApiConfig::where('id', $apiConfigId)
                ->where('is_active', true)
                ->first();

            if (!$config) {
                continue;
            }

            // Generate tools based on provider type
            $providerTools = $this->generateToolsForProvider($config);
            $tools = array_merge($tools, $providerTools);
        }

        return $tools;
    }

    /**
     * Generate tool definitions for a specific provider
     */
    protected function generateToolsForProvider(ExternalApiConfig $config): array
    {
        $provider = strtolower($config->provider);
        $baseName = $this->sanitizeName($config->name ?? $config->provider);

        return match ($provider) {
            'fda', 'openfda' => $this->generateFdaTools($baseName, $config),
            'crunchbase' => $this->generateCrunchbaseTools($baseName, $config),
            'patents', 'google_patents' => $this->generatePatentsTools($baseName, $config),
            'news', 'newsapi' => $this->generateNewsTools($baseName, $config),
            default => $this->generateGenericTools($baseName, $config),
        };
    }

    /**
     * Generate FDA/OpenFDA tools
     */
    protected function generateFdaTools(string $baseName, ExternalApiConfig $config): array
    {
        $tools = [];

        // Tool 1: Search drugs
        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => strtolower($baseName) . '_searchDrug',
                'description' => 'Search FDA drug database by brand name, generic name, or indication. Returns drug information including brand name, generic name, indications, warnings, and dosage.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search term: drug name (brand or generic), indication, or condition (e.g., "aspirin", "diabetes", "hypertension")',
                        ],
                        'year' => [
                            'type' => 'string',
                            'description' => 'Optional: Filter by approval year (e.g., "2023")',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];

        // Tool 2: Get all drugs
        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => strtolower($baseName) . '_getAllDrugs',
                'description' => 'Fetch a list of recent FDA-approved drugs. Use when user asks for "all drugs" or wants to browse available drugs.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Number of drugs to return (default: 20, max: 100)',
                        ],
                    ],
                    'required' => [],
                ],
            ],
        ];

        // Tool 3: Get recall information
        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => strtolower($baseName) . '_getRecallInfo',
                'description' => 'Get FDA recall information for a product, company, or brand name.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product' => [
                            'type' => 'string',
                            'description' => 'Product name, brand name, or company name to search for recalls (e.g., "Tyson chicken", "aspirin", "Johnson & Johnson")',
                        ],
                    ],
                    'required' => ['product'],
                ],
            ],
        ];

        // Tool 4: Search medical devices
        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => strtolower($baseName) . '_searchDevice',
                'description' => 'Search FDA medical device database by device name or classification.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Device name or classification to search for',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];

        return $tools;
    }

    /**
     * Generate Crunchbase tools
     */
    protected function generateCrunchbaseTools(string $baseName, ExternalApiConfig $config): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => strtolower($baseName) . '_searchCompany',
                    'description' => 'Search Crunchbase for company information by name.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Company name to search for',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate Google Patents tools
     */
    protected function generatePatentsTools(string $baseName, ExternalApiConfig $config): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => strtolower($baseName) . '_searchPatent',
                    'description' => 'Search Google Patents database for patents by keyword, inventor, or assignee.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Search term: patent title, keyword, inventor name, or assignee company',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate News API tools
     */
    protected function generateNewsTools(string $baseName, ExternalApiConfig $config): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => strtolower($baseName) . '_searchNews',
                    'description' => 'Search for recent news articles by keyword or topic.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Search term or topic (e.g., "artificial intelligence", "FDA approval")',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate generic tools for unknown providers
     */
    protected function generateGenericTools(string $baseName, ExternalApiConfig $config): array
    {
        $apiType = $config->api_type ?? 'rest';
        $description = $config->config['description'] ?? "Call the {$config->name} API";

        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => strtolower($baseName) . '_call',
                    'description' => $description,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Search query or parameters for the API',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Sanitize name for use in function names
     */
    protected function sanitizeName(string $name): string
    {
        // Convert to lowercase, replace spaces/special chars with underscores
        $name = Str::slug($name, '_');
        // Remove multiple underscores
        $name = preg_replace('/_+/', '_', $name);
        return trim($name, '_');
    }
}

