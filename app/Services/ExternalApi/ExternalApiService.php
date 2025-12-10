<?php

namespace App\Services\ExternalApi;

use App\Models\ExternalApiConfig;
use App\Services\Encryption\EncryptionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalApiService
{
    protected EncryptionService $encryption;

    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    /**
     * Fetch data from multiple external APIs based on agent configuration
     * $apiConfigs is an array of ExternalApiConfig IDs
     */
    public function fetchData(array $apiConfigs, string $query): array
    {
        $results = [];

        foreach ($apiConfigs as $apiConfigId) {
            try {
                // Look up by ID (apiConfigs contains database IDs, not provider names)
                $config = ExternalApiConfig::where('id', $apiConfigId)
                    ->where('is_active', true)
                    ->first();

                if (!$config) {
                    Log::warning("External API not found or inactive", [
                        'api_config_id' => $apiConfigId,
                    ]);
                    continue;
                }

                // Use the provider field to determine which fetch method to call
                $provider = $config->provider;
                $data = match ($provider) {
                    'crunchbase' => $this->fetchCrunchbase($config, $query),
                    'patents' => $this->fetchGooglePatents($config, $query),
                    'fda' => $this->fetchFda($config, $query),
                    'news' => $this->fetchNewsApi($config, $query),
                    'serper', 'bing', 'brave' => $this->fetchWebSearch($config, $query),
                    default => $this->fetchGenericApi($config, $query),
                };

                if ($data) {
                    $results[] = $data;
                }
            } catch (\Exception $e) {
                Log::error('External API fetch failed', [
                    'api_config_id' => $apiConfigId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Return error status instead of failing silently
                $results[] = [
                    'source' => $config->provider ?? 'unknown',
                    'status' => 'FAILED_OR_EMPTY',
                    'error' => $e->getMessage(),
                    'data' => null,
                ];
            }
        }

        return $results;
    }

    /**
     * Fetch data from Crunchbase API
     */
    protected function fetchCrunchbase(ExternalApiConfig $config, string $query): ?array
    {
        $apiKey = $this->encryption->decryptApiKey($config->encrypted_api_key);

        // Extract company/product name from query (simplified)
        $searchTerm = $this->extractSearchTerm($query);

        // Crunchbase API v4 endpoint
        $response = Http::withHeaders([
            'X-cb-user-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->get('https://api.crunchbase.com/v4/searches/organizations', [
                    'query' => [
                        [
                            'type' => 'name',
                            'query' => [
                                'operator' => 'contains',
                                'value' => $searchTerm,
                            ],
                        ],
                    ],
                    'limit' => 5,
                ]);

        if (!$response->successful()) {
            throw new \Exception('Crunchbase API request failed: ' . $response->body());
        }

        $data = $response->json();
        $entities = $data['entities'] ?? [];

        if (empty($entities)) {
            return [
                'source' => 'crunchbase',
                'status' => 'FAILED_OR_EMPTY',
                'data' => null,
                'url' => null,
            ];
        }

        // Normalize response
        $results = [];
        foreach ($entities as $entity) {
            $properties = $entity['properties'] ?? [];
            $results[] = [
                'name' => $properties['name'] ?? '',
                'description' => $properties['short_description'] ?? '',
                'funding_total' => $properties['funding_total'] ?? null,
                'num_funding_rounds' => $properties['num_funding_rounds'] ?? 0,
                'website' => $properties['website'] ?? null,
                'linkedin' => $properties['linkedin'] ?? null,
            ];
        }

        return [
            'source' => 'crunchbase',
            'status' => 'SUCCESS',
            'data' => $results,
            'url' => 'https://www.crunchbase.com',
        ];
    }

    /**
     * Fetch data from Google Patents API
     */
    protected function fetchGooglePatents(ExternalApiConfig $config, string $query): ?array
    {
        $apiKey = $this->encryption->decryptApiKey($config->encrypted_api_key);

        // Google Patents API (using Custom Search API)
        $response = Http::get('https://www.googleapis.com/customsearch/v1', [
            'key' => $apiKey,
            'cx' => $config->config['search_engine_id'] ?? '',
            'q' => $query . ' site:patents.google.com',
            'num' => 5,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Google Patents API request failed: ' . $response->body());
        }

        $data = $response->json();
        $items = $data['items'] ?? [];

        if (empty($items)) {
            return [
                'source' => 'patents',
                'status' => 'FAILED_OR_EMPTY',
                'data' => null,
                'url' => null,
            ];
        }

        $results = [];
        foreach ($items as $item) {
            $results[] = [
                'title' => $item['title'] ?? '',
                'snippet' => $item['snippet'] ?? '',
                'link' => $item['link'] ?? '',
            ];
        }

        return [
            'source' => 'patents',
            'status' => 'SUCCESS',
            'data' => $results,
            'url' => 'https://patents.google.com',
        ];
    }

    /**
     * Fetch data from FDA API (OpenFDA)
     */
    protected function fetchFda(ExternalApiConfig $config, string $query): ?array
    {
        // Extract search term (returns null if user wants "all")
        $searchTerm = $this->extractSearchTerm($query);

        // Try drug endpoint first (most common use case)
        $endpoints = [
            'drug' => 'https://api.fda.gov/drug/label.json',
            'device' => 'https://api.fda.gov/device/510k.json',
            'device_classification' => 'https://api.fda.gov/device/classification.json',
        ];

        $data = null;
        $endpointUsed = null;

        // Determine if user is asking for drugs or devices
        $isDrugQuery = preg_match('/\b(drugs?|medications?|medicine|pharmaceutical)\b/i', $query);
        $isDeviceQuery = preg_match('/\b(devices?|medical\s+device|equipment)\b/i', $query);

        foreach ($endpoints as $type => $url) {
            try {
                // Skip device endpoints if user is asking for drugs
                if ($isDrugQuery && $type !== 'drug') {
                    continue;
                }

                // Skip drug endpoint if user is asking for devices
                if ($isDeviceQuery && $type === 'drug') {
                    continue;
                }

                // For drugs, search in various fields
                if ($type === 'drug') {
                    // Use smaller limit to avoid timeout (FDA responses can be very large)
                    $params = ['limit' => 20]; // Reduced from 100 to avoid timeout

                    // If searchTerm is null, user wants "all" - fetch without search filter
                    if ($searchTerm === null) {
                        // Fetch drugs without search filter (FDA returns most recent)
                        // Note: Using smaller limit to avoid timeout
                        $response = Http::timeout(15)->get($url, $params);
                    } elseif (!empty($searchTerm)) {
                        // Search for specific drugs matching the term
                        // FDA OpenFDA search syntax: field:value (no quotes, case-insensitive)
                        // Use OR to search multiple fields
                        $searchQuery = "openfda.brand_name:{$searchTerm}+OR+openfda.generic_name:{$searchTerm}+OR+openfda.substance_name:{$searchTerm}";
                        $params['search'] = $searchQuery;
                        $response = Http::timeout(15)->get($url, $params);

                        // If search fails, try simpler search
                        if (!$response->successful() || empty($response->json()['results'] ?? [])) {
                            Log::info("FDA complex search failed, trying simple search", [
                                'search_term' => $searchTerm,
                            ]);
                            $params['search'] = "openfda.brand_name:{$searchTerm}";
                            $response = Http::timeout(15)->get($url, $params);
                        }

                        // If still no results, try generic name
                        if (!$response->successful() || empty($response->json()['results'] ?? [])) {
                            Log::info("FDA brand name search failed, trying generic name", [
                                'search_term' => $searchTerm,
                            ]);
                            $params['search'] = "openfda.generic_name:{$searchTerm}";
                            $response = Http::timeout(15)->get($url, $params);
                        }
                    } else {
                        // Empty search term but not "all" - fetch recent drugs
                        $response = Http::timeout(15)->get($url, $params);
                    }
                } else {
                    // For devices, search device name
                    if ($searchTerm === null) {
                        // Fetch devices without search filter
                        $response = Http::timeout(15)->get($url, ['limit' => 20]);
                    } else {
                        $response = Http::timeout(15)->get($url, [
                            'search' => "device_name:\"$searchTerm\"",
                            'limit' => 10,
                        ]);
                    }
                }

                if ($response->successful()) {
                    $responseData = $response->json();
                    $results = $responseData['results'] ?? [];

                    if (!empty($results)) {
                        $data = $results;
                        $endpointUsed = $type;
                        break; // Found data, stop trying other endpoints
                    }
                } else {
                    Log::warning("FDA API request failed", [
                        'endpoint' => $type,
                        'url' => $url,
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 500), // First 500 chars of error
                    ]);
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::warning("FDA API connection timeout", [
                    'endpoint' => $type,
                    'error' => $e->getMessage(),
                ]);
                // Continue to next endpoint if this one times out
                continue;
            } catch (\Exception $e) {
                Log::warning("FDA API endpoint failed", [
                    'endpoint' => $type,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        if (empty($data)) {
            return [
                'source' => 'fda',
                'status' => 'FAILED_OR_EMPTY',
                'data' => null,
                'url' => null,
                'message' => 'No results found for query: ' . $query,
            ];
        }

        // Normalize response based on endpoint type
        $normalized = [];
        foreach ($data as $result) {
            if ($endpointUsed === 'drug') {
                $openfda = $result['openfda'] ?? [];
                $normalized[] = [
                    'brand_name' => implode(', ', $openfda['brand_name'] ?? []),
                    'generic_name' => implode(', ', $openfda['generic_name'] ?? []),
                    'substance_name' => implode(', ', $openfda['substance_name'] ?? []),
                    'indications_and_usage' => $result['indications_and_usage'] ?? [],
                    'description' => $result['description'] ?? [],
                    'warnings' => $result['warnings'] ?? [],
                    'dosage_and_administration' => $result['dosage_and_administration'] ?? [],
                    'product_ndc' => implode(', ', $openfda['product_ndc'] ?? []),
                ];
            } else {
                // Device data
                $normalized[] = [
                    'device_name' => $result['device_name'] ?? '',
                    'device_class' => $result['device_class'] ?? '',
                    'regulation_number' => $result['regulation_number'] ?? '',
                    'product_code' => $result['product_code'] ?? '',
                ];
            }
        }

        return [
            'source' => 'fda',
            'status' => 'SUCCESS',
            'data' => $normalized,
            'url' => 'https://www.fda.gov',
            'endpoint' => $endpointUsed,
        ];
    }

    /**
     * Fetch data from News API
     */
    protected function fetchNewsApi(ExternalApiConfig $config, string $query): ?array
    {
        $apiKey = $this->encryption->decryptApiKey($config->encrypted_api_key);

        $response = Http::get('https://newsapi.org/v2/everything', [
            'q' => $query,
            'apiKey' => $apiKey,
            'pageSize' => 5,
            'sortBy' => 'relevancy',
        ]);

        if (!$response->successful()) {
            throw new \Exception('News API request failed: ' . $response->body());
        }

        $data = $response->json();
        $articles = $data['articles'] ?? [];

        if (empty($articles)) {
            return [
                'source' => 'news',
                'status' => 'FAILED_OR_EMPTY',
                'data' => null,
                'url' => null,
            ];
        }

        $results = [];
        foreach ($articles as $article) {
            $results[] = [
                'title' => $article['title'] ?? '',
                'description' => $article['description'] ?? '',
                'url' => $article['url'] ?? '',
                'published_at' => $article['publishedAt'] ?? '',
                'source' => $article['source']['name'] ?? '',
            ];
        }

        return [
            'source' => 'news',
            'status' => 'SUCCESS',
            'data' => $results,
            'url' => 'https://newsapi.org',
        ];
    }

    /**
     * Fetch data from web search APIs (Serper, Bing, Brave)
     */
    protected function fetchWebSearch(ExternalApiConfig $config, string $query): ?array
    {
        // Web search APIs are handled by SearchService, not ExternalApiService
        // This is a placeholder in case we want to use them here
        return null;
    }

    /**
     * Fetch data from generic/unknown API using config
     */
    protected function fetchGenericApi(ExternalApiConfig $config, string $query): ?array
    {
        if (empty($config->base_url)) {
            Log::warning('Generic API has no base_url configured', [
                'config_id' => $config->id,
                'provider' => $config->provider,
            ]);
            return null;
        }

        try {
            $apiKey = $config->encrypted_api_key
                ? $this->encryption->decryptApiKey($config->encrypted_api_key)
                : null;

            $headers = [];
            if ($apiKey) {
                // Determine auth method from config
                $authLocation = $config->config['api_key_location'] ?? 'query';
                if ($authLocation === 'header') {
                    $headerName = $config->config['api_key_param'] ?? 'X-API-Key';
                    $headers[$headerName] = $apiKey;
                }
            }

            $params = ['q' => $query];
            if ($apiKey && ($config->config['api_key_location'] ?? 'query') === 'query') {
                $paramName = $config->config['api_key_param'] ?? 'api_key';
                $params[$paramName] = $apiKey;
            }

            $response = Http::withHeaders($headers)->get($config->base_url, $params);

            if (!$response->successful()) {
                throw new \Exception('API request failed: ' . $response->body());
            }

            return [
                'source' => $config->provider,
                'status' => 'SUCCESS',
                'data' => $response->json(),
                'url' => $config->base_url,
            ];
        } catch (\Exception $e) {
            Log::error('Generic API fetch failed', [
                'config_id' => $config->id,
                'provider' => $config->provider,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract search term from query (simplified)
     * Returns null if query is asking for "all" items
     */
    protected function extractSearchTerm(string $query): ?string
    {
        // Check if user is asking for "all" items (no specific search term)
        $isFetchAll = preg_match('/\b(fetch|get|list|show|find|search)\s+(all|every)\s+(drugs|drug|medications|medication|devices|device)\b/i', $query);

        if ($isFetchAll) {
            return null; // Signal that we want all items, not a search
        }

        // Remove common question words and extract key terms
        $query = preg_replace('/\b(what|who|where|when|why|how|is|are|can|could|should|will|would|the|a|an|fetch|get|details|information|about)\b/i', '', $query);
        $query = trim($query);

        // Take first 50 characters as search term
        $term = substr($query, 0, 50) ?: $query;

        // If term is empty or just common words, return null
        if (empty($term) || preg_match('/^(drugs?|medications?|devices?)$/i', $term)) {
            return null;
        }

        return $term;
    }
}





