<?php

namespace App\Services\Search;

use App\Models\ExternalApiConfig;
use App\Services\Encryption\EncryptionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SearchService
{
    protected EncryptionService $encryption;

    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    /**
     * Perform web search
     */
    public function search(string $query, string $provider = 'serper'): array
    {
        $config = ExternalApiConfig::where('provider', $provider)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            throw new \Exception("Search provider '{$provider}' not configured");
        }

        logger("search");
        logger($provider);

        return match ($provider) {
            'serper' => $this->searchSerper($query, $config),
            'bing' => $this->searchBing($query, $config),
            'brave' => $this->searchBrave($query, $config),
            default => throw new \Exception("Unsupported search provider: {$provider}"),
        };
    }

    /**
     * Search using Serper API
     */
    protected function searchSerper(string $query, ExternalApiConfig $config): array
    {
        $apiKey = $this->encryption->decryptApiKey($config->encrypted_api_key);

        $response = Http::withHeaders([
            'X-API-KEY' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://google.serper.dev/search', [
                    'q' => $query,
                    'num' => 10,
                ]);

        if (!$response->successful()) {
            throw new \Exception('Serper API request failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'results' => array_map(function ($result) {
                return [
                    'title' => $result['title'] ?? '',
                    'link' => $result['link'] ?? '',
                    'snippet' => $result['snippet'] ?? '',
                ];
            }, $data['organic'] ?? []),
            'provider' => 'serper',
        ];
    }

    /**
     * Search using Bing API
     */
    protected function searchBing(string $query, ExternalApiConfig $config): array
    {
        $apiKey = $this->encryption->decryptApiKey($config->encrypted_api_key);

        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $apiKey,
        ])->get('https://api.bing.microsoft.com/v7.0/search', [
                    'q' => $query,
                    'count' => 10,
                ]);

        if (!$response->successful()) {
            throw new \Exception('Bing API request failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'results' => array_map(function ($result) {
                return [
                    'title' => $result['name'] ?? '',
                    'link' => $result['url'] ?? '',
                    'snippet' => $result['snippet'] ?? '',
                ];
            }, $data['webPages']['value'] ?? []),
            'provider' => 'bing',
        ];
    }

    /**
     * Search using Brave API
     */
    protected function searchBrave(string $query, ExternalApiConfig $config): array
    {
        $apiKey = $this->encryption->decryptApiKey($config->encrypted_api_key);

        $response = Http::withHeaders([
            'X-Subscription-Token' => $apiKey,
        ])->get('https://api.search.brave.com/res/v1/web/search', [
                    'q' => $query,
                    'count' => 10,
                ]);

        if (!$response->successful()) {
            throw new \Exception('Brave API request failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'results' => array_map(function ($result) {
                return [
                    'title' => $result['title'] ?? '',
                    'link' => $result['url'] ?? '',
                    'snippet' => $result['description'] ?? '',
                ];
            }, $data['web']['results'] ?? []),
            'provider' => 'brave',
        ];
    }
}

