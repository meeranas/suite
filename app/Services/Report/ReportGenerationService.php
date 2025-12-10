<?php

namespace App\Services\Report;

use App\Models\Chat;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportGenerationService
{
    /**
     * Generate PDF report from chat
     */
    public function generatePdf(Chat $chat): string
    {
        // Load relationships
        $chat->load(['suite', 'agent', 'workflow', 'messages.agent']);

        $messages = $chat->messages()->orderBy('order')->get();

        $html = view('reports.chat-pdf', [
            'chat' => $chat,
            'messages' => $messages,
        ])->render();

        $pdf = Pdf::loadHTML($html);
        $filename = 'reports/chat_' . $chat->id . '_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        $path = storage_path('app/public/' . $filename);

        $pdf->save($path);

        return $filename;
    }

    /**
     * Generate DOCX report from chat
     */
    public function generateDocx(Chat $chat): string
    {
        $messages = $chat->messages()->orderBy('order')->get();

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Title
        $section->addText($chat->title ?? 'Chat Report', ['bold' => true, 'size' => 16]);
        $section->addTextBreak(2);

        // Messages
        foreach ($messages as $message) {
            $role = $message->role === 'user' ? 'User' : 'Assistant';
            $section->addText($role . ':', ['bold' => true]);
            $section->addText($message->content);
            $section->addTextBreak(1);
        }

        // Save
        $filename = 'reports/chat_' . $chat->id . '_' . now()->format('Y-m-d_H-i-s') . '.docx';
        $path = storage_path('app/public/' . $filename);

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($path);

        return $filename;
    }

    /**
     * Get signed download URL
     */
    public function getSignedUrl(string $filename, ?string $baseUrl = null, int $expiresInMinutes = 60): string
    {
        $expiresAt = now()->addMinutes($expiresInMinutes);
        $basename = basename($filename);
        $token = hash('sha256', $basename . $expiresAt->timestamp . config('app.key'));

        // Use provided base URL, or fallback to config/url helper
        if (!$baseUrl) {
            $baseUrl = config('app.url', url('/'));
        }

        // Ensure base URL doesn't have trailing slash
        $baseUrl = rtrim($baseUrl, '/');

        // Construct the full URL
        $url = $baseUrl . '/api/reports/download/' . $basename . '?token=' . $token . '&expires=' . $expiresAt->timestamp;

        return $url;
    }
}

