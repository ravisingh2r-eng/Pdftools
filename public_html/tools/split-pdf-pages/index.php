<?php
/**
 * Split PDF into Single Pages Tool
 *
 * Split a PDF into individual single-page files.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Define tool slug
$tool_slug = 'split-pdf-pages';

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
    $outputFiles = [];
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
            throw new Exception('Please select a PDF file to split.');
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

        if ($totalPages < 2) {
            throw new Exception("PDF must have at least 2 pages to split.");
        }

        // Create output directory
        $outputDir = $pdfEngine->generate_temp_filename('split_output', '');
        mkdir($outputDir, 0755, true);

        // Split into individual pages
        $outputFiles = $pdfEngine->split_all_pages($tempPath, $outputDir);

        if (empty($outputFiles)) {
            throw new Exception('Failed to split PDF. Please try again.');
        }

        // Create ZIP file
        $zipPath = $pdfEngine->generate_temp_filename('split_pages', 'zip');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new Exception('Failed to create ZIP archive.');
        }

        foreach ($outputFiles as $index => $file) {
            $zip->addFile($file, 'page_' . ($index + 1) . '.pdf');
        }

        $zip->close();

        // Clean up individual files
        $pdfEngine->delete_temp_files($outputFiles);
        array_map('unlink', glob($outputDir . '/*'));
        rmdir($outputDir);

        // Clean up uploaded file
        unlink($uploadedFile);

        // Stream ZIP for download
        $downloadName = 'split_pages_' . date('Ymd_His') . '.zip';
        $outputSize = filesize($zipPath);

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . $outputSize);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Log successful usage
        $durationMs = calculate_duration_ms($startTime);
        log_usage($tool_slug, $fileCount, $totalSizeIn, $durationMs, 'success');

        readfile($zipPath);
        unlink($zipPath);
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
        foreach ($outputFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}

// Build error message HTML if there's an error
$errorHtml = '';
if ($error) {
    $errorHtml = '<div class="message message-error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
}

// Tool configuration
$config = [
    // Basic Info
    'tool_slug' => $tool_slug,
    'tool_name' => 'Split PDF into Pages',
    'primary_keyword' => 'split PDF into single pages',

    // SEO Meta
    'meta_title' => 'Split PDF into Single Pages - Extract Every Page | ' . SITE_NAME,
    'meta_description' => 'Split any PDF into individual single-page files online for free. Get a ZIP file with each page as a separate PDF. No registration required.',
    'canonical_url' => BASE_URL . '/tools/' . $tool_slug . '/',

    // Schema Info
    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'og_image_url' => BASE_URL . '/assets/img/split-pdf-pages-og.png',

    // Upload Settings
    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your PDF file here or click to browse',
    'button_text' => 'Split into Pages',

    // Error message (if any)
    'custom_form_html' => $errorHtml,

    // Content: Introduction
    'short_intro_html' => '
        <p>Split your PDF into individual pages instantly. Each page becomes its own PDF file, delivered in a convenient ZIP archive.</p>
    ',

    // Content: How It Works
    'how_it_works_html' => '
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Upload PDF File</h3>
                    <p>Click the upload area or drag and drop your PDF file that you want to split into pages.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Automatic Splitting</h3>
                    <p>Our tool automatically separates each page into its own individual PDF file.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Download ZIP</h3>
                    <p>Click "Split into Pages" and download a ZIP file containing all your individual page PDFs.</p>
                </div>
            </div>
        </div>
    ',

    // Content: Features
    'features_html' => '
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">📄</div>
                <div class="feature-content">
                    <h3>Every Page Separated</h3>
                    <p>Each page becomes its own PDF file for maximum flexibility.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📦</div>
                <div class="feature-content">
                    <h3>ZIP Download</h3>
                    <p>All pages packaged in a convenient ZIP file for easy download.</p>
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
                    <p>Original quality is maintained in every extracted page.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🚀</div>
                <div class="feature-content">
                    <h3>Fast Processing</h3>
                    <p>Split even large PDFs in seconds with optimized processing.</p>
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
            'q' => 'How are the pages named?',
            'a' => 'Each page is named sequentially: page_1.pdf, page_2.pdf, page_3.pdf, etc. The ZIP file contains all pages in order.'
        ],
        [
            'q' => 'Is there a limit on the number of pages?',
            'a' => 'There\'s no limit on pages. However, the total file size must be under 50MB.'
        ],
        [
            'q' => 'Will the quality be affected?',
            'a' => 'No, each page maintains its original quality exactly as in the source PDF.'
        ],
        [
            'q' => 'Can I split a single-page PDF?',
            'a' => 'The PDF must have at least 2 pages to split. Single-page PDFs don\'t need splitting.'
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
