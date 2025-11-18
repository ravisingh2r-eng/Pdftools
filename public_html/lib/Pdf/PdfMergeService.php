<?php
/**
 * PDF Merge Service
 *
 * Handles merging multiple PDF files into one document.
 *
 * @package PDFTools\Pdf
 */

namespace PDFTools\Pdf;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;
use InvalidArgumentException;
use Exception;

class PdfMergeService
{
    private string $tempDir;

    /**
     * Constructor
     *
     * @param string $tempDir Temporary directory for file operations
     */
    public function __construct(string $tempDir)
    {
        $this->tempDir = rtrim($tempDir, '/');
    }

    /**
     * Merge multiple PDF files into one
     *
     * @param array $filePaths Array of input PDF file paths
     * @param string $outputPath Path for the merged output file
     * @return bool True on success
     * @throws InvalidArgumentException If files are invalid
     * @throws Exception On processing errors
     */
    public function merge(array $filePaths, string $outputPath): bool
    {
        if (empty($filePaths)) {
            throw new InvalidArgumentException('No PDF files provided for merging');
        }

        // Validate all files
        foreach ($filePaths as $file) {
            if (!file_exists($file)) {
                throw new InvalidArgumentException("File not found: {$file}");
            }
            if (!$this->isValidPdf($file)) {
                throw new InvalidArgumentException("Invalid PDF file: {$file}");
            }
        }

        try {
            $pdf = new Fpdi();

            foreach ($filePaths as $filePath) {
                $pageCount = $pdf->setSourceFile($filePath);

                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);

                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            }

            $this->ensureDirectory(dirname($outputPath));
            $pdf->Output('F', $outputPath);

            return file_exists($outputPath);

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        }
    }

    /**
     * Get total page count from multiple PDFs
     *
     * @param array $filePaths Array of PDF file paths
     * @return int Total page count
     */
    public function getTotalPageCount(array $filePaths): int
    {
        $total = 0;
        $pdf = new Fpdi();

        foreach ($filePaths as $filePath) {
            if (file_exists($filePath)) {
                $total += $pdf->setSourceFile($filePath);
            }
        }

        return $total;
    }

    /**
     * Check if file is a valid PDF
     */
    private function isValidPdf(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 5);
        fclose($handle);

        return $header === '%PDF-';
    }

    /**
     * Ensure directory exists
     */
    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
