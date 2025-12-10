<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\VectorEmbedding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateEmbeddingsChatId extends Command
{
    protected $signature = 'embeddings:update-chat-id';
    protected $description = 'Update existing embeddings to have chat_id from their associated files.';

    public function handle()
    {
        $this->info('Updating embeddings with chat_id from their files...');

        // Get all embeddings without chat_id
        $embeddings = VectorEmbedding::whereNull('chat_id')
            ->whereNotNull('file_id')
            ->get();

        $updated = 0;
        foreach ($embeddings as $embedding) {
            $file = File::find($embedding->file_id);
            if ($file && $file->chat_id) {
                $embedding->update(['chat_id' => $file->chat_id]);
                $updated++;
                $this->comment("Updated embedding ID {$embedding->id} with chat_id {$file->chat_id}");
            }
        }

        $this->info("Updated {$updated} embeddings with chat_id.");

        // Also update Chroma metadata if possible
        $this->info('Note: Chroma vector DB metadata may need to be updated separately.');
        $this->info('You may need to re-process files to update Chroma metadata.');

        return 0;
    }
}

