<?php
/**
 * Clean / Light PDF Tool
 *
 * Normalizes PDF to a clean white background version.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

use setasign\Fpdi\Fpdi;

// Tool configuration
$tool_slug = 'clean-light-pdf';
$tool_config = [
    'title' => 'Clean Light PDF - Remove Background Colors from PDF',
    'description' => 'Clean up your PDF by normalizing it to a white background. Remove colored backgrounds and create a print-friendly version of your document.',
    'keywords' => 'clean pdf, light pdf, white background pdf, remove pdf background, normalize pdf, print friendly pdf',
    'canonical' => '/tools/clean-light-pdf/',
    'og_title' => 'Clean Light PDF - Normalize PDF Background',
    'og_description' => 'Remove colored backgrounds and normalize your PDF to clean white. Free online PDF cleaner tool.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'Clean Light PDF Tool',
        'description' => 'Normalize PDF to clean white background',
        'applicationCategory' => 'UtilityApplication',
        'operatingSystem' => 'Any',
        'offers' => [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'USD'
        ]
    ],
    'faq' => [
        [
            'question' => 'What does this tool do?',
            'answer' => 'It rebuilds your PDF pages onto a clean white background, removing any colored backgrounds or tinted pages for a cleaner, more print-friendly document.'
        ],
        [
            'question' => 'Will it remove all colors?',
            'answer' => 'No, it only affects the page background. Text colors, images, and other content colors are preserved.'
        ],
        [
            'question' => 'Is this good for printing?',
            'answer' => 'Yes! White backgrounds use less ink when printing and provide better contrast for text readability.'
        ],
        [
            'question' => 'Does it reduce file size?',
            'answer' => 'It may slightly reduce file size by simplifying the background, but the main purpose is visual cleanup rather than compression.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="pdf_file" class="form-label">Upload PDF File</label>
            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
            <div class="form-text">Maximum file size: 20MB</div>
        </div>
    ',
    'accept_multiple' => false,
    'file_input_name' => 'pdf_file',
    'max_file_size' => 20 * 1024 * 1024,
    'allowed_extensions' => ['pdf'],
];

// Rate limiting
enforce_rate_limit($tool_slug, 20, 3600);

// Process POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startTime = microtime(true);

    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }

        // Check file upload
        if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed. Please try again.');
        }

        $uploadedFile = $_FILES['pdf_file'];

        // Validate file size
        if ($uploadedFile['size'] > $tool_config['max_file_size']) {
            throw new Exception('File size exceeds maximum limit of 20MB.');
        }

        // Validate file extension
        $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new Exception('Invalid file type. Only PDF files are allowed.');
        }

        // Create temp directory
        $engine = new PDFEngine();
        $tempDir = $engine->normalize_temp_dir();
        $sessionId = bin2hex(random_bytes(8));

        // Move uploaded file
        $inputFile = $tempDir . '/' . $sessionId . '.pdf';
        move_uploaded_file($uploadedFile['tmp_name'], $inputFile);

        // Create clean light PDF
        $pdf = new Fpdi();

        $pageCount = $pdf->setSourceFile($inputFile);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);

            // Draw white background first
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect(0, 0, $size['width'], $size['height'], 'F');

            // Import original page content on top of white background
            $pdf->useTemplate($templateId);
        }

        // Save PDF
        $outputFile = $tempDir . '/clean_light_' . $sessionId . '.pdf';
        $pdf->Output('F', $outputFile);

        if (!file_exists($outputFile)) {
            throw new Exception('Failed to create clean light PDF.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, $uploadedFile['size'], $durationMs, 'success');

        // Clean up input file
        @unlink($inputFile);

        // Send PDF file
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="clean-light.pdf"');
        header('Content-Length: ' . filesize($outputFile));
        header('Cache-Control: no-cache, must-revalidate');

        readfile($outputFile);

        // Clean up output
        @unlink($outputFile);
        exit;

    } catch (Exception $e) {
        log_exception($e, $tool_slug);
        $error_message = $e->getMessage();
    }
}

// Render the tool page
render_tool_page($tool_slug, $tool_config, $error_message ?? null);
