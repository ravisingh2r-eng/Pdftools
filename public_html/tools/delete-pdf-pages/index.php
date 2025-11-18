<?php
/**
 * Delete PDF Pages Tool
 *
 * Remove specific pages from a PDF document.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Define tool slug
$tool_slug = 'delete-pdf-pages';

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

        // Check pages to delete input
        if (empty($_POST['pages_to_delete'])) {
            throw new Exception('Please enter the pages to delete (e.g., 2, 4, 7).');
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

        // Parse pages to delete
        $deleteInput = trim($_POST['pages_to_delete']);
        $pagesToDelete = [];
        $deleteParts = preg_split('/[,\s]+/', $deleteInput);

        foreach ($deleteParts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            // Support ranges like "2-5"
            if (strpos($part, '-') !== false) {
                $bounds = explode('-', $part, 2);
                $start = (int)trim($bounds[0]);
                $end = (int)trim($bounds[1]);

                if ($start < 1 || $end < 1 || $start > $totalPages || $end > $totalPages) {
                    throw new Exception("Page range '{$part}' is out of bounds (1-{$totalPages}).");
                }

                for ($i = $start; $i <= $end; $i++) {
                    $pagesToDelete[] = $i;
                }
            } else {
                $page = (int)$part;
                if ($page < 1 || $page > $totalPages) {
                    throw new Exception("Page {$page} is out of bounds (1-{$totalPages}).");
                }
                $pagesToDelete[] = $page;
            }
        }

        $pagesToDelete = array_unique($pagesToDelete);

        if (empty($pagesToDelete)) {
            throw new Exception('Please enter valid page numbers to delete.');
        }

        // Check if trying to delete all pages
        if (count($pagesToDelete) >= $totalPages) {
            throw new Exception('Cannot delete all pages. At least one page must remain.');
        }

        // Generate output filename
        $outputPath = $pdfEngine->generate_temp_filename('pages_removed', 'pdf');

        // Delete the pages
        $result = $pdfEngine->delete_pages($tempPath, $pagesToDelete, $outputPath);

        if (!$result || !file_exists($outputPath)) {
            throw new Exception('Failed to remove pages. Please try again.');
        }

        // Clean up uploaded file
        unlink($uploadedFile);

        // Stream file for download
        $downloadName = 'pages_removed_' . date('Ymd_His') . '.pdf';
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

// Custom form HTML with pages to delete input
$customFormHtml = $errorHtml . '
<div class="custom-input-group" style="margin-top: 1rem;">
    <label for="pages_to_delete" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Pages to Delete</label>
    <input type="text" name="pages_to_delete" id="pages_to_delete" required
           placeholder="e.g., 2, 4, 7 or 2-5"
           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
    <small style="color: #666; display: block; margin-top: 0.25rem;">
        Enter page numbers to remove, separated by commas. You can also use ranges like "2-5" to delete pages 2 through 5.
    </small>
</div>';

// Tool configuration
$config = [
    // Basic Info
    'tool_slug' => $tool_slug,
    'tool_name' => 'Delete PDF Pages',
    'primary_keyword' => 'delete PDF pages online',

    // SEO Meta
    'meta_title' => 'Delete PDF Pages - Remove Pages from PDF Online | ' . SITE_NAME,
    'meta_description' => 'Remove unwanted pages from your PDF online for free. Delete specific pages or page ranges from PDF documents. No registration required.',
    'canonical_url' => BASE_URL . '/tools/' . $tool_slug . '/',

    // Schema Info
    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'og_image_url' => BASE_URL . '/assets/img/delete-pdf-pages-og.png',

    // Upload Settings
    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your PDF file here or click to browse',
    'button_text' => 'Delete Pages',

    // Custom form HTML
    'custom_form_html' => $customFormHtml,

    // Content: Introduction
    'short_intro_html' => '
        <p>Remove unwanted pages from your PDF documents instantly. Simply specify which pages to delete and download your cleaned-up PDF.</p>
    ',

    // Content: How It Works
    'how_it_works_html' => '
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Upload PDF File</h3>
                    <p>Click the upload area or drag and drop your PDF file that contains pages you want to remove.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Enter Pages to Delete</h3>
                    <p>Specify which pages to remove. Use individual numbers like "2, 4, 7" or ranges like "2-5".</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Download Result</h3>
                    <p>Click "Delete Pages" and download your PDF with the specified pages removed.</p>
                </div>
            </div>
        </div>
    ',

    // Content: Features
    'features_html' => '
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">🗑️</div>
                <div class="feature-content">
                    <h3>Precise Deletion</h3>
                    <p>Remove exactly the pages you want - single pages or ranges.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📄</div>
                <div class="feature-content">
                    <h3>Multiple Pages</h3>
                    <p>Delete any number of pages in one operation.</p>
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
                    <p>Remaining pages maintain their original quality.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🚀</div>
                <div class="feature-content">
                    <h3>Fast Processing</h3>
                    <p>Remove pages from your PDF in seconds.</p>
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
            'q' => 'How do I specify which pages to delete?',
            'a' => 'Enter page numbers separated by commas (e.g., "2, 4, 7") or use ranges (e.g., "2-5" deletes pages 2, 3, 4, and 5). You can combine both: "2-5, 8, 10".'
        ],
        [
            'q' => 'Can I delete all pages?',
            'a' => 'No, at least one page must remain in the PDF. The tool will show an error if you try to delete all pages.'
        ],
        [
            'q' => 'Is the original quality preserved?',
            'a' => 'Yes, all remaining pages maintain their original quality exactly as in the source PDF.'
        ],
        [
            'q' => 'Is there a limit on pages to delete?',
            'a' => 'You can delete any number of pages, as long as at least one page remains in the document.'
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
