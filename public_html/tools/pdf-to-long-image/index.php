<?php
/**
 * PDF to Long Image Tool
 *
 * Converts all PDF pages into a single long image by stitching them vertically.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Tool configuration
$tool_slug = 'pdf-to-long-image';
$tool_config = [
    'title' => 'PDF to Long Image - Stitch All Pages Vertically',
    'description' => 'Convert your entire PDF document into a single long image by stitching all pages together vertically. Perfect for creating scrollable document images.',
    'keywords' => 'pdf to long image, stitch pdf pages, combine pdf to image, scrollable pdf image, full page screenshot, continuous image',
    'canonical' => '/tools/pdf-to-long-image/',
    'og_title' => 'PDF to Long Image - Combine All Pages into One',
    'og_description' => 'Stitch all PDF pages into a single long image. Create scrollable document images from your PDFs.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'PDF to Long Image Converter',
        'description' => 'Convert PDF to a single long image by stitching pages vertically',
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
            'question' => 'What is a long image?',
            'answer' => 'A long image is created by stacking all PDF pages vertically into one continuous image. This is useful for creating scrollable previews or sharing entire documents as a single image.'
        ],
        [
            'question' => 'What quality should I use?',
            'answer' => 'Use 90-100 for high quality. For smaller file sizes, use 60-80. Very long documents may produce large images, so lower quality can help reduce file size.'
        ],
        [
            'question' => 'Is there a page limit?',
            'answer' => 'While there\'s no strict page limit, very long documents (50+ pages) may produce very large images. Consider using PDF to PNG for large documents.'
        ],
        [
            'question' => 'Is Imagick required?',
            'answer' => 'For best results with actual PDF content rendering, Imagick extension is recommended. Without it, placeholder images will be generated.'
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
                <small>Lower (Smaller file)</small>
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

        // Move uploaded file
        $inputFile = $tempDir . '/' . $sessionId . '.pdf';
        move_uploaded_file($uploadedFile['tmp_name'], $inputFile);

        // Convert PDF to long image
        $outputFile = $tempDir . '/long_image_' . $sessionId . '.png';
        $result = $engine->pdf_to_long_image($inputFile, $outputFile, $quality);

        if (!$result || !file_exists($outputFile)) {
            throw new Exception('Failed to create long image from PDF.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, $uploadedFile['size'], $durationMs, 'success');

        // Clean up input file
        @unlink($inputFile);

        // Send image file
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="long_image.png"');
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
