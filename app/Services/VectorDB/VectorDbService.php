<?php

namespace App\Services\VectorDB;

use App\Models\VectorEmbedding;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VectorDbService
{
    protected string $chromaUrl;
    protected string $collectionName = 'ai_suite_embeddings';

    public function __construct()
    {
        $this->chromaUrl = config('services.chroma.url', 'http://chroma:8000');
    }

    /**
     * Generate embedding using OpenAI
     */
    public function generateEmbedding(string $text): array
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            throw new \Exception('OpenAI API key not configured for embeddings');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/embeddings', [
                    'model' => 'text-embedding-3-small',
                    'input' => $text,
                ]);

        if (!$response->successful()) {
            throw new \Exception('Embedding generation failed: ' . $response->body());
        }

        $data = $response->json();
        return $data['data'][0]['embedding'];
    }

    /**
     * Store embedding in vector DB
     */
    public function storeEmbedding(VectorEmbedding $embedding): void
    {
        $vector = $this->generateEmbedding($embedding->content);

        // Get filename from embedding metadata or file relationship
        $fileName = $embedding->metadata['file_name'] ?? null;
        if (!$fileName && $embedding->file_id) {
            $file = \App\Models\File::find($embedding->file_id);
            $fileName = $file?->original_name ?? 'unknown';
        }

        // Store in Chroma with complete metadata including filename
        $this->storeInChroma($embedding->id, $vector, $embedding->content, [
            'user_id' => $embedding->user_id,
            'chat_id' => $embedding->chat_id, // Include chat_id for filtering
            'file_id' => $embedding->file_id,
            'chunk_index' => $embedding->chunk_index,
            'file_name' => $fileName, // Include filename for proper citation
        ]);

        // Update embedding record
        $embedding->update([
            'embedding' => $vector,
            'vector_id' => (string) $embedding->id,
        ]);
    }

    /**
     * Store in Chroma vector DB (v2 API)
     */
    protected function storeInChroma(int $id, array $vector, string $content, array $metadata): void
    {
        try {
            // Ensure collection exists
            $this->ensureCollection();

            // Use v2 API
            $response = Http::timeout(5)->post("{$this->chromaUrl}/api/v1/collections/{$this->collectionName}/add", [
                'ids' => [(string) $id],
                'embeddings' => [$vector],
                'documents' => [$content],
                'metadatas' => $metadata, // Include chat_id in metadata
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();
                throw new \Exception("Chroma API error (HTTP {$statusCode}): " . ($errorBody ?: 'Empty response'));
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Connection failed - Chroma might not be running
            Log::warning('Chroma connection failed, using database fallback', [
                'error' => $e->getMessage(),
                'chroma_url' => $this->chromaUrl,
            ]);
            // Fallback: store in database only (embeddings are already stored in vector_embeddings table)
        } catch (\Exception $e) {
            Log::warning('Chroma storage failed, using database fallback', [
                'error' => $e->getMessage(),
                'chroma_url' => $this->chromaUrl,
            ]);
            // Fallback: store in database only (embeddings are already stored in vector_embeddings table)
        }
    }

    /**
     * Similarity search (scoped to specific chat if provided)
     */
    public function similaritySearch(array $queryVector, int $userId, ?int $chatId = null, int $limit = 5): array
    {
        // IMPORTANT: If chatId is provided, ONLY return documents from that chat
        // If chatId is null, return empty (no documents should be used)
        if ($chatId === null) {
            // No chat specified - return empty results to prevent cross-chat contamination
            return [];
        }

        try {
            $this->ensureCollection();

            // Build where clause - Chroma query format
            // Try different query formats for compatibility
            $where = [
                'user_id' => $userId,
                'chat_id' => $chatId, // Only search within this chat's documents
            ];

            $response = Http::timeout(5)->post("{$this->chromaUrl}/api/v1/collections/{$this->collectionName}/query", [
                'query_embeddings' => [$queryVector],
                'n_results' => $limit * 2, // Get more results to filter
                'where' => $where,
            ]);

            if (!$response->successful()) {
                // Try with $and syntax if simple format fails
                $whereAnd = [
                    '$and' => [
                        ['user_id' => $userId],
                        ['chat_id' => $chatId],
                    ],
                ];

                $response = Http::timeout(5)->post("{$this->chromaUrl}/api/v1/collections/{$this->collectionName}/query", [
                    'query_embeddings' => [$queryVector],
                    'n_results' => $limit * 2,
                    'where' => $whereAnd,
                ]);

                if (!$response->successful()) {
                    $errorBody = $response->body();
                    $statusCode = $response->status();
                    throw new \Exception("Chroma query failed (HTTP {$statusCode}): " . ($errorBody ?: 'Empty response'));
                }
            }

            $data = $response->json();

            $results = [];
            if (!empty($data['ids'][0])) {
                foreach ($data['ids'][0] as $index => $id) {
                    $metadata = $data['metadatas'][0][$index] ?? [];
                    // Double-check that the result belongs to the correct chat
                    // Also handle cases where chat_id might be null in old embeddings
                    if (isset($metadata['chat_id'])) {
                        if ($metadata['chat_id'] != $chatId) {
                            continue; // Skip results from other chats
                        }
                    } else {
                        // Old embedding without chat_id - skip it to prevent cross-chat contamination
                        continue;
                    }

                    // Ensure filename is in metadata (fallback to database if missing)
                    if (empty($metadata['file_name']) && isset($metadata['file_id'])) {
                        $file = \App\Models\File::find($metadata['file_id']);
                        if ($file) {
                            $metadata['file_name'] = $file->original_name;
                        }
                    }

                    $results[] = [
                        'id' => $id,
                        'content' => $data['documents'][0][$index] ?? '',
                        'score' => $data['distances'][0][$index] ?? 0,
                        'metadata' => $metadata,
                    ];

                    // Stop once we have enough results
                    if (count($results) >= $limit) {
                        break;
                    }
                }
            }

            return $results;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Connection failed - Chroma might not be running, use database fallback
            Log::info('Chroma connection failed, using database fallback for search', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'chat_id' => $chatId,
                'chroma_url' => $this->chromaUrl,
            ]);

            // Fallback: simple text search in database
            return $this->fallbackSearch($queryVector, $userId, $chatId, $limit);
        } catch (\Exception $e) {
            Log::warning('Vector search failed, using database fallback', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'chat_id' => $chatId,
                'chroma_url' => $this->chromaUrl,
            ]);

            // Fallback: simple text search in database
            return $this->fallbackSearch($queryVector, $userId, $chatId, $limit);
        }
    }

    /**
     * Fallback search in database (scoped to specific chat if provided)
     */
    protected function fallbackSearch(array $queryVector, int $userId, ?int $chatId = null, int $limit): array
    {
        // If no chat_id provided, return empty to prevent cross-chat contamination
        if ($chatId === null) {
            Log::warning('Fallback search called without chat_id');
            return [];
        }

        // Simple text-based search as fallback - ONLY for this specific chat
        $query = VectorEmbedding::where('user_id', $userId)
            ->where('chat_id', $chatId) // Strictly filter by chat_id
            ->with('file'); // Eager load file relationship

        $count = $query->count();
        Log::info('Fallback search found embeddings', [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'count' => $count,
        ]);

        return $query->limit($limit)
            ->get()
            ->map(function ($embedding) {
                $metadata = $embedding->metadata ?? [];
                // Ensure filename is in metadata
                if (empty($metadata['file_name']) && $embedding->file) {
                    $metadata['file_name'] = $embedding->file->original_name;
                }
                return [
                    'id' => $embedding->id,
                    'content' => $embedding->content,
                    'score' => 0.5, // Placeholder
                    'metadata' => $metadata,
                ];
            })
            ->toArray();
    }

    /**
     * Ensure Chroma collection exists
     */
    protected function ensureCollection(): void
    {
        try {
            // Check if collection exists first
            $checkResponse = Http::timeout(2)->get("{$this->chromaUrl}/api/v1/collections/{$this->collectionName}");

            // If collection doesn't exist (404), create it
            if ($checkResponse->status() === 404) {
                Http::timeout(5)->post("{$this->chromaUrl}/api/v1/collections", [
                    'name' => $this->collectionName,
                    'metadata' => [],
                ]);
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Chroma not available - will use database fallback
            throw $e;
        } catch (\Exception $e) {
            // Collection might already exist or other error, log but don't fail
            Log::debug('Chroma collection check/create', [
                'error' => $e->getMessage(),
                'collection' => $this->collectionName,
            ]);
        }
    }
}

