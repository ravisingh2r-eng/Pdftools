<?php
/**
 * Rotate PDF Tool
 *
 * Rotate PDF pages to any angle.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../_template_tool.php';

// Define tool slug
$tool_slug = 'rotate-pdf';

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
    $outputPath = null;

    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }

        // Check if file was uploaded
        if (empty($_FILES['pdf_files']['name'][0])) {
            throw new Exception('Please select a PDF file to rotate.');
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

        // Get rotation angle
        $rotation = (int)($_POST['rotation_angle'] ?? 90);
        if (!in_array($rotation, [90, 180, 270])) {
            $rotation = 90;
        }

        // Generate output filename
        $outputPath = $pdfEngine->generate_temp_filename('rotated', 'pdf');

        // Rotate the PDF
        $result = $pdfEngine->rotate_pdf($uploadedFile, $rotation, $outputPath);

        if (!$result || !file_exists($outputPath)) {
            throw new Exception('Failed to rotate PDF file.');
        }

        // Stream file for download
        $downloadName = 'rotated_' . date('Ymd_His') . '.pdf';
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

        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();

        // Cleanup on error
        if ($uploadedFile && file_exists($uploadedFile)) {
            unlink($uploadedFile);
        }
        if ($outputPath && file_exists($outputPath)) {
            unlink($outputPath);
        }
    }
}

// Build error message HTML
$errorHtml = '';
if ($error) {
    $errorHtml = '<div class="message message-error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
}

// Custom form fields for rotation options
$customFormHtml = $errorHtml . '
<div class="tool-options">
    <label class="option-label">Rotation Angle</label>
    <div class="radio-group rotation-options">
        <label class="radio-option">
            <input type="radio" name="rotation_angle" value="90" checked>
            <span class="rotation-preview">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/>
                    <path d="M21 3v5h-5"/>
                </svg>
                90° Clockwise
            </span>
        </label>
        <label class="radio-option">
            <input type="radio" name="rotation_angle" value="180">
            <span class="rotation-preview">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12a9 9 0 1 1-9-9"/>
                    <path d="M3 12a9 9 0 0 1 9-9"/>
                </svg>
                180° Flip
            </span>
        </label>
        <label class="radio-option">
            <input type="radio" name="rotation_angle" value="270">
            <span class="rotation-preview">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 12a9 9 0 1 0 9 9c0-2.52-1-4.93-2.74-6.74L3 8"/>
                    <path d="M3 3v5h5"/>
                </svg>
                90° Counter-clockwise
            </span>
        </label>
    </div>
</div>';

// Tool configuration
$config = [
    'tool_slug' => 'rotate-pdf',
    'tool_name' => 'Rotate PDF',
    'primary_keyword' => 'rotate PDF online',

    'meta_title' => 'Rotate PDF Online - Free PDF Rotator | ' . SITE_NAME,
    'meta_description' => 'Rotate PDF pages online for free. Turn pages 90, 180, or 270 degrees easily. No registration or software installation required.',
    'canonical_url' => BASE_URL . '/tools/rotate-pdf/',

    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'og_image_url' => BASE_URL . '/assets/img/rotate-pdf-og.png',

    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your PDF file here or click to browse',
    'button_text' => 'Rotate PDF',

    'custom_form_html' => $customFormHtml,

    'short_intro_html' => '
        <p>Easily rotate all pages in your PDF document. Choose 90°, 180°, or 270° rotation to fix page orientation instantly.</p>
    ',

    'how_it_works_html' => '
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Upload Your PDF</h3>
                    <p>Select the PDF file you want to rotate by clicking the upload area or dragging the file.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Select Rotation Angle</h3>
                    <p>Choose 90° clockwise, 180° flip, or 90° counter-clockwise rotation.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Download Result</h3>
                    <p>Click "Rotate PDF" and download your rotated PDF file instantly.</p>
                </div>
            </div>
        </div>
    ',

    'features_html' => '
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">🔄</div>
                <div class="feature-content">
                    <h3>Multiple Angles</h3>
                    <p>Rotate pages 90°, 180°, or 270° to correct any orientation.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📄</div>
                <div class="feature-content">
                    <h3>All Pages</h3>
                    <p>Rotation applies to all pages in the document at once.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">💯</div>
                <div class="feature-content">
                    <h3>Quality Preserved</h3>
                    <p>Original PDF quality is maintained after rotation.</p>
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
                <div class="feature-icon">⚡</div>
                <div class="feature-content">
                    <h3>Instant Processing</h3>
                    <p>Rotate your PDF in seconds with no waiting.</p>
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
            'q' => 'Does rotation affect PDF quality?',
            'a' => 'No, rotation preserves the original quality of your PDF. All text, images, and formatting remain intact.'
        ],
        [
            'q' => 'Can I rotate individual pages?',
            'a' => 'Currently, this tool rotates all pages by the same angle. For individual page rotation, you may need to split and merge.'
        ],
        [
            'q' => 'What is the difference between the rotation options?',
            'a' => '90° clockwise turns pages to the right, 90° counter-clockwise turns them to the left, and 180° flips them upside down.'
        ],
        [
            'q' => 'Can I rotate a scanned PDF?',
            'a' => 'Yes, you can rotate any PDF including scanned documents. The tool works with all PDF types.'
        ]
    ],

    // Related Tools - dynamically loaded from registry
    'related_tools' => get_related_tools($tool_slug)
];

render_tool_page($config);
