<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Services\RAG\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileRetryController extends Controller
{
    protected RagService $ragService;

    public function __construct(RagService $ragService)
    {
        $this->ragService = $ragService;
    }

    /**
     * Retry processing a file
     */
    public function retry(Request $request, File $file): JsonResponse
    {
        $this->authorize('view', $file);

        try {
            $this->ragService->processFile($file);

            return response()->json([
                'message' => 'File processing completed successfully',
                'file' => $file->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}





