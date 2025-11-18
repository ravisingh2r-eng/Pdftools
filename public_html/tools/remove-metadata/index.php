<?php
/**
 * Remove PDF Metadata Tool
 *
 * Strip metadata from PDF files for privacy.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

$tool_slug = 'remove-metadata';

if (!is_tool_active($tool_slug)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Tool Not Found</title></head><body>';
    echo '<h1>Tool Not Found</h1><p>This tool is not available. <a href="/">Return to homepage</a></p>';
    echo '</body></html>';
    exit;
}

$tool_data = get_tool($tool_slug);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_rate_limit($tool_slug, 20, 3600);

    $uploadedFile = null;
    $startTime = microtime(true);
    $totalSizeIn = get_upload_size($_FILES, 'pdf_file');
    $fileCount = 1;

    try {
        if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }

        if (empty($_FILES['pdf_file']['name'])) {
            throw new Exception('Please select a PDF file.');
        }

        $fileName = $_FILES['pdf_file']['name'];
        $tmpName = $_FILES['pdf_file']['tmp_name'];
        $fileError = $_FILES['pdf_file']['error'];
        $fileSize = $_FILES['pdf_file']['size'];

        if ($fileError !== UPLOAD_ERR_OK) {
            throw new Exception("Upload error occurred.");
        }

        if ($fileSize > MAX_FILE_SIZE) {
            throw new Exception("File exceeds maximum size.");
        }

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new Exception("File is not a PDF.");
        }

        $pdfEngine = new PDFEngine();
        $tempPath = $pdfEngine->generate_temp_filename('upload', 'pdf');

        if (!move_uploaded_file($tmpName, $tempPath)) {
            throw new Exception("Failed to save uploaded file.");
        }

        $uploadedFile = $tempPath;

        if (!$pdfEngine->is_valid_pdf($tempPath)) {
            throw new Exception("File is not a valid PDF.");
        }

        $outputPath = $pdfEngine->generate_temp_filename('clean', 'pdf');
        $result = $pdfEngine->remove_metadata($tempPath, $outputPath);

        if (!$result || !file_exists($outputPath)) {
            throw new Exception('Failed to remove metadata. Please try again.');
        }

        unlink($uploadedFile);

        $downloadName = 'clean_' . date('Ymd_His') . '.pdf';

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($outputPath));

        $durationMs = calculate_duration_ms($startTime);
        log_usage($tool_slug, $fileCount, $totalSizeIn, $durationMs, 'success');

        readfile($outputPath);
        unlink($outputPath);
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
        $durationMs = calculate_duration_ms($startTime);
        log_usage($tool_slug, $fileCount, $totalSizeIn, $durationMs, 'error', $error);
        log_exception($e, $tool_slug);

        if ($uploadedFile && file_exists($uploadedFile)) {
            unlink($uploadedFile);
        }
    }
}

$errorHtml = $error ? '<div class="message message-error">' . htmlspecialchars($error) . '</div>' : '';

$config = [
    'tool_slug' => $tool_slug,
    'tool_name' => 'Remove PDF Metadata',
    'primary_keyword' => 'remove PDF metadata',
    'meta_title' => 'Remove PDF Metadata - Clean PDF Properties | ' . SITE_NAME,
    'meta_description' => 'Remove metadata from PDF files for privacy. Strip author, title, creation date, and other hidden information from your documents.',
    'canonical_url' => BASE_URL . '/tools/' . $tool_slug . '/',
    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your PDF file here or click to browse',
    'button_text' => 'Remove Metadata',
    'custom_form_html' => $errorHtml,
    'short_intro_html' => '<p>Remove hidden metadata from PDF files to protect your privacy. Strips author name, creation date, software info, and more.</p>',
    'how_it_works_html' => '
        <div class="steps">
            <div class="step"><div class="step-number">1</div><div class="step-content"><h3>Upload PDF</h3><p>Select the PDF file to clean.</p></div></div>
            <div class="step"><div class="step-number">2</div><div class="step-content"><h3>Process</h3><p>We remove all metadata automatically.</p></div></div>
            <div class="step"><div class="step-number">3</div><div class="step-content"><h3>Download</h3><p>Get your clean PDF file.</p></div></div>
        </div>',
    'features_html' => '
        <div class="features-grid">
            <div class="feature-item"><div class="feature-icon">🔏</div><div class="feature-content"><h3>Privacy Protection</h3><p>Remove identifying information.</p></div></div>
            <div class="feature-item"><div class="feature-icon">📋</div><div class="feature-content"><h3>Complete Cleaning</h3><p>Author, title, dates, keywords all removed.</p></div></div>
            <div class="feature-item"><div class="feature-icon">💯</div><div class="feature-content"><h3>Content Preserved</h3><p>Document content unchanged.</p></div></div>
            <div class="feature-item"><div class="feature-icon">🔒</div><div class="feature-content"><h3>Secure</h3><p>Files deleted after processing.</p></div></div>
        </div>',
    'faq_items' => [
        ['q' => 'What metadata is removed?', 'a' => 'Author name, title, subject, keywords, creator software, creation date, and modification date.'],
        ['q' => 'Will my content change?', 'a' => 'No, only metadata is removed. The visible content remains exactly the same.'],
        ['q' => 'Why remove metadata?', 'a' => 'To protect privacy when sharing documents, or to anonymize files before publication.'],
        ['q' => 'Is it secure?', 'a' => 'Yes, all files are processed securely and deleted immediately.']
    ],
    'related_tools' => get_related_tools($tool_slug)
];

render_tool_page($config);
