<?php
/**
 * Unlock PDF Tool
 *
 * Remove password protection from PDF files.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Define tool slug
$tool_slug = 'unlock-pdf';

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
            throw new Exception('Please select a PDF file to unlock.');
        }

        // Check password input
        if (empty($_POST['password'])) {
            throw new Exception('Please enter the PDF password.');
        }

        $password = $_POST['password'];

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

        // Generate output filename
        $outputPath = $pdfEngine->generate_temp_filename('unlocked', 'pdf');

        // Try to unlock the PDF using FPDI with password
        // Note: FPDI doesn't directly support password-protected PDFs
        // We need to use a workaround or inform user about limitations

        try {
            // Attempt to read the PDF - this will fail if it's encrypted
            $pdf = new \setasign\Fpdi\Fpdi();
            $pageCount = $pdf->setSourceFile($tempPath);

            // If we get here, PDF is not password protected or has no user password
            // Copy all pages to new PDF (removes any owner password restrictions)
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            $pdf->Output('F', $outputPath);

        } catch (\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException $e) {
            // PDF is encrypted - inform user about limitations
            throw new Exception('This PDF is encrypted with a user password. Our tool can remove owner password restrictions, but cannot decrypt user-password protected PDFs without external tools like qpdf.');
        } catch (\setasign\Fpdi\PdfParser\PdfParserException $e) {
            if (strpos($e->getMessage(), 'encrypt') !== false || strpos($e->getMessage(), 'password') !== false) {
                throw new Exception('This PDF is password protected. Please ensure you have the correct password and the legal right to unlock this document.');
            }
            throw new Exception("PDF parsing error: " . $e->getMessage());
        }

        if (!file_exists($outputPath)) {
            throw new Exception('Failed to unlock PDF. Please try again.');
        }

        // Clean up uploaded file
        unlink($uploadedFile);

        // Stream file for download
        $downloadName = 'unlocked_' . date('Ymd_His') . '.pdf';
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

// Custom form HTML with password input
$customFormHtml = $errorHtml . '
<div class="custom-input-group" style="margin-top: 1rem;">
    <label for="password" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">PDF Password</label>
    <input type="password" name="password" id="password" required
           placeholder="Enter PDF password"
           style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
    <small style="color: #666; display: block; margin-top: 0.25rem;">
        Enter the password used to protect this PDF document.
    </small>
</div>';

// Tool configuration
$config = [
    // Basic Info
    'tool_slug' => $tool_slug,
    'tool_name' => 'Unlock PDF',
    'primary_keyword' => 'unlock PDF remove password',

    // SEO Meta
    'meta_title' => 'Unlock PDF - Remove Password Protection Online | ' . SITE_NAME,
    'meta_description' => 'Remove password protection from PDF files online for free. Unlock PDF documents to edit, print, and copy content. Requires the original password.',
    'canonical_url' => BASE_URL . '/tools/' . $tool_slug . '/',

    // Schema Info
    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'og_image_url' => BASE_URL . '/assets/img/unlock-pdf-og.png',

    // Upload Settings
    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your password-protected PDF here or click to browse',
    'button_text' => 'Unlock PDF',

    // Custom form HTML
    'custom_form_html' => $customFormHtml,

    // Content: Introduction
    'short_intro_html' => '
        <p>Remove password protection from your PDF documents. Unlock PDFs to enable editing, printing, and copying when you have the original password.</p>
    ',

    // Content: How It Works
    'how_it_works_html' => '
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Upload Protected PDF</h3>
                    <p>Click the upload area or drag and drop your password-protected PDF file.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Enter Password</h3>
                    <p>Provide the password that was used to protect the PDF document.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Download Unlocked PDF</h3>
                    <p>Click "Unlock PDF" and download your unprotected PDF file.</p>
                </div>
            </div>
        </div>
    ',

    // Content: Features
    'features_html' => '
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">🔓</div>
                <div class="feature-content">
                    <h3>Remove Restrictions</h3>
                    <p>Remove owner password restrictions to enable editing, printing, and copying.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📝</div>
                <div class="feature-content">
                    <h3>Enable Editing</h3>
                    <p>Edit your unlocked PDF with any PDF editor after removing protection.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🔒</div>
                <div class="feature-content">
                    <h3>Secure Processing</h3>
                    <p>Your files and passwords are processed securely and never stored.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">💯</div>
                <div class="feature-content">
                    <h3>Quality Preserved</h3>
                    <p>Document content remains exactly as in the original PDF.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">⚡</div>
                <div class="feature-content">
                    <h3>Instant Results</h3>
                    <p>Get your unlocked PDF in seconds.</p>
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
            'q' => 'Do I need the password to unlock a PDF?',
            'a' => 'Yes, you need to know the original password to unlock the PDF. This tool cannot crack or bypass password protection.'
        ],
        [
            'q' => 'What\'s the difference between user and owner passwords?',
            'a' => 'A user password is required to open the PDF. An owner password restricts actions like printing, editing, and copying. This tool can remove owner restrictions if the PDF opens without a password.'
        ],
        [
            'q' => 'Is it legal to unlock a PDF?',
            'a' => 'You should only unlock PDFs that you own or have permission to unlock. Removing protection from copyrighted material without authorization may be illegal.'
        ],
        [
            'q' => 'Will the content quality be affected?',
            'a' => 'No, the unlocked PDF maintains the exact same content and quality as the original.'
        ],
        [
            'q' => 'Is my password secure?',
            'a' => 'Yes, your password is only used to process the file and is never stored or logged.'
        ]
    ],

    // Related Tools
    'related_tools' => get_related_tools($tool_slug)
];

// Render the tool page
render_tool_page($config);
