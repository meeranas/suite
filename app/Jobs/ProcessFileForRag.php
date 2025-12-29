<?php

namespace App\Jobs;

use App\Models\File;
use App\Services\RAG\RagService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessFileForRag implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public File $file)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(RagService $ragService): void
    {
        try {
            Log::info('Processing file for RAG', [
                'file_id' => $this->file->id,
                'agent_id' => $this->file->agent_id,
                'chat_id' => $this->file->chat_id,
            ]);

            // Process file: extract text, chunk, create embeddings, store in ChromaDB
            $ragService->processFile($this->file);

            Log::info('File processed successfully', [
                'file_id' => $this->file->id,
                'is_processed' => $this->file->fresh()->is_processed,
                'is_embedded' => $this->file->fresh()->is_embedded,
            ]);
        } catch (\Exception $e) {
            Log::error('File processing job failed', [
                'file_id' => $this->file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update file status to show processing failed
            $this->file->update([
                'metadata' => array_merge($this->file->metadata ?? [], [
                    'processing_error' => $e->getMessage(),
                    'processing_failed_at' => now()->toDateTimeString(),
                ]),
            ]);

            throw $e; // Re-throw to mark job as failed
        }
    }
}
