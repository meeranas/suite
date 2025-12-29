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

        // Store in Chroma with complete metadata including filename, chat_id, and agent_id
        $this->storeInChroma($embedding->id, $vector, $embedding->content, [
            'user_id' => $embedding->user_id,
            'chat_id' => $embedding->chat_id, // Include chat_id for filtering (user-uploaded files)
            'agent_id' => $embedding->agent_id, // Include agent_id for filtering (admin-uploaded files)
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

            // Try v1 API first (v1 is deprecated but still works)
            $response = Http::timeout(5)->post("{$this->chromaUrl}/api/v1/collections/{$this->collectionName}/add", [
                'ids' => [(string) $id],
                'embeddings' => [$vector],
                'documents' => [$content],
                'metadatas' => [$metadata], // Include chat_id and agent_id in metadata
            ]);

            // If v1 fails with deprecation (405), try v2 API
            if (!$response->successful() && ($response->status() === 405 || str_contains($response->body() ?? '', 'deprecated'))) {
                Log::info('Chroma v1 API deprecated, trying v2 API', [
                    'collection' => $this->collectionName,
                    'v1_status' => $response->status(),
                ]);
                // Try v2 API
                $response = Http::timeout(5)->post("{$this->chromaUrl}/api/v2/collections/{$this->collectionName}/add", [
                    'ids' => [(string) $id],
                    'embeddings' => [$vector],
                    'documents' => [$content],
                    'metadatas' => [$metadata],
                ]);
            }

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
     * Delete embeddings from Chroma for a specific file
     */
    public function deleteEmbeddingsForFile(int $fileId): void
    {
        try {
            $this->ensureCollection();

            // Get all embedding IDs for this file from the database
            $embeddings = \App\Models\VectorEmbedding::where('file_id', $fileId)->get();

            if ($embeddings->isEmpty()) {
                return; // No embeddings to delete
            }

            $embeddingIds = $embeddings->pluck('id')->map(fn($id) => (string) $id)->toArray();

            // Delete from Chroma using v1 API
            $response = Http::timeout(5)->post("{$this->chromaUrl}/api/v1/collections/{$this->collectionName}/delete", [
                'ids' => $embeddingIds,
            ]);

            // If v1 fails, try v2 API
            if (!$response->successful() && ($response->status() === 405 || str_contains($response->body() ?? '', 'deprecated'))) {
                $response = Http::timeout(5)->post("{$this->chromaUrl}/api/v2/collections/{$this->collectionName}/delete", [
                    'ids' => $embeddingIds,
                ]);
            }

            if (!$response->successful()) {
                Log::warning('Failed to delete embeddings from Chroma', [
                    'file_id' => $fileId,
                    'embedding_count' => count($embeddingIds),
                    'error' => $response->body(),
                ]);
            } else {
                Log::info('Deleted embeddings from Chroma', [
                    'file_id' => $fileId,
                    'embedding_count' => count($embeddingIds),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error deleting embeddings from Chroma', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - allow file deletion to continue even if Chroma deletion fails
        }
    }

    /**
     * Similarity search (scoped to specific chat and/or agent)
     * Searches both user-uploaded files (chat-scoped) and admin-uploaded files (agent-scoped)
     */
    public function similaritySearch(array $queryVector, int $userId, ?int $chatId = null, ?int $agentId = null, int $limit = 5): array
    {
        // IMPORTANT: If neither chatId nor agentId is provided, return empty
        if ($chatId === null && $agentId === null) {
            // No chat or agent specified - return empty results to prevent cross-chat contamination
            return [];
        }

        try {
            $this->ensureCollection();

            // Build where clause - search for files that match chat_id OR agent_id
            // Use $or to search both user-uploaded (chat) and admin-uploaded (agent) files
            $where = [
                '$or' => [],
            ];

            if ($chatId !== null) {
                $where['$or'][] = [
                    '$and' => [
                        ['user_id' => $userId],
                        ['chat_id' => $chatId],
                    ],
                ];
            }

            if ($agentId !== null) {
                // For agent files, don't filter by user_id since they're admin-uploaded
                // and should be accessible to all users using that agent
                $where['$or'][] = ['agent_id' => $agentId];
            }

            // If only one condition, simplify
            if (count($where['$or']) === 1) {
                $where = $where['$or'][0];
            }

            // Try v1 API first (v1 is deprecated but still works)
            $response = Http::timeout(5)->post("{$this->chromaUrl}/api/v1/collections/{$this->collectionName}/query", [
                'query_embeddings' => [$queryVector],
                'n_results' => $limit * 2, // Get more results to filter
                'where' => $where,
            ]);

            // If v1 fails with deprecation (405), try v2 API
            if (!$response->successful() && ($response->status() === 405 || str_contains($response->body() ?? '', 'deprecated'))) {
                Log::info('Chroma v1 query deprecated, trying v2 API');
                $response = Http::timeout(5)->post("{$this->chromaUrl}/api/v2/collections/{$this->collectionName}/query", [
                    'query_embeddings' => [$queryVector],
                    'n_results' => $limit * 2,
                    'where' => $where,
                ]);
            }

            if (!$response->successful()) {
                // Try with $and syntax if simple format fails
                $whereAnd = [
                    '$or' => [],
                ];

                if ($chatId !== null) {
                    $whereAnd['$or'][] = [
                        '$and' => [
                            ['user_id' => $userId],
                            ['chat_id' => $chatId],
                        ],
                    ];
                }

                if ($agentId !== null) {
                    $whereAnd['$or'][] = ['agent_id' => $agentId];
                }

                // Try alternative query format with $and syntax (v1 API)
                $response = Http::timeout(5)->post("{$this->chromaUrl}/api/v1/collections/{$this->collectionName}/query", [
                    'query_embeddings' => [$queryVector],
                    'n_results' => $limit * 2,
                    'where' => $whereAnd,
                ]);

                // If v1 fails with deprecation, try v2
                if (!$response->successful() && ($response->status() === 405 || str_contains($response->body() ?? '', 'deprecated'))) {
                    $response = Http::timeout(5)->post("{$this->chromaUrl}/api/v2/collections/{$this->collectionName}/query", [
                        'query_embeddings' => [$queryVector],
                        'n_results' => $limit * 2,
                        'where' => $whereAnd,
                    ]);
                }

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
                    // Double-check that the result belongs to the correct chat or agent
                    $resultChatId = $metadata['chat_id'] ?? null;
                    $resultAgentId = $metadata['agent_id'] ?? null;

                    $isValid = false;
                    if ($chatId && $resultChatId == $chatId) {
                        $isValid = true; // User-uploaded file for this chat
                    } elseif ($agentId && $resultAgentId == $agentId) {
                        $isValid = true; // Admin-uploaded file for this agent
                    }

                    if (!$isValid) {
                        continue; // Skip results that don't match
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
            return $this->fallbackSearch($queryVector, $userId, $chatId, $agentId, $limit);
        } catch (\Exception $e) {
            Log::warning('Vector search failed, using database fallback', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'chat_id' => $chatId,
                'agent_id' => $agentId,
                'chroma_url' => $this->chromaUrl,
            ]);

            // Fallback: simple text search in database
            return $this->fallbackSearch($queryVector, $userId, $chatId, $agentId, $limit);
        }
    }

    /**
     * Fallback search in database (scoped to specific chat and/or agent)
     */
    protected function fallbackSearch(array $queryVector, int $userId, ?int $chatId = null, ?int $agentId = null, int $limit): array
    {
        // If neither chat_id nor agent_id provided, return empty to prevent cross-chat contamination
        if ($chatId === null && $agentId === null) {
            Log::warning('Fallback search called without chat_id or agent_id');
            return [];
        }

        // Simple text-based search as fallback - search for chat_id OR agent_id
        $query = VectorEmbedding::with('file'); // Eager load file relationship

        // Search for user-uploaded files (chat_id) OR admin-uploaded files (agent_id)
        if ($chatId !== null && $agentId !== null) {
            $query->where(function ($q) use ($userId, $chatId, $agentId) {
                $q->where(function ($subQ) use ($userId, $chatId) {
                    $subQ->where('user_id', $userId)
                        ->where('chat_id', $chatId);
                })->orWhere('agent_id', $agentId);
            });
        } elseif ($chatId !== null) {
            $query->where('user_id', $userId)
                ->where('chat_id', $chatId);
        } elseif ($agentId !== null) {
            // For agent files, don't filter by user_id since they're admin-uploaded
            $query->where('agent_id', $agentId);
        }

        $count = $query->count();
        Log::info('Fallback search found embeddings', [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'agent_id' => $agentId,
            'count' => $count,
        ]);

        // Get all matching embeddings and calculate cosine similarity
        $embeddings = $query->get();

        // Calculate cosine similarity for each embedding
        $resultsWithScores = $embeddings->map(function ($embedding) use ($queryVector) {
            $storedEmbedding = $embedding->embedding;

            // Calculate cosine similarity if embedding exists
            $similarity = 0.0;
            if (!empty($storedEmbedding) && is_array($storedEmbedding) && count($storedEmbedding) === count($queryVector)) {
                $similarity = $this->cosineSimilarity($queryVector, $storedEmbedding);
            }

            $metadata = $embedding->metadata ?? [];
            // Ensure filename is in metadata
            if (empty($metadata['file_name']) && $embedding->file) {
                $metadata['file_name'] = $embedding->file->original_name;
            }
            // Include chat_id and agent_id from database columns in metadata
            // This is needed for filtering in RagService
            if ($embedding->chat_id !== null) {
                $metadata['chat_id'] = $embedding->chat_id;
            }
            if ($embedding->agent_id !== null) {
                $metadata['agent_id'] = $embedding->agent_id;
            }
            // Also include file_id and user_id for consistency
            if ($embedding->file_id !== null) {
                $metadata['file_id'] = $embedding->file_id;
            }
            if ($embedding->user_id !== null) {
                $metadata['user_id'] = $embedding->user_id;
            }

            return [
                'id' => $embedding->id,
                'content' => $embedding->content,
                'score' => $similarity,
                'metadata' => $metadata,
            ];
        })
            ->sortByDesc('score') // Sort by similarity score (highest first)
            ->take($limit) // Take top N results
            ->values() // Reset array keys
            ->toArray();

        Log::info('Fallback search calculated similarities', [
            'total_embeddings' => $embeddings->count(),
            'results_returned' => count($resultsWithScores),
            'top_score' => !empty($resultsWithScores) ? $resultsWithScores[0]['score'] : 0,
        ]);

        return $resultsWithScores;
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    protected function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        if (count($vectorA) !== count($vectorB)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $normA += $vectorA[$i] * $vectorA[$i];
            $normB += $vectorB[$i] * $vectorB[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Ensure Chroma collection exists
     */
    protected function ensureCollection(): void
    {
        try {
            // Try v1 API first (v2 requires CRN format which is more complex)
            // v1 is deprecated but still works for basic operations
            $checkResponse = Http::timeout(2)->get("{$this->chromaUrl}/api/v1/collections/{$this->collectionName}");

            // If v1 returns deprecation error, try v2
            if ($checkResponse->status() === 400 || ($checkResponse->status() === 405 && str_contains($checkResponse->body() ?? '', 'deprecated'))) {
                Log::info('Chroma v1 API deprecated, trying v2 API');
                $checkResponse = Http::timeout(2)->get("{$this->chromaUrl}/api/v2/collections/{$this->collectionName}");
            }

            // If collection doesn't exist (404), create it
            if ($checkResponse->status() === 404) {
                // Try v1 first
                $createResponse = Http::timeout(5)->post("{$this->chromaUrl}/api/v1/collections", [
                    'name' => $this->collectionName,
                    'metadata' => [],
                ]);

                // If v1 fails with deprecation, try v2
                if (!$createResponse->successful() && ($createResponse->status() === 405 || str_contains($createResponse->body() ?? '', 'deprecated'))) {
                    Log::info('Chroma v1 collection create deprecated, trying v2 API');
                    $createResponse = Http::timeout(5)->post("{$this->chromaUrl}/api/v2/collections", [
                        'name' => $this->collectionName,
                        'metadata' => [],
                    ]);
                }
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

