<?php
/**
 * Extract Images from PDF Tool
 *
 * Extracts embedded images from a PDF file.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Tool configuration
$tool_slug = 'extract-images';
$tool_config = [
    'title' => 'Extract Images from PDF - Get All Embedded Images',
    'description' => 'Extract all embedded images from your PDF document. Download images in their original format (JPG, PNG) as a ZIP file.',
    'keywords' => 'extract images from pdf, get images from pdf, pdf image extractor, export pdf images, download pdf pictures',
    'canonical' => '/tools/extract-images/',
    'og_title' => 'Extract Images from PDF - Download Embedded Pictures',
    'og_description' => 'Extract all images from your PDF document. Free online tool to get embedded pictures from PDF files.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'PDF Image Extractor',
        'description' => 'Extract embedded images from PDF documents',
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
            'question' => 'What types of images can be extracted?',
            'answer' => 'This tool extracts embedded JPEG and PNG images that are stored within the PDF. It finds images in the PDF\'s internal structure.'
        ],
        [
            'question' => 'Will I get all images from my PDF?',
            'answer' => 'The tool extracts images embedded in the PDF stream. Some PDFs may use specialized encoding that cannot be fully extracted without advanced tools.'
        ],
        [
            'question' => 'What format will the extracted images be in?',
            'answer' => 'Images are extracted in their original format - typically JPEG or PNG. All images are packaged in a ZIP file for download.'
        ],
        [
            'question' => 'Why were no images found?',
            'answer' => 'Some PDFs contain text-only content or use image formats that cannot be extracted. Vector graphics and text are not images and won\'t be extracted.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="pdf_file" class="form-label">Select PDF File</label>
            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
            <div class="form-text">Maximum file size: 50MB</div>
        </div>
        <div class="alert alert-info">
            <small><strong>Note:</strong> This tool extracts embedded images from the PDF structure. Results may vary depending on how the PDF was created.</small>
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

        // Create temp directory
        $engine = new PDFEngine();
        $tempDir = $engine->normalize_temp_dir();
        $sessionId = bin2hex(random_bytes(8));
        $outputDir = $tempDir . '/extract_' . $sessionId;
        mkdir($outputDir, 0755, true);

        // Move uploaded file
        $inputFile = $tempDir . '/' . $sessionId . '.pdf';
        move_uploaded_file($uploadedFile['tmp_name'], $inputFile);

        // Extract images from PDF
        $imageFiles = $engine->extract_images_from_pdf($inputFile, $outputDir);

        if (empty($imageFiles)) {
            throw new Exception('No images were found in the PDF. The document may contain only text or vector graphics.');
        }

        // Create ZIP file
        $zipFile = $tempDir . '/extracted_images_' . $sessionId . '.zip';
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
        header('Content-Disposition: attachment; filename="extracted_images.zip"');
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
