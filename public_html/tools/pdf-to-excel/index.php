<?php
/**
 * PDF to Excel (XLSX) Tool
 *
 * Converts PDF documents to Excel spreadsheets using PhpSpreadsheet.
 * Best-effort conversion - extracts tabular text, no complex layout preservation.
 *
 * Note: Requires phpoffice/phpspreadsheet (composer require phpoffice/phpspreadsheet)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// PhpSpreadsheet for Excel creation
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;

// Tool configuration
$tool_slug = 'pdf-to-excel';
$tool_config = [
    'title' => 'PDF to Excel - Convert PDF to XLSX Online Free',
    'description' => 'Convert PDF documents to Excel XLSX spreadsheets online. Extract tables and data from PDF files to editable Excel format for free.',
    'keywords' => 'pdf to excel, pdf to xlsx, convert pdf to excel, pdf excel converter, extract tables from pdf, pdf to spreadsheet',
    'canonical' => '/tools/pdf-to-excel/',
    'og_title' => 'PDF to Excel Converter - PDF to XLSX Online Free',
    'og_description' => 'Convert PDF to Excel spreadsheets online. Extract tabular data from PDF to XLSX format for free.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'PDF to Excel Converter',
        'description' => 'Convert PDF documents to editable Microsoft Excel XLSX format',
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
            'question' => 'Does it preserve table formatting?',
            'answer' => 'This tool extracts text and attempts to detect columns based on spacing. Complex table layouts, merged cells, and formatting are not preserved.'
        ],
        [
            'question' => 'Does it work with scanned PDFs?',
            'answer' => 'No. Scanned PDFs and images are not supported because there is no OCR. You need a text-based PDF (where you can select and copy text) for best results.'
        ],
        [
            'question' => 'What output formats are available?',
            'answer' => 'You can choose between XLSX (Excel) or CSV format. XLSX is recommended for spreadsheet applications, CSV for data import.'
        ],
        [
            'question' => 'Are my PDF files stored on your server?',
            'answer' => 'No. Your files are processed in memory and automatically deleted immediately after conversion. We do not store any uploaded documents.'
        ],
        [
            'question' => 'How are columns detected?',
            'answer' => 'The converter splits text lines by multiple spaces or tab characters to detect column boundaries. This works best with well-structured tabular PDFs.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="pdf_file" class="form-label">Upload PDF File</label>
            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
            <div class="form-text">Maximum file size: 20MB. Works best with tabular PDFs.</div>
        </div>
        <div class="mb-3">
            <label for="output_format" class="form-label">Output Format</label>
            <select class="form-select" id="output_format" name="output_format">
                <option value="xlsx" selected>XLSX (Excel)</option>
                <option value="csv">CSV (Comma-separated)</option>
            </select>
        </div>
    ',
    'accept_multiple' => false,
    'file_input_name' => 'pdf_file',
    'max_file_size' => 20 * 1024 * 1024,
    'allowed_extensions' => ['pdf'],
];

// Rate limiting
enforce_rate_limit($tool_slug, 15, 3600);

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
        $outputFormat = $_POST['output_format'] ?? 'xlsx';

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

        // Create spreadsheet
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('PDF Tools')
            ->setTitle('Converted from PDF');

        // Process each page
        $sheetIndex = 0;
        foreach ($pageTexts as $pageNum => $text) {
            // Create sheet for each page (or use single sheet)
            if ($sheetIndex === 0) {
                $sheet = $spreadsheet->getActiveSheet();
            } else {
                $sheet = $spreadsheet->createSheet();
            }
            $sheet->setTitle('Page ' . ($pageNum + 1));

            // Parse text into rows and columns
            $rows = parseTextToTable($text);

            // Write to spreadsheet
            $rowNum = 1;
            foreach ($rows as $row) {
                $colNum = 1;
                foreach ($row as $cell) {
                    $sheet->setCellValueByColumnAndRow($colNum, $rowNum, $cell);
                    $colNum++;
                }
                $rowNum++;
            }

            // Auto-size columns
            $highestCol = $sheet->getHighestColumn();
            for ($col = 'A'; $col <= $highestCol; $col++) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $sheetIndex++;
        }

        // Save file
        if ($outputFormat === 'csv') {
            $outputFile = $tempDir . '/pdf_excel_' . $sessionId . '.csv';
            $writer = new CsvWriter($spreadsheet);
            $writer->setDelimiter(',');
            $writer->setEnclosure('"');
            $writer->save($outputFile);
            $contentType = 'text/csv';
            $filename = 'converted-excel.csv';
        } else {
            $outputFile = $tempDir . '/pdf_excel_' . $sessionId . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($outputFile);
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $filename = 'converted-excel.xlsx';
        }

        if (!file_exists($outputFile)) {
            throw new Exception('Failed to create spreadsheet file.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, $uploadedFile['size'], $durationMs, 'success');

        // Clean up input file
        @unlink($inputFile);

        // Send file
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
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

    // Fallback method
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

    // Extract Tj operators
    preg_match_all('/\((.*?)\)\s*Tj/s', $block, $tjMatches);
    foreach ($tjMatches[1] as $str) {
        $text .= decodePdfString($str);
    }

    // Extract TJ operators
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
