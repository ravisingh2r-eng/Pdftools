<?php
/**
 * Add Watermark Tool
 *
 * Add text watermark to PDF pages.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Define tool slug
$tool_slug = 'add-watermark';

// Check if tool exists and is active in registry
if (!is_tool_active($tool_slug)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Tool Not Found</title></head><body>';
    echo '<h1>Tool Not Found</h1><p>This tool is not available. <a href="/">Return to homepage</a></p>';
    echo '</body></html>';
    exit;
}

// Get tool metadata from registry
$tool_data = get_tool($tool_slug);

// Initialize error variable for display
$error = null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check rate limit (20 requests per hour per IP for this tool)
    enforce_rate_limit($tool_slug, 20, 3600);

    $uploadedFile = null;
    $startTime = microtime(true);
    $totalSizeIn = get_upload_size($_FILES, 'pdf_file');
    $fileCount = 1;

    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }

        // Check if file was uploaded
        if (empty($_FILES['pdf_file']['name'])) {
            throw new Exception('Please select a PDF file.');
        }

        // Check watermark text
        if (empty($_POST['watermark_text'])) {
            throw new Exception('Please enter watermark text.');
        }

        $watermarkText = trim($_POST['watermark_text']);
        $position = $_POST['position'] ?? 'center';
        $opacity = isset($_POST['opacity']) ? (float)$_POST['opacity'] / 100 : 0.3;
        $fontSize = isset($_POST['font_size']) ? (int)$_POST['font_size'] : 40;

        // Validate opacity
        $opacity = max(0.1, min(1.0, $opacity));

        // Validate font size
        $fontSize = max(10, min(100, $fontSize));

        // Validate position
        $validPositions = ['center', 'top-left', 'top-right', 'bottom-left', 'bottom-right'];
        if (!in_array($position, $validPositions)) {
            $position = 'center';
        }

        $fileName = $_FILES['pdf_file']['name'];
        $tmpName = $_FILES['pdf_file']['tmp_name'];
        $fileError = $_FILES['pdf_file']['error'];
        $fileSize = $_FILES['pdf_file']['size'];

        // Check for upload errors
        if ($fileError !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            ];
            $errorMsg = $errorMessages[$fileError] ?? 'Unknown upload error';
            throw new Exception("Upload error: {$errorMsg}");
        }

        // Validate file size
        if ($fileSize > MAX_FILE_SIZE) {
            $maxMB = MAX_FILE_SIZE / (1024 * 1024);
            throw new Exception("File exceeds maximum size of {$maxMB}MB.");
        }

        // Validate file extension
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new Exception("File is not a PDF.");
        }

        // Initialize PDF engine
        $pdfEngine = new PDFEngine();

        // Generate temp filename and move uploaded file
        $tempPath = $pdfEngine->generate_temp_filename('upload', 'pdf');

        if (!move_uploaded_file($tmpName, $tempPath)) {
            throw new Exception("Failed to save uploaded file.");
        }

        $uploadedFile = $tempPath;

        // Validate PDF content
        if (!$pdfEngine->is_valid_pdf($tempPath)) {
            throw new Exception("File is not a valid PDF.");
        }

        // Generate output filename
        $outputPath = $pdfEngine->generate_temp_filename('watermarked', 'pdf');

        // Add watermark
        $options = [
            'position' => $position,
            'opacity' => $opacity,
            'fontSize' => $fontSize,
            'angle' => ($position === 'center') ? 45 : 0,
            'color' => [128, 128, 128]
        ];

        $result = $pdfEngine->add_watermark($tempPath, $watermarkText, $outputPath, $options);

        if (!$result || !file_exists($outputPath)) {
            throw new Exception('Failed to add watermark. Please try again.');
        }

        // Clean up uploaded file
        unlink($uploadedFile);

        // Stream file for download
        $downloadName = 'watermarked_' . date('Ymd_His') . '.pdf';
        $outputSize = filesize($outputPath);

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . $outputSize);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Log successful usage
        $durationMs = calculate_duration_ms($startTime);
        log_usage($tool_slug, $fileCount, $totalSizeIn, $durationMs, 'success');

        readfile($outputPath);
        unlink($outputPath);
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();

        // Log failed usage
        $durationMs = calculate_duration_ms($startTime);
        log_usage($tool_slug, $fileCount, $totalSizeIn, $durationMs, 'error', $error);

        // Log to centralized error logger
        log_exception($e, $tool_slug, [
            'file_count' => $fileCount,
            'total_size' => $totalSizeIn,
            'duration_ms' => $durationMs
        ]);

        // Clean up on error
        if ($uploadedFile && file_exists($uploadedFile)) {
            unlink($uploadedFile);
        }
    }
}

// Build error message HTML if there's an error
$errorHtml = '';
if ($error) {
    $errorHtml = '<div class="message message-error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
}

// Custom form HTML with watermark options
$customFormHtml = $errorHtml . '
<div class="custom-input-group" style="margin-top: 1rem;">
    <label for="watermark_text" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Watermark Text</label>
    <input type="text" name="watermark_text" id="watermark_text" required
           placeholder="e.g., CONFIDENTIAL, DRAFT, Your Company Name"
           maxlength="50"
           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
    <div class="custom-input-group">
        <label for="position" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Position</label>
        <select name="position" id="position" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
            <option value="center" selected>Center (Diagonal)</option>
            <option value="top-left">Top Left</option>
            <option value="top-right">Top Right</option>
            <option value="bottom-left">Bottom Left</option>
            <option value="bottom-right">Bottom Right</option>
        </select>
    </div>

    <div class="custom-input-group">
        <label for="opacity" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Opacity (%)</label>
        <input type="number" name="opacity" id="opacity" value="30" min="10" max="100"
               style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
    </div>
</div>

<div class="custom-input-group" style="margin-top: 1rem;">
    <label for="font_size" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Font Size</label>
    <input type="number" name="font_size" id="font_size" value="40" min="10" max="100"
           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
</div>';

// Tool configuration
$config = [
    // Basic Info
    'tool_slug' => $tool_slug,
    'tool_name' => 'Add Watermark',
    'primary_keyword' => 'add watermark to PDF',

    // SEO Meta
    'meta_title' => 'Add Watermark to PDF - Text Watermark Online | ' . SITE_NAME,
    'meta_description' => 'Add text watermarks to PDF files online for free. Customize position, opacity, and font size. Perfect for marking documents as confidential or draft.',
    'canonical_url' => BASE_URL . '/tools/' . $tool_slug . '/',

    // Schema Info
    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'og_image_url' => BASE_URL . '/assets/img/add-watermark-og.png',

    // Upload Settings
    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your PDF file here or click to browse',
    'button_text' => 'Add Watermark',

    // Custom form HTML
    'custom_form_html' => $customFormHtml,

    // Content: Introduction
    'short_intro_html' => '
        <p>Add custom text watermarks to your PDF documents. Mark files as confidential, draft, or with your company name. Fully customizable position and opacity.</p>
    ',

    // Content: How It Works
    'how_it_works_html' => '
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Upload PDF File</h3>
                    <p>Click the upload area or drag and drop your PDF file.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Customize Watermark</h3>
                    <p>Enter your watermark text and choose position, opacity, and font size.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Download Result</h3>
                    <p>Click "Add Watermark" and download your watermarked PDF.</p>
                </div>
            </div>
        </div>
    ',

    // Content: Features
    'features_html' => '
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">✍️</div>
                <div class="feature-content">
                    <h3>Custom Text</h3>
                    <p>Add any text as your watermark - company name, "CONFIDENTIAL", "DRAFT", etc.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🎯</div>
                <div class="feature-content">
                    <h3>Multiple Positions</h3>
                    <p>Place watermark in center (diagonal), corners, or edges.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🔧</div>
                <div class="feature-content">
                    <h3>Adjustable Opacity</h3>
                    <p>Control transparency from subtle to prominent.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📐</div>
                <div class="feature-content">
                    <h3>Custom Font Size</h3>
                    <p>Adjust the watermark size to fit your needs.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🔒</div>
                <div class="feature-content">
                    <h3>Secure Processing</h3>
                    <p>Files are processed securely and deleted after download.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">💰</div>
                <div class="feature-content">
                    <h3>100% Free</h3>
                    <p>No registration, no hidden fees, no watermarks on our watermarks!</p>
                </div>
            </div>
        </div>
    ',

    // FAQ Items
    'faq_items' => [
        [
            'q' => 'What positions are available for the watermark?',
            'a' => 'You can place the watermark in the center (diagonal), top-left, top-right, bottom-left, or bottom-right of each page.'
        ],
        [
            'q' => 'Can I adjust how visible the watermark is?',
            'a' => 'Yes, use the opacity slider to control transparency. Lower values (10-30%) are subtle, while higher values (70-100%) are more prominent.'
        ],
        [
            'q' => 'Will the watermark appear on every page?',
            'a' => 'Yes, the watermark is automatically added to all pages in the PDF.'
        ],
        [
            'q' => 'Can I use special characters in the watermark?',
            'a' => 'Basic Latin characters work best. Some special characters may not display correctly.'
        ],
        [
            'q' => 'Is the original quality preserved?',
            'a' => 'Yes, the original PDF content maintains its quality with the watermark overlaid on top.'
        ]
    ],

    // Related Tools
    'related_tools' => get_related_tools($tool_slug)
];

// Render the tool page
render_tool_page($config);
