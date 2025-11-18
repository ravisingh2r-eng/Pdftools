<?php
/**
 * Split PDF by Range Tool
 *
 * Extract specific page ranges from a PDF into separate files.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Define tool slug
$tool_slug = 'split-pdf-range';

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

        // Check page ranges input
        if (empty($_POST['page_ranges'])) {
            throw new Exception('Please enter page ranges (e.g., 1-3, 5-7, 9).');
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

        // Parse page ranges
        $rangesInput = trim($_POST['page_ranges']);
        $pageRanges = [];
        $rangeParts = preg_split('/[,;]+/', $rangesInput);

        foreach ($rangeParts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            if (strpos($part, '-') !== false) {
                // Range like "1-3"
                $bounds = explode('-', $part, 2);
                $start = (int)trim($bounds[0]);
                $end = (int)trim($bounds[1]);

                if ($start < 1 || $end < 1 || $start > $totalPages || $end > $totalPages) {
                    throw new Exception("Page range '{$part}' is out of bounds (1-{$totalPages}).");
                }
                if ($start > $end) {
                    throw new Exception("Invalid range '{$part}': start must be less than or equal to end.");
                }

                $pageRanges[] = [$start, $end];
            } else {
                // Single page like "5"
                $page = (int)$part;
                if ($page < 1 || $page > $totalPages) {
                    throw new Exception("Page {$page} is out of bounds (1-{$totalPages}).");
                }
                $pageRanges[] = [$page, $page];
            }
        }

        if (empty($pageRanges)) {
            throw new Exception('Please enter valid page ranges.');
        }

        // Create output directory
        $outputDir = $pdfEngine->generate_temp_filename('split_output', '');
        mkdir($outputDir, 0755, true);

        // Split the PDF
        $outputFiles = $pdfEngine->split_pdf($tempPath, $pageRanges, $outputDir);

        if (empty($outputFiles)) {
            throw new Exception('Failed to split PDF. Please try again.');
        }

        // Create ZIP if multiple files
        if (count($outputFiles) > 1) {
            $zipPath = $pdfEngine->generate_temp_filename('split', 'zip');
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                throw new Exception('Failed to create ZIP archive.');
            }

            foreach ($outputFiles as $index => $file) {
                $zip->addFile($file, 'split_' . ($index + 1) . '.pdf');
            }

            $zip->close();

            // Clean up individual files
            $pdfEngine->delete_temp_files($outputFiles);
            array_map('unlink', glob($outputDir . '/*'));
            rmdir($outputDir);

            // Clean up uploaded file
            unlink($uploadedFile);

            // Stream ZIP for download
            $downloadName = 'split_pdfs_' . date('Ymd_His') . '.zip';
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

        } else {
            // Single file output
            $outputPath = $outputFiles[0];

            // Clean up
            unlink($uploadedFile);

            // Stream file for download
            $downloadName = 'split_' . date('Ymd_His') . '.pdf';
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
            rmdir($outputDir);
            exit;
        }

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

// Custom form HTML with page ranges input
$customFormHtml = $errorHtml . '
<div class="custom-input-group" style="margin-top: 1rem;">
    <label for="page_ranges" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Page Ranges</label>
    <input type="text" name="page_ranges" id="page_ranges" required
           placeholder="e.g., 1-3, 5-7, 9"
           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
    <small style="color: #666; display: block; margin-top: 0.25rem;">
        Enter page ranges separated by commas. Examples: "1-3" (pages 1 to 3), "5" (page 5 only), "1-3, 5-7, 9"
    </small>
</div>';

// Tool configuration
$config = [
    // Basic Info
    'tool_slug' => $tool_slug,
    'tool_name' => 'Split PDF by Range',
    'primary_keyword' => 'split PDF by page range',

    // SEO Meta
    'meta_title' => 'Split PDF by Page Range - Extract Pages Online | ' . SITE_NAME,
    'meta_description' => 'Split PDF files by page ranges online for free. Extract specific pages like 1-3, 5-7, or individual pages into separate PDFs. No registration required.',
    'canonical_url' => BASE_URL . '/tools/' . $tool_slug . '/',

    // Schema Info
    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'og_image_url' => BASE_URL . '/assets/img/split-pdf-range-og.png',

    // Upload Settings
    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your PDF file here or click to browse',
    'button_text' => 'Split PDF',

    // Custom form HTML
    'custom_form_html' => $customFormHtml,

    // Content: Introduction
    'short_intro_html' => '
        <p>Extract specific page ranges from your PDF document into separate files. Perfect for splitting chapters, sections, or specific pages from large documents.</p>
    ',

    // Content: How It Works
    'how_it_works_html' => '
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Upload PDF File</h3>
                    <p>Click the upload area or drag and drop your PDF file that you want to split.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Enter Page Ranges</h3>
                    <p>Specify which pages to extract. Use ranges like "1-5" or individual pages like "7". Separate multiple ranges with commas.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Download Result</h3>
                    <p>Click "Split PDF" to extract the specified pages. If you have multiple ranges, you\'ll get a ZIP file with all the split PDFs.</p>
                </div>
            </div>
        </div>
    ',

    // Content: Features
    'features_html' => '
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">🎯</div>
                <div class="feature-content">
                    <h3>Precise Extraction</h3>
                    <p>Extract exactly the pages you need with flexible range syntax.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📦</div>
                <div class="feature-content">
                    <h3>Multiple Ranges</h3>
                    <p>Split into multiple PDFs at once by specifying multiple page ranges.</p>
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
                    <p>Original quality is maintained in all extracted pages.</p>
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
            'q' => 'How do I specify page ranges?',
            'a' => 'Use the format "start-end" for ranges (e.g., "1-5" for pages 1 through 5) or just a number for single pages (e.g., "7"). Separate multiple ranges with commas: "1-3, 5-7, 9".'
        ],
        [
            'q' => 'What happens if I specify multiple ranges?',
            'a' => 'Each range will create a separate PDF file. If you have multiple ranges, you\'ll receive a ZIP file containing all the split PDFs.'
        ],
        [
            'q' => 'Is the original quality preserved?',
            'a' => 'Yes, the extracted pages maintain their original quality exactly as they appear in the source PDF.'
        ],
        [
            'q' => 'Is there a page limit?',
            'a' => 'There\'s no limit on the number of pages in your PDF. However, each file must be under 50MB.'
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
