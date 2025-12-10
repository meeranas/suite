<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\VectorEmbedding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CleanupOldFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'files:cleanup {--days=30 : Number of days to retain files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete files and embeddings older than specified days (default: 30)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up files older than {$days} days (before {$cutoffDate->toDateString()})...");

        // Find old files
        $oldFiles = File::where('created_at', '<', $cutoffDate)->get();

        $deletedCount = 0;
        $errorCount = 0;

        foreach ($oldFiles as $file) {
            try {
                // Delete physical file
                if ($file->path && Storage::exists($file->path)) {
                    Storage::delete($file->path);
                }

                // Delete associated embeddings
                VectorEmbedding::where('file_id', $file->id)->delete();

                // Delete file record
                $file->delete();

                $deletedCount++;
            } catch (\Exception $e) {
                Log::error('Failed to delete file', [
                    'file_id' => $file->id,
                    'error' => $e->getMessage(),
                ]);
                $errorCount++;
            }
        }

        $this->info("Deleted {$deletedCount} files and their embeddings.");

        if ($errorCount > 0) {
            $this->warn("Encountered {$errorCount} errors during cleanup.");
        }

        // Also cleanup old chats (30 days retention)
        $oldChats = \App\Models\Chat::where('created_at', '<', $cutoffDate)
            ->where('status', '!=', 'active')
            ->get();

        $chatDeletedCount = 0;
        foreach ($oldChats as $chat) {
            try {
                // Delete associated messages
                $chat->messages()->delete();
                $chat->delete();
                $chatDeletedCount++;
            } catch (\Exception $e) {
                Log::error('Failed to delete chat', [
                    'chat_id' => $chat->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($chatDeletedCount > 0) {
            $this->info("Deleted {$chatDeletedCount} old chat sessions.");
        }

        return Command::SUCCESS;
    }
}





