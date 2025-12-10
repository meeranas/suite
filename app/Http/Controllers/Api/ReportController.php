<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Services\Report\ReportGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    protected ReportGenerationService $reportService;

    public function __construct(ReportGenerationService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function generatePdf(Request $request, Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);

        $filename = $this->reportService->generatePdf($chat);
        // Construct base URL from scheme and host only (no path)
        $baseUrl = $request->getScheme() . '://' . $request->getHost();
        // Add port if not standard
        $port = $request->getPort();
        if ($port && !in_array($port, [80, 443])) {
            $baseUrl .= ':' . $port;
        }
        $url = $this->reportService->getSignedUrl($filename, $baseUrl);

        return response()->json([
            'filename' => $filename,
            'download_url' => $url,
        ]);
    }

    public function generateDocx(Request $request, Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);

        $filename = $this->reportService->generateDocx($chat);
        // Construct base URL from scheme and host only (no path)
        $baseUrl = $request->getScheme() . '://' . $request->getHost();
        // Add port if not standard
        $port = $request->getPort();
        if ($port && !in_array($port, [80, 443])) {
            $baseUrl .= ':' . $port;
        }
        $url = $this->reportService->getSignedUrl($filename, $baseUrl);

        return response()->json([
            'filename' => $filename,
            'download_url' => $url,
        ]);
    }

    public function download(Request $request, string $filename): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $token = $request->query('token');
        $expires = $request->query('expires');

        // Verify token
        $expectedToken = hash('sha256', $filename . $expires . config('app.key'));

        if ($token !== $expectedToken || now()->timestamp > $expires) {
            abort(403, 'Invalid or expired download link');
        }

        $path = storage_path('app/public/reports/' . $filename);

        if (!file_exists($path)) {
            abort(404, 'File not found');
        }

        return response()->download($path);
    }
}

