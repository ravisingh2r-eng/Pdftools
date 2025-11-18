<?php
/**
 * PDF Engine - Core PDF Utility Layer
 *
 * Provides common PDF operations using FPDI + FPDF libraries.
 * No external dependencies like Ghostscript required.
 *
 * @package PDFTools
 * @version 1.0.0
 */

// Load Composer autoloader (vendor is one level above public_html)
require_once __DIR__ . '/../../vendor/autoload.php';

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;

/**
 * PDFEngine Class
 *
 * Wrapper class for common PDF manipulation operations.
 * Uses FPDI for reading existing PDFs and FPDF for creating new ones.
 */
class PDFEngine
{
    /**
     * @var string Base temporary directory for file operations
     */
    private string $tempDir;

    /**
     * @var array Supported rotation angles
     */
    private const VALID_ROTATIONS = [0, 90, 180, 270];

    /**
     * @var array Compression modes and their settings
     */
    private const COMPRESSION_MODES = [
        'low'    => ['quality' => 90, 'scale' => 1.0],
        'medium' => ['quality' => 75, 'scale' => 0.8],
        'high'   => ['quality' => 50, 'scale' => 0.6],
    ];

    /**
     * Constructor
     *
     * @param string|null $tempDir Custom temp directory (optional)
     */
    public function __construct(?string $tempDir = null)
    {
        $this->tempDir = $tempDir ?? $this->normalize_temp_dir();
    }

    // =========================================================================
    // CORE PDF OPERATIONS
    // =========================================================================

    /**
     * Merge multiple PDF files into one
     *
     * Combines all pages from multiple PDF files into a single output file.
     *
     * @param array  $filePaths  Array of input PDF file paths
     * @param string $outputPath Path for the merged output file
     *
     * @return bool True on success, false on failure
     *
     * @throws InvalidArgumentException If no files provided or files don't exist
     * @throws Exception On PDF processing errors
     *
     * @example
     * $engine = new PDFEngine();
     * $result = $engine->merge_pdfs(
     *     ['/path/to/file1.pdf', '/path/to/file2.pdf'],
     *     '/path/to/merged.pdf'
     * );
     */
    public function merge_pdfs(array $filePaths, string $outputPath): bool
    {
        // Validate input
        if (empty($filePaths)) {
            throw new InvalidArgumentException('No PDF files provided for merging');
        }

        // Verify all files exist
        foreach ($filePaths as $file) {
            if (!file_exists($file)) {
                throw new InvalidArgumentException("File not found: {$file}");
            }
            if (!$this->is_valid_pdf($file)) {
                throw new InvalidArgumentException("Invalid PDF file: {$file}");
            }
        }

        try {
            $pdf = new Fpdi();

            // Process each input file
            foreach ($filePaths as $filePath) {
                $pageCount = $pdf->setSourceFile($filePath);

                // Import all pages from this file
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);

                    // Add page with same dimensions as source
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            }

            // Ensure output directory exists
            $this->ensure_directory(dirname($outputPath));

            // Save the merged PDF
            $pdf->Output('F', $outputPath);

            return file_exists($outputPath);

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Merge failed: " . $e->getMessage());
        }
    }

    /**
     * Split a PDF into multiple files based on page ranges
     *
     * Extracts specified page ranges from a PDF into separate files.
     *
     * @param string $filePath   Path to the source PDF file
     * @param array  $pageRanges Array of page ranges, e.g., [[1,3], [4,6], [7,7]]
     *                           Each range is [start, end] (1-indexed, inclusive)
     * @param string $outputDir  Directory to save the split files
     *
     * @return array Array of paths to the created files
     *
     * @throws InvalidArgumentException If file doesn't exist or ranges are invalid
     * @throws Exception On PDF processing errors
     *
     * @example
     * $engine = new PDFEngine();
     * $files = $engine->split_pdf(
     *     '/path/to/source.pdf',
     *     [[1, 3], [4, 6], [7, 10]],
     *     '/path/to/output/'
     * );
     * // Returns: ['/path/to/output/split_1.pdf', '/path/to/output/split_2.pdf', ...]
     */
    public function split_pdf(string $filePath, array $pageRanges, string $outputDir): array
    {
        // Validate input file
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        if (!$this->is_valid_pdf($filePath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$filePath}");
        }

        // Ensure output directory exists
        $this->ensure_directory($outputDir);

        // Get total page count
        $sourcePdf = new Fpdi();
        $totalPages = $sourcePdf->setSourceFile($filePath);

        // Validate page ranges
        foreach ($pageRanges as $index => $range) {
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
            // Process each page range
            foreach ($pageRanges as $index => $range) {
                [$start, $end] = $range;

                $pdf = new Fpdi();
                $pdf->setSourceFile($filePath);

                // Extract pages in this range
                for ($pageNo = $start; $pageNo <= $end; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);

                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }

                // Generate output filename
                $outputPath = rtrim($outputDir, '/') . '/split_' . ($index + 1) . '.pdf';
                $pdf->Output('F', $outputPath);

                if (file_exists($outputPath)) {
                    $outputFiles[] = $outputPath;
                }
            }

            return $outputFiles;

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Split failed: " . $e->getMessage());
        }
    }

    /**
     * Rotate all pages in a PDF by specified degrees
     *
     * Rotates every page in the PDF by the given angle.
     *
     * @param string $filePath   Path to the source PDF file
     * @param int    $degrees    Rotation angle (90, 180, or 270)
     * @param string $outputPath Path for the rotated output file
     *
     * @return bool True on success, false on failure
     *
     * @throws InvalidArgumentException If file doesn't exist or rotation is invalid
     * @throws Exception On PDF processing errors
     *
     * @example
     * $engine = new PDFEngine();
     * $result = $engine->rotate_pdf('/path/to/input.pdf', 90, '/path/to/rotated.pdf');
     */
    public function rotate_pdf(string $filePath, int $degrees, string $outputPath): bool
    {
        // Validate input file
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        if (!$this->is_valid_pdf($filePath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$filePath}");
        }

        // Validate rotation angle
        if (!in_array($degrees, self::VALID_ROTATIONS)) {
            throw new InvalidArgumentException(
                "Invalid rotation angle: {$degrees}. Must be one of: " .
                implode(', ', self::VALID_ROTATIONS)
            );
        }

        // No rotation needed
        if ($degrees === 0) {
            return copy($filePath, $outputPath);
        }

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($filePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                // Calculate new page dimensions after rotation
                $width = $size['width'];
                $height = $size['height'];

                // For 90 or 270 degree rotation, swap dimensions
                if ($degrees === 90 || $degrees === 270) {
                    $newWidth = $height;
                    $newHeight = $width;
                    $orientation = $newWidth > $newHeight ? 'L' : 'P';
                } else {
                    $newWidth = $width;
                    $newHeight = $height;
                    $orientation = $size['orientation'];
                }

                $pdf->AddPage($orientation, [$newWidth, $newHeight]);

                // Calculate position and apply rotation
                switch ($degrees) {
                    case 90:
                        $pdf->useTemplate($templateId, 0, $newHeight, $newHeight, $newWidth, -90);
                        break;
                    case 180:
                        $pdf->useTemplate($templateId, $newWidth, $newHeight, $width, $height, 180);
                        break;
                    case 270:
                        $pdf->useTemplate($templateId, $newWidth, 0, $newHeight, $newWidth, 90);
                        break;
                }
            }

            // Ensure output directory exists
            $this->ensure_directory(dirname($outputPath));

            $pdf->Output('F', $outputPath);

            return file_exists($outputPath);

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Rotation failed: " . $e->getMessage());
        }
    }

    /**
     * Add password protection to a PDF file
     *
     * Encrypts the PDF with user and/or owner passwords.
     *
     * @param string      $filePath      Path to the source PDF file
     * @param string      $userPassword  Password required to open the PDF
     * @param string|null $ownerPassword Password for full permissions (null = same as user)
     * @param string      $outputPath    Path for the protected output file
     *
     * @return bool True on success, false on failure
     *
     * @throws InvalidArgumentException If file doesn't exist
     * @throws Exception On PDF processing errors
     *
     * @example
     * $engine = new PDFEngine();
     * $result = $engine->protect_pdf(
     *     '/path/to/input.pdf',
     *     'userpass123',
     *     'ownerpass456',
     *     '/path/to/protected.pdf'
     * );
     */
    public function protect_pdf(
        string $filePath,
        string $userPassword,
        ?string $ownerPassword,
        string $outputPath
    ): bool {
        // Validate input file
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        if (!$this->is_valid_pdf($filePath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$filePath}");
        }

        // Owner password defaults to user password
        $ownerPassword = $ownerPassword ?? $userPassword;

        try {
            // Create protected PDF by extending Fpdi with protection
            $pdf = new class extends Fpdi {
                use \setasign\Fpdi\FpdiTrait;

                /**
                 * Apply protection to the PDF
                 */
                public function applyProtection(string $userPwd, string $ownerPwd): void
                {
                    // FPDF's SetProtection method
                    // Permissions: print, modify, copy, annot-forms
                    $this->SetProtection(
                        ['print', 'copy'], // Allowed permissions
                        $userPwd,
                        $ownerPwd
                    );
                }
            };

            // Set protection before importing pages
            $pdf->applyProtection($userPassword, $ownerPassword);

            // Import all pages from source
            $pageCount = $pdf->setSourceFile($filePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            // Ensure output directory exists
            $this->ensure_directory(dirname($outputPath));

            $pdf->Output('F', $outputPath);

            return file_exists($outputPath);

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Protection failed: " . $e->getMessage());
        }
    }

    /**
     * Basic PDF compression without external tools
     *
     * Attempts to reduce file size by re-encoding the PDF.
     * Note: Without Ghostscript, compression capabilities are limited.
     * This primarily removes redundant data and optimizes the PDF structure.
     *
     * @param string $filePath   Path to the source PDF file
     * @param string $outputPath Path for the compressed output file
     * @param string $mode       Compression mode: 'low', 'medium', 'high'
     *
     * @return bool True on success, false on failure
     *
     * @throws InvalidArgumentException If file doesn't exist or mode is invalid
     * @throws Exception On PDF processing errors
     *
     * @example
     * $engine = new PDFEngine();
     * $result = $engine->compress_pdf_basic(
     *     '/path/to/input.pdf',
     *     '/path/to/compressed.pdf',
     *     'medium'
     * );
     */
    public function compress_pdf_basic(
        string $filePath,
        string $outputPath,
        string $mode = 'medium'
    ): bool {
        // Validate input file
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        if (!$this->is_valid_pdf($filePath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$filePath}");
        }

        // Validate compression mode
        if (!isset(self::COMPRESSION_MODES[$mode])) {
            throw new InvalidArgumentException(
                "Invalid compression mode: {$mode}. Must be one of: " .
                implode(', ', array_keys(self::COMPRESSION_MODES))
            );
        }

        try {
            // Create PDF with compression enabled
            $pdf = new class extends Fpdi {
                public function __construct()
                {
                    parent::__construct();
                    // Enable compression
                    $this->SetCompression(true);
                }
            };

            // Import all pages from source
            $pageCount = $pdf->setSourceFile($filePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            // Ensure output directory exists
            $this->ensure_directory(dirname($outputPath));

            // Output with compression
            $pdf->Output('F', $outputPath);

            // Check if compression was effective
            if (file_exists($outputPath)) {
                $originalSize = filesize($filePath);
                $compressedSize = filesize($outputPath);

                // If compressed file is larger, keep original
                if ($compressedSize >= $originalSize) {
                    copy($filePath, $outputPath);
                }

                return true;
            }

            return false;

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Compression failed: " . $e->getMessage());
        }
    }

    /**
     * Reorder pages in a PDF file
     *
     * Rearranges pages according to the specified order.
     *
     * @param string $filePath   Path to the source PDF file
     * @param array  $pageOrder  Array of page numbers in desired order (1-indexed)
     *                           e.g., [3, 1, 2, 5, 4] reorders pages
     * @param string $outputPath Path for the reordered output file
     *
     * @return bool True on success, false on failure
     *
     * @throws InvalidArgumentException If file doesn't exist or page order is invalid
     * @throws Exception On PDF processing errors
     *
     * @example
     * $engine = new PDFEngine();
     * $result = $engine->reorder_pdf(
     *     '/path/to/input.pdf',
     *     [3, 1, 2, 5, 4],  // New page order
     *     '/path/to/reordered.pdf'
     * );
     */
    public function reorder_pdf(string $filePath, array $pageOrder, string $outputPath): bool
    {
        // Validate input file
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        if (!$this->is_valid_pdf($filePath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$filePath}");
        }

        if (empty($pageOrder)) {
            throw new InvalidArgumentException("Page order array cannot be empty");
        }

        try {
            $pdf = new Fpdi();
            $totalPages = $pdf->setSourceFile($filePath);

            // Validate page numbers
            foreach ($pageOrder as $pageNo) {
                if (!is_int($pageNo) || $pageNo < 1 || $pageNo > $totalPages) {
                    throw new InvalidArgumentException(
                        "Invalid page number: {$pageNo}. Must be between 1 and {$totalPages}"
                    );
                }
            }

            // Import pages in the specified order
            foreach ($pageOrder as $pageNo) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            // Ensure output directory exists
            $this->ensure_directory(dirname($outputPath));

            $pdf->Output('F', $outputPath);

            return file_exists($outputPath);

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Reorder failed: " . $e->getMessage());
        }
    }

    /**
     * Delete specific pages from a PDF file
     *
     * Removes the specified pages and outputs the remaining pages.
     *
     * @param string $filePath      Path to the source PDF file
     * @param array  $pagesToDelete Array of page numbers to remove (1-indexed)
     * @param string $outputPath    Path for the output file
     *
     * @return bool True on success, false on failure
     *
     * @throws InvalidArgumentException If file doesn't exist or pages are invalid
     * @throws Exception On PDF processing errors
     *
     * @example
     * $engine = new PDFEngine();
     * $result = $engine->delete_pages(
     *     '/path/to/input.pdf',
     *     [2, 4, 7],  // Pages to remove
     *     '/path/to/output.pdf'
     * );
     */
    public function delete_pages(string $filePath, array $pagesToDelete, string $outputPath): bool
    {
        // Validate input file
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        if (!$this->is_valid_pdf($filePath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$filePath}");
        }

        if (empty($pagesToDelete)) {
            throw new InvalidArgumentException("Pages to delete array cannot be empty");
        }

        try {
            $pdf = new Fpdi();
            $totalPages = $pdf->setSourceFile($filePath);

            // Validate and normalize page numbers
            $pagesToDelete = array_map('intval', $pagesToDelete);
            $pagesToDelete = array_unique($pagesToDelete);

            foreach ($pagesToDelete as $pageNo) {
                if ($pageNo < 1 || $pageNo > $totalPages) {
                    throw new InvalidArgumentException(
                        "Invalid page number: {$pageNo}. Must be between 1 and {$totalPages}"
                    );
                }
            }

            // Check if we're deleting all pages
            if (count($pagesToDelete) >= $totalPages) {
                throw new InvalidArgumentException(
                    "Cannot delete all pages. At least one page must remain."
                );
            }

            // Import pages that are NOT in the delete list
            for ($pageNo = 1; $pageNo <= $totalPages; $pageNo++) {
                if (in_array($pageNo, $pagesToDelete)) {
                    continue; // Skip this page
                }

                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            // Ensure output directory exists
            $this->ensure_directory(dirname($outputPath));

            $pdf->Output('F', $outputPath);

            return file_exists($outputPath);

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Delete pages failed: " . $e->getMessage());
        }
    }

    /**
     * Split PDF into individual single-page files
     *
     * Creates a separate PDF file for each page.
     *
     * @param string $filePath  Path to the source PDF file
     * @param string $outputDir Directory to save the split files
     *
     * @return array Array of paths to the created files
     *
     * @throws InvalidArgumentException If file doesn't exist
     * @throws Exception On PDF processing errors
     *
     * @example
     * $engine = new PDFEngine();
     * $files = $engine->split_all_pages(
     *     '/path/to/source.pdf',
     *     '/path/to/output/'
     * );
     * // Returns: ['/path/to/output/page_1.pdf', '/path/to/output/page_2.pdf', ...]
     */
    public function split_all_pages(string $filePath, string $outputDir): array
    {
        // Validate input file
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        if (!$this->is_valid_pdf($filePath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$filePath}");
        }

        // Ensure output directory exists
        $this->ensure_directory($outputDir);

        // Get total page count
        $sourcePdf = new Fpdi();
        $totalPages = $sourcePdf->setSourceFile($filePath);

        $outputFiles = [];

        try {
            // Create a file for each page
            for ($pageNo = 1; $pageNo <= $totalPages; $pageNo++) {
                $pdf = new Fpdi();
                $pdf->setSourceFile($filePath);

                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                // Generate output filename
                $outputPath = rtrim($outputDir, '/') . '/page_' . $pageNo . '.pdf';
                $pdf->Output('F', $outputPath);

                if (file_exists($outputPath)) {
                    $outputFiles[] = $outputPath;
                }
            }

            return $outputFiles;

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Split all pages failed: " . $e->getMessage());
        }
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Get total page count of a PDF file
     *
     * @param string $filePath Path to the PDF file
     *
     * @return int Number of pages
     *
     * @throws InvalidArgumentException If file doesn't exist
     */
    public function get_page_count(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $pdf = new Fpdi();
        return $pdf->setSourceFile($filePath);
    }

    /**
     * Check if a file is a valid PDF
     *
     * @param string $filePath Path to the file
     *
     * @return bool True if valid PDF
     */
    public function is_valid_pdf(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        // Check file extension
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            return false;
        }

        // Check PDF magic bytes
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 5);
        fclose($handle);

        return $header === '%PDF-';
    }

    /**
     * Get normalized temp directory path
     *
     * Creates the directory if it doesn't exist.
     *
     * @return string Absolute path to temp directory
     */
    public function normalize_temp_dir(): string
    {
        // Default temp directory in uploads
        $tempDir = __DIR__ . '/../uploads/temp';

        $this->ensure_directory($tempDir);

        return realpath($tempDir);
    }

    /**
     * Generate a unique temporary filename
     *
     * @param string $prefix    Filename prefix
     * @param string $extension File extension (without dot)
     *
     * @return string Full path to the temp file
     *
     * @example
     * $filename = $engine->generate_temp_filename('merged', 'pdf');
     * // Returns: /path/to/temp/merged_abc123def456.pdf
     */
    public function generate_temp_filename(string $prefix, string $extension): string
    {
        $uniqueId = bin2hex(random_bytes(8));
        $filename = $prefix . '_' . $uniqueId . '.' . ltrim($extension, '.');

        return $this->tempDir . '/' . $filename;
    }

    /**
     * Clean up old temp files
     *
     * Removes files older than the specified age from the temp directory.
     *
     * @param int $maxAgeMinutes Maximum age in minutes (default: 60)
     *
     * @return int Number of files deleted
     */
    public function cleanup_temp_files(int $maxAgeMinutes = 60): int
    {
        $deleted = 0;
        $cutoffTime = time() - ($maxAgeMinutes * 60);

        $files = glob($this->tempDir . '/*');

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Delete specific temp files
     *
     * @param array $filePaths Array of file paths to delete
     *
     * @return int Number of files deleted
     */
    public function delete_temp_files(array $filePaths): int
    {
        $deleted = 0;

        foreach ($filePaths as $file) {
            if (is_file($file) && unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    // =========================================================================
    // PRIVATE HELPER METHODS
    // =========================================================================

    /**
     * Ensure a directory exists, create if needed
     *
     * @param string $path Directory path
     *
     * @return bool True if directory exists or was created
     *
     * @throws Exception If directory cannot be created
     */
    private function ensure_directory(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }

        if (!mkdir($path, 0755, true)) {
            throw new Exception("Failed to create directory: {$path}");
        }

        return true;
    }
}

// =============================================================================
// STANDALONE HELPER FUNCTIONS (for convenience)
// =============================================================================

/**
 * Quick merge PDFs function
 *
 * @param array  $filePaths  Input file paths
 * @param string $outputPath Output file path
 *
 * @return bool Success status
 */
function pdf_merge(array $filePaths, string $outputPath): bool
{
    $engine = new PDFEngine();
    return $engine->merge_pdfs($filePaths, $outputPath);
}

/**
 * Quick split PDF function
 *
 * @param string $filePath   Input file path
 * @param array  $pageRanges Page ranges
 * @param string $outputDir  Output directory
 *
 * @return array Created file paths
 */
function pdf_split(string $filePath, array $pageRanges, string $outputDir): array
{
    $engine = new PDFEngine();
    return $engine->split_pdf($filePath, $pageRanges, $outputDir);
}

/**
 * Quick rotate PDF function
 *
 * @param string $filePath   Input file path
 * @param int    $degrees    Rotation degrees
 * @param string $outputPath Output file path
 *
 * @return bool Success status
 */
function pdf_rotate(string $filePath, int $degrees, string $outputPath): bool
{
    $engine = new PDFEngine();
    return $engine->rotate_pdf($filePath, $degrees, $outputPath);
}

/**
 * Quick protect PDF function
 *
 * @param string      $filePath      Input file path
 * @param string      $userPassword  User password
 * @param string|null $ownerPassword Owner password
 * @param string      $outputPath    Output file path
 *
 * @return bool Success status
 */
function pdf_protect(string $filePath, string $userPassword, ?string $ownerPassword, string $outputPath): bool
{
    $engine = new PDFEngine();
    return $engine->protect_pdf($filePath, $userPassword, $ownerPassword, $outputPath);
}

/**
 * Quick compress PDF function
 *
 * @param string $filePath   Input file path
 * @param string $outputPath Output file path
 * @param string $mode       Compression mode
 *
 * @return bool Success status
 */
function pdf_compress(string $filePath, string $outputPath, string $mode = 'medium'): bool
{
    $engine = new PDFEngine();
    return $engine->compress_pdf_basic($filePath, $outputPath, $mode);
}

/**
 * Quick reorder PDF pages function
 *
 * @param string $filePath   Input file path
 * @param array  $pageOrder  Page order array
 * @param string $outputPath Output file path
 *
 * @return bool Success status
 */
function pdf_reorder(string $filePath, array $pageOrder, string $outputPath): bool
{
    $engine = new PDFEngine();
    return $engine->reorder_pdf($filePath, $pageOrder, $outputPath);
}

/**
 * Quick delete PDF pages function
 *
 * @param string $filePath      Input file path
 * @param array  $pagesToDelete Pages to delete
 * @param string $outputPath    Output file path
 *
 * @return bool Success status
 */
function pdf_delete_pages(string $filePath, array $pagesToDelete, string $outputPath): bool
{
    $engine = new PDFEngine();
    return $engine->delete_pages($filePath, $pagesToDelete, $outputPath);
}

/**
 * Quick split all pages function
 *
 * @param string $filePath  Input file path
 * @param string $outputDir Output directory
 *
 * @return array Created file paths
 */
function pdf_split_all(string $filePath, string $outputDir): array
{
    $engine = new PDFEngine();
    return $engine->split_all_pages($filePath, $outputDir);
}
