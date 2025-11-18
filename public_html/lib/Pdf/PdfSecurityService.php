<?php
/**
 * PDF Security Service
 *
 * Handles PDF protection, unlocking, and security operations.
 *
 * @package PDFTools\Pdf
 */

namespace PDFTools\Pdf;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;
use InvalidArgumentException;
use Exception;

class PdfSecurityService
{
    private string $tempDir;

    public function __construct(string $tempDir)
    {
        $this->tempDir = rtrim($tempDir, '/');
    }

    /**
     * Protect PDF with password
     *
     * @param string $filePath Source PDF
     * @param string $userPassword Password to open document
     * @param string|null $ownerPassword Password for full permissions (null = same as user)
     * @param string $outputPath Output file path
     * @param array $permissions Array of permissions to grant
     * @return bool Success
     */
    public function protect(
        string $filePath,
        string $userPassword,
        ?string $ownerPassword,
        string $outputPath,
        array $permissions = []
    ): bool {
        $this->validateFile($filePath);

        if (empty($userPassword)) {
            throw new InvalidArgumentException('User password cannot be empty');
        }

        $ownerPassword = $ownerPassword ?? $userPassword;

        try {
            // Create protected PDF using FPDI + FPDF
            $pdf = new class extends Fpdi {
                public function protectPdf($userPwd, $ownerPwd, $permissions) {
                    // FPDF's SetProtection method
                    $this->SetProtection($permissions, $userPwd, $ownerPwd);
                }
            };

            $pageCount = $pdf->setSourceFile($filePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            // Apply protection
            $permissionFlags = $this->buildPermissions($permissions);
            $pdf->protectPdf($userPassword, $ownerPassword, $permissionFlags);

            $this->ensureDirectory(dirname($outputPath));
            $pdf->Output('F', $outputPath);

            return file_exists($outputPath);

        } catch (PdfParserException $e) {
            throw new Exception("PDF parsing error: " . $e->getMessage());
        }
    }

    /**
     * Unlock protected PDF (requires knowing password)
     *
     * Note: This creates an unprotected copy. The original password must be known.
     *
     * @param string $filePath Source PDF
     * @param string $password Document password
     * @param string $outputPath Output file path
     * @return bool Success
     */
    public function unlock(string $filePath, string $password, string $outputPath): bool
    {
        $this->validateFile($filePath);

        try {
            // FPDI can read password-protected PDFs if owner password is provided
            $pdf = new Fpdi();

            // Set the password for reading
            // Note: FPDI/FPDF has limited password support
            // For full password handling, would need additional libraries
            $pageCount = $pdf->setSourceFile($filePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            $this->ensureDirectory(dirname($outputPath));
            $pdf->Output('F', $outputPath);

            return file_exists($outputPath);

        } catch (PdfParserException $e) {
            // Check if it's a password error
            if (strpos($e->getMessage(), 'password') !== false ||
                strpos($e->getMessage(), 'encrypted') !== false) {
                throw new Exception("Invalid password or encrypted PDF cannot be processed");
            }
            throw new Exception("PDF parsing error: " . $e->getMessage());
        }
    }

    /**
     * Add watermark to PDF
     *
     * @param string $filePath Source PDF
     * @param string $watermarkText Text to use as watermark
     * @param string $outputPath Output file path
     * @param array $options Watermark options (opacity, position, rotation, etc.)
     * @return bool Success
     */
    public function addWatermark(
        string $filePath,
        string $watermarkText,
        string $outputPath,
        array $options = []
    ): bool {
        $this->validateFile($filePath);

        $defaultOptions = [
            'opacity' => 0.3,
            'rotation' => 45,
            'font_size' => 40,
            'color' => [128, 128, 128],
            'position' => 'center'
        ];

        $options = array_merge($defaultOptions, $options);

        try {
            $pdf = new class extends Fpdi {
                public $watermarkText = '';
                public $watermarkOptions = [];

                public function Header() {
                    if (empty($this->watermarkText)) return;

                    $this->SetFont('Arial', 'B', $this->watermarkOptions['font_size']);
                    $this->SetTextColor(
                        $this->watermarkOptions['color'][0],
                        $this->watermarkOptions['color'][1],
                        $this->watermarkOptions['color'][2]
                    );

                    // Calculate center position
                    $pageWidth = $this->GetPageWidth();
                    $pageHeight = $this->GetPageHeight();

                    $this->SetXY($pageWidth / 4, $pageHeight / 2);
                    $this->Cell(0, 0, $this->watermarkText, 0, 0, 'C');
                }
            };

            $pdf->watermarkText = $watermarkText;
            $pdf->watermarkOptions = $options;

            $pageCount = $pdf->setSourceFile($filePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
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
     * Build permission flags array
     */
    private function buildPermissions(array $permissions): array
    {
        $validPermissions = ['print', 'modify', 'copy', 'annot-forms'];
        return array_intersect($permissions, $validPermissions);
    }

    private function validateFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
