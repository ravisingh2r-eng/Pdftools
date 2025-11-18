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

    /**
     * Add text watermark to PDF pages
     *
     * Overlays text on each page of the PDF.
     *
     * @param string $filePath   Path to the source PDF file
     * @param string $text       Watermark text
     * @param string $outputPath Path for the output file
     * @param array  $options    Options: position, opacity, fontSize, angle, color
     *
     * @return bool True on success, false on failure
     *
     * @throws InvalidArgumentException If file doesn't exist
     * @throws Exception On PDF processing errors
     */
    public function add_watermark(
        string $filePath,
        string $text,
        string $outputPath,
        array $options = []
    ): bool {
        // Validate input file
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        if (!$this->is_valid_pdf($filePath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$filePath}");
        }

        if (empty($text)) {
            throw new InvalidArgumentException("Watermark text cannot be empty");
        }

        // Default options
        $defaults = [
            'position' => 'center',      // center, top-left, top-right, bottom-left, bottom-right
            'opacity' => 0.3,            // 0.0 to 1.0
            'fontSize' => 40,
            'angle' => 45,               // Rotation angle in degrees
            'color' => [128, 128, 128],  // RGB color
        ];

        $options = array_merge($defaults, $options);

        try {
            // Create custom FPDI class with watermark support
            $pdf = new class extends Fpdi {
                public function addWatermarkText(
                    string $text,
                    float $x,
                    float $y,
                    int $fontSize,
                    float $angle,
                    array $color,
                    float $opacity
                ): void {
                    // Set transparency
                    $this->SetAlpha($opacity);

                    // Set font and color
                    $this->SetFont('Helvetica', 'B', $fontSize);
                    $this->SetTextColor($color[0], $color[1], $color[2]);

                    // Rotate and position text
                    $this->Rotate($angle, $x, $y);
                    $this->Text($x, $y, $text);
                    $this->Rotate(0);

                    // Reset transparency
                    $this->SetAlpha(1);
                }

                // Alpha transparency support
                protected $extgstates = [];

                public function SetAlpha($alpha, $bm = 'Normal'): void
                {
                    $gs = $this->AddExtGState(['ca' => $alpha, 'CA' => $alpha, 'BM' => '/' . $bm]);
                    $this->SetExtGState($gs);
                }

                public function AddExtGState($parms)
                {
                    $n = count($this->extgstates) + 1;
                    $this->extgstates[$n]['parms'] = $parms;
                    return $n;
                }

                public function SetExtGState($gs): void
                {
                    $this->_out(sprintf('/GS%d gs', $gs));
                }

                public function _enddoc(): void
                {
                    if (!empty($this->extgstates) && count($this->extgstates) > 0) {
                        foreach ($this->extgstates as $k => $extgstate) {
                            $this->extgstates[$k]['n'] = $this->n + 1;
                            $this->_newobj();
                            $this->_put('<</Type /ExtGState');
                            $parms = $this->extgstates[$k]['parms'];
                            $this->_put(sprintf('/ca %.3F', $parms['ca']));
                            $this->_put(sprintf('/CA %.3F', $parms['CA']));
                            $this->_put('/BM ' . $parms['BM']);
                            $this->_put('>>');
                            $this->_put('endobj');
                        }
                    }
                    parent::_enddoc();
                }

                public function _putresourcedict(): void
                {
                    parent::_putresourcedict();
                    if (!empty($this->extgstates)) {
                        $this->_put('/ExtGState <<');
                        foreach ($this->extgstates as $k => $extgstate) {
                            $this->_put('/GS' . $k . ' ' . $extgstate['n'] . ' 0 R');
                        }
                        $this->_put('>>');
                    }
                }

                public function Rotate($angle, $x = -1, $y = -1): void
                {
                    if ($x == -1) $x = $this->x;
                    if ($y == -1) $y = $this->y;
                    if ($this->angle != 0) {
                        $this->_out('Q');
                    }
                    $this->angle = $angle;
                    if ($angle != 0) {
                        $angle *= M_PI / 180;
                        $c = cos($angle);
                        $s = sin($angle);
                        $cx = $x * $this->k;
                        $cy = ($this->h - $y) * $this->k;
                        $this->_out(sprintf(
                            'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
                            $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy
                        ));
                    }
                }

                protected $angle = 0;

                public function _endpage(): void
                {
                    if ($this->angle != 0) {
                        $this->angle = 0;
                        $this->_out('Q');
                    }
                    parent::_endpage();
                }
            };

            $pageCount = $pdf->setSourceFile($filePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                // Calculate watermark position
                $pageWidth = $size['width'];
                $pageHeight = $size['height'];

                switch ($options['position']) {
                    case 'top-left':
                        $x = 20;
                        $y = 30;
                        break;
                    case 'top-right':
                        $x = $pageWidth - 60;
                        $y = 30;
                        break;
                    case 'bottom-left':
                        $x = 20;
                        $y = $pageHeight - 20;
                        break;
                    case 'bottom-right':
                        $x = $pageWidth - 60;
                        $y = $pageHeight - 20;
                        break;
                    case 'center':
                    default:
                        $x = $pageWidth / 2 - 20;
                        $y = $pageHeight / 2;
                        break;
                }

                $pdf->addWatermarkText(
                    $text,
                    $x,
                    $y,
                    $options['fontSize'],
                    $options['angle'],
                    $options['color'],
                    $options['opacity']
                );
            }

            // Ensure output directory exists
            $this->ensure_directory(dirname($outputPath));

            $pdf->Output('F', $outputPath);

            return file_exists($outputPath);

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Watermark failed: " . $e->getMessage());
        }
    }

    /**
     * Add page numbers to PDF
     *
     * Adds page numbers to each page of the PDF.
     *
     * @param string $filePath   Path to the source PDF file
     * @param string $outputPath Path for the output file
     * @param array  $options    Options: position, startNumber, format, fontSize
     *
     * @return bool True on success, false on failure
     *
     * @throws InvalidArgumentException If file doesn't exist
     * @throws Exception On PDF processing errors
     */
    public function add_page_numbers(
        string $filePath,
        string $outputPath,
        array $options = []
    ): bool {
        // Validate input file
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        if (!$this->is_valid_pdf($filePath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$filePath}");
        }

        // Default options
        $defaults = [
            'position' => 'bottom-center',  // bottom-center, bottom-left, bottom-right, top-center, top-left, top-right
            'startNumber' => 1,
            'format' => 'Page {n}',         // {n} = page number, {total} = total pages
            'fontSize' => 10,
            'color' => [0, 0, 0],           // RGB color (black)
            'margin' => 20,
        ];

        $options = array_merge($defaults, $options);

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($filePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                // Calculate page number
                $currentNumber = $options['startNumber'] + $pageNo - 1;
                $text = str_replace(
                    ['{n}', '{total}'],
                    [$currentNumber, $pageCount],
                    $options['format']
                );

                // Set font
                $pdf->SetFont('Helvetica', '', $options['fontSize']);
                $pdf->SetTextColor($options['color'][0], $options['color'][1], $options['color'][2]);

                // Calculate position
                $pageWidth = $size['width'];
                $pageHeight = $size['height'];
                $textWidth = $pdf->GetStringWidth($text);
                $margin = $options['margin'];

                switch ($options['position']) {
                    case 'top-left':
                        $x = $margin;
                        $y = $margin;
                        break;
                    case 'top-center':
                        $x = ($pageWidth - $textWidth) / 2;
                        $y = $margin;
                        break;
                    case 'top-right':
                        $x = $pageWidth - $textWidth - $margin;
                        $y = $margin;
                        break;
                    case 'bottom-left':
                        $x = $margin;
                        $y = $pageHeight - $margin;
                        break;
                    case 'bottom-right':
                        $x = $pageWidth - $textWidth - $margin;
                        $y = $pageHeight - $margin;
                        break;
                    case 'bottom-center':
                    default:
                        $x = ($pageWidth - $textWidth) / 2;
                        $y = $pageHeight - $margin;
                        break;
                }

                $pdf->Text($x, $y, $text);
            }

            // Ensure output directory exists
            $this->ensure_directory(dirname($outputPath));

            $pdf->Output('F', $outputPath);

            return file_exists($outputPath);

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Add page numbers failed: " . $e->getMessage());
        }
    }

    /**
     * Remove metadata from PDF
     *
     * Rebuilds the PDF without any metadata (author, creator, etc.)
     *
     * @param string $filePath   Path to the source PDF file
     * @param string $outputPath Path for the output file
     *
     * @return bool True on success, false on failure
     *
     * @throws InvalidArgumentException If file doesn't exist
     * @throws Exception On PDF processing errors
     */
    public function remove_metadata(string $filePath, string $outputPath): bool
    {
        // Validate input file
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        if (!$this->is_valid_pdf($filePath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$filePath}");
        }

        try {
            // Create PDF without metadata
            $pdf = new Fpdi();

            // Don't set any metadata - leave it blank
            $pdf->SetCreator('');
            $pdf->SetAuthor('');
            $pdf->SetTitle('');
            $pdf->SetSubject('');
            $pdf->SetKeywords('');

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
            throw new Exception("Remove metadata failed: " . $e->getMessage());
        }
    }

    /**
     * Remove blank pages from PDF
     *
     * Detects and removes pages with minimal or no content.
     *
     * @param string $filePath   Path to the source PDF file
     * @param string $outputPath Path for the output file
     * @param int    $threshold  Content threshold in bytes (default: 500)
     *
     * @return array ['success' => bool, 'removed' => int, 'remaining' => int]
     *
     * @throws InvalidArgumentException If file doesn't exist
     * @throws Exception On PDF processing errors
     */
    public function remove_blank_pages(string $filePath, string $outputPath, int $threshold = 500): array
    {
        // Validate input file
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        if (!$this->is_valid_pdf($filePath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$filePath}");
        }

        try {
            // First pass: identify blank pages
            $blankPages = [];
            $sourcePdf = new Fpdi();
            $pageCount = $sourcePdf->setSourceFile($filePath);

            // Read PDF file to analyze content
            $content = file_get_contents($filePath);

            // Simple heuristic: check each page by re-importing and measuring size
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $testPdf = new Fpdi();
                $testPdf->setSourceFile($filePath);
                $templateId = $testPdf->importPage($pageNo);
                $size = $testPdf->getTemplateSize($templateId);

                $testPdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $testPdf->useTemplate($templateId);

                // Output to string and check size
                $pageContent = $testPdf->Output('S');

                // Estimate content size (subtract PDF overhead ~500 bytes)
                $contentSize = strlen($pageContent) - 500;

                if ($contentSize < $threshold) {
                    $blankPages[] = $pageNo;
                }
            }

            // Ensure at least one page remains
            $nonBlankCount = $pageCount - count($blankPages);
            if ($nonBlankCount < 1) {
                // Keep the first page if all are "blank"
                array_shift($blankPages);
            }

            // Second pass: create PDF without blank pages
            $pdf = new Fpdi();
            $pdf->setSourceFile($filePath);

            $pagesKept = 0;
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                if (in_array($pageNo, $blankPages)) {
                    continue;
                }

                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
                $pagesKept++;
            }

            // Ensure output directory exists
            $this->ensure_directory(dirname($outputPath));

            $pdf->Output('F', $outputPath);

            return [
                'success' => file_exists($outputPath),
                'removed' => count($blankPages),
                'remaining' => $pagesKept
            ];

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Remove blank pages failed: " . $e->getMessage());
        }
    }

    /**
     * Flatten PDF (remove annotations, form fields, layers)
     *
     * Re-imports the PDF to flatten all interactive elements.
     * FPDI doesn't import annotations, so this effectively flattens them.
     *
     * @param string $filePath   Path to the source PDF file
     * @param string $outputPath Path for the output file
     *
     * @return bool True on success, false on failure
     *
     * @throws InvalidArgumentException If file doesn't exist
     * @throws Exception On PDF processing errors
     */
    public function flatten_pdf(string $filePath, string $outputPath): bool
    {
        // Validate input file
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        if (!$this->is_valid_pdf($filePath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$filePath}");
        }

        try {
            // Create flattened PDF - FPDI naturally flattens by not importing
            // annotations, form fields, or interactive elements
            $pdf = new Fpdi();

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
            throw new Exception("Flatten PDF failed: " . $e->getMessage());
        }
    }

    // =========================================================================
    // IMAGE OPERATIONS
    // =========================================================================

    /**
     * Convert multiple images to PDF
     *
     * Creates a PDF with each image on a separate page.
     *
     * @param array  $imagePaths Array of image file paths
     * @param string $outputPath Path for the output PDF file
     * @param array  $options    Options: orientation, pageSize, margin
     *
     * @return bool True on success, false on failure
     *
     * @throws InvalidArgumentException If no images provided or files don't exist
     * @throws Exception On processing errors
     */
    public function images_to_pdf(array $imagePaths, string $outputPath, array $options = []): bool
    {
        if (empty($imagePaths)) {
            throw new InvalidArgumentException('No image files provided');
        }

        // Default options
        $defaults = [
            'orientation' => 'auto',  // auto, P (portrait), L (landscape)
            'pageSize' => 'A4',
            'margin' => 10,
            'fitToPage' => true,
        ];

        $options = array_merge($defaults, $options);

        // Validate all images exist
        foreach ($imagePaths as $imagePath) {
            if (!file_exists($imagePath)) {
                throw new InvalidArgumentException("Image file not found: {$imagePath}");
            }
        }

        try {
            $pdf = new \FPDF();

            foreach ($imagePaths as $imagePath) {
                // Get image dimensions
                $imageInfo = @getimagesize($imagePath);
                if (!$imageInfo) {
                    throw new Exception("Invalid image file: {$imagePath}");
                }

                $imgWidth = $imageInfo[0];
                $imgHeight = $imageInfo[1];
                $imgType = $imageInfo[2];

                // Convert WebP to PNG if needed (FPDF doesn't support WebP)
                $tempImage = null;
                if ($imgType === IMAGETYPE_WEBP) {
                    $tempImage = $this->convert_webp_to_png($imagePath);
                    $imagePath = $tempImage;
                }

                // Determine orientation
                $orientation = $options['orientation'];
                if ($orientation === 'auto') {
                    $orientation = ($imgWidth > $imgHeight) ? 'L' : 'P';
                }

                // Add page
                $pdf->AddPage($orientation, $options['pageSize']);

                // Calculate dimensions to fit image on page
                $pageWidth = $pdf->GetPageWidth();
                $pageHeight = $pdf->GetPageHeight();
                $margin = $options['margin'];

                $availWidth = $pageWidth - (2 * $margin);
                $availHeight = $pageHeight - (2 * $margin);

                if ($options['fitToPage']) {
                    // Scale to fit while maintaining aspect ratio
                    $scale = min($availWidth / $imgWidth, $availHeight / $imgHeight);
                    $newWidth = $imgWidth * $scale;
                    $newHeight = $imgHeight * $scale;

                    // Center on page
                    $x = $margin + ($availWidth - $newWidth) / 2;
                    $y = $margin + ($availHeight - $newHeight) / 2;
                } else {
                    $newWidth = min($imgWidth, $availWidth);
                    $newHeight = min($imgHeight, $availHeight);
                    $x = $margin;
                    $y = $margin;
                }

                // Add image to PDF
                $pdf->Image($imagePath, $x, $y, $newWidth, $newHeight);

                // Clean up temp file
                if ($tempImage && file_exists($tempImage)) {
                    unlink($tempImage);
                }
            }

            // Ensure output directory exists
            $this->ensure_directory(dirname($outputPath));

            $pdf->Output('F', $outputPath);

            return file_exists($outputPath);

        } catch (Exception $e) {
            throw new Exception("Images to PDF failed: " . $e->getMessage());
        }
    }

    /**
     * Convert PDF pages to PNG images
     *
     * Requires Imagick extension. Falls back to GD workaround if unavailable.
     *
     * @param string $pdfPath   Path to the source PDF file
     * @param string $outputDir Directory to save the images
     * @param int    $quality   Image quality (1-100)
     * @param int    $dpi       Resolution in DPI (default: 150)
     *
     * @return array Array of created image paths
     *
     * @throws InvalidArgumentException If file doesn't exist
     * @throws Exception On processing errors
     */
    public function pdf_to_images(string $pdfPath, string $outputDir, int $quality = 90, int $dpi = 150): array
    {
        // Validate input file
        if (!file_exists($pdfPath)) {
            throw new InvalidArgumentException("File not found: {$pdfPath}");
        }

        if (!$this->is_valid_pdf($pdfPath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$pdfPath}");
        }

        // Ensure output directory exists
        $this->ensure_directory($outputDir);

        $outputFiles = [];

        // Check for Imagick
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick();
                $imagick->setResolution($dpi, $dpi);
                $imagick->readImage($pdfPath);

                $pageCount = $imagick->getNumberImages();

                for ($i = 0; $i < $pageCount; $i++) {
                    $imagick->setIteratorIndex($i);
                    $imagick->setImageFormat('png');
                    $imagick->setImageCompressionQuality($quality);

                    $outputPath = rtrim($outputDir, '/') . '/page_' . ($i + 1) . '.png';
                    $imagick->writeImage($outputPath);

                    if (file_exists($outputPath)) {
                        $outputFiles[] = $outputPath;
                    }
                }

                $imagick->clear();
                $imagick->destroy();

                return $outputFiles;

            } catch (Exception $e) {
                throw new Exception("Imagick PDF conversion failed: " . $e->getMessage());
            }
        }

        // GD Fallback: Create placeholder images with page info
        // Note: GD cannot actually render PDF content
        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($pdfPath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                // Create a placeholder image
                $width = (int)($size['width'] * 2); // Scale up
                $height = (int)($size['height'] * 2);

                $img = imagecreatetruecolor($width, $height);

                // White background
                $white = imagecolorallocate($img, 255, 255, 255);
                $gray = imagecolorallocate($img, 128, 128, 128);
                $black = imagecolorallocate($img, 0, 0, 0);

                imagefill($img, 0, 0, $white);

                // Add border
                imagerectangle($img, 0, 0, $width - 1, $height - 1, $gray);

                // Add text indicating this is a placeholder
                $text = "Page {$pageNo}";
                $fontSize = 5;
                $textWidth = imagefontwidth($fontSize) * strlen($text);
                $textHeight = imagefontheight($fontSize);
                $x = ($width - $textWidth) / 2;
                $y = ($height - $textHeight) / 2;
                imagestring($img, $fontSize, (int)$x, (int)$y, $text, $black);

                // Add note about Imagick requirement
                $note = "Imagick required for full rendering";
                $noteWidth = imagefontwidth(2) * strlen($note);
                imagestring($img, 2, (int)(($width - $noteWidth) / 2), (int)$y + 30, $note, $gray);

                $outputPath = rtrim($outputDir, '/') . '/page_' . $pageNo . '.png';
                imagepng($img, $outputPath, (int)((100 - $quality) / 10));
                imagedestroy($img);

                if (file_exists($outputPath)) {
                    $outputFiles[] = $outputPath;
                }
            }

            return $outputFiles;

        } catch (Exception $e) {
            throw new Exception("PDF to images failed: " . $e->getMessage());
        }
    }

    /**
     * Extract embedded images from PDF
     *
     * Parses PDF structure to extract image streams.
     *
     * @param string $pdfPath   Path to the source PDF file
     * @param string $outputDir Directory to save extracted images
     *
     * @return array Array of extracted image paths
     *
     * @throws InvalidArgumentException If file doesn't exist
     * @throws Exception On processing errors
     */
    public function extract_images_from_pdf(string $pdfPath, string $outputDir): array
    {
        // Validate input file
        if (!file_exists($pdfPath)) {
            throw new InvalidArgumentException("File not found: {$pdfPath}");
        }

        if (!$this->is_valid_pdf($pdfPath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$pdfPath}");
        }

        // Ensure output directory exists
        $this->ensure_directory($outputDir);

        $outputFiles = [];

        try {
            // Read PDF content
            $content = file_get_contents($pdfPath);

            // Find image streams (XObject with Subtype Image)
            $imageCount = 0;

            // Pattern to find image XObjects
            // Look for stream...endstream blocks that are images
            preg_match_all('/\/Subtype\s*\/Image.*?stream\r?\n(.*?)endstream/s', $content, $matches);

            if (!empty($matches[1])) {
                foreach ($matches[1] as $imageData) {
                    $imageCount++;

                    // Try to decode the image data
                    // Check for common filters
                    $decoded = $imageData;

                    // Try FlateDecode
                    if (function_exists('gzuncompress')) {
                        $uncompressed = @gzuncompress($imageData);
                        if ($uncompressed !== false) {
                            $decoded = $uncompressed;
                        }
                    }

                    // Try to create image from raw data
                    $img = @imagecreatefromstring($decoded);

                    if ($img !== false) {
                        $outputPath = rtrim($outputDir, '/') . '/image_' . $imageCount . '.png';
                        imagepng($img, $outputPath);
                        imagedestroy($img);

                        if (file_exists($outputPath)) {
                            $outputFiles[] = $outputPath;
                        }
                    } else {
                        // Save raw data for manual inspection
                        // Check if it's JPEG by header
                        if (substr($decoded, 0, 2) === "\xFF\xD8") {
                            $outputPath = rtrim($outputDir, '/') . '/image_' . $imageCount . '.jpg';
                            file_put_contents($outputPath, $decoded);
                            if (file_exists($outputPath)) {
                                $outputFiles[] = $outputPath;
                            }
                        }
                    }
                }
            }

            // Alternative: Look for DCTDecode (JPEG) streams
            preg_match_all('/\/Filter\s*\/DCTDecode.*?stream\r?\n(.*?)endstream/s', $content, $jpegMatches);

            if (!empty($jpegMatches[1])) {
                foreach ($jpegMatches[1] as $jpegData) {
                    $imageCount++;
                    $outputPath = rtrim($outputDir, '/') . '/image_' . $imageCount . '.jpg';
                    file_put_contents($outputPath, $jpegData);
                    if (file_exists($outputPath)) {
                        $outputFiles[] = $outputPath;
                    }
                }
            }

            return $outputFiles;

        } catch (Exception $e) {
            throw new Exception("Extract images failed: " . $e->getMessage());
        }
    }

    /**
     * Convert PDF to a single long image
     *
     * Stitches all PDF pages vertically into one image.
     * Requires Imagick for full PDF rendering.
     *
     * @param string $pdfPath    Path to the source PDF file
     * @param string $outputPath Path for the output image
     * @param int    $quality    Image quality (1-100)
     * @param int    $dpi        Resolution in DPI (default: 150)
     *
     * @return bool True on success, false on failure
     *
     * @throws InvalidArgumentException If file doesn't exist
     * @throws Exception On processing errors
     */
    public function pdf_to_long_image(string $pdfPath, string $outputPath, int $quality = 90, int $dpi = 150): bool
    {
        // Validate input file
        if (!file_exists($pdfPath)) {
            throw new InvalidArgumentException("File not found: {$pdfPath}");
        }

        if (!$this->is_valid_pdf($pdfPath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$pdfPath}");
        }

        // Ensure output directory exists
        $this->ensure_directory(dirname($outputPath));

        // Check for Imagick
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick();
                $imagick->setResolution($dpi, $dpi);
                $imagick->readImage($pdfPath);

                // Append all pages vertically
                $imagick->resetIterator();
                $combined = $imagick->appendImages(true); // true = stack vertically

                $combined->setImageFormat('png');
                $combined->setImageCompressionQuality($quality);
                $combined->writeImage($outputPath);

                $imagick->clear();
                $imagick->destroy();
                $combined->clear();
                $combined->destroy();

                return file_exists($outputPath);

            } catch (Exception $e) {
                throw new Exception("Imagick PDF to long image failed: " . $e->getMessage());
            }
        }

        // GD Fallback: Create placeholder
        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($pdfPath);

            // Calculate total dimensions
            $totalHeight = 0;
            $maxWidth = 0;
            $pages = [];

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $width = (int)($size['width'] * 2);
                $height = (int)($size['height'] * 2);

                $pages[] = ['width' => $width, 'height' => $height, 'pageNo' => $pageNo];
                $totalHeight += $height;
                $maxWidth = max($maxWidth, $width);
            }

            // Create combined image
            $img = imagecreatetruecolor($maxWidth, $totalHeight);
            $white = imagecolorallocate($img, 255, 255, 255);
            $gray = imagecolorallocate($img, 128, 128, 128);
            $black = imagecolorallocate($img, 0, 0, 0);
            imagefill($img, 0, 0, $white);

            $yOffset = 0;
            foreach ($pages as $page) {
                // Draw page placeholder
                imagerectangle($img, 0, $yOffset, $page['width'] - 1, $yOffset + $page['height'] - 1, $gray);

                // Add page number
                $text = "Page " . $page['pageNo'];
                $fontSize = 5;
                $textWidth = imagefontwidth($fontSize) * strlen($text);
                $x = ($page['width'] - $textWidth) / 2;
                $y = $yOffset + ($page['height'] / 2);
                imagestring($img, $fontSize, (int)$x, (int)$y, $text, $black);

                $yOffset += $page['height'];
            }

            // Add note
            $note = "Imagick required for full rendering";
            imagestring($img, 2, 10, 10, $note, $gray);

            // Save
            imagepng($img, $outputPath, (int)((100 - $quality) / 10));
            imagedestroy($img);

            return file_exists($outputPath);

        } catch (Exception $e) {
            throw new Exception("PDF to long image failed: " . $e->getMessage());
        }
    }

    /**
     * Join multiple images vertically or horizontally
     *
     * Combines images using GD library.
     *
     * @param array  $imagePaths Array of image file paths
     * @param string $outputPath Path for the output image
     * @param string $mode       Join mode: 'vertical' or 'horizontal'
     * @param int    $quality    Image quality (1-100)
     *
     * @return bool True on success, false on failure
     *
     * @throws InvalidArgumentException If no images provided or files don't exist
     * @throws Exception On processing errors
     */
    public function join_images(array $imagePaths, string $outputPath, string $mode = 'vertical', int $quality = 90): bool
    {
        if (empty($imagePaths)) {
            throw new InvalidArgumentException('No image files provided');
        }

        // Validate mode
        if (!in_array($mode, ['vertical', 'horizontal'])) {
            throw new InvalidArgumentException("Invalid mode: {$mode}. Must be 'vertical' or 'horizontal'");
        }

        // Validate and load all images
        $images = [];
        $totalWidth = 0;
        $totalHeight = 0;
        $maxWidth = 0;
        $maxHeight = 0;

        foreach ($imagePaths as $imagePath) {
            if (!file_exists($imagePath)) {
                throw new InvalidArgumentException("Image file not found: {$imagePath}");
            }

            $img = $this->load_image($imagePath);
            if (!$img) {
                throw new Exception("Failed to load image: {$imagePath}");
            }

            $width = imagesx($img);
            $height = imagesy($img);

            $images[] = [
                'resource' => $img,
                'width' => $width,
                'height' => $height
            ];

            if ($mode === 'vertical') {
                $totalHeight += $height;
                $maxWidth = max($maxWidth, $width);
            } else {
                $totalWidth += $width;
                $maxHeight = max($maxHeight, $height);
            }
        }

        try {
            // Calculate final dimensions
            if ($mode === 'vertical') {
                $finalWidth = $maxWidth;
                $finalHeight = $totalHeight;
            } else {
                $finalWidth = $totalWidth;
                $finalHeight = $maxHeight;
            }

            // Create output image
            $output = imagecreatetruecolor($finalWidth, $finalHeight);

            // Set white background
            $white = imagecolorallocate($output, 255, 255, 255);
            imagefill($output, 0, 0, $white);

            // Enable alpha blending
            imagealphablending($output, true);
            imagesavealpha($output, true);

            // Copy images
            $offset = 0;

            foreach ($images as $imgData) {
                $img = $imgData['resource'];
                $width = $imgData['width'];
                $height = $imgData['height'];

                if ($mode === 'vertical') {
                    // Center horizontally
                    $x = (int)(($finalWidth - $width) / 2);
                    $y = $offset;
                    imagecopy($output, $img, $x, $y, 0, 0, $width, $height);
                    $offset += $height;
                } else {
                    // Center vertically
                    $x = $offset;
                    $y = (int)(($finalHeight - $height) / 2);
                    imagecopy($output, $img, $x, $y, 0, 0, $width, $height);
                    $offset += $width;
                }

                // Free memory
                imagedestroy($img);
            }

            // Ensure output directory exists
            $this->ensure_directory(dirname($outputPath));

            // Save output image
            $ext = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));

            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($output, $outputPath, $quality);
                    break;
                case 'png':
                    imagepng($output, $outputPath, (int)((100 - $quality) / 10));
                    break;
                case 'webp':
                    if (function_exists('imagewebp')) {
                        imagewebp($output, $outputPath, $quality);
                    } else {
                        imagepng($output, $outputPath);
                    }
                    break;
                default:
                    imagepng($output, $outputPath);
            }

            imagedestroy($output);

            return file_exists($outputPath);

        } catch (Exception $e) {
            // Clean up
            foreach ($images as $imgData) {
                if (is_resource($imgData['resource']) || $imgData['resource'] instanceof \GdImage) {
                    imagedestroy($imgData['resource']);
                }
            }
            throw new Exception("Join images failed: " . $e->getMessage());
        }
    }

    /**
     * Load image from file (supports JPG, PNG, GIF, WebP)
     *
     * @param string $imagePath Path to the image file
     *
     * @return resource|GdImage|false GD image resource or false on failure
     */
    private function load_image(string $imagePath)
    {
        $imageInfo = @getimagesize($imagePath);
        if (!$imageInfo) {
            return false;
        }

        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($imagePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($imagePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($imagePath);
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    return imagecreatefromwebp($imagePath);
                }
                return false;
            default:
                return false;
        }
    }

    /**
     * Convert WebP image to PNG (for FPDF compatibility)
     *
     * @param string $webpPath Path to the WebP file
     *
     * @return string|null Path to the converted PNG file
     */
    private function convert_webp_to_png(string $webpPath): ?string
    {
        if (!function_exists('imagecreatefromwebp')) {
            return null;
        }

        $img = imagecreatefromwebp($webpPath);
        if (!$img) {
            return null;
        }

        $tempPath = $this->tempDir . '/webp_' . bin2hex(random_bytes(8)) . '.png';
        imagepng($img, $tempPath);
        imagedestroy($img);

        return file_exists($tempPath) ? $tempPath : null;
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

/**
 * Quick add watermark function
 *
 * @param string $filePath   Input file path
 * @param string $text       Watermark text
 * @param string $outputPath Output file path
 * @param array  $options    Watermark options
 *
 * @return bool Success status
 */
function pdf_add_watermark(string $filePath, string $text, string $outputPath, array $options = []): bool
{
    $engine = new PDFEngine();
    return $engine->add_watermark($filePath, $text, $outputPath, $options);
}

/**
 * Quick add page numbers function
 *
 * @param string $filePath   Input file path
 * @param string $outputPath Output file path
 * @param array  $options    Page number options
 *
 * @return bool Success status
 */
function pdf_add_page_numbers(string $filePath, string $outputPath, array $options = []): bool
{
    $engine = new PDFEngine();
    return $engine->add_page_numbers($filePath, $outputPath, $options);
}

/**
 * Quick remove metadata function
 *
 * @param string $filePath   Input file path
 * @param string $outputPath Output file path
 *
 * @return bool Success status
 */
function pdf_remove_metadata(string $filePath, string $outputPath): bool
{
    $engine = new PDFEngine();
    return $engine->remove_metadata($filePath, $outputPath);
}

/**
 * Quick remove blank pages function
 *
 * @param string $filePath   Input file path
 * @param string $outputPath Output file path
 * @param int    $threshold  Content threshold
 *
 * @return array Result with removed count
 */
function pdf_remove_blank_pages(string $filePath, string $outputPath, int $threshold = 500): array
{
    $engine = new PDFEngine();
    return $engine->remove_blank_pages($filePath, $outputPath, $threshold);
}

/**
 * Quick flatten PDF function
 *
 * @param string $filePath   Input file path
 * @param string $outputPath Output file path
 *
 * @return bool Success status
 */
function pdf_flatten(string $filePath, string $outputPath): bool
{
    $engine = new PDFEngine();
    return $engine->flatten_pdf($filePath, $outputPath);
}

/**
 * Quick images to PDF function
 *
 * @param array  $imagePaths Image file paths
 * @param string $outputPath Output PDF path
 * @param array  $options    Options
 *
 * @return bool Success status
 */
function images_to_pdf(array $imagePaths, string $outputPath, array $options = []): bool
{
    $engine = new PDFEngine();
    return $engine->images_to_pdf($imagePaths, $outputPath, $options);
}

/**
 * Quick PDF to images function
 *
 * @param string $pdfPath   Input PDF path
 * @param string $outputDir Output directory
 * @param int    $quality   Image quality
 * @param int    $dpi       Resolution
 *
 * @return array Created image paths
 */
function pdf_to_images(string $pdfPath, string $outputDir, int $quality = 90, int $dpi = 150): array
{
    $engine = new PDFEngine();
    return $engine->pdf_to_images($pdfPath, $outputDir, $quality, $dpi);
}

/**
 * Quick extract images from PDF function
 *
 * @param string $pdfPath   Input PDF path
 * @param string $outputDir Output directory
 *
 * @return array Extracted image paths
 */
function extract_images_from_pdf(string $pdfPath, string $outputDir): array
{
    $engine = new PDFEngine();
    return $engine->extract_images_from_pdf($pdfPath, $outputDir);
}

/**
 * Quick PDF to long image function
 *
 * @param string $pdfPath    Input PDF path
 * @param string $outputPath Output image path
 * @param int    $quality    Image quality
 * @param int    $dpi        Resolution
 *
 * @return bool Success status
 */
function pdf_to_long_image(string $pdfPath, string $outputPath, int $quality = 90, int $dpi = 150): bool
{
    $engine = new PDFEngine();
    return $engine->pdf_to_long_image($pdfPath, $outputPath, $quality, $dpi);
}

/**
 * Quick join images function
 *
 * @param array  $imagePaths Image file paths
 * @param string $outputPath Output image path
 * @param string $mode       Join mode: vertical or horizontal
 * @param int    $quality    Image quality
 *
 * @return bool Success status
 */
function join_images(array $imagePaths, string $outputPath, string $mode = 'vertical', int $quality = 90): bool
{
    $engine = new PDFEngine();
    return $engine->join_images($imagePaths, $outputPath, $mode, $quality);
}
