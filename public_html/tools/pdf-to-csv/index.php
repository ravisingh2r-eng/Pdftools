<?php
/**
 * PDF to CSV Tool
 *
 * Extracts tabular data from PDF and converts to CSV format.
 * Best-effort extraction - detects columns by spacing patterns.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Tool configuration
$tool_slug = 'pdf-to-csv';
$tool_config = [
    'title' => 'PDF to CSV - Extract Tables from PDF Online Free',
    'description' => 'Extract tabular data from PDF documents and convert to CSV format online. Free PDF table extractor for spreadsheet-ready data.',
    'keywords' => 'pdf to csv, extract tables from pdf, pdf table extractor, convert pdf to csv, pdf data extraction, pdf to spreadsheet',
    'canonical' => '/tools/pdf-to-csv/',
    'og_title' => 'PDF to CSV Converter - Extract Tables from PDF Free',
    'og_description' => 'Extract tables from PDF documents and convert to CSV format online for free. Best-effort tabular data extraction.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'PDF to CSV Converter',
        'description' => 'Extract tabular data from PDF documents and convert to CSV format',
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
            'question' => 'How does table detection work?',
            'answer' => 'The converter splits text lines by multiple spaces or tabs to detect column boundaries. This works best with PDFs that have consistent spacing between columns.'
        ],
        [
            'question' => 'Does it work with scanned PDFs?',
            'answer' => 'No. Scanned PDFs and images are not supported because there is no OCR. You need a text-based PDF (where you can select and copy text) for best results.'
        ],
        [
            'question' => 'What about complex table layouts?',
            'answer' => 'Complex tables with merged cells, nested tables, or irregular layouts may not extract correctly. The tool works best with simple, well-structured tables.'
        ],
        [
            'question' => 'Are my PDF files stored on your server?',
            'answer' => 'No. Your files are processed in memory and automatically deleted immediately after conversion. We do not store any uploaded documents.'
        ],
        [
            'question' => 'How are multiple pages handled?',
            'answer' => 'You can choose to combine all pages into one CSV or add a "Page" column to identify which page each row came from.'
        ],
        [
            'question' => 'Can I import the CSV into Excel?',
            'answer' => 'Yes! CSV files can be opened directly in Microsoft Excel, Google Sheets, LibreOffice Calc, or any spreadsheet application.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="pdf_file" class="form-label">Upload PDF File</label>
            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
            <div class="form-text">Maximum file size: 20MB. Works best with tabular PDFs.</div>
        </div>
        <div class="mb-3">
            <label for="include_page" class="form-label">Include Page Number Column</label>
            <select class="form-select" id="include_page" name="include_page">
                <option value="1" selected>Yes - Add page number column</option>
                <option value="0">No - Combine all pages</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="delimiter" class="form-label">CSV Delimiter</label>
            <select class="form-select" id="delimiter" name="delimiter">
                <option value="," selected>Comma (,)</option>
                <option value=";">Semicolon (;)</option>
                <option value="tab">Tab</option>
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
        $includePage = (bool)($_POST['include_page'] ?? true);
        $delimiter = $_POST['delimiter'] ?? ',';
        if ($delimiter === 'tab') {
            $delimiter = "\t";
        }

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

        // Generate CSV
        $outputFile = $tempDir . '/pdf_csv_' . $sessionId . '.csv';
        $fp = fopen($outputFile, 'w');

        if ($fp === false) {
            throw new Exception('Failed to create CSV file.');
        }

        // Write BOM for Excel compatibility
        fwrite($fp, "\xEF\xBB\xBF");

        $totalRows = 0;

        foreach ($pageTexts as $pageNum => $text) {
            $rows = parseTextToTable($text);

            foreach ($rows as $row) {
                if ($includePage) {
                    array_unshift($row, 'Page ' . ($pageNum + 1));
                }

                fputcsv($fp, $row, $delimiter);
                $totalRows++;
            }
        }

        fclose($fp);

        if ($totalRows === 0) {
            @unlink($outputFile);
            throw new Exception('No tabular data could be extracted from the PDF.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, $uploadedFile['size'], $durationMs, 'success');

        // Clean up input file
        @unlink($inputFile);

        // Send CSV file
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="converted-tables.csv"');
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
 * Parse text content into table rows and columns
 */
function parseTextToTable($text) {
    $rows = [];
    $lines = explode("\n", $text);

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }

        // Split by multiple spaces (2+) or tabs to detect columns
        $cells = preg_split('/\s{2,}|\t/', $line);
        $cells = array_map('trim', $cells);
        $cells = array_filter($cells, function($cell) {
            return $cell !== '';
        });

        if (!empty($cells)) {
            $rows[] = array_values($cells);
        }
    }

    return $rows;
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
