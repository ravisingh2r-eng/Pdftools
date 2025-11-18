<?php
/**
 * Protect PDF Tool
 *
 * Add password protection to PDF files.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../_template_tool.php';

// Define tool slug
$tool_slug = 'protect-pdf';

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
            throw new Exception('Please select a PDF file to protect.');
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

        // Get passwords
        $userPassword = trim($_POST['user_password'] ?? '');
        $ownerPassword = trim($_POST['owner_password'] ?? '');

        // Validate user password
        if (empty($userPassword)) {
            throw new Exception('Please enter a password to protect your PDF.');
        }

        if (strlen($userPassword) < 4) {
            throw new Exception('Password must be at least 4 characters long.');
        }

        // Owner password defaults to user password if not specified
        if (empty($ownerPassword)) {
            $ownerPassword = $userPassword;
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

        // Generate output filename
        $outputPath = $pdfEngine->generate_temp_filename('protected', 'pdf');

        // Protect the PDF
        $result = $pdfEngine->protect_pdf($uploadedFile, $userPassword, $ownerPassword, $outputPath);

        if (!$result || !file_exists($outputPath)) {
            throw new Exception('Failed to protect PDF file.');
        }

        // Stream file for download
        $downloadName = 'protected_' . date('Ymd_His') . '.pdf';
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

// Custom form fields for password inputs
$customFormHtml = $errorHtml . '
<div class="tool-options">
    <div class="option-field">
        <label for="user_password">Password to open PDF <span class="required">*</span></label>
        <input type="password" name="user_password" id="user_password" required minlength="4"
               placeholder="Enter password (min. 4 characters)" autocomplete="new-password">
        <small class="field-hint">This password will be required to open the PDF file.</small>
    </div>

    <div class="option-field">
        <label for="owner_password">Owner password (optional)</label>
        <input type="password" name="owner_password" id="owner_password"
               placeholder="Enter owner password" autocomplete="new-password">
        <small class="field-hint">Different password for full permissions. Leave empty to use same password.</small>
    </div>

    <div class="password-toggle">
        <label>
            <input type="checkbox" id="showPasswords">
            Show passwords
        </label>
    </div>
</div>';

// Custom scripts for password toggle
$customScriptsHtml = '
<script>
document.getElementById("showPasswords").addEventListener("change", function() {
    var type = this.checked ? "text" : "password";
    document.getElementById("user_password").type = type;
    document.getElementById("owner_password").type = type;
});
</script>';

// Tool configuration
$config = [
    'tool_slug' => 'protect-pdf',
    'tool_name' => 'Protect PDF',
    'primary_keyword' => 'password protect PDF online',

    'meta_title' => 'Protect PDF with Password Online - Free PDF Encryption | ' . SITE_NAME,
    'meta_description' => 'Add password protection to your PDF files online for free. Secure your documents with encryption. No registration or software required.',
    'canonical_url' => BASE_URL . '/tools/protect-pdf/',

    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'og_image_url' => BASE_URL . '/assets/img/protect-pdf-og.png',

    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => false,
    'upload_text' => 'Drop your PDF file here or click to browse',
    'button_text' => 'Protect PDF',

    'custom_form_html' => $customFormHtml,
    'custom_scripts_html' => $customScriptsHtml,

    'short_intro_html' => '
        <p>Secure your PDF documents with password protection. Add encryption to prevent unauthorized access to your sensitive files.</p>
    ',

    'how_it_works_html' => '
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Upload Your PDF</h3>
                    <p>Select the PDF file you want to protect by clicking the upload area or dragging the file.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Set Password</h3>
                    <p>Enter a strong password. Optionally set a different owner password for full control.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Download Protected PDF</h3>
                    <p>Click "Protect PDF" and download your encrypted file. Remember your password!</p>
                </div>
            </div>
        </div>
    ',

    'features_html' => '
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">🔐</div>
                <div class="feature-content">
                    <h3>Strong Encryption</h3>
                    <p>Your PDF is encrypted with industry-standard security algorithms.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">👤</div>
                <div class="feature-content">
                    <h3>Two Password Levels</h3>
                    <p>Set user password to open and owner password for full permissions.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🛡️</div>
                <div class="feature-content">
                    <h3>Permission Control</h3>
                    <p>Control printing and copying permissions with owner password.</p>
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
                    <h3>Instant Protection</h3>
                    <p>Protect your PDF in seconds with no waiting.</p>
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
            'q' => 'What is the difference between user and owner password?',
            'a' => 'The user password is required to open and view the PDF. The owner password provides full permissions including printing and copying. If someone has the owner password, they can modify the PDF settings.'
        ],
        [
            'q' => 'What happens if I forget the password?',
            'a' => 'There is no way to recover a forgotten password. Make sure to store your password in a secure place. We do not store any passwords on our servers.'
        ],
        [
            'q' => 'Is my password stored on your servers?',
            'a' => 'No, we do not store your password. The encryption happens during processing and both the file and password are deleted immediately after you download the protected PDF.'
        ],
        [
            'q' => 'Can I remove the password later?',
            'a' => 'Yes, but you will need to know the password. You can use a PDF tool to remove the password by entering the correct password first.'
        ],
        [
            'q' => 'How secure is the encryption?',
            'a' => 'The PDF is encrypted using standard PDF encryption which is widely supported. For highly sensitive documents, consider additional security measures.'
        ]
    ],

    // Related Tools - dynamically loaded from registry
    'related_tools' => get_related_tools($tool_slug)
];

render_tool_page($config);
