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
    public function searchContext(string $query, User $user, ?int $chatId = null, int $limit = 5): array
    {
        // Require chatId to prevent using documents from other chats
        if ($chatId === null) {
            Log::warning('RAG search called without chat_id - returning empty results to prevent cross-chat contamination');
            return [];
        }

        Log::info('RAG searchContext called', [
            'user_id' => $user->id,
            'chat_id' => $chatId,
            'query' => substr($query, 0, 100), // Log first 100 chars
        ]);

        $queryEmbedding = $this->vectorDb->generateEmbedding($query);
        $results = $this->vectorDb->similaritySearch($queryEmbedding, $user->id, $chatId, $limit);

        // Double-check: filter out any results that don't belong to this chat
        $filteredResults = [];
        foreach ($results as $result) {
            $metadata = $result['metadata'] ?? [];
            $resultChatId = $metadata['chat_id'] ?? null;

            // Skip if chat_id doesn't match
            if ($resultChatId != $chatId) {
                Log::warning('RAG result filtered out - wrong chat_id', [
                    'expected_chat_id' => $chatId,
                    'result_chat_id' => $resultChatId,
                    'file_name' => $metadata['file_name'] ?? 'unknown',
                ]);
                continue;
            }

            $filteredResults[] = $result;
        }

        Log::info('RAG searchContext results', [
            'user_id' => $user->id,
            'chat_id' => $chatId,
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
            'chat_id' => $file->chat_id, // Associate embedding with the chat
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

