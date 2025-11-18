<?php
/**
 * Remove Blank Pages Tool
 *
 * Detect and remove blank pages from PDF files.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

$tool_slug = 'remove-blank-pages';

if (!is_tool_active($tool_slug)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Tool Not Found</title></head><body>';
    echo '<h1>Tool Not Found</h1><p>This tool is not available. <a href="/">Return to homepage</a></p>';
    echo '</body></html>';
    exit;
}

$tool_data = get_tool($tool_slug);
$error = null;
$successMessage = null;

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

        $sensitivity = $_POST['sensitivity'] ?? 'medium';
        $thresholds = ['low' => 300, 'medium' => 500, 'high' => 800];
        $threshold = $thresholds[$sensitivity] ?? 500;

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

        $outputPath = $pdfEngine->generate_temp_filename('cleaned', 'pdf');
        $result = $pdfEngine->remove_blank_pages($tempPath, $outputPath, $threshold);

        if (!$result['success'] || !file_exists($outputPath)) {
            throw new Exception('Failed to process PDF. Please try again.');
        }

        if ($result['removed'] === 0) {
            unlink($uploadedFile);
            unlink($outputPath);
            throw new Exception('No blank pages were detected in this document.');
        }

        unlink($uploadedFile);

        $downloadName = 'cleaned_' . date('Ymd_His') . '.pdf';

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($outputPath));
        header('X-Removed-Pages: ' . $result['removed']);

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

$customFormHtml = $errorHtml . '
<div class="custom-input-group" style="margin-top: 1rem;">
    <label for="sensitivity" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Detection Sensitivity</label>
    <select name="sensitivity" id="sensitivity" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;">
        <option value="low">Low - Only completely blank pages</option>
        <option value="medium" selected>Medium - Mostly blank pages</option>
        <option value="high">High - Pages with minimal content</option>
    </select>
    <small style="color: #666; display: block; margin-top: 0.25rem;">Higher sensitivity removes pages with very little content.</small>
</div>';

$config = [
    'tool_slug' => $tool_slug,
    'tool_name' => 'Remove Blank Pages',
    'primary_keyword' => 'remove blank pages from PDF',
    'meta_title' => 'Remove Blank Pages from PDF Online | ' . SITE_NAME,
    'meta_description' => 'Automatically detect and remove blank pages from PDF documents. Clean up scanned documents and reduce file size.',
    'canonical_url' => BASE_URL . '/tools/' . $tool_slug . '/',
    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your PDF file here or click to browse',
    'button_text' => 'Remove Blank Pages',
    'custom_form_html' => $customFormHtml,
    'short_intro_html' => '<p>Automatically detect and remove blank or nearly-blank pages from your PDF documents.</p>',
    'how_it_works_html' => '
        <div class="steps">
            <div class="step"><div class="step-number">1</div><div class="step-content"><h3>Upload PDF</h3><p>Select your PDF file.</p></div></div>
            <div class="step"><div class="step-number">2</div><div class="step-content"><h3>Set Sensitivity</h3><p>Choose detection sensitivity.</p></div></div>
            <div class="step"><div class="step-number">3</div><div class="step-content"><h3>Download</h3><p>Get PDF without blank pages.</p></div></div>
        </div>',
    'features_html' => '
        <div class="features-grid">
            <div class="feature-item"><div class="feature-icon">🔍</div><div class="feature-content"><h3>Auto Detection</h3><p>Smart blank page detection.</p></div></div>
            <div class="feature-item"><div class="feature-icon">⚙️</div><div class="feature-content"><h3>Adjustable</h3><p>Three sensitivity levels.</p></div></div>
            <div class="feature-item"><div class="feature-icon">📄</div><div class="feature-content"><h3>Content Safe</h3><p>Only removes truly blank pages.</p></div></div>
            <div class="feature-item"><div class="feature-icon">🔒</div><div class="feature-content"><h3>Secure</h3><p>Files deleted after processing.</p></div></div>
        </div>',
    'faq_items' => [
        ['q' => 'How are blank pages detected?', 'a' => 'We analyze page content size. Pages with very little content are considered blank.'],
        ['q' => 'What if no blank pages are found?', 'a' => 'You\'ll receive a message indicating no blank pages were detected.'],
        ['q' => 'Will content pages be removed?', 'a' => 'No, the detection is conservative. Use lower sensitivity if unsure.'],
        ['q' => 'Is my file secure?', 'a' => 'Yes, all files are deleted immediately after processing.']
    ],
    'related_tools' => get_related_tools($tool_slug)
];

render_tool_page($config);
