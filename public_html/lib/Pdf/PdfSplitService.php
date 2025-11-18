<?php
/**
 * PDF Split Service
 *
 * Handles splitting PDFs into multiple files by ranges or individual pages.
 *
 * @package PDFTools\Pdf
 */

namespace PDFTools\Pdf;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;
use InvalidArgumentException;
use Exception;

class PdfSplitService
{
    private string $tempDir;

    public function __construct(string $tempDir)
    {
        $this->tempDir = rtrim($tempDir, '/');
    }

    /**
     * Split PDF by page ranges
     *
     * @param string $filePath Source PDF file
     * @param array $ranges Array of [start, end] page ranges (1-indexed)
     * @param string $outputDir Output directory
     * @return array Array of created file paths
     */
    public function splitByRange(string $filePath, array $ranges, string $outputDir): array
    {
        $this->validateFile($filePath);
        $this->ensureDirectory($outputDir);

        $sourcePdf = new Fpdi();
        $totalPages = $sourcePdf->setSourceFile($filePath);

        // Validate ranges
        foreach ($ranges as $index => $range) {
            if (!is_array($range) || count($range) !== 2) {
                throw new InvalidArgumentException("Invalid range format at index {$index}");
            }

            [$start, $end] = $range;

            if ($start < 1 || $end < 1 || $start > $totalPages || $end > $totalPages) {
                throw new InvalidArgumentException(
                    "Page range [{$start}, {$end}] is out of bounds (1-{$totalPages})"
                );
            }

            if ($start > $end) {
                throw new InvalidArgumentException(
                    "Invalid range [{$start}, {$end}]: start must be <= end"
                );
            }
        }

        $outputFiles = [];

        try {
            foreach ($ranges as $index => $range) {
                [$start, $end] = $range;

                $pdf = new Fpdi();
                $pdf->setSourceFile($filePath);

                for ($pageNo = $start; $pageNo <= $end; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);

                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }

                $outputPath = rtrim($outputDir, '/') . '/split_' . ($index + 1) . '.pdf';
                $pdf->Output('F', $outputPath);
                $outputFiles[] = $outputPath;
            }

            return $outputFiles;

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        }
    }

    /**
     * Split PDF into individual pages
     *
     * @param string $filePath Source PDF file
     * @param string $outputDir Output directory
     * @return array Array of created file paths
     */
    public function splitEveryPage(string $filePath, string $outputDir): array
    {
        $this->validateFile($filePath);
        $this->ensureDirectory($outputDir);

        $sourcePdf = new Fpdi();
        $totalPages = $sourcePdf->setSourceFile($filePath);

        $outputFiles = [];

        try {
            for ($pageNo = 1; $pageNo <= $totalPages; $pageNo++) {
                $pdf = new Fpdi();
                $pdf->setSourceFile($filePath);

                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                $outputPath = rtrim($outputDir, '/') . '/page_' . $pageNo . '.pdf';
                $pdf->Output('F', $outputPath);
                $outputFiles[] = $outputPath;
            }

            return $outputFiles;

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        }
    }

    /**
     * Extract specific pages
     *
     * @param string $filePath Source PDF
     * @param array $pageNumbers Array of page numbers to extract
     * @param string $outputPath Output file path
     * @return bool Success
     */
    public function extractPages(string $filePath, array $pageNumbers, string $outputPath): bool
    {
        $this->validateFile($filePath);

        $sourcePdf = new Fpdi();
        $totalPages = $sourcePdf->setSourceFile($filePath);

        // Validate page numbers
        foreach ($pageNumbers as $pageNo) {
            if ($pageNo < 1 || $pageNo > $totalPages) {
                throw new InvalidArgumentException("Page {$pageNo} is out of bounds (1-{$totalPages})");
            }
        }

        try {
            $pdf = new Fpdi();
            $pdf->setSourceFile($filePath);

            foreach ($pageNumbers as $pageNo) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            $this->ensureDirectory(dirname($outputPath));
            $pdf->Output('F', $outputPath);

            return file_exists($outputPath);

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        }
    }

    /**
     * Get page count of a PDF
     */
    public function getPageCount(string $filePath): int
    {
        $this->validateFile($filePath);
        $pdf = new Fpdi();
        return $pdf->setSourceFile($filePath);
    }

    private function validateFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException("Cannot read file: {$filePath}");
        }

        $header = fread($handle, 5);
        fclose($handle);

        if ($header !== '%PDF-') {
            throw new InvalidArgumentException("Invalid PDF file: {$filePath}");
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
