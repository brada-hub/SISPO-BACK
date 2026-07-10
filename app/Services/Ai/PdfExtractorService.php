<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class PdfExtractorService
{
    /**
     * Extract text from a PDF file stored in the public disk.
     *
     * @param  string  $storagePath  Relative path in the 'public' disk (e.g. "postulantes/cv/abc.pdf")
     * @return array{text: string, method: string, length: int, pages: int}
     *
     * @throws \RuntimeException If extraction fails or file not found
     */
    public function extract(string $storagePath): array
    {
        $fullPath = Storage::disk('public')->path($storagePath);

        if (! file_exists($fullPath)) {
            throw new \RuntimeException("PDF file not found: {$storagePath}");
        }

        return $this->extractWithSmalot($fullPath);
    }

    /**
     * Primary extraction method using smalot/pdfparser (pure PHP, no external dependencies).
     */
    private function extractWithSmalot(string $fullPath): array
    {
        \Illuminate\Support\Facades\Log::info("PdfExtractorService: Iniciando extracción de PDF", ['path' => $fullPath]);
        
        $parser = new Parser();
        $pdf    = $parser->parseFile($fullPath);
        $text   = $pdf->getText();
        $pages  = count($pdf->getPages());

        // Clean up extracted text
        $text = $this->cleanText($text);
        
        $length = mb_strlen($text);
        \Illuminate\Support\Facades\Log::info("PdfExtractorService: PDF extraído", [
            'pages' => $pages,
            'length' => $length,
            'preview' => mb_substr($text, 0, 3000)
        ]);

        if ($length < 50) {
            \Illuminate\Support\Facades\Log::warning("PdfExtractorService: Posible PDF escaneado o vacío detectado.", [
                'path' => $fullPath,
                'length' => $length
            ]);
            // It will be caught by validateExtractedText in AiAnalysisService
        }

        return [
            'text'   => $text,
            'method' => 'smalot',
            'length' => $length,
            'pages'  => $pages,
        ];
    }

    /**
     * Normalize and clean extracted PDF text.
     */
    private function cleanText(string $text): string
    {
        // Remove excessive whitespace but preserve paragraph breaks
        $text = preg_replace('/[ \t]+/', ' ', $text);

        // Normalize line breaks
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Remove non-printable characters except newlines and tabs
        $text = preg_replace('/[^\P{C}\n\t]+/u', '', $text);

        return trim($text);
    }

    /**
     * Check if a stored file appears to be a valid PDF.
     */
    public function isValidPdf(string $storagePath): bool
    {
        $fullPath = Storage::disk('public')->path($storagePath);

        if (! file_exists($fullPath)) {
            return false;
        }

        $handle = fopen($fullPath, 'rb');

        if (! $handle) {
            return false;
        }

        $header = fread($handle, 5);
        fclose($handle);

        return $header === '%PDF-';
    }
}
