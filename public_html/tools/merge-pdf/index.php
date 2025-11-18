<?php
/**
 * Merge PDF Tool
 *
 * Combine multiple PDF files into a single document.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';

// Page meta information
$tool_name = 'Merge PDF';
$tool_description = 'Combine multiple PDF files into one document online for free. Easy to use, no registration required.';
$page_title = $tool_name . ' - ' . SITE_NAME;
$canonical_url = BASE_URL . '/tools/merge-pdf/';

// Initialize variables
$error = null;
$success = false;
$uploadedFiles = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $tempDir = $pdfEngine->normalize_temp_dir();

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
        $fileSize = filesize($outputPath);

        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set download headers
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . $fileSize);
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

// Generate CSRF token for the form
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo h($tool_description); ?>">
    <meta name="robots" content="index, follow">

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo h($page_title); ?>">
    <meta property="og:description" content="<?php echo h($tool_description); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo h($canonical_url); ?>">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo h($canonical_url); ?>">

    <title><?php echo h($page_title); ?></title>

    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/style.css">

    <style>
        /* Tool-specific styles */
        .upload-area {
            border: 3px dashed var(--border-color);
            border-radius: var(--radius);
            padding: 3rem 2rem;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            background: #fafafa;
        }

        .upload-area:hover,
        .upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(33, 150, 243, 0.05);
        }

        .upload-area input[type="file"] {
            display: none;
        }

        .upload-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .upload-text {
            font-size: 1.1rem;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }

        .upload-hint {
            font-size: 0.85rem;
            color: var(--text-light);
        }

        .file-list {
            margin: 1.5rem 0;
            text-align: left;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: #f5f5f5;
            border-radius: var(--radius);
            margin-bottom: 0.5rem;
        }

        .file-item-name {
            font-size: 0.9rem;
            word-break: break-all;
        }

        .file-item-remove {
            background: none;
            border: none;
            color: #f44336;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.25rem;
        }

        .file-item-remove:hover {
            color: #d32f2f;
        }

        .merge-btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .merge-btn:hover {
            background: var(--primary-dark);
        }

        .merge-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
        }

        .info-box {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: var(--radius);
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: #1565c0;
        }

        .reorder-hint {
            font-size: 0.85rem;
            color: var(--text-light);
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <!-- Header Include -->
    <div data-include="/partials/header.html"></div>

    <!-- Tool Page Content -->
    <main class="main-content tool-page">
        <div class="container">
            <div class="tool-header">
                <h1><?php echo h($tool_name); ?></h1>
                <p><?php echo h($tool_description); ?></p>
            </div>

            <div class="tool-content">
                <?php if ($error): ?>
                <div class="error-message">
                    <?php echo h($error); ?>
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="mergeForm">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">

                    <!-- Upload Area -->
                    <div class="upload-area" id="uploadArea">
                        <input type="file" name="pdf_files[]" id="fileInput" multiple accept=".pdf,application/pdf">
                        <div class="upload-icon">📄</div>
                        <div class="upload-text">Drop PDF files here or click to browse</div>
                        <div class="upload-hint">Select multiple PDF files (minimum 2)</div>
                    </div>

                    <!-- File List -->
                    <div class="file-list" id="fileList" style="display: none;">
                        <p class="reorder-hint">Files will be merged in the order shown below:</p>
                    </div>

                    <!-- Merge Button -->
                    <button type="submit" class="merge-btn" id="mergeBtn" disabled>
                        Merge PDF Files
                    </button>
                </form>

                <div class="info-box">
                    <strong>How it works:</strong> Select 2 or more PDF files, then click "Merge PDF Files".
                    Your files will be combined in the order selected, and the merged PDF will download automatically.
                    All files are automatically deleted after processing.
                </div>
            </div>
        </div>
    </main>

    <!-- Footer Include -->
    <div data-include="/partials/footer.html"></div>

    <!-- Include.js for dynamic includes -->
    <script src="/include.js"></script>

    <script>
        (function() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('fileInput');
            const fileList = document.getElementById('fileList');
            const mergeBtn = document.getElementById('mergeBtn');
            const mergeForm = document.getElementById('mergeForm');

            let selectedFiles = [];

            // Click to open file dialog
            uploadArea.addEventListener('click', () => fileInput.click());

            // Handle file selection
            fileInput.addEventListener('change', handleFiles);

            // Drag and drop events
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                handleFiles({ target: { files: e.dataTransfer.files } });
            });

            function handleFiles(e) {
                const files = Array.from(e.target.files || []);

                // Filter for PDF files only
                const pdfFiles = files.filter(file => {
                    return file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
                });

                if (pdfFiles.length !== files.length) {
                    alert('Some files were skipped. Only PDF files are allowed.');
                }

                // Add to selected files
                selectedFiles = [...selectedFiles, ...pdfFiles];

                updateFileList();
                updateMergeButton();
            }

            function updateFileList() {
                if (selectedFiles.length === 0) {
                    fileList.style.display = 'none';
                    return;
                }

                fileList.style.display = 'block';

                // Clear existing items (keep the hint)
                const existingItems = fileList.querySelectorAll('.file-item');
                existingItems.forEach(item => item.remove());

                // Add file items
                selectedFiles.forEach((file, index) => {
                    const item = document.createElement('div');
                    item.className = 'file-item';
                    item.innerHTML = `
                        <span class="file-item-name">${index + 1}. ${file.name}</span>
                        <button type="button" class="file-item-remove" data-index="${index}">&times;</button>
                    `;
                    fileList.appendChild(item);
                });

                // Add remove event listeners
                fileList.querySelectorAll('.file-item-remove').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const index = parseInt(e.target.dataset.index);
                        selectedFiles.splice(index, 1);
                        updateFileList();
                        updateMergeButton();
                    });
                });

                // Update the file input with selected files
                updateFileInput();
            }

            function updateFileInput() {
                // Create a new DataTransfer to update the input
                const dt = new DataTransfer();
                selectedFiles.forEach(file => dt.items.add(file));
                fileInput.files = dt.files;
            }

            function updateMergeButton() {
                mergeBtn.disabled = selectedFiles.length < 2;
                mergeBtn.textContent = selectedFiles.length > 0
                    ? `Merge ${selectedFiles.length} PDF Files`
                    : 'Merge PDF Files';
            }

            // Form submission
            mergeForm.addEventListener('submit', (e) => {
                if (selectedFiles.length < 2) {
                    e.preventDefault();
                    alert('Please select at least 2 PDF files to merge.');
                    return;
                }

                mergeBtn.disabled = true;
                mergeBtn.textContent = 'Merging...';
            });
        })();
    </script>
</body>
</html>
