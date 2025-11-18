<?php
/**
 * Add Page Numbers Tool
 *
 * Add page numbers to PDF documents.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Define tool slug
$tool_slug = 'add-page-numbers';

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

        $position = $_POST['position'] ?? 'bottom-center';
        $startNumber = isset($_POST['start_number']) ? (int)$_POST['start_number'] : 1;
        $format = $_POST['format'] ?? 'Page {n}';

        // Validate position
        $validPositions = ['bottom-center', 'bottom-left', 'bottom-right', 'top-center', 'top-left', 'top-right'];
        if (!in_array($position, $validPositions)) {
            $position = 'bottom-center';
        }

        // Validate start number
        $startNumber = max(1, min(9999, $startNumber));

        // Validate format
        $validFormats = [
            'Page {n}' => 'Page {n}',
            '{n}' => '{n}',
            'Page {n} of {total}' => 'Page {n} of {total}',
            '{n} / {total}' => '{n} / {total}',
            '- {n} -' => '- {n} -'
        ];
        if (!isset($validFormats[$format])) {
            $format = 'Page {n}';
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
        $outputPath = $pdfEngine->generate_temp_filename('numbered', 'pdf');

        // Add page numbers
        $options = [
            'position' => $position,
            'startNumber' => $startNumber,
            'format' => $format,
            'fontSize' => 10,
            'color' => [0, 0, 0],
            'margin' => 20
        ];

        $result = $pdfEngine->add_page_numbers($tempPath, $outputPath, $options);

        if (!$result || !file_exists($outputPath)) {
            throw new Exception('Failed to add page numbers. Please try again.');
        }

        // Clean up uploaded file
        unlink($uploadedFile);

        // Stream file for download
        $downloadName = 'numbered_' . date('Ymd_His') . '.pdf';
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

// Custom form HTML with page number options
$customFormHtml = $errorHtml . '
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
    <div class="custom-input-group">
        <label for="position" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Position</label>
        <select name="position" id="position" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
            <option value="bottom-center" selected>Bottom Center</option>
            <option value="bottom-left">Bottom Left</option>
            <option value="bottom-right">Bottom Right</option>
            <option value="top-center">Top Center</option>
            <option value="top-left">Top Left</option>
            <option value="top-right">Top Right</option>
        </select>
    </div>

    <div class="custom-input-group">
        <label for="start_number" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Start Number</label>
        <input type="number" name="start_number" id="start_number" value="1" min="1" max="9999"
               style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
    </div>
</div>

<div class="custom-input-group" style="margin-top: 1rem;">
    <label for="format" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Format</label>
    <select name="format" id="format" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
        <option value="Page {n}" selected>Page 1, Page 2, ...</option>
        <option value="{n}">1, 2, 3, ...</option>
        <option value="Page {n} of {total}">Page 1 of 10, Page 2 of 10, ...</option>
        <option value="{n} / {total}">1 / 10, 2 / 10, ...</option>
        <option value="- {n} -">- 1 -, - 2 -, ...</option>
    </select>
</div>';

// Tool configuration
$config = [
    // Basic Info
    'tool_slug' => $tool_slug,
    'tool_name' => 'Add Page Numbers',
    'primary_keyword' => 'add page numbers to PDF',

    // SEO Meta
    'meta_title' => 'Add Page Numbers to PDF - Number PDF Pages Online | ' . SITE_NAME,
    'meta_description' => 'Add page numbers to PDF documents online for free. Choose position, format, and starting number. Perfect for reports, books, and documents.',
    'canonical_url' => BASE_URL . '/tools/' . $tool_slug . '/',

    // Schema Info
    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'og_image_url' => BASE_URL . '/assets/img/add-page-numbers-og.png',

    // Upload Settings
    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your PDF file here or click to browse',
    'button_text' => 'Add Page Numbers',

    // Custom form HTML
    'custom_form_html' => $customFormHtml,

    // Content: Introduction
    'short_intro_html' => '
        <p>Add professional page numbers to your PDF documents. Choose from multiple positions and formats to match your document style.</p>
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
                    <h3>Choose Settings</h3>
                    <p>Select position, starting number, and format for your page numbers.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Download Result</h3>
                    <p>Click "Add Page Numbers" and download your numbered PDF.</p>
                </div>
            </div>
        </div>
    ',

    // Content: Features
    'features_html' => '
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">📍</div>
                <div class="feature-content">
                    <h3>6 Positions</h3>
                    <p>Place numbers at top or bottom, left, center, or right.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🔢</div>
                <div class="feature-content">
                    <h3>Custom Start</h3>
                    <p>Start numbering from any page number you choose.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📝</div>
                <div class="feature-content">
                    <h3>Multiple Formats</h3>
                    <p>Choose from "Page X", "X of Y", or simple numbers.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">💯</div>
                <div class="feature-content">
                    <h3>Quality Preserved</h3>
                    <p>Original content remains unchanged.</p>
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
                    <p>No registration, no hidden fees.</p>
                </div>
            </div>
        </div>
    ',

    // FAQ Items
    'faq_items' => [
        [
            'q' => 'Where can I place the page numbers?',
            'a' => 'You can place page numbers in 6 positions: top-left, top-center, top-right, bottom-left, bottom-center, or bottom-right.'
        ],
        [
            'q' => 'Can I start numbering from a specific page?',
            'a' => 'Yes, use the "Start Number" field to begin numbering from any number (e.g., start from 5 if first pages are cover/contents).'
        ],
        [
            'q' => 'What format options are available?',
            'a' => 'Choose from: "Page 1", just "1", "Page 1 of 10", "1 / 10", or "- 1 -". More formats coming soon!'
        ],
        [
            'q' => 'Will existing page numbers be affected?',
            'a' => 'No, we add numbers as an overlay. Existing content including any previous page numbers will remain.'
        ],
        [
            'q' => 'Is the original quality preserved?',
            'a' => 'Yes, the PDF content maintains its original quality with numbers added on top.'
        ]
    ],

    // Related Tools
    'related_tools' => get_related_tools($tool_slug)
];

// Render the tool page
render_tool_page($config);
