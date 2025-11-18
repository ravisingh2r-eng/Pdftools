<?php
/**
 * Reorder PDF Pages Tool
 *
 * Rearrange pages in a PDF to a new custom order.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Define tool slug
$tool_slug = 'reorder-pdf';

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
            throw new Exception('Please select a PDF file to reorder.');
        }

        // Check page order input
        if (empty($_POST['page_order'])) {
            throw new Exception('Please enter the new page order (e.g., 3, 1, 2, 5, 4).');
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

        // Get page count
        $totalPages = $pdfEngine->get_page_count($tempPath);

        // Parse page order
        $orderInput = trim($_POST['page_order']);
        $pageOrder = [];
        $orderParts = preg_split('/[,\s]+/', $orderInput);

        foreach ($orderParts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            $page = (int)$part;
            if ($page < 1 || $page > $totalPages) {
                throw new Exception("Page {$page} is out of bounds (1-{$totalPages}).");
            }
            $pageOrder[] = $page;
        }

        if (empty($pageOrder)) {
            throw new Exception('Please enter a valid page order.');
        }

        // Generate output filename
        $outputPath = $pdfEngine->generate_temp_filename('reordered', 'pdf');

        // Reorder the PDF
        $result = $pdfEngine->reorder_pdf($tempPath, $pageOrder, $outputPath);

        if (!$result || !file_exists($outputPath)) {
            throw new Exception('Failed to reorder PDF. Please try again.');
        }

        // Clean up uploaded file
        unlink($uploadedFile);

        // Stream file for download
        $downloadName = 'reordered_' . date('Ymd_His') . '.pdf';
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

// Custom form HTML with page order input
$customFormHtml = $errorHtml . '
<div class="custom-input-group" style="margin-top: 1rem;">
    <label for="page_order" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">New Page Order</label>
    <input type="text" name="page_order" id="page_order" required
           placeholder="e.g., 3, 1, 2, 5, 4"
           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
    <small style="color: #666; display: block; margin-top: 0.25rem;">
        Enter page numbers in the desired order, separated by commas. You can repeat pages or omit them.
    </small>
</div>';

// Tool configuration
$config = [
    // Basic Info
    'tool_slug' => $tool_slug,
    'tool_name' => 'Reorder PDF Pages',
    'primary_keyword' => 'reorder PDF pages online',

    // SEO Meta
    'meta_title' => 'Reorder PDF Pages - Rearrange Page Order Online | ' . SITE_NAME,
    'meta_description' => 'Rearrange PDF pages in any order online for free. Move, duplicate, or reorganize pages in your PDF documents. No registration required.',
    'canonical_url' => BASE_URL . '/tools/' . $tool_slug . '/',

    // Schema Info
    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'og_image_url' => BASE_URL . '/assets/img/reorder-pdf-og.png',

    // Upload Settings
    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your PDF file here or click to browse',
    'button_text' => 'Reorder Pages',

    // Custom form HTML
    'custom_form_html' => $customFormHtml,

    // Content: Introduction
    'short_intro_html' => '
        <p>Rearrange pages in your PDF to any custom order. Move pages around, duplicate pages, or create a new arrangement exactly how you need it.</p>
    ',

    // Content: How It Works
    'how_it_works_html' => '
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Upload PDF File</h3>
                    <p>Click the upload area or drag and drop your PDF file that you want to reorganize.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Enter New Order</h3>
                    <p>Specify the new page order. For a 5-page PDF, "3, 1, 2, 5, 4" puts page 3 first, then 1, 2, 5, 4.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Download Result</h3>
                    <p>Click "Reorder Pages" and download your reorganized PDF with pages in the new order.</p>
                </div>
            </div>
        </div>
    ',

    // Content: Features
    'features_html' => '
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">🔄</div>
                <div class="feature-content">
                    <h3>Flexible Ordering</h3>
                    <p>Arrange pages in any order you need - move, swap, or reverse.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📋</div>
                <div class="feature-content">
                    <h3>Duplicate Pages</h3>
                    <p>Include the same page multiple times by repeating its number.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🔒</div>
                <div class="feature-content">
                    <h3>Secure Processing</h3>
                    <p>Your files are processed securely and deleted immediately after download.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">💯</div>
                <div class="feature-content">
                    <h3>Quality Preserved</h3>
                    <p>Original quality is maintained in all pages.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📱</div>
                <div class="feature-content">
                    <h3>Works Everywhere</h3>
                    <p>Use on any device - desktop, tablet, or smartphone.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">💰</div>
                <div class="feature-content">
                    <h3>100% Free</h3>
                    <p>No registration, no hidden fees, no watermarks.</p>
                </div>
            </div>
        </div>
    ',

    // FAQ Items
    'faq_items' => [
        [
            'q' => 'How do I specify the page order?',
            'a' => 'Enter page numbers separated by commas in the order you want them. For example, "3, 1, 2" puts page 3 first, then pages 1 and 2.'
        ],
        [
            'q' => 'Can I duplicate a page?',
            'a' => 'Yes, simply include the page number multiple times. For example, "1, 1, 2, 3" creates a PDF with page 1 appearing twice at the beginning.'
        ],
        [
            'q' => 'Can I remove pages while reordering?',
            'a' => 'Yes, just don\'t include those page numbers in your order. Any pages not listed will not appear in the output.'
        ],
        [
            'q' => 'Is the original quality preserved?',
            'a' => 'Yes, all pages maintain their original quality exactly as they appear in the source PDF.'
        ],
        [
            'q' => 'Are my files secure?',
            'a' => 'Yes, all files are processed securely and automatically deleted after you download the result.'
        ]
    ],

    // Related Tools
    'related_tools' => get_related_tools($tool_slug)
];

// Render the tool page
render_tool_page($config);
