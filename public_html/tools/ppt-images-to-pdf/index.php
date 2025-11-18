<?php
/**
 * PPT Images to PDF Tool
 *
 * Converts PowerPoint slide images to PDF document.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Tool configuration
$tool_slug = 'ppt-images-to-pdf';
$tool_config = [
    'title' => 'PPT Images to PDF - Convert Slide Screenshots to PDF',
    'description' => 'Convert PowerPoint slide images or screenshots to a PDF presentation. Each image becomes a separate page in landscape format.',
    'keywords' => 'ppt to pdf, powerpoint images to pdf, slides to pdf, presentation to pdf, slide screenshots pdf',
    'canonical' => '/tools/ppt-images-to-pdf/',
    'og_title' => 'PPT Images to PDF - Convert Slide Images',
    'og_description' => 'Convert PowerPoint slide screenshots to PDF. Free online slide image to PDF converter.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'PPT Images to PDF Converter',
        'description' => 'Convert PowerPoint slide images to PDF',
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
            'question' => 'How do I export slides as images?',
            'answer' => 'In PowerPoint: File > Export > Change File Type > PNG or JPEG. Or File > Save As and choose image format. This creates one image per slide.'
        ],
        [
            'question' => 'What image formats are supported?',
            'answer' => 'We support JPG/JPEG, PNG, and WebP images. These are the common formats PowerPoint exports to.'
        ],
        [
            'question' => 'Will slides be in the correct order?',
            'answer' => 'Slides are added in the order you upload them. Make sure your files are named with numbers (Slide1.png, Slide2.png) for easy sorting.'
        ],
        [
            'question' => 'What page size is used?',
            'answer' => 'Slides are placed on landscape-oriented pages by default, which matches typical PowerPoint dimensions. Images are centered and scaled to fit.'
        ],
        [
            'question' => 'How many slides can I convert?',
            'answer' => 'You can upload up to 100 slides at once with a combined file size of up to 100MB.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="slide_files" class="form-label">Upload Slide Images</label>
            <input type="file" class="form-control" id="slide_files" name="slide_files[]" accept=".jpg,.jpeg,.png,.webp" multiple required>
            <div class="form-text">Supported formats: JPG, PNG, WebP. Max 100 files, 100MB total. Upload in slide order.</div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="page_size" class="form-label">Page Size</label>
                <select class="form-select" id="page_size" name="page_size">
                    <option value="A4" selected>A4</option>
                    <option value="Letter">Letter</option>
                    <option value="A3">A3</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="orientation" class="form-label">Orientation</label>
                <select class="form-select" id="orientation" name="orientation">
                    <option value="L" selected>Landscape (Recommended)</option>
                    <option value="P">Portrait</option>
                    <option value="auto">Auto (based on image)</option>
                </select>
            </div>
        </div>
        <div class="alert alert-info">
            <small><strong>Tip:</strong> Export your PowerPoint slides as images first (PNG recommended for best quality), then upload them here in order.</small>
        </div>
    ',
    'accept_multiple' => true,
    'file_input_name' => 'slide_files',
    'max_file_size' => 100 * 1024 * 1024,
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
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
        if (!isset($_FILES['slide_files']) || empty($_FILES['slide_files']['name'][0])) {
            throw new Exception('No files uploaded. Please select slide images.');
        }

        $files = $_FILES['slide_files'];
        $fileCount = count($files['name']);

        // Validate file count
        if ($fileCount > 100) {
            throw new Exception('Too many files. Maximum is 100 slides.');
        }

        // Get options
        $pageSize = $_POST['page_size'] ?? 'A4';
        $orientation = $_POST['orientation'] ?? 'L';

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

            // Move to temp directory with sort-friendly name
            $tempFile = $tempDir . '/' . $sessionId . '_' . str_pad($i, 4, '0', STR_PAD_LEFT) . '.' . $ext;
            move_uploaded_file($files['tmp_name'][$i], $tempFile);
            $imagePaths[] = $tempFile;
        }

        if (empty($imagePaths)) {
            throw new Exception('No valid slide images were uploaded.');
        }

        // Sort by filename to maintain order
        sort($imagePaths);

        // Convert slides to PDF
        $outputFile = $tempDir . '/presentation_' . $sessionId . '.pdf';

        $options = [
            'pageSize' => $pageSize,
            'orientation' => $orientation,
            'margin' => 5, // Small margin for slides
            'fitToPage' => true,
        ];

        $result = $engine->images_to_pdf($imagePaths, $outputFile, $options);

        if (!$result || !file_exists($outputFile)) {
            throw new Exception('Failed to create PDF from slides.');
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
        header('Content-Disposition: attachment; filename="presentation.pdf"');
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
