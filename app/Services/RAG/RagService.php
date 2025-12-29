<?php

namespace App\Services\RAG;

use App\Models\File;
use App\Models\User;
use App\Models\VectorEmbedding;
use App\Services\VectorDB\VectorDbService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RagService
{
    protected VectorDbService $vectorDb;
    protected int $chunkSize = 1000;
    protected int $chunkOverlap = 200;

    public function __construct(VectorDbService $vectorDb)
    {
        $this->vectorDb = $vectorDb;
    }

    /**
     * Process and embed file for RAG
     */
    public function processFile(File $file): void
    {
        try {
            // Extract text
            $content = $this->extractText($file);

            if (empty(trim($content))) {
                throw new \Exception('No text could be extracted from the file');
            }

            // Chunk text
            $chunks = $this->chunkText($content);

            if (empty($chunks)) {
                throw new \Exception('No chunks could be created from the file content');
            }

            // Process chunks
            $processedCount = 0;
            foreach ($chunks as $index => $chunk) {
                try {
                    $embedding = $this->createEmbedding($chunk, $file, $index);
                    $this->vectorDb->storeEmbedding($embedding);
                    $processedCount++;
                } catch (\Exception $e) {
                    Log::warning('Failed to process chunk', [
                        'file_id' => $file->id,
                        'chunk_index' => $index,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with next chunk
                }
            }

            if ($processedCount === 0) {
                throw new \Exception('No chunks could be processed and embedded');
            }

            // Update file status
            $file->update([
                'is_processed' => true,
                'is_embedded' => true,
                'metadata' => array_merge($file->metadata ?? [], [
                    'chunks_processed' => $processedCount,
                    'total_chunks' => count($chunks),
                    'processed_at' => now()->toDateTimeString(),
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error('RAG processing failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update file with error status
            $file->update([
                'metadata' => array_merge($file->metadata ?? [], [
                    'processing_error' => $e->getMessage(),
                    'processing_failed_at' => now()->toDateTimeString(),
                ]),
            ]);

            throw $e;
        }
    }

    /**
     * Search relevant context for query (scoped to specific chat)
     * IMPORTANT: chatId is required - will return empty if not provided to prevent cross-chat contamination
     */
    public function searchContext(string $query, User $user, ?int $chatId = null, ?int $agentId = null, int $limit = 5): array
    {
        // Require chatId or agentId to prevent using documents from other chats/agents
        if ($chatId === null && $agentId === null) {
            Log::warning('RAG search called without chat_id or agent_id - returning empty results to prevent cross-chat contamination');
            return [];
        }

        Log::info('RAG searchContext called', [
            'user_id' => $user->id,
            'chat_id' => $chatId,
            'agent_id' => $agentId,
            'query' => substr($query, 0, 100), // Log first 100 chars
        ]);

        // Check if query is generic (summarize, what's in, etc.)
        $isGenericQuery = $this->isGenericQuery($query);

        $queryEmbedding = $this->vectorDb->generateEmbedding($query);
        $results = $this->vectorDb->similaritySearch($queryEmbedding, $user->id, $chatId, $agentId, $limit);

        // Double-check: filter out any results that don't belong to this chat or agent
        // Also filter out results from soft-deleted files
        $filteredResults = [];
        foreach ($results as $result) {
            $metadata = $result['metadata'] ?? [];
            $resultChatId = $metadata['chat_id'] ?? null;
            $resultAgentId = $metadata['agent_id'] ?? null;
            $fileId = $metadata['file_id'] ?? null;

            // Check if file exists and is not soft-deleted
            if ($fileId) {
                $file = \App\Models\File::find($fileId);
                if (!$file || $file->trashed()) {
                    Log::info('RAG result filtered out - file is soft-deleted', [
                        'file_id' => $fileId,
                        'file_name' => $metadata['file_name'] ?? 'unknown',
                    ]);
                    continue;
                }
            }

            // Accept if it matches chat_id (user-uploaded) OR agent_id (admin-uploaded)
            $isValid = false;
            if ($chatId && $resultChatId == $chatId) {
                $isValid = true; // User-uploaded file for this chat
            } elseif ($agentId && $resultAgentId == $agentId) {
                $isValid = true; // Admin-uploaded file for this agent
            }

            if (!$isValid) {
                Log::warning('RAG result filtered out - wrong chat_id or agent_id', [
                    'expected_chat_id' => $chatId,
                    'expected_agent_id' => $agentId,
                    'result_chat_id' => $resultChatId,
                    'result_agent_id' => $resultAgentId,
                    'file_name' => $metadata['file_name'] ?? 'unknown',
                ]);
                continue;
            }

            $filteredResults[] = $result;
        }

        // If no results found, try fallback strategies:
        // 1. For generic queries: get sample chunks
        // 2. For specific queries: try broader search or get more chunks
        if (empty($filteredResults)) {
            // Check if files exist for this chat/agent
            $hasFiles = $this->hasFilesForChatOrAgent($user->id, $chatId, $agentId);

            if ($hasFiles) {
                if ($isGenericQuery) {
                    // For generic queries, get sample chunks
                    Log::info('Generic query with no similarity results, fetching sample chunks', [
                        'user_id' => $user->id,
                        'chat_id' => $chatId,
                        'agent_id' => $agentId,
                    ]);
                    $sampleResults = $this->getSampleChunks($user->id, $chatId, $agentId, $limit);
                    if (!empty($sampleResults)) {
                        $filteredResults = $sampleResults;
                    }
                } else {
                    // For specific queries, try to get more chunks with lower threshold
                    // or get a broader sample to ensure we have content to search
                    Log::info('Specific query with no similarity results, trying broader search', [
                        'user_id' => $user->id,
                        'chat_id' => $chatId,
                        'agent_id' => $agentId,
                        'query' => substr($query, 0, 50),
                    ]);

                    // Try with a larger limit to get more results
                    $broaderResults = $this->vectorDb->similaritySearch($queryEmbedding, $user->id, $chatId, $agentId, $limit * 3);

                    // Filter the broader results
                    foreach ($broaderResults as $result) {
                        $metadata = $result['metadata'] ?? [];
                        $resultChatId = $metadata['chat_id'] ?? null;
                        $resultAgentId = $metadata['agent_id'] ?? null;
                        $fileId = $metadata['file_id'] ?? null;

                        // Check if file exists and is not soft-deleted
                        if ($fileId) {
                            $file = \App\Models\File::find($fileId);
                            if (!$file || $file->trashed()) {
                                continue;
                            }
                        }

                        // Accept if it matches chat_id OR agent_id
                        $isValid = false;
                        if ($chatId && $resultChatId == $chatId) {
                            $isValid = true;
                        } elseif ($agentId && $resultAgentId == $agentId) {
                            $isValid = true;
                        }

                        if ($isValid) {
                            $filteredResults[] = $result;
                            if (count($filteredResults) >= $limit) {
                                break;
                            }
                        }
                    }

                    // If still no results, get sample chunks as last resort
                    if (empty($filteredResults)) {
                        Log::info('Broader search also failed, fetching sample chunks as fallback', [
                            'user_id' => $user->id,
                            'chat_id' => $chatId,
                            'agent_id' => $agentId,
                        ]);
                        $sampleResults = $this->getSampleChunks($user->id, $chatId, $agentId, $limit * 2);
                        if (!empty($sampleResults)) {
                            $filteredResults = $sampleResults;
                        }
                    }
                }
            }
        }

        Log::info('RAG searchContext results', [
            'user_id' => $user->id,
            'chat_id' => $chatId,
            'agent_id' => $agentId,
            'results_count' => count($filteredResults),
            'file_names' => array_map(function ($r) {
                return $r['metadata']['file_name'] ?? 'unknown';
            }, $filteredResults),
        ]);

        return array_map(function ($result) {
            return [
                'content' => $result['content'],
                'score' => $result['score'],
                'metadata' => $result['metadata'] ?? [],
            ];
        }, $filteredResults);
    }

    /**
     * Check if query is generic (summarize, what's in document, etc.)
     */
    protected function isGenericQuery(string $query): bool
    {
        $genericPatterns = [
            '/\bsummarize\b/i',
            '/\bsummary\b/i',
            '/\bwhat\s+(is|are|does|do)\s+in\b/i',
            '/\bwhat\s+(is|are)\s+the\s+doc/i',
            '/\btell\s+me\s+about\b/i',
            '/\bwhat\s+does\s+it\s+say\b/i',
            '/\bwhat\s+is\s+the\s+content\b/i',
            '/\bwhat\s+is\s+this\s+document\b/i',
            '/\bwhat\s+is\s+in\s+the\s+doc/i',
            '/\boverview\b/i',
            '/\bdescribe\b/i',
        ];

        foreach ($genericPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if files exist for this chat or agent
     */
    protected function hasFilesForChatOrAgent(int $userId, ?int $chatId, ?int $agentId): bool
    {
        $fileQuery = \App\Models\File::query()
            ->whereNull('deleted_at')
            ->where('is_processed', true)
            ->where('is_embedded', true);

        if ($chatId) {
            $fileQuery->where(function ($q) use ($userId, $chatId) {
                $q->where('chat_id', $chatId)->where('user_id', $userId);
            });
        }
        if ($agentId) {
            if ($chatId) {
                $fileQuery->orWhere('agent_id', $agentId);
            } else {
                $fileQuery->where('agent_id', $agentId);
            }
        }

        return $fileQuery->exists();
    }

    /**
     * Get sample chunks from available files when similarity search fails
     */
    protected function getSampleChunks(int $userId, ?int $chatId, ?int $agentId, int $limit): array
    {
        try {
            // Get files for this chat/agent
            $fileQuery = \App\Models\File::query()
                ->whereNull('deleted_at')
                ->where('is_processed', true)
                ->where('is_embedded', true);

            if ($chatId && $agentId) {
                $fileQuery->where(function ($q) use ($userId, $chatId, $agentId) {
                    $q->where(function ($subQ) use ($userId, $chatId) {
                        $subQ->where('chat_id', $chatId)->where('user_id', $userId);
                    })->orWhere('agent_id', $agentId);
                });
            } elseif ($chatId) {
                $fileQuery->where('chat_id', $chatId)->where('user_id', $userId);
            } elseif ($agentId) {
                $fileQuery->where('agent_id', $agentId);
            }

            $files = $fileQuery->get();

            if ($files->isEmpty()) {
                return [];
            }

            // Get sample chunks from these files (first few chunks from each file)
            $chunksPerFile = max(1, (int) ceil($limit / $files->count()));
            $allChunks = [];

            foreach ($files as $file) {
                $chunks = \App\Models\VectorEmbedding::where('file_id', $file->id)
                    ->where(function ($q) use ($chatId, $agentId) {
                        if ($chatId) {
                            $q->where('chat_id', $chatId);
                        }
                        if ($agentId) {
                            $q->orWhere('agent_id', $agentId);
                        }
                    })
                    ->orderBy('chunk_index', 'asc')
                    ->limit($chunksPerFile)
                    ->get();

                foreach ($chunks as $chunk) {
                    $allChunks[] = [
                        'id' => (string) $chunk->id,
                        'content' => $chunk->content,
                        'score' => 0.5, // Neutral score for sample chunks
                        'metadata' => array_merge($chunk->metadata ?? [], [
                            'file_id' => $chunk->file_id,
                            'chat_id' => $chunk->chat_id,
                            'agent_id' => $chunk->agent_id,
                            'file_name' => $file->original_name,
                            'chunk_index' => $chunk->chunk_index,
                        ]),
                    ];
                }
            }

            // Limit to requested number
            return array_slice($allChunks, 0, $limit);
        } catch (\Exception $e) {
            Log::warning('Failed to get sample chunks for generic query', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'chat_id' => $chatId,
                'agent_id' => $agentId,
            ]);
            return [];
        }
    }

    /**
     * Extract text from file
     */
    protected function extractText(File $file): string
    {
        $path = storage_path('app/' . $file->path);

        return match ($file->type) {
            'pdf' => $this->extractPdfText($path),
            'docx' => $this->extractDocxText($path),
            'txt' => file_get_contents($path),
            'csv' => $this->extractCsvText($path),
            'image' => $this->extractImageText($path), // OCR for images
            default => '',
        };
    }

    /**
     * Extract text from PDF
     */
    protected function extractPdfText(string $path): string
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new \Exception('PDF parser library not installed. Run: composer require smalot/pdfparser');
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();

            return $text ?: '';
        } catch (\Exception $e) {
            Log::error('PDF extraction failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to extract text from PDF: ' . $e->getMessage());
        }
    }

    /**
     * Extract text from DOCX
     */
    protected function extractDocxText(string $path): string
    {
        if (!class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
            throw new \Exception('PhpWord library not installed. Run: composer require phpoffice/phpword');
        }

        try {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
            $sections = $phpWord->getSections();
            $text = '';

            foreach ($sections as $section) {
                $elements = $section->getElements();
                foreach ($elements as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                        foreach ($element->getElements() as $textElement) {
                            if (method_exists($textElement, 'getText')) {
                                $text .= $textElement->getText();
                            }
                        }
                        $text .= "\n";
                    }
                }
            }

            return trim($text);
        } catch (\Exception $e) {
            Log::error('DOCX extraction failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to extract text from DOCX: ' . $e->getMessage());
        }
    }

    /**
     * Extract text from CSV
     */
    protected function extractCsvText(string $path): string
    {
        try {
            $content = file_get_contents($path);
            $lines = str_getcsv($content, "\n");
            return implode("\n", $lines);
        } catch (\Exception $e) {
            Log::error('CSV extraction failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Extract text from image using OCR (placeholder - requires OCR library)
     */
    protected function extractImageText(string $path): string
    {
        // TODO: Implement OCR using a library like tesseract-ocr or Google Vision API
        // For now, return placeholder
        Log::warning('Image OCR not yet implemented', ['path' => $path]);
        return 'Image OCR not yet implemented. Please provide text-based documents (PDF, DOCX, TXT) for RAG processing.';
    }

    /**
     * Chunk text into smaller pieces
     */
    protected function chunkText(string $text): array
    {
        $chunks = [];
        $words = explode(' ', $text);
        $currentChunk = [];
        $currentLength = 0;

        foreach ($words as $word) {
            $wordLength = strlen($word) + 1; // +1 for space

            if ($currentLength + $wordLength > $this->chunkSize && !empty($currentChunk)) {
                $chunks[] = implode(' ', $currentChunk);
                $currentChunk = array_slice($currentChunk, -$this->chunkOverlap);
                $currentLength = array_sum(array_map('strlen', $currentChunk));
            }

            $currentChunk[] = $word;
            $currentLength += $wordLength;
        }

        if (!empty($currentChunk)) {
            $chunks[] = implode(' ', $currentChunk);
        }

        return $chunks;
    }

    /**
     * Create embedding record
     */
    protected function createEmbedding(string $content, File $file, int $chunkIndex): VectorEmbedding
    {
        $hash = hash('sha256', $content);

        $embedding = VectorEmbedding::create([
            'file_id' => $file->id,
            'chat_id' => $file->chat_id, // Associate embedding with the chat (user-uploaded files)
            'agent_id' => $file->agent_id, // Associate embedding with the agent (admin-uploaded files)
            'user_id' => $file->user_id,
            'content' => $content,
            'content_hash' => $hash,
            'chunk_index' => $chunkIndex,
            'metadata' => [
                'file_name' => $file->original_name,
                'file_type' => $file->type,
            ],
        ]);

        return $embedding;
    }
}

