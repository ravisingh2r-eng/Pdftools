<?php
/**
 * Split PDF Tool
 *
 * Separate PDF pages into multiple files.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../_template_tool.php';

// Define tool slug
$tool_slug = 'split-pdf';

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

// Initialize error variable
$error = null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadedFile = null;
    $outputFiles = [];

    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }

        // Check if file was uploaded
        if (empty($_FILES['pdf_files']['name'][0])) {
            throw new Exception('Please select a PDF file to split.');
        }

        $fileName = $_FILES['pdf_files']['name'][0];
        $tmpName = $_FILES['pdf_files']['tmp_name'][0];
        $fileError = $_FILES['pdf_files']['error'][0];
        $fileSize = $_FILES['pdf_files']['size'][0];

        // Check for upload errors
        if ($fileError !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed. Please try again.');
        }

        // Validate file size
        if ($fileSize > MAX_FILE_SIZE) {
            $maxMB = MAX_FILE_SIZE / (1024 * 1024);
            throw new Exception("File exceeds maximum size of {$maxMB}MB.");
        }

        // Validate file extension
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new Exception('Only PDF files are allowed.');
        }

        // Initialize PDF engine
        $pdfEngine = new PDFEngine();

        // Move uploaded file
        $uploadedFile = $pdfEngine->generate_temp_filename('upload', 'pdf');
        if (!move_uploaded_file($tmpName, $uploadedFile)) {
            throw new Exception('Failed to save uploaded file.');
        }

        // Validate PDF
        if (!$pdfEngine->is_valid_pdf($uploadedFile)) {
            throw new Exception('The file is not a valid PDF.');
        }

        // Get page count
        $totalPages = $pdfEngine->get_page_count($uploadedFile);

        // Get split mode
        $splitMode = $_POST['split_mode'] ?? 'all';
        $pageRanges = [];

        if ($splitMode === 'all') {
            // Split into individual pages
            for ($i = 1; $i <= $totalPages; $i++) {
                $pageRanges[] = [$i, $i];
            }
        } elseif ($splitMode === 'ranges') {
            // Parse custom ranges
            $rangesInput = trim($_POST['page_ranges'] ?? '');
            if (empty($rangesInput)) {
                throw new Exception('Please specify page ranges.');
            }

            // Parse ranges like "1-3, 5, 7-10"
            $parts = explode(',', $rangesInput);
            foreach ($parts as $part) {
                $part = trim($part);
                if (empty($part)) continue;

                if (strpos($part, '-') !== false) {
                    $range = explode('-', $part);
                    if (count($range) !== 2) {
                        throw new Exception("Invalid range format: {$part}");
                    }
                    $start = (int)trim($range[0]);
                    $end = (int)trim($range[1]);
                } else {
                    $start = $end = (int)$part;
                }

                if ($start < 1 || $end < 1 || $start > $totalPages || $end > $totalPages) {
                    throw new Exception("Page range {$start}-{$end} is out of bounds (1-{$totalPages}).");
                }
                if ($start > $end) {
                    throw new Exception("Invalid range: {$start}-{$end}");
                }

                $pageRanges[] = [$start, $end];
            }

            if (empty($pageRanges)) {
                throw new Exception('No valid page ranges specified.');
            }
        } elseif ($splitMode === 'fixed') {
            // Split every N pages
            $pagesPerFile = max(1, (int)($_POST['pages_per_file'] ?? 1));
            for ($i = 1; $i <= $totalPages; $i += $pagesPerFile) {
                $end = min($i + $pagesPerFile - 1, $totalPages);
                $pageRanges[] = [$i, $end];
            }
        }

        // Create output directory
        $outputDir = $pdfEngine->normalize_temp_dir() . '/split_' . bin2hex(random_bytes(8));
        if (!mkdir($outputDir, 0755, true)) {
            throw new Exception('Failed to create output directory.');
        }

        // Split the PDF
        $outputFiles = $pdfEngine->split_pdf($uploadedFile, $pageRanges, $outputDir);

        if (empty($outputFiles)) {
            throw new Exception('Failed to split PDF file.');
        }

        // If single file, download directly
        if (count($outputFiles) === 1) {
            $outputPath = $outputFiles[0];
            $downloadName = 'split_' . date('Ymd_His') . '.pdf';
            $outputSize = filesize($outputPath);

            if (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
            header('Content-Length: ' . $outputSize);
            header('Cache-Control: private, max-age=0, must-revalidate');

            readfile($outputPath);

            // Cleanup
            unlink($outputPath);
            unlink($uploadedFile);
            rmdir($outputDir);

            exit;
        }

        // Multiple files - create ZIP
        $zipPath = $pdfEngine->generate_temp_filename('split', 'zip');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new Exception('Failed to create ZIP archive.');
        }

        foreach ($outputFiles as $index => $file) {
            $zip->addFile($file, 'page_' . ($index + 1) . '.pdf');
        }

        $zip->close();

        // Stream ZIP for download
        $downloadName = 'split_pages_' . date('Ymd_His') . '.zip';
        $zipSize = filesize($zipPath);

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . $zipSize);
        header('Cache-Control: private, max-age=0, must-revalidate');

        readfile($zipPath);

        // Cleanup
        unlink($zipPath);
        foreach ($outputFiles as $file) {
            if (file_exists($file)) unlink($file);
        }
        unlink($uploadedFile);
        rmdir($outputDir);

        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();

        // Cleanup on error
        if ($uploadedFile && file_exists($uploadedFile)) {
            unlink($uploadedFile);
        }
        foreach ($outputFiles as $file) {
            if (file_exists($file)) unlink($file);
        }
    }
}

// Build error message HTML
$errorHtml = '';
if ($error) {
    $errorHtml = '<div class="message message-error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
}

// Custom form fields for split options
$customFormHtml = $errorHtml . '
<div class="tool-options">
    <label class="option-label">Split Mode</label>
    <div class="radio-group">
        <label class="radio-option">
            <input type="radio" name="split_mode" value="all" checked>
            <span>Extract all pages individually</span>
        </label>
        <label class="radio-option">
            <input type="radio" name="split_mode" value="fixed">
            <span>Split every N pages</span>
        </label>
        <label class="radio-option">
            <input type="radio" name="split_mode" value="ranges">
            <span>Custom page ranges</span>
        </label>
    </div>

    <div class="option-field" id="fixedOptions" style="display: none;">
        <label for="pages_per_file">Pages per file:</label>
        <input type="number" name="pages_per_file" id="pages_per_file" value="1" min="1" max="100">
    </div>

    <div class="option-field" id="rangeOptions" style="display: none;">
        <label for="page_ranges">Page ranges (e.g., 1-3, 5, 7-10):</label>
        <input type="text" name="page_ranges" id="page_ranges" placeholder="1-3, 5, 7-10">
    </div>
</div>';

// Custom scripts for split options
$customScriptsHtml = '
<script>
document.querySelectorAll(\'input[name="split_mode"]\').forEach(function(radio) {
    radio.addEventListener("change", function() {
        document.getElementById("fixedOptions").style.display = this.value === "fixed" ? "block" : "none";
        document.getElementById("rangeOptions").style.display = this.value === "ranges" ? "block" : "none";
    });
});
</script>';

// Tool configuration
$config = [
    'tool_slug' => 'split-pdf',
    'tool_name' => 'Split PDF',
    'primary_keyword' => 'split PDF online',

    'meta_title' => 'Split PDF Online - Free PDF Splitter | ' . SITE_NAME,
    'meta_description' => 'Split PDF files into multiple documents online for free. Extract specific pages or split by page ranges. No registration required.',
    'canonical_url' => BASE_URL . '/tools/split-pdf/',

    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'og_image_url' => BASE_URL . '/assets/img/split-pdf-og.png',

    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your PDF file here or click to browse',
    'button_text' => 'Split PDF',

    'custom_form_html' => $customFormHtml,
    'custom_scripts_html' => $customScriptsHtml,

    'short_intro_html' => '
        <p>Easily split your PDF into separate documents. Extract individual pages, split by page ranges, or divide every N pages.</p>
    ',

    'how_it_works_html' => '
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Upload Your PDF</h3>
                    <p>Select the PDF file you want to split by clicking the upload area or dragging the file.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Choose Split Mode</h3>
                    <p>Select how you want to split: extract all pages, split every N pages, or specify custom ranges.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Download Results</h3>
                    <p>Click "Split PDF" and download your split files as a ZIP archive or individual PDF.</p>
                </div>
            </div>
        </div>
    ',

    'features_html' => '
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">📄</div>
                <div class="feature-content">
                    <h3>Multiple Split Modes</h3>
                    <p>Extract all pages, split every N pages, or specify custom page ranges.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📦</div>
                <div class="feature-content">
                    <h3>ZIP Download</h3>
                    <p>Multiple split files are automatically packaged into a convenient ZIP archive.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🎯</div>
                <div class="feature-content">
                    <h3>Precise Control</h3>
                    <p>Specify exact page ranges like "1-3, 5, 7-10" for custom splits.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🔒</div>
                <div class="feature-content">
                    <h3>Secure Processing</h3>
                    <p>Files are processed securely and deleted immediately after download.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">💯</div>
                <div class="feature-content">
                    <h3>Quality Preserved</h3>
                    <p>Original PDF quality is maintained in all split files.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">💰</div>
                <div class="feature-content">
                    <h3>100% Free</h3>
                    <p>No registration, no watermarks, completely free to use.</p>
                </div>
            </div>
        </div>
    ',

    'faq_items' => [
        [
            'q' => 'How do I specify page ranges?',
            'a' => 'Use comma-separated values with optional ranges. For example: "1-3, 5, 7-10" will create three files: pages 1-3, page 5, and pages 7-10.'
        ],
        [
            'q' => 'What format are the split files in?',
            'a' => 'Split files are in PDF format. If you split into multiple files, they will be downloaded as a ZIP archive.'
        ],
        [
            'q' => 'Is there a limit on PDF size?',
            'a' => 'Yes, the maximum file size is 50MB. For larger files, consider compressing them first.'
        ],
        [
            'q' => 'Can I split a password-protected PDF?',
            'a' => 'Currently, password-protected PDFs cannot be split. Please remove the password first.'
        ]
    ],

    // Related Tools - dynamically loaded from registry
    'related_tools' => get_related_tools($tool_slug)
];

render_tool_page($config);
