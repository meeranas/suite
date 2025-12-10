<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Services\RAG\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    protected RagService $ragService;

    public function __construct(RagService $ragService)
    {
        $this->ragService = $ragService;
    }

    public function index(Request $request): JsonResponse
    {
        $files = File::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($files);
    }

    public function store(Request $request): JsonResponse
    {
        // Validate chat_id separately to check ownership
        $chatId = $request->input('chat_id');
        if ($chatId) {
            $chat = \App\Models\Chat::find($chatId);
            if (!$chat) {
                return response()->json([
                    'error' => 'Chat not found',
                    'message' => 'The specified chat does not exist.',
                ], 404);
            }
            // Check if user owns the chat
            if ($chat->user_id !== $request->user()->id) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You do not have permission to upload files to this chat.',
                ], 403);
            }
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,docx,xlsx,txt,csv,jpeg,jpg,png,tiff|max:15360', // 15MB max, add image support
            'chat_id' => 'nullable|integer',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $storedName = Str::random(40) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('files', $storedName, 'local');

        $dbFile = File::create([
            'user_id' => $request->user()->id,
            'chat_id' => $chatId ? (int) $chatId : null,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'type' => $this->getFileType($file->getClientOriginalExtension()),
        ]);

        // Process file for RAG - process synchronously for immediate feedback
        // This ensures the file is processed before the response is sent
        try {
            $ragService = app(\App\Services\RAG\RagService::class);
            $ragService->processFile($dbFile);

            // Refresh the file to get updated status
            $dbFile->refresh();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('File processing failed', [
                'file_id' => $dbFile->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update file status to show processing failed
            $dbFile->update([
                'metadata' => array_merge($dbFile->metadata ?? [], [
                    'processing_error' => $e->getMessage(),
                    'processing_failed_at' => now()->toDateTimeString(),
                ]),
            ]);

            // Don't throw - return the file with error status so user can see what happened
        }

        return response()->json($dbFile, 201);
    }

    public function show(File $file): JsonResponse
    {
        $this->authorize('view', $file);

        return response()->json($file);
    }

    public function destroy(File $file): JsonResponse
    {
        $this->authorize('delete', $file);

        Storage::delete($file->path);
        $file->delete();

        return response()->json(['message' => 'File deleted']);
    }

    protected function getFileType(string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf' => 'pdf',
            'docx', 'doc' => 'docx',
            'xlsx', 'xls' => 'xlsx',
            'txt' => 'txt',
            'csv' => 'csv',
            'jpeg', 'jpg', 'png', 'tiff' => 'image',
            default => 'other',
        };
    }
}

