<?php
/**
 * CSV to PDF Tool
 *
 * Converts CSV data to PDF table.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Tool configuration
$tool_slug = 'csv-to-pdf';
$tool_config = [
    'title' => 'CSV to PDF - Convert Spreadsheet Data to PDF Table',
    'description' => 'Convert CSV files to formatted PDF tables. Perfect for exporting spreadsheet data with headers and grid lines.',
    'keywords' => 'csv to pdf, convert csv to pdf, spreadsheet to pdf, excel data to pdf, table pdf generator',
    'canonical' => '/tools/csv-to-pdf/',
    'og_title' => 'CSV to PDF Converter - Create PDF Tables',
    'og_description' => 'Convert CSV spreadsheet data to PDF tables. Free online CSV to PDF converter.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'CSV to PDF Converter',
        'description' => 'Convert CSV data to PDF tables',
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
            'question' => 'What CSV format is supported?',
            'answer' => 'Standard CSV with comma or semicolon delimiters. The first row can optionally be treated as a header with bold formatting.'
        ],
        [
            'question' => 'Will the table fit on the page?',
            'answer' => 'Column widths are automatically calculated to fit the page. For wide tables (more than 5 columns), landscape orientation is used automatically.'
        ],
        [
            'question' => 'What about Excel files?',
            'answer' => 'Please save your Excel file as CSV first, then upload it here. We support .csv files only.'
        ],
        [
            'question' => 'Is there a row limit?',
            'answer' => 'There\'s no strict row limit. Tables automatically continue on new pages. File size is limited to 2MB.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="csv_file" class="form-label">Upload CSV File (optional)</label>
            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv">
            <div class="form-text">Or paste your CSV data below</div>
        </div>
        <div class="mb-3">
            <label for="csv_content" class="form-label">CSV Data</label>
            <textarea class="form-control font-monospace" id="csv_content" name="csv_content" rows="10" placeholder="Name,Email,Phone&#10;John Doe,john@example.com,555-1234&#10;Jane Smith,jane@example.com,555-5678"></textarea>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="delimiter" class="form-label">Delimiter</label>
                <select class="form-select" id="delimiter" name="delimiter">
                    <option value="," selected>Comma (,)</option>
                    <option value=";">Semicolon (;)</option>
                    <option value="	">Tab</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="has_header" class="form-label">First Row is Header</label>
                <select class="form-select" id="has_header" name="has_header">
                    <option value="1" selected>Yes</option>
                    <option value="0">No</option>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label for="font_size" class="form-label">Font Size</label>
            <select class="form-select" id="font_size" name="font_size">
                <option value="8">8pt (Small)</option>
                <option value="10" selected>10pt (Normal)</option>
                <option value="12">12pt (Large)</option>
            </select>
        </div>
    ',
    'accept_multiple' => false,
    'file_input_name' => 'csv_file',
    'max_file_size' => 2 * 1024 * 1024,
    'allowed_extensions' => ['csv'],
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

        $csv = '';

        // Check for uploaded file first
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['csv_file'];

            // Validate file size
            if ($uploadedFile['size'] > $tool_config['max_file_size']) {
                throw new Exception('File size exceeds maximum limit of 2MB.');
            }

            // Validate extension
            $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                throw new Exception('Invalid file type. Only .csv files are allowed.');
            }

            $csv = file_get_contents($uploadedFile['tmp_name']);
        } elseif (!empty($_POST['csv_content'])) {
            $csv = $_POST['csv_content'];
        }

        if (empty(trim($csv))) {
            throw new Exception('Please upload a CSV file or enter CSV data.');
        }

        // Get options
        $delimiter = $_POST['delimiter'] ?? ',';
        $hasHeader = (bool)($_POST['has_header'] ?? true);
        $fontSize = (int)($_POST['font_size'] ?? 10);

        // Validate delimiter
        if (!in_array($delimiter, [',', ';', "\t"])) {
            $delimiter = ',';
        }

        // Create temp directory
        $engine = new PDFEngine();
        $tempDir = $engine->normalize_temp_dir();
        $sessionId = bin2hex(random_bytes(8));

        // Convert to PDF
        $outputFile = $tempDir . '/csv_' . $sessionId . '.pdf';

        $options = [
            'hasHeader' => $hasHeader,
            'delimiter' => $delimiter,
            'fontSize' => $fontSize,
            'margin' => 10,
        ];

        $result = $engine->csv_to_pdf($csv, $outputFile, $options);

        if (!$result || !file_exists($outputFile)) {
            throw new Exception('Failed to convert CSV to PDF.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, strlen($csv), $durationMs, 'success');

        // Send PDF file
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="table.pdf"');
        header('Content-Length: ' . filesize($outputFile));
        header('Cache-Control: no-cache, must-revalidate');

        readfile($outputFile);

        // Clean up
        @unlink($outputFile);
        exit;

    } catch (Exception $e) {
        log_exception($e, $tool_slug);
        $error_message = $e->getMessage();
    }
}

// Render the tool page
render_tool_page($tool_slug, $tool_config, $error_message ?? null);
