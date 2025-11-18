<?php
/**
 * PDF to PNG Tool
 *
 * Converts PDF pages to PNG images.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Tool configuration
$tool_slug = 'pdf-to-png';
$tool_config = [
    'title' => 'PDF to PNG - Convert PDF Pages to Images',
    'description' => 'Convert each page of your PDF document to high-quality PNG images. Choose quality settings for optimal file size and clarity.',
    'keywords' => 'pdf to png, convert pdf to image, pdf to picture, export pdf pages, pdf page to png, pdf converter',
    'canonical' => '/tools/pdf-to-png/',
    'og_title' => 'PDF to PNG Converter - Export PDF Pages as Images',
    'og_description' => 'Convert PDF pages to PNG images online. Free, fast, and secure PDF to image conversion.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'PDF to PNG Converter',
        'description' => 'Convert PDF pages to PNG images',
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
            'question' => 'How do I convert a PDF to PNG images?',
            'answer' => 'Upload your PDF file, select your desired quality (1-100), and click Convert. Each page will be exported as a separate PNG image in a ZIP file.'
        ],
        [
            'question' => 'What quality setting should I use?',
            'answer' => 'Use 90-100 for high quality prints and presentations. Use 60-80 for web use and sharing. Lower values create smaller files but less detail.'
        ],
        [
            'question' => 'Can I convert a multi-page PDF?',
            'answer' => 'Yes! Each page of your PDF will be converted to a separate PNG image, all packaged in a convenient ZIP file for download.'
        ],
        [
            'question' => 'Is Imagick required for this tool?',
            'answer' => 'For best results, Imagick extension is recommended. Without it, placeholder images with page dimensions will be generated.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="pdf_file" class="form-label">Select PDF File</label>
            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
            <div class="form-text">Maximum file size: 50MB</div>
        </div>
        <div class="mb-3">
            <label for="quality" class="form-label">Image Quality</label>
            <input type="range" class="form-range" id="quality" name="quality" min="1" max="100" value="90" oninput="document.getElementById(\'quality_value\').textContent = this.value">
            <div class="d-flex justify-content-between">
                <small>Lower (Smaller files)</small>
                <strong id="quality_value">90</strong>
                <small>Higher (Better quality)</small>
            </div>
        </div>
    ',
    'accept_multiple' => false,
    'file_input_name' => 'pdf_file',
    'max_file_size' => 50 * 1024 * 1024,
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
            throw new Exception('File size exceeds maximum limit of 50MB.');
        }

        // Validate file extension
        $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $tool_config['allowed_extensions'])) {
            throw new Exception('Invalid file type. Only PDF files are allowed.');
        }

        // Get quality parameter
        $quality = isset($_POST['quality']) ? (int)$_POST['quality'] : 90;
        $quality = max(1, min(100, $quality));

        // Create temp directory
        $engine = new PDFEngine();
        $tempDir = $engine->normalize_temp_dir();
        $sessionId = bin2hex(random_bytes(8));
        $outputDir = $tempDir . '/png_' . $sessionId;
        mkdir($outputDir, 0755, true);

        // Move uploaded file
        $inputFile = $tempDir . '/' . $sessionId . '.pdf';
        move_uploaded_file($uploadedFile['tmp_name'], $inputFile);

        // Convert PDF to images
        $imageFiles = $engine->pdf_to_images($inputFile, $outputDir, $quality);

        if (empty($imageFiles)) {
            throw new Exception('No images were generated from the PDF.');
        }

        // Create ZIP file
        $zipFile = $tempDir . '/pdf_images_' . $sessionId . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
            throw new Exception('Failed to create ZIP archive.');
        }

        foreach ($imageFiles as $imageFile) {
            $zip->addFile($imageFile, basename($imageFile));
        }

        $zip->close();

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, $uploadedFile['size'], $durationMs, 'success');

        // Clean up input and image files
        @unlink($inputFile);
        foreach ($imageFiles as $imageFile) {
            @unlink($imageFile);
        }
        @rmdir($outputDir);

        // Send ZIP file
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="pdf_images.zip"');
        header('Content-Length: ' . filesize($zipFile));
        header('Cache-Control: no-cache, must-revalidate');

        readfile($zipFile);

        // Clean up ZIP
        @unlink($zipFile);
        exit;

    } catch (Exception $e) {
        log_exception($e, $tool_slug);
        $error_message = $e->getMessage();
    }
}

// Render the tool page
render_tool_page($tool_slug, $tool_config, $error_message ?? null);
