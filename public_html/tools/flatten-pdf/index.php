<?php
/**
 * Flatten PDF Tool
 *
 * Flatten PDF forms, annotations, and layers.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

$tool_slug = 'flatten-pdf';

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

        $outputPath = $pdfEngine->generate_temp_filename('flattened', 'pdf');
        $result = $pdfEngine->flatten_pdf($tempPath, $outputPath);

        if (!$result || !file_exists($outputPath)) {
            throw new Exception('Failed to flatten PDF. Please try again.');
        }

        unlink($uploadedFile);

        $downloadName = 'flattened_' . date('Ymd_His') . '.pdf';

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
    'tool_name' => 'Flatten PDF',
    'primary_keyword' => 'flatten PDF forms',
    'meta_title' => 'Flatten PDF - Remove Forms & Annotations | ' . SITE_NAME,
    'meta_description' => 'Flatten PDF forms and annotations online for free. Convert fillable forms to static documents. Remove interactive elements.',
    'canonical_url' => BASE_URL . '/tools/' . $tool_slug . '/',
    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your PDF file here or click to browse',
    'button_text' => 'Flatten PDF',
    'custom_form_html' => $errorHtml,
    'short_intro_html' => '<p>Flatten PDF forms and annotations to create static documents. Converts fillable fields to regular text and removes interactive elements.</p>',
    'how_it_works_html' => '
        <div class="steps">
            <div class="step"><div class="step-number">1</div><div class="step-content"><h3>Upload PDF</h3><p>Select your PDF with forms or annotations.</p></div></div>
            <div class="step"><div class="step-number">2</div><div class="step-content"><h3>Flatten</h3><p>We convert all interactive elements.</p></div></div>
            <div class="step"><div class="step-number">3</div><div class="step-content"><h3>Download</h3><p>Get your flattened PDF.</p></div></div>
        </div>',
    'features_html' => '
        <div class="features-grid">
            <div class="feature-item"><div class="feature-icon">📝</div><div class="feature-content"><h3>Forms Flattened</h3><p>Fillable fields become static text.</p></div></div>
            <div class="feature-item"><div class="feature-icon">📌</div><div class="feature-content"><h3>Annotations Fixed</h3><p>Comments and markups become permanent.</p></div></div>
            <div class="feature-item"><div class="feature-icon">🎨</div><div class="feature-content"><h3>Layers Merged</h3><p>Multiple layers combined into one.</p></div></div>
            <div class="feature-item"><div class="feature-icon">🔒</div><div class="feature-content"><h3>Secure</h3><p>Files deleted after processing.</p></div></div>
            <div class="feature-item"><div class="feature-icon">✅</div><div class="feature-content"><h3>Compatible</h3><p>Works with any PDF viewer.</p></div></div>
            <div class="feature-item"><div class="feature-icon">💰</div><div class="feature-content"><h3>Free</h3><p>No registration required.</p></div></div>
        </div>',
    'faq_items' => [
        ['q' => 'What does flattening do?', 'a' => 'It converts interactive elements (forms, annotations) into static content that cannot be edited.'],
        ['q' => 'Will form data be preserved?', 'a' => 'Yes, filled form values become permanent text in the flattened document.'],
        ['q' => 'Can I edit after flattening?', 'a' => 'The flattened content becomes part of the page and cannot be edited as form fields.'],
        ['q' => 'Why flatten a PDF?', 'a' => 'To lock form data, ensure consistent viewing across devices, or prepare for printing.'],
        ['q' => 'Is my file secure?', 'a' => 'Yes, all files are deleted immediately after processing.']
    ],
    'related_tools' => get_related_tools($tool_slug)
];

render_tool_page($config);
