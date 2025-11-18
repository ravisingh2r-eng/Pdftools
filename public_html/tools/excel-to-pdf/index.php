<?php
/**
 * Excel to PDF Tool
 *
 * Converts XLSX/XLS/CSV files to PDF using PhpSpreadsheet and FPDF.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// PhpSpreadsheet for Excel parsing
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Tool configuration
$tool_slug = 'excel-to-pdf';
$tool_config = [
    'title' => 'Excel to PDF - Convert XLSX/XLS/CSV to PDF Online',
    'description' => 'Convert Microsoft Excel spreadsheets (XLSX, XLS) and CSV files to PDF format online. Renders data as formatted tables with automatic column sizing and page breaks.',
    'keywords' => 'excel to pdf, xlsx to pdf, xls to pdf, csv to pdf, spreadsheet to pdf, convert excel to pdf',
    'canonical' => '/tools/excel-to-pdf/',
    'og_title' => 'Excel to PDF Converter - Spreadsheet to PDF Online Free',
    'og_description' => 'Convert Excel and CSV files to PDF tables online. Supports XLSX, XLS, and CSV formats.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'Excel to PDF Converter',
        'description' => 'Convert Excel spreadsheets and CSV files to PDF format',
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
            'question' => 'What spreadsheet formats are supported?',
            'answer' => 'We support XLSX (Excel 2007+), XLS (older Excel), and CSV files. XLSX is recommended for best compatibility.'
        ],
        [
            'question' => 'Are formulas calculated?',
            'answer' => 'Yes, formula results are included in the PDF. The PDF shows calculated values, not the formulas themselves.'
        ],
        [
            'question' => 'What about multiple worksheets?',
            'answer' => 'Each worksheet in your Excel file is converted to separate pages in the PDF, with the sheet name as a header.'
        ],
        [
            'question' => 'Are cell colors and styles preserved?',
            'answer' => 'Basic styling like bold headers is preserved. Cell colors, borders, and complex formatting are simplified for the PDF output.'
        ],
        [
            'question' => 'What is the maximum file size?',
            'answer' => 'You can upload spreadsheets up to 10MB. Very large spreadsheets with many rows may take longer to process.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="input_file" class="form-label">Upload Spreadsheet</label>
            <input type="file" class="form-control" id="input_file" name="input_file" accept=".xlsx,.xls,.csv" required>
            <div class="form-text">Supported formats: XLSX, XLS, CSV. Max size: 10MB</div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="font_size" class="form-label">Font Size</label>
                <select class="form-select" id="font_size" name="font_size">
                    <option value="8">8pt (More columns)</option>
                    <option value="10" selected>10pt (Normal)</option>
                    <option value="12">12pt (Larger text)</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="orientation" class="form-label">Page Orientation</label>
                <select class="form-select" id="orientation" name="orientation">
                    <option value="auto" selected>Auto (based on columns)</option>
                    <option value="P">Portrait</option>
                    <option value="L">Landscape</option>
                </select>
            </div>
        </div>
    ',
    'accept_multiple' => false,
    'file_input_name' => 'input_file',
    'max_file_size' => 10 * 1024 * 1024,
    'allowed_extensions' => ['xlsx', 'xls', 'csv'],
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
        if (!isset($_FILES['input_file']) || $_FILES['input_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed. Please try again.');
        }

        $uploadedFile = $_FILES['input_file'];

        // Validate file size
        if ($uploadedFile['size'] > $tool_config['max_file_size']) {
            throw new Exception('File size exceeds maximum limit of 10MB.');
        }

        // Validate file extension
        $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $tool_config['allowed_extensions'])) {
            throw new Exception('Invalid file type. Only XLSX, XLS, and CSV files are allowed.');
        }

        // Get options
        $fontSize = (int)($_POST['font_size'] ?? 10);
        $orientation = $_POST['orientation'] ?? 'auto';

        // Create temp directory
        $engine = new PDFEngine();
        $tempDir = $engine->normalize_temp_dir();
        $sessionId = bin2hex(random_bytes(8));

        // Move uploaded file
        $inputFile = $tempDir . '/' . $sessionId . '.' . $ext;
        move_uploaded_file($uploadedFile['tmp_name'], $inputFile);

        // Load spreadsheet
        try {
            $spreadsheet = SpreadsheetIOFactory::load($inputFile);
        } catch (Exception $e) {
            throw new Exception('Failed to read spreadsheet. The file may be corrupted or password-protected.');
        }

        // Determine orientation
        $pdfOrientation = $orientation;
        if ($orientation === 'auto') {
            // Check first sheet for column count
            $firstSheet = $spreadsheet->getSheet(0);
            $highestColumn = $firstSheet->getHighestColumn();
            $colCount = Coordinate::columnIndexFromString($highestColumn);
            $pdfOrientation = ($colCount > 6) ? 'L' : 'P';
        }

        // Create PDF
        $pdf = new \FPDF($pdfOrientation, 'mm', 'A4');
        $pdf->SetMargins(10, 10);
        $pdf->SetAutoPageBreak(true, 15);

        // Process each worksheet
        foreach ($spreadsheet->getAllSheets() as $sheetIndex => $worksheet) {
            $pdf->AddPage();

            // Sheet name header
            $sheetName = $worksheet->getTitle();
            $pdf->SetFont('Arial', 'B', $fontSize + 2);
            $pdf->Cell(0, 8, utf8_decode($sheetName), 0, 1, 'L');
            $pdf->Ln(3);

            // Get data range
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            // Skip empty sheets
            if ($highestRow < 1) {
                $pdf->SetFont('Arial', 'I', $fontSize);
                $pdf->Cell(0, 6, '(Empty worksheet)', 0, 1);
                continue;
            }

            // Calculate column widths
            $pageWidth = $pdf->GetPageWidth() - 20; // Minus margins
            $colWidth = $pageWidth / $highestColumnIndex;
            $colWidth = min($colWidth, 50); // Max column width

            // Read data
            $data = $worksheet->toArray(null, true, true, true);

            // Render table
            $rowNum = 0;
            foreach ($data as $row) {
                $rowNum++;

                // Check for page break
                if ($pdf->GetY() > $pdf->GetPageHeight() - 20) {
                    $pdf->AddPage();
                    // Re-add header on new page
                    $pdf->SetFont('Arial', 'B', $fontSize);
                    $pdf->SetFillColor(230, 230, 230);
                    $colIndex = 0;
                    foreach ($data[array_key_first($data)] as $cell) {
                        $colIndex++;
                        if ($colIndex > $highestColumnIndex) break;
                        $pdf->Cell($colWidth, 6, '', 1, 0, 'L', true);
                    }
                    $pdf->Ln();
                }

                // First row as header
                if ($rowNum === 1) {
                    $pdf->SetFont('Arial', 'B', $fontSize);
                    $pdf->SetFillColor(230, 230, 230);
                    $fill = true;
                } else {
                    $pdf->SetFont('Arial', '', $fontSize);
                    $fill = false;
                }

                // Render cells
                $colIndex = 0;
                foreach ($row as $cell) {
                    $colIndex++;
                    if ($colIndex > $highestColumnIndex) break;

                    $cellValue = $cell ?? '';
                    // Truncate long values
                    if (strlen($cellValue) > 30) {
                        $cellValue = substr($cellValue, 0, 27) . '...';
                    }

                    $pdf->Cell($colWidth, 6, utf8_decode($cellValue), 1, 0, 'L', $fill);
                }

                $pdf->Ln();

                // Limit rows to prevent memory issues
                if ($rowNum > 1000) {
                    $pdf->SetFont('Arial', 'I', $fontSize - 2);
                    $pdf->Cell(0, 6, '... (truncated at 1000 rows)', 0, 1);
                    break;
                }
            }
        }

        // Save PDF
        $outputFile = $tempDir . '/excel_pdf_' . $sessionId . '.pdf';
        $pdf->Output('F', $outputFile);

        if (!file_exists($outputFile)) {
            throw new Exception('Failed to create PDF file.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, $uploadedFile['size'], $durationMs, 'success');

        // Clean up input file
        @unlink($inputFile);

        // Send PDF file
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="spreadsheet.pdf"');
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

// Render the tool page
render_tool_page($tool_slug, $tool_config, $error_message ?? null);
