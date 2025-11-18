<?php
/**
 * Images to PDF Tool
 *
 * Converts multiple images to a PDF document.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Tool configuration
$tool_slug = 'images-to-pdf';
$tool_config = [
    'title' => 'Images to PDF - Convert Multiple Images to PDF',
    'description' => 'Convert JPG, PNG, and WebP images to a PDF document. Combine multiple images into a single PDF file with each image on a separate page.',
    'keywords' => 'images to pdf, jpg to pdf, png to pdf, convert images to pdf, picture to pdf, photo to pdf, image converter',
    'canonical' => '/tools/images-to-pdf/',
    'og_title' => 'Images to PDF Converter - Combine Images into PDF',
    'og_description' => 'Convert multiple images to PDF online. Supports JPG, PNG, WebP. Free and easy to use.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'Images to PDF Converter',
        'description' => 'Convert multiple images to a single PDF document',
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
            'question' => 'What image formats are supported?',
            'answer' => 'We support JPG/JPEG, PNG, GIF, and WebP image formats. WebP images are automatically converted for PDF compatibility.'
        ],
        [
            'question' => 'How many images can I convert at once?',
            'answer' => 'You can upload up to 50 images at once, with a total combined size of up to 100MB.'
        ],
        [
            'question' => 'Will my images be resized?',
            'answer' => 'Images are scaled to fit the page while maintaining their original aspect ratio. They are centered on each page.'
        ],
        [
            'question' => 'What page size is used?',
            'answer' => 'The default page size is A4. Page orientation (portrait/landscape) is automatically determined based on each image\'s dimensions.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="image_files" class="form-label">Select Images</label>
            <input type="file" class="form-control" id="image_files" name="image_files[]" accept=".jpg,.jpeg,.png,.gif,.webp" multiple required>
            <div class="form-text">Supported formats: JPG, PNG, GIF, WebP. Max 50 files, 100MB total.</div>
        </div>
        <div class="mb-3">
            <label for="page_size" class="form-label">Page Size</label>
            <select class="form-select" id="page_size" name="page_size">
                <option value="A4" selected>A4</option>
                <option value="Letter">Letter</option>
                <option value="Legal">Legal</option>
                <option value="A3">A3</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="orientation" class="form-label">Orientation</label>
            <select class="form-select" id="orientation" name="orientation">
                <option value="auto" selected>Auto (based on image)</option>
                <option value="P">Portrait</option>
                <option value="L">Landscape</option>
            </select>
        </div>
    ',
    'accept_multiple' => true,
    'file_input_name' => 'image_files',
    'max_file_size' => 100 * 1024 * 1024,
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
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
        if (!isset($_FILES['image_files']) || empty($_FILES['image_files']['name'][0])) {
            throw new Exception('No files uploaded. Please select at least one image.');
        }

        $files = $_FILES['image_files'];
        $fileCount = count($files['name']);

        // Validate file count
        if ($fileCount > 50) {
            throw new Exception('Too many files. Maximum is 50 images.');
        }

        // Get options
        $pageSize = $_POST['page_size'] ?? 'A4';
        $orientation = $_POST['orientation'] ?? 'auto';

        // Create temp directory
        $engine = new PDFEngine();
        $tempDir = $engine->normalize_temp_dir();
        $sessionId = bin2hex(random_bytes(8));

        // Process uploaded files
        $imagePaths = [];
        $totalSize = 0;

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            // Validate extension
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $tool_config['allowed_extensions'])) {
                continue;
            }

            $totalSize += $files['size'][$i];

            // Check total size
            if ($totalSize > $tool_config['max_file_size']) {
                throw new Exception('Total file size exceeds 100MB limit.');
            }

            // Move to temp directory
            $tempFile = $tempDir . '/' . $sessionId . '_' . $i . '.' . $ext;
            move_uploaded_file($files['tmp_name'][$i], $tempFile);
            $imagePaths[] = $tempFile;
        }

        if (empty($imagePaths)) {
            throw new Exception('No valid images were uploaded.');
        }

        // Convert images to PDF
        $outputFile = $tempDir . '/images_' . $sessionId . '.pdf';

        $options = [
            'pageSize' => $pageSize,
            'orientation' => $orientation,
            'margin' => 10,
            'fitToPage' => true,
        ];

        $result = $engine->images_to_pdf($imagePaths, $outputFile, $options);

        if (!$result || !file_exists($outputFile)) {
            throw new Exception('Failed to create PDF from images.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, $fileCount, $totalSize, $durationMs, 'success');

        // Clean up input files
        foreach ($imagePaths as $imagePath) {
            @unlink($imagePath);
        }

        // Send PDF file
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="images.pdf"');
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
