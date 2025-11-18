<?php
/**
 * Join Images Tool
 *
 * Combines multiple images vertically or horizontally.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Tool configuration
$tool_slug = 'join-images';
$tool_config = [
    'title' => 'Join Images - Combine Pictures Vertically or Horizontally',
    'description' => 'Combine multiple images into one by joining them vertically or horizontally. Create collages, panoramas, or concatenated images easily.',
    'keywords' => 'join images, combine pictures, merge images, image collage, stitch images, concatenate photos, vertical join, horizontal join',
    'canonical' => '/tools/join-images/',
    'og_title' => 'Join Images Online - Merge Pictures Together',
    'og_description' => 'Combine multiple images into one. Join vertically or horizontally. Free online image joiner.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'Image Joiner',
        'description' => 'Combine multiple images vertically or horizontally',
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
            'answer' => 'We support JPG/JPEG, PNG, GIF, and WebP formats. The output will be a PNG image.'
        ],
        [
            'question' => 'What\'s the difference between vertical and horizontal joining?',
            'answer' => 'Vertical joining stacks images top to bottom, creating a tall image. Horizontal joining places images side by side, creating a wide image.'
        ],
        [
            'question' => 'What if my images have different sizes?',
            'answer' => 'Images are centered along the perpendicular axis. For vertical joining, narrower images are centered horizontally. For horizontal joining, shorter images are centered vertically.'
        ],
        [
            'question' => 'How many images can I join?',
            'answer' => 'You can join up to 20 images at once with a total combined size of up to 50MB.'
        ],
        [
            'question' => 'In what order will images be joined?',
            'answer' => 'Images are joined in the order they are uploaded. For vertical mode: top to bottom. For horizontal mode: left to right.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="image_files" class="form-label">Select Images</label>
            <input type="file" class="form-control" id="image_files" name="image_files[]" accept=".jpg,.jpeg,.png,.gif,.webp" multiple required>
            <div class="form-text">Supported formats: JPG, PNG, GIF, WebP. Max 20 files, 50MB total.</div>
        </div>
        <div class="mb-3">
            <label for="join_mode" class="form-label">Join Mode</label>
            <select class="form-select" id="join_mode" name="join_mode">
                <option value="vertical" selected>Vertical (Top to Bottom)</option>
                <option value="horizontal">Horizontal (Left to Right)</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="quality" class="form-label">Output Quality</label>
            <input type="range" class="form-range" id="quality" name="quality" min="1" max="100" value="90" oninput="document.getElementById(\'quality_value\').textContent = this.value">
            <div class="d-flex justify-content-between">
                <small>Lower (Smaller file)</small>
                <strong id="quality_value">90</strong>
                <small>Higher (Better quality)</small>
            </div>
        </div>
    ',
    'accept_multiple' => true,
    'file_input_name' => 'image_files',
    'max_file_size' => 50 * 1024 * 1024,
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
            throw new Exception('No files uploaded. Please select at least two images to join.');
        }

        $files = $_FILES['image_files'];
        $fileCount = count($files['name']);

        // Validate file count
        if ($fileCount < 2) {
            throw new Exception('Please select at least 2 images to join.');
        }

        if ($fileCount > 20) {
            throw new Exception('Too many files. Maximum is 20 images.');
        }

        // Get options
        $joinMode = $_POST['join_mode'] ?? 'vertical';
        $quality = isset($_POST['quality']) ? (int)$_POST['quality'] : 90;
        $quality = max(1, min(100, $quality));

        // Validate join mode
        if (!in_array($joinMode, ['vertical', 'horizontal'])) {
            $joinMode = 'vertical';
        }

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
                throw new Exception('Total file size exceeds 50MB limit.');
            }

            // Move to temp directory
            $tempFile = $tempDir . '/' . $sessionId . '_' . $i . '.' . $ext;
            move_uploaded_file($files['tmp_name'][$i], $tempFile);
            $imagePaths[] = $tempFile;
        }

        if (count($imagePaths) < 2) {
            throw new Exception('At least 2 valid images are required to join.');
        }

        // Join images
        $outputFile = $tempDir . '/joined_' . $sessionId . '.png';
        $result = $engine->join_images($imagePaths, $outputFile, $joinMode, $quality);

        if (!$result || !file_exists($outputFile)) {
            throw new Exception('Failed to join images.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, $fileCount, $totalSize, $durationMs, 'success');

        // Clean up input files
        foreach ($imagePaths as $imagePath) {
            @unlink($imagePath);
        }

        // Send output image
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="joined_image.png"');
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
