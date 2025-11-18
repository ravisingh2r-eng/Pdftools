<?php
/**
 * Reduce PDF Quality Tool
 *
 * Reduce PDF quality level for smaller file sizes.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

$tool_slug = 'reduce-pdf-quality';

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

        $quality = $_POST['quality_level'] ?? 'medium';
        $validLevels = ['low', 'medium', 'high'];
        if (!in_array($quality, $validLevels)) {
            $quality = 'medium';
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

        $outputPath = $pdfEngine->generate_temp_filename('reduced', 'pdf');

        // Map quality to compression mode (inverse)
        $modeMap = ['low' => 'high', 'medium' => 'medium', 'high' => 'low'];
        $mode = $modeMap[$quality];

        $result = $pdfEngine->compress_pdf_basic($tempPath, $outputPath, $mode);

        if (!$result || !file_exists($outputPath)) {
            throw new Exception('Failed to reduce quality. Please try again.');
        }

        unlink($uploadedFile);

        $downloadName = 'reduced_' . date('Ymd_His') . '.pdf';

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

$customFormHtml = $errorHtml . '
<div class="custom-input-group" style="margin-top: 1rem;">
    <label for="quality_level" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Output Quality</label>
    <select name="quality_level" id="quality_level" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;">
        <option value="high">High Quality - Larger file size</option>
        <option value="medium" selected>Medium Quality - Balanced</option>
        <option value="low">Low Quality - Smallest file size</option>
    </select>
</div>';

$config = [
    'tool_slug' => $tool_slug,
    'tool_name' => 'Reduce PDF Quality',
    'primary_keyword' => 'reduce PDF quality',
    'meta_title' => 'Reduce PDF Quality - Lower Resolution Online | ' . SITE_NAME,
    'meta_description' => 'Reduce PDF quality to create smaller files. Choose from high, medium, or low quality output for different use cases.',
    'canonical_url' => BASE_URL . '/tools/' . $tool_slug . '/',
    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your PDF file here or click to browse',
    'button_text' => 'Reduce Quality',
    'custom_form_html' => $customFormHtml,
    'short_intro_html' => '<p>Reduce PDF quality to create smaller files for web uploads, emails, or archiving.</p>',
    'how_it_works_html' => '
        <div class="steps">
            <div class="step"><div class="step-number">1</div><div class="step-content"><h3>Upload PDF</h3><p>Select your PDF file.</p></div></div>
            <div class="step"><div class="step-number">2</div><div class="step-content"><h3>Select Quality</h3><p>Choose output quality level.</p></div></div>
            <div class="step"><div class="step-number">3</div><div class="step-content"><h3>Download</h3><p>Get your reduced PDF.</p></div></div>
        </div>',
    'features_html' => '
        <div class="features-grid">
            <div class="feature-item"><div class="feature-icon">📊</div><div class="feature-content"><h3>3 Quality Levels</h3><p>High, medium, and low options.</p></div></div>
            <div class="feature-item"><div class="feature-icon">📉</div><div class="feature-content"><h3>Smaller Files</h3><p>Significant size reduction.</p></div></div>
            <div class="feature-item"><div class="feature-icon">🔒</div><div class="feature-content"><h3>Secure</h3><p>Files deleted after download.</p></div></div>
            <div class="feature-item"><div class="feature-icon">💰</div><div class="feature-content"><h3>Free</h3><p>No cost, no registration.</p></div></div>
        </div>',
    'faq_items' => [
        ['q' => 'What quality should I choose?', 'a' => 'High for printing, Medium for screen viewing, Low for quick previews or archiving.'],
        ['q' => 'Will I lose content?', 'a' => 'No, only image resolution is affected. Text remains sharp.'],
        ['q' => 'Is my file secure?', 'a' => 'Yes, all files are deleted immediately after processing.']
    ],
    'related_tools' => get_related_tools($tool_slug)
];

render_tool_page($config);
