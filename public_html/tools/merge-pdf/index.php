<?php
/**
 * Merge PDF Tool
 *
 * Combine multiple PDF files into a single document.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../_template_tool.php';

// Initialize error variable for display
$error = null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadedFiles = [];

    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }

        // Check if files were uploaded
        if (empty($_FILES['pdf_files']['name'][0])) {
            throw new Exception('Please select at least 2 PDF files to merge.');
        }

        // Count valid files
        $fileCount = count(array_filter($_FILES['pdf_files']['name']));
        if ($fileCount < 2) {
            throw new Exception('Please select at least 2 PDF files to merge.');
        }

        // Initialize PDF engine
        $pdfEngine = new PDFEngine();

        // Process each uploaded file
        $filesToMerge = [];

        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = $_FILES['pdf_files']['name'][$i];
            $tmpName = $_FILES['pdf_files']['tmp_name'][$i];
            $fileError = $_FILES['pdf_files']['error'][$i];
            $fileSize = $_FILES['pdf_files']['size'][$i];

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
                throw new Exception("Upload error for '{$fileName}': {$errorMsg}");
            }

            // Validate file size
            if ($fileSize > MAX_FILE_SIZE) {
                $maxMB = MAX_FILE_SIZE / (1024 * 1024);
                throw new Exception("File '{$fileName}' exceeds maximum size of {$maxMB}MB.");
            }

            // Validate file extension
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                throw new Exception("File '{$fileName}' is not a PDF file.");
            }

            // Generate temp filename and move uploaded file
            $tempPath = $pdfEngine->generate_temp_filename('upload_' . $i, 'pdf');

            if (!move_uploaded_file($tmpName, $tempPath)) {
                throw new Exception("Failed to save uploaded file: {$fileName}");
            }

            // Validate PDF content
            if (!$pdfEngine->is_valid_pdf($tempPath)) {
                unlink($tempPath);
                throw new Exception("File '{$fileName}' is not a valid PDF.");
            }

            $uploadedFiles[] = $tempPath;
            $filesToMerge[] = $tempPath;
        }

        // Generate output filename
        $outputPath = $pdfEngine->generate_temp_filename('merged', 'pdf');

        // Merge the PDFs
        $result = $pdfEngine->merge_pdfs($filesToMerge, $outputPath);

        if (!$result || !file_exists($outputPath)) {
            throw new Exception('Failed to merge PDF files. Please try again.');
        }

        // Clean up uploaded files
        $pdfEngine->delete_temp_files($uploadedFiles);

        // Stream the merged file for download
        $downloadName = 'merged_' . date('Ymd_His') . '.pdf';
        $outputSize = filesize($outputPath);

        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set download headers
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . $outputSize);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Output file and clean up
        readfile($outputPath);
        unlink($outputPath);

        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();

        // Clean up any uploaded files on error
        if (!empty($uploadedFiles)) {
            $pdfEngine = new PDFEngine();
            $pdfEngine->delete_temp_files($uploadedFiles);
        }
    }
}

// Build error message HTML if there's an error
$errorHtml = '';
if ($error) {
    $errorHtml = '<div class="message message-error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
}

// Tool configuration
$config = [
    // Basic Info
    'tool_slug' => 'merge-pdf',
    'tool_name' => 'Merge PDF',
    'primary_keyword' => 'merge PDF files online',

    // SEO Meta
    'meta_title' => 'Merge PDF Files Online - Free PDF Combiner | ' . SITE_NAME,
    'meta_description' => 'Combine multiple PDF files into one document online for free. Easy to use PDF merger with drag & drop support. No registration, no watermarks.',
    'canonical_url' => BASE_URL . '/tools/merge-pdf/',

    // Schema Info
    'author_name' => 'PDF Tools Team',
    'publish_date' => '2024-01-01T00:00:00+00:00',
    'modified_date' => date('c'),
    'brand_name' => SITE_NAME,
    'og_image_url' => BASE_URL . '/assets/img/merge-pdf-og.png',

    // Upload Settings
    'upload_accept' => '.pdf,application/pdf',
    'upload_multiple' => true,
    'upload_text' => 'Drop your PDF files here or click to browse',
    'button_text' => 'Merge PDF Files',

    // Error message (if any)
    'custom_form_html' => $errorHtml,

    // Content: Introduction
    'short_intro_html' => '
        <p>Combine multiple PDF documents into a single file in seconds. Simply upload your files, arrange them in the desired order, and download your merged PDF.</p>
    ',

    // Content: How It Works
    'how_it_works_html' => '
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Upload PDF Files</h3>
                    <p>Click the upload area or drag and drop multiple PDF files. You can add as many files as you need.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Arrange Order</h3>
                    <p>Review your files in the list. They will be merged in the order shown. Remove any files you don\'t need.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Download Result</h3>
                    <p>Click "Merge PDF Files" and your combined document will download automatically. All files are deleted after processing.</p>
                </div>
            </div>
        </div>
    ',

    // Content: Features
    'features_html' => '
        <div class="features-grid">
            <div class="feature-item">
                <div class="feature-icon">🚀</div>
                <div class="feature-content">
                    <h3>Fast Processing</h3>
                    <p>Merge your PDF files in seconds with our optimized server-side processing.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🔒</div>
                <div class="feature-content">
                    <h3>Secure & Private</h3>
                    <p>Your files are processed securely and automatically deleted after download.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">📱</div>
                <div class="feature-content">
                    <h3>Works Everywhere</h3>
                    <p>Use on any device - desktop, tablet, or smartphone. No software installation needed.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">💯</div>
                <div class="feature-content">
                    <h3>Quality Preserved</h3>
                    <p>Original quality is maintained. No compression or quality loss in your merged PDF.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🔢</div>
                <div class="feature-content">
                    <h3>Unlimited Files</h3>
                    <p>Merge as many PDF files as you need. No artificial limits on file count.</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">💰</div>
                <div class="feature-content">
                    <h3>100% Free</h3>
                    <p>No registration, no hidden fees, no watermarks. Completely free to use.</p>
                </div>
            </div>
        </div>
    ',

    // FAQ Items
    'faq_items' => [
        [
            'q' => 'How many PDF files can I merge at once?',
            'a' => 'You can merge as many PDF files as you need. There is no limit on the number of files. However, each individual file must be under 50MB in size.'
        ],
        [
            'q' => 'Will the quality of my PDFs be reduced?',
            'a' => 'No, the quality of your PDF files will remain exactly the same. We preserve all text, images, and formatting in the original quality.'
        ],
        [
            'q' => 'Is it safe to upload my PDF files?',
            'a' => 'Yes, your files are processed securely on our servers and automatically deleted immediately after you download the merged file. We do not store or access the contents of your documents.'
        ],
        [
            'q' => 'Can I change the order of PDF pages?',
            'a' => 'Yes, you can arrange the files in any order before merging. The files will be combined in the exact order shown in the file list.'
        ],
        [
            'q' => 'Do I need to create an account?',
            'a' => 'No, you can use our PDF merger without registration. Simply upload your files and download the result - no account required.'
        ],
        [
            'q' => 'What browsers are supported?',
            'a' => 'Our tool works on all modern browsers including Chrome, Firefox, Safari, and Edge. It also works on mobile browsers.'
        ]
    ],

    // Related Tools
    'related_tools' => [
        ['slug' => 'split-pdf', 'name' => 'Split PDF'],
        ['slug' => 'compress-pdf', 'name' => 'Compress PDF'],
        ['slug' => 'rotate-pdf', 'name' => 'Rotate PDF'],
        ['slug' => 'protect-pdf', 'name' => 'Protect PDF']
    ]
];

// Render the tool page
render_tool_page($config);
