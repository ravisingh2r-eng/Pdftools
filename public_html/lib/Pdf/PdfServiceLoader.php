<?php
/**
 * PDF Service Loader
 *
 * Factory class for loading PDF service instances.
 * Provides backwards compatibility with legacy PDFEngine usage.
 *
 * @package PDFTools\Pdf
 */

namespace PDFTools\Pdf;

class PdfServiceLoader
{
    private static ?PdfServiceLoader $instance = null;
    private string $tempDir;

    // Service instances (lazy loaded)
    private ?PdfMergeService $mergeService = null;
    private ?PdfSplitService $splitService = null;
    private ?PdfSecurityService $securityService = null;
    private ?PdfOptimizeService $optimizeService = null;
    private ?PdfConvertService $convertService = null;

    private function __construct(string $tempDir)
    {
        $this->tempDir = $tempDir;
    }

    /**
     * Get singleton instance
     *
     * @param string|null $tempDir Temp directory (required on first call)
     * @return PdfServiceLoader
     */
    public static function getInstance(?string $tempDir = null): PdfServiceLoader
    {
        if (self::$instance === null) {
            if ($tempDir === null) {
                $tempDir = defined('TEMP_DIR') ? TEMP_DIR : sys_get_temp_dir();
            }
            self::$instance = new self($tempDir);
        }

        return self::$instance;
    }

    /**
     * Get merge service
     */
    public function getMergeService(): PdfMergeService
    {
        if ($this->mergeService === null) {
            $this->mergeService = new PdfMergeService($this->tempDir);
        }
        return $this->mergeService;
    }

    /**
     * Get split service
     */
    public function getSplitService(): PdfSplitService
    {
        if ($this->splitService === null) {
            $this->splitService = new PdfSplitService($this->tempDir);
        }
        return $this->splitService;
    }

    /**
     * Get security service
     */
    public function getSecurityService(): PdfSecurityService
    {
        if ($this->securityService === null) {
            $this->securityService = new PdfSecurityService($this->tempDir);
        }
        return $this->securityService;
    }

    /**
     * Get optimize service
     */
    public function getOptimizeService(): PdfOptimizeService
    {
        if ($this->optimizeService === null) {
            $this->optimizeService = new PdfOptimizeService($this->tempDir);
        }
        return $this->optimizeService;
    }

    /**
     * Get convert service
     */
    public function getConvertService(): PdfConvertService
    {
        if ($this->convertService === null) {
            $this->convertService = new PdfConvertService($this->tempDir);
        }
        return $this->convertService;
    }

    /**
     * Reset singleton (for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}

// =============================================================================
// BACKWARDS COMPATIBILITY HELPER FUNCTIONS
// =============================================================================

/**
 * Get PDF services loader
 *
 * @return PdfServiceLoader
 */
function pdf_services(): PdfServiceLoader
{
    return PdfServiceLoader::getInstance();
}

/**
 * Merge PDFs using new service
 *
 * @deprecated Use pdf_services()->getMergeService()->merge() instead
 */
function merge_pdfs(array $filePaths, string $outputPath): bool
{
    return pdf_services()->getMergeService()->merge($filePaths, $outputPath);
}

/**
 * Split PDF using new service
 *
 * @deprecated Use pdf_services()->getSplitService()->splitByRange() instead
 */
function split_pdf(string $filePath, array $ranges, string $outputDir): array
{
    return pdf_services()->getSplitService()->splitByRange($filePath, $ranges, $outputDir);
}

/**
 * Compress PDF using new service
 *
 * @deprecated Use pdf_services()->getOptimizeService()->compressBasic() instead
 */
function compress_pdf(string $filePath, string $outputPath, string $mode = 'medium'): bool
{
    return pdf_services()->getOptimizeService()->compressBasic($filePath, $outputPath, $mode);
}

/**
 * Extract text from PDF using new service
 *
 * @deprecated Use pdf_services()->getConvertService()->pdfToText() instead
 */
function pdf_to_text(string $filePath): string
{
    return pdf_services()->getConvertService()->pdfToText($filePath);
}
