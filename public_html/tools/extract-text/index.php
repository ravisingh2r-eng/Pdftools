<?php
/**
 * Extract Text from PDF Tool
 *
 * Extracts embedded text content from PDF (no OCR).
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Tool configuration
$tool_slug = 'extract-text';
$tool_config = [
    'title' => 'Extract Text from PDF - PDF to Text Converter',
    'description' => 'Extract all text content from your PDF documents. Convert PDF to plain text format for easy editing, searching, or repurposing content.',
    'keywords' => 'extract text from pdf, pdf to text, copy text from pdf, pdf text extractor, convert pdf to txt, get text from pdf',
    'canonical' => '/tools/extract-text/',
    'og_title' => 'Extract Text from PDF - Free PDF to Text Converter',
    'og_description' => 'Extract all text from PDF documents. Free online PDF to text converter - no OCR needed.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'PDF Text Extractor',
        'description' => 'Extract text content from PDF documents',
        'applicationCategory' => 'UtilityApplication',
        'operatingSystem' => 'Any',
        'offers' => [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'USD'
        ]
    ],
    'faq' => [
        [
            'question' => 'Does this use OCR?',
            'answer' => 'No, this tool extracts text that is already embedded in the PDF. For scanned documents or images, you would need an OCR tool.'
        ],
        [
            'question' => 'Why is no text extracted from my PDF?',
            'answer' => 'If your PDF is a scanned image or contains only images, there is no embedded text to extract. You would need OCR for such documents.'
        ],
        [
            'question' => 'Will formatting be preserved?',
            'answer' => 'Only plain text is extracted. Formatting like bold, fonts, and colors are not included. Line breaks and paragraphs are approximated.'
        ],
        [
            'question' => 'What is the output format?',
            'answer' => 'The extracted text is provided as plain text (.txt file) and also displayed on the page for preview. You can copy or download it.'
        ],
        [
            'question' => 'Is there a page limit?',
            'answer' => 'No strict page limit, but very large documents may take longer to process. File size is limited to 20MB.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="pdf_file" class="form-label">Upload PDF File</label>
            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
            <div class="form-text">Maximum file size: 20MB. Note: Only embedded text is extracted (no OCR).</div>
        </div>
    ',
    'accept_multiple' => false,
    'file_input_name' => 'pdf_file',
    'max_file_size' => 20 * 1024 * 1024,
    'allowed_extensions' => ['pdf'],
];

// Rate limiting
enforce_rate_limit($tool_slug, 20, 3600);

// Store extracted text for display
$extracted_text = null;
$download_ready = false;

// Process POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startTime = microtime(true);

    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }

        // Check file upload
        if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed. Please try again.');
        }

        $uploadedFile = $_FILES['pdf_file'];

        // Validate file size
        if ($uploadedFile['size'] > $tool_config['max_file_size']) {
            throw new Exception('File size exceeds maximum limit of 20MB.');
        }

        // Validate file extension
        $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            throw new Exception('Invalid file type. Only PDF files are allowed.');
        }

        // Create temp directory
        $engine = new PDFEngine();
        $tempDir = $engine->normalize_temp_dir();
        $sessionId = bin2hex(random_bytes(8));

        // Move uploaded file
        $inputFile = $tempDir . '/' . $sessionId . '.pdf';
        move_uploaded_file($uploadedFile['tmp_name'], $inputFile);

        // Extract text from PDF
        $extracted_text = extractPdfText($inputFile);

        if (empty(trim($extracted_text))) {
            $extracted_text = "(No text content found. The PDF may contain only images or scanned content.)";
        }

        // Check if download requested
        if (isset($_POST['download']) && $_POST['download'] === '1') {
            // Calculate duration
            $durationMs = (int)((microtime(true) - $startTime) * 1000);

            // Log usage
            log_usage($tool_slug, 1, $uploadedFile['size'], $durationMs, 'success');

            // Clean up input file
            @unlink($inputFile);

            // Send text file
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="extracted-text.txt"');
            header('Content-Length: ' . strlen($extracted_text));
            header('Cache-Control: no-cache, must-revalidate');

            echo $extracted_text;
            exit;
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, $uploadedFile['size'], $durationMs, 'success');

        // Clean up input file
        @unlink($inputFile);

        $download_ready = true;

    } catch (Exception $e) {
        log_exception($e, $tool_slug);
        $error_message = $e->getMessage();
    }
}

/**
 * Extract text content from PDF file
 * This is a best-effort extractor that parses PDF streams for text
 */
function extractPdfText($pdfFile) {
    $text = '';

    // Read PDF content
    $content = file_get_contents($pdfFile);

    // Find all stream objects
    preg_match_all('/stream\s*\n(.*?)endstream/s', $content, $streams);

    foreach ($streams[1] as $stream) {
        // Try to decompress if FlateDecode
        $decoded = $stream;
        if (function_exists('gzuncompress')) {
            $uncompressed = @gzuncompress($stream);
            if ($uncompressed !== false) {
                $decoded = $uncompressed;
            }
        }

        // Extract text between BT (begin text) and ET (end text) blocks
        preg_match_all('/BT\s*(.*?)\s*ET/s', $decoded, $textBlocks);

        foreach ($textBlocks[1] as $block) {
            // Extract text from Tj and TJ operators
            // Tj: show string
            preg_match_all('/\((.*?)\)\s*Tj/s', $block, $tjMatches);
            foreach ($tjMatches[1] as $match) {
                $text .= decodeTextString($match);
            }

            // TJ: show array of strings
            preg_match_all('/\[(.*?)\]\s*TJ/s', $block, $tjArrayMatches);
            foreach ($tjArrayMatches[1] as $arrayContent) {
                preg_match_all('/\((.*?)\)/', $arrayContent, $arrayStrings);
                foreach ($arrayStrings[1] as $str) {
                    $text .= decodeTextString($str);
                }
            }

            // Check for newlines/spacing
            if (preg_match('/T[d*]\s/', $block)) {
                $text .= "\n";
            }
        }
    }

    // Clean up text
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);
    $text = preg_replace('/\n\s*\n/', "\n\n", $text);
    $text = trim($text);

    return $text;
}

/**
 * Decode PDF text string escapes
 */
function decodeTextString($str) {
    // Handle basic escapes
    $str = str_replace(['\\n', '\\r', '\\t', '\\\\', '\\(', '\\)'], ["\n", "\r", "\t", '\\', '(', ')'], $str);

    // Handle octal escapes
    $str = preg_replace_callback('/\\\\([0-7]{1,3})/', function($m) {
        return chr(octdec($m[1]));
    }, $str);

    return $str;
}

// Custom form HTML with results display
if ($download_ready && $extracted_text !== null) {
    $tool_config['form_html'] = '
        <div class="alert alert-success mb-3">
            <strong>Text extracted successfully!</strong>
        </div>
        <div class="mb-3">
            <label class="form-label">Extracted Text Preview</label>
            <textarea class="form-control font-monospace" rows="15" readonly>' . htmlspecialchars($extracted_text) . '</textarea>
        </div>
        <div class="d-flex gap-2 mb-4">
            <form method="post" enctype="multipart/form-data" style="display:inline;">
                <input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">
                <input type="hidden" name="download" value="1">
                <input type="file" name="pdf_file" style="display:none;" id="reupload">
                <button type="button" class="btn btn-primary" onclick="downloadText()">Download as TXT</button>
            </form>
            <button type="button" class="btn btn-secondary" onclick="copyText()">Copy to Clipboard</button>
            <a href="' . $_SERVER['REQUEST_URI'] . '" class="btn btn-outline-secondary">Extract Another</a>
        </div>
        <script>
        function downloadText() {
            const text = document.querySelector("textarea").value;
            const blob = new Blob([text], {type: "text/plain"});
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = "extracted-text.txt";
            a.click();
            URL.revokeObjectURL(url);
        }
        function copyText() {
            const textarea = document.querySelector("textarea");
            textarea.select();
            document.execCommand("copy");
            alert("Text copied to clipboard!");
        }
        </script>
        <hr>
        <h5>Extract from another PDF:</h5>
        <div class="mb-3">
            <label for="pdf_file" class="form-label">Upload PDF File</label>
            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
            <div class="form-text">Maximum file size: 20MB</div>
        </div>
    ';
}

// Render the tool page
render_tool_page($tool_slug, $tool_config, $error_message ?? null);
