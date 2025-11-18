<?php
/**
 * Compress PDF Tool
 *
 * Reduce PDF file size while maintaining quality.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../_template_tool.php';

// Define tool slug
$tool_slug = 'compress-pdf';

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
            throw new Exception('Please select a PDF file to compress.');
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

        // Get compression mode
        $compressionMode = $_POST['compression_mode'] ?? 'medium';
        if (!in_array($compressionMode, ['low', 'medium', 'high'])) {
            $compressionMode = 'medium';
        }

        // Generate output filename
        $outputPath = $pdfEngine->generate_temp_filename('compressed', 'pdf');

        // Compress the PDF
        $result = $pdfEngine->compress_pdf_basic($uploadedFile, $outputPath, $compressionMode);

        if (!$result || !file_exists($outputPath)) {
            throw new Exception('Failed to compress PDF file.');
        }

        // Calculate compression stats
        $originalSize = filesize($uploadedFile);
        $compressedSize = filesize($outputPath);
        $savings = $originalSize - $compressedSize;
        $percentage = $originalSize > 0 ? round(($savings / $originalSize) * 100, 1) : 0;

        // Stream file for download
        $downloadName = 'compressed_' . date('Ymd_His') . '.pdf';

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . $compressedSize);
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

// Custom form fields for compression options
$customFormHtml = $errorHtml . '
<div class="tool-options">
    <label class="option-label">Compression Level</label>
    <div class="radio-group">
        <label class="radio-option">
            <input type="radio" name="compression_mode" value="low">
            <span>
                <strong>Low Compression</strong>
                <small>Best quality, smaller file reduction</small>
            </span>
        </label>
        <label class="radio-option">
            <input type="radio" name="compression_mode" value="medium" checked>
            <span>
                <strong>Medium Compression</strong>
                <small>Good balance of quality and size</small>
            </span>
        </label>
        <label class="radio-option">
            <input type="radio" name="compression_mode" value="high">
            <span>
                <strong>High Compression</strong>
                <small>Maximum reduction, some quality loss</small>
            </span>
        </label>
    </div>
</div>';

// Tool configuration
$config = [
    'tool_slug' => 'compress-pdf',
    'tool_name' => 'Compress PDF',
    'primary_keyword' => 'compress PDF online',

    'meta_title' => 'Compress PDF Online - Free PDF Compressor | ' . SITE_NAME,
    'meta_description' => 'Reduce PDF file size online for free while maintaining quality. Perfect for email attachments and web uploads. No registration required.',
    'canonical_url' => BASE_URL . '/tools/compress-pdf/',

    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'og_image_url' => BASE_URL . '/assets/img/compress-pdf-og.png',

    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your PDF file here or click to browse',
    'button_text' => 'Compress PDF',

    'custom_form_html' => $customFormHtml,

    'short_intro_html' => '
        <p>Reduce your PDF file size instantly while preserving quality. Perfect for email attachments, web uploads, and storage optimization.</p>
    ',

    'how_it_works_html' => '
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Upload Your PDF</h3>
                    <p>Select the PDF file you want to compress by clicking the upload area or dragging the file.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Choose Compression Level</h3>
                    <p>Select low, medium, or high compression based on your quality and size requirements.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Download Result</h3>
                    <p>Click "Compress PDF" and download your smaller PDF file instantly.</p>
                </div>
            </div>
        </div>
    ',

    'features_html' => '
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">📉</div>
                <div class="feature-content">
                    <h3>Significant Size Reduction</h3>
                    <p>Reduce PDF file size by up to 80% depending on content and compression level.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🎨</div>
                <div class="feature-content">
                    <h3>Quality Control</h3>
                    <p>Choose from three compression levels to balance quality and file size.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📧</div>
                <div class="feature-content">
                    <h3>Email Ready</h3>
                    <p>Compress large PDFs to meet email attachment size limits.</p>
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
                    <h3>Fast Processing</h3>
                    <p>Compress your PDF in seconds with our optimized algorithms.</p>
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
            'q' => 'How much can I reduce my PDF size?',
            'a' => 'Compression results vary based on the PDF content. PDFs with images typically see larger reductions (30-80%), while text-heavy PDFs may see smaller reductions (10-30%).'
        ],
        [
            'q' => 'Will compression affect my PDF quality?',
            'a' => 'Low and medium compression preserve good quality. High compression may slightly reduce image quality but significantly reduces file size.'
        ],
        [
            'q' => 'What is the maximum file size I can compress?',
            'a' => 'You can compress PDF files up to 50MB in size.'
        ],
        [
            'q' => 'Can I compress a password-protected PDF?',
            'a' => 'Currently, password-protected PDFs cannot be compressed. Please remove the password first.'
        ],
        [
            'q' => 'Is the text in my PDF still searchable after compression?',
            'a' => 'Yes, all text remains searchable. Compression only affects embedded images and metadata.'
        ]
    ],

    // Related Tools - dynamically loaded from registry
    'related_tools' => get_related_tools($tool_slug)
];

render_tool_page($config);
