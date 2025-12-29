<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFileForRag;
use App\Models\File;
use App\Services\VectorDB\VectorDbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    protected VectorDbService $vectorDb;

    public function __construct(VectorDbService $vectorDb)
    {
        $this->vectorDb = $vectorDb;
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

        // Validate agent_id if provided (for admin uploads)
        $agentId = $request->input('agent_id');
        if ($agentId) {
            $agent = \App\Models\Agent::find($agentId);
            if (!$agent) {
                return response()->json([
                    'error' => 'Agent not found',
                    'message' => 'The specified agent does not exist.',
                ], 404);
            }
            // Check if user is admin
            $user = $request->user();
            if (!$user->hasRole('admin') && !$user->hasRole('super-admin')) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Only admins can upload files to agents.',
                ], 403);
            }
        }

        // Ensure either chat_id or agent_id, but not both
        if ($chatId && $agentId) {
            return response()->json([
                'error' => 'Invalid request',
                'message' => 'Cannot specify both chat_id and agent_id. Use chat_id for user uploads or agent_id for admin uploads.',
            ], 400);
        }

        if (!$chatId && !$agentId) {
            return response()->json([
                'error' => 'Invalid request',
                'message' => 'Either chat_id or agent_id must be provided.',
            ], 400);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,docx,xlsx,txt,csv,jpeg,jpg,png,tiff|max:40960', // 40MB max
            'chat_id' => 'nullable|integer',
            'agent_id' => 'nullable|integer|exists:agents,id',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $storedName = Str::random(40) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('files', $storedName, 'local');

        $dbFile = File::create([
            'user_id' => $request->user()->id,
            'chat_id' => $chatId ? (int) $chatId : null,
            'agent_id' => $agentId ? (int) $agentId : null,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'type' => $this->getFileType($file->getClientOriginalExtension()),
            'is_processed' => false,
            'is_embedded' => false,
        ]);

        // Dispatch job to process file for RAG asynchronously
        // Use dispatchAfterResponse to ensure response is sent before processing starts
        // This prevents 504 timeouts on large files
        ProcessFileForRag::dispatchAfterResponse($dbFile);

        return response()->json($dbFile, 201);
    }

    public function show(File $file): JsonResponse
    {
        $this->authorize('view', $file);

        return response()->json($file);
    }

    /**
     * Download a file
     */
    public function download(File $file)
    {
        $this->authorize('view', $file);

        if (!Storage::exists($file->path)) {
            return response()->json([
                'error' => 'File not found',
                'message' => 'The file does not exist on the server.',
            ], 404);
        }

        return Storage::download($file->path, $file->original_name);
    }

    public function destroy(File $file): JsonResponse
    {
        $this->authorize('delete', $file);

        // Delete embeddings from Chroma vector DB before deleting the file
        if ($file->is_embedded) {
            try {
                $this->vectorDb->deleteEmbeddingsForFile($file->id);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to delete embeddings from Chroma', [
                    'file_id' => $file->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue with file deletion even if Chroma deletion fails
            }
        }

        // Delete physical file from storage
        if ($file->path && Storage::exists($file->path)) {
            Storage::delete($file->path);
        }

        // Delete file record (this will cascade delete vector_embeddings records from database)
        $file->delete();

        return response()->json(['message' => 'File deleted successfully']);
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

