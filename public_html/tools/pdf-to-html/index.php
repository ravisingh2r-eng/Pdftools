<?php
/**
 * PDF to HTML Tool
 *
 * Converts PDF documents to HTML format.
 * Best-effort conversion - extracts text and creates simple HTML structure.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Tool configuration
$tool_slug = 'pdf-to-html';
$tool_config = [
    'title' => 'PDF to HTML - Convert PDF to HTML Online Free',
    'description' => 'Convert PDF documents to HTML format online. Extract text content from PDF files and generate clean HTML with proper structure.',
    'keywords' => 'pdf to html, convert pdf to html, pdf html converter, pdf to web page, extract text to html, pdf html online',
    'canonical' => '/tools/pdf-to-html/',
    'og_title' => 'PDF to HTML Converter - Convert PDF to Web Pages Free',
    'og_description' => 'Convert PDF to HTML online for free. Extract text content and generate clean, structured HTML files.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'PDF to HTML Converter',
        'description' => 'Convert PDF documents to HTML web page format',
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
            'question' => 'Does it preserve formatting and layout?',
            'answer' => 'This tool extracts text content and creates a simple HTML structure with headings and paragraphs. Complex layouts, images, and styling are not preserved.'
        ],
        [
            'question' => 'Does it work with scanned PDFs?',
            'answer' => 'No. Scanned PDFs and images are not supported because there is no OCR. You need a text-based PDF (where you can select and copy text) for best results.'
        ],
        [
            'question' => 'Are images included in the HTML?',
            'answer' => 'No, only text content is extracted. Images, graphics, and embedded media are not included in the output HTML.'
        ],
        [
            'question' => 'What HTML structure is generated?',
            'answer' => 'The output includes basic HTML with page headings (h2) and paragraphs (p). Each PDF page becomes a section in the HTML document.'
        ],
        [
            'question' => 'Are my PDF files stored on your server?',
            'answer' => 'No. Your files are processed in memory and automatically deleted immediately after conversion. We do not store any uploaded documents.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="pdf_file" class="form-label">Upload PDF File</label>
            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
            <div class="form-text">Maximum file size: 20MB. Text-based PDFs only.</div>
        </div>
        <div class="mb-3">
            <label for="include_styles" class="form-label">Include Basic Styles</label>
            <select class="form-select" id="include_styles" name="include_styles">
                <option value="1" selected>Yes - Include basic CSS styling</option>
                <option value="0">No - Plain HTML only</option>
            </select>
        </div>
    ',
    'accept_multiple' => false,
    'file_input_name' => 'pdf_file',
    'max_file_size' => 20 * 1024 * 1024,
    'allowed_extensions' => ['pdf'],
];

// Rate limiting
enforce_rate_limit($tool_slug, 20, 3600);

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
        $includeStyles = (bool)($_POST['include_styles'] ?? true);

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
        $pageTexts = extractPdfTextByPage($inputFile);

        if (empty($pageTexts)) {
            throw new Exception('No text content found in PDF. The file may be scanned or contain only images.');
        }

        // Generate HTML
        $html = generateHtml($pageTexts, $includeStyles);

        // Save HTML file
        $outputFile = $tempDir . '/pdf_html_' . $sessionId . '.html';
        file_put_contents($outputFile, $html);

        if (!file_exists($outputFile)) {
            throw new Exception('Failed to create HTML file.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, $uploadedFile['size'], $durationMs, 'success');

        // Clean up input file
        @unlink($inputFile);

        // Send HTML file
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="converted.html"');
        header('Content-Length: ' . filesize($outputFile));
        header('Cache-Control: no-cache, must-revalidate');

        readfile($outputFile);

        // Clean up output
        @unlink($outputFile);
        exit;

    } catch (Exception $e) {
        log_exception($e, $tool_slug);
        $error_message = $e->getMessage();
    }
}

/**
 * Generate HTML from page texts
 */
function generateHtml($pageTexts, $includeStyles = true) {
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Converted from PDF</title>';

    if ($includeStyles) {
        $html .= '
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            color: #333;
        }
        h2 {
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-top: 30px;
            color: #007bff;
        }
        p {
            margin-bottom: 1em;
            text-align: justify;
        }
        .page-break {
            page-break-after: always;
        }
    </style>';
    }

    $html .= '
</head>
<body>
    <h1>Converted PDF Document</h1>
';

    foreach ($pageTexts as $pageNum => $text) {
        $html .= '    <section class="page">' . "\n";
        $html .= '        <h2>Page ' . ($pageNum + 1) . '</h2>' . "\n";

        // Split text into paragraphs
        $paragraphs = preg_split('/\n\s*\n/', $text);

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (empty($para)) continue;

            // Escape HTML special chars
            $para = htmlspecialchars($para, ENT_QUOTES, 'UTF-8');

            // Replace single newlines with <br>
            $para = nl2br($para);

            $html .= '        <p>' . $para . '</p>' . "\n";
        }

        $html .= '    </section>' . "\n";

        // Add page break class (for printing)
        if ($pageNum < count($pageTexts) - 1) {
            $html .= '    <div class="page-break"></div>' . "\n";
        }
    }

    $html .= '</body>
</html>';

    return $html;
}

/**
 * Extract text from PDF file, organized by page
 */
function extractPdfTextByPage($pdfFile) {
    $pageTexts = [];
    $content = file_get_contents($pdfFile);

    // Find all streams
    preg_match_all('/(\d+)\s+0\s+obj[^>]*>>\s*stream\s*(.*?)\s*endstream/s', $content, $streamMatches, PREG_SET_ORDER);

    $pageNum = 0;
    foreach ($streamMatches as $match) {
        $streamData = $match[2];

        // Try to decompress
        $decoded = @gzuncompress($streamData);
        if ($decoded === false) {
            $decoded = @gzuncompress(substr($streamData, 2));
        }
        if ($decoded === false) {
            $decoded = $streamData;
        }

        // Check for text operators
        if (preg_match('/BT\s.*?\sET/s', $decoded)) {
            $text = extractTextFromStream($decoded);
            if (!empty(trim($text))) {
                $pageTexts[$pageNum] = $text;
                $pageNum++;
            }
        }
    }

    // Fallback
    if (empty($pageTexts)) {
        preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $textBlocks);
        $allText = '';
        foreach ($textBlocks[1] as $block) {
            $allText .= extractTextFromBlock($block) . "\n";
        }
        if (!empty(trim($allText))) {
            $pageTexts[0] = $allText;
        }
    }

    return $pageTexts;
}

/**
 * Extract text from a PDF stream
 */
function extractTextFromStream($stream) {
    $text = '';
    preg_match_all('/BT\s*(.*?)\s*ET/s', $stream, $textBlocks);

    foreach ($textBlocks[1] as $block) {
        $text .= extractTextFromBlock($block) . "\n";
    }

    return $text;
}

/**
 * Extract text from a BT...ET block
 */
function extractTextFromBlock($block) {
    $text = '';

    preg_match_all('/\((.*?)\)\s*Tj/s', $block, $tjMatches);
    foreach ($tjMatches[1] as $str) {
        $text .= decodePdfString($str);
    }

    preg_match_all('/\[(.*?)\]\s*TJ/s', $block, $tjArrayMatches);
    foreach ($tjArrayMatches[1] as $arr) {
        preg_match_all('/\((.*?)\)/', $arr, $strings);
        foreach ($strings[1] as $str) {
            $text .= decodePdfString($str);
        }
    }

    if (preg_match('/Td|TD|T\*|\'|"/', $block)) {
        $text .= ' ';
    }

    return $text;
}

/**
 * Decode PDF string escapes
 */
function decodePdfString($str) {
    $str = str_replace(
        ['\\n', '\\r', '\\t', '\\\\', '\\(', '\\)'],
        ["\n", "\r", "\t", '\\', '(', ')'],
        $str
    );

    $str = preg_replace_callback('/\\\\([0-7]{1,3})/', function($m) {
        return chr(octdec($m[1]));
    }, $str);

    return $str;
}

// Render the tool page
render_tool_page($tool_slug, $tool_config, $error_message ?? null);
