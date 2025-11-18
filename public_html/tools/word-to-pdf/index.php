<?php
/**
 * Word to PDF Tool
 *
 * Converts DOCX/DOC files to PDF using PhpWord and FPDF.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// PhpWord for DOCX parsing
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\Table;

// Tool configuration
$tool_slug = 'word-to-pdf';
$tool_config = [
    'title' => 'Word to PDF - Convert DOCX/DOC to PDF Online',
    'description' => 'Convert Microsoft Word documents (DOCX, DOC) to PDF format online. Free Word to PDF converter with basic formatting preservation for headings, paragraphs, bold, italic, and lists.',
    'keywords' => 'word to pdf, docx to pdf, doc to pdf, convert word to pdf, microsoft word pdf converter, office to pdf',
    'canonical' => '/tools/word-to-pdf/',
    'og_title' => 'Word to PDF Converter - DOCX to PDF Online Free',
    'og_description' => 'Convert Word documents to PDF online. Supports DOCX files with basic formatting preservation.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'Word to PDF Converter',
        'description' => 'Convert Microsoft Word documents to PDF format',
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
            'question' => 'What Word formats are supported?',
            'answer' => 'We primarily support DOCX files (Word 2007 and later). DOC files (older Word format) have limited support and may not preserve all formatting.'
        ],
        [
            'question' => 'Are fonts and formatting preserved?',
            'answer' => 'Basic formatting like bold, italic, underline, headings, and lists are preserved. Complex formatting, images, and custom fonts may not render exactly as in Word.'
        ],
        [
            'question' => 'What is the maximum file size?',
            'answer' => 'You can upload Word documents up to 10MB. For larger files, consider splitting them into smaller documents.'
        ],
        [
            'question' => 'Is my document stored on your server?',
            'answer' => 'No. Your document is processed in memory and deleted immediately after conversion. We do not store any uploaded files permanently.'
        ],
        [
            'question' => 'Can it handle tables and images?',
            'answer' => 'Tables are converted to basic text format. Images in Word documents are currently not supported in the PDF output.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="input_file" class="form-label">Upload Word Document</label>
            <input type="file" class="form-control" id="input_file" name="input_file" accept=".docx,.doc" required>
            <div class="form-text">Supported formats: DOCX (recommended), DOC. Max size: 10MB</div>
        </div>
        <div class="mb-3">
            <label for="font_size" class="form-label">PDF Font Size</label>
            <select class="form-select" id="font_size" name="font_size">
                <option value="10">10pt</option>
                <option value="11">11pt</option>
                <option value="12" selected>12pt</option>
                <option value="14">14pt</option>
            </select>
        </div>
    ',
    'accept_multiple' => false,
    'file_input_name' => 'input_file',
    'max_file_size' => 10 * 1024 * 1024,
    'allowed_extensions' => ['docx', 'doc'],
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
            throw new Exception('Invalid file type. Only DOCX and DOC files are allowed.');
        }

        // Get options
        $fontSize = (int)($_POST['font_size'] ?? 12);

        // Create temp directory
        $engine = new PDFEngine();
        $tempDir = $engine->normalize_temp_dir();
        $sessionId = bin2hex(random_bytes(8));

        // Move uploaded file
        $inputFile = $tempDir . '/' . $sessionId . '.' . $ext;
        move_uploaded_file($uploadedFile['tmp_name'], $inputFile);

        // Load Word document
        try {
            $phpWord = WordIOFactory::load($inputFile);
        } catch (Exception $e) {
            throw new Exception('Failed to read Word document. The file may be corrupted or password-protected.');
        }

        // Create PDF
        $pdf = new \FPDF();
        $pdf->SetMargins(15, 15);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', $fontSize);

        // Extract content from Word document
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                processWordElement($pdf, $element, $fontSize);
            }
        }

        // Save PDF
        $outputFile = $tempDir . '/word_pdf_' . $sessionId . '.pdf';
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
        header('Content-Disposition: attachment; filename="converted-word.pdf"');
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
 * Process Word element and add to PDF
 */
function processWordElement($pdf, $element, $baseFontSize) {
    if ($element instanceof Title) {
        // Handle headings
        $depth = $element->getDepth();
        $size = $baseFontSize + (6 - min($depth, 6)) * 2;
        $pdf->SetFont('Arial', 'B', $size);
        $pdf->Ln(3);
        $text = $element->getText();
        if (is_string($text)) {
            $pdf->MultiCell(0, 7, utf8_decode($text));
        }
        $pdf->SetFont('Arial', '', $baseFontSize);
        $pdf->Ln(2);
    } elseif ($element instanceof TextRun) {
        // Handle text runs (mixed formatting)
        foreach ($element->getElements() as $textElement) {
            if ($textElement instanceof Text) {
                $text = $textElement->getText();
                $font = $textElement->getFontStyle();

                $style = '';
                if ($font) {
                    if ($font->isBold()) $style .= 'B';
                    if ($font->isItalic()) $style .= 'I';
                    if ($font->isUnderline()) $style .= 'U';
                }

                $pdf->SetFont('Arial', $style, $baseFontSize);
                $pdf->Write(5, utf8_decode($text));
            }
        }
        $pdf->Ln(5);
        $pdf->SetFont('Arial', '', $baseFontSize);
    } elseif ($element instanceof Text) {
        // Handle plain text
        $text = $element->getText();
        $pdf->MultiCell(0, 5, utf8_decode($text));
        $pdf->Ln(2);
    } elseif ($element instanceof ListItem) {
        // Handle list items
        $text = $element->getText();
        $depth = $element->getDepth();
        $indent = 10 + ($depth * 5);
        $pdf->SetX($indent);
        $pdf->Cell(5, 5, chr(149)); // Bullet

        if ($text instanceof TextRun) {
            $textContent = '';
            foreach ($text->getElements() as $te) {
                if ($te instanceof Text) {
                    $textContent .= $te->getText();
                }
            }
            $pdf->MultiCell(0, 5, utf8_decode($textContent));
        } else {
            $pdf->MultiCell(0, 5, utf8_decode((string)$text));
        }
    } elseif ($element instanceof Table) {
        // Handle tables (basic text extraction)
        $pdf->Ln(3);
        foreach ($element->getRows() as $row) {
            $rowText = '';
            foreach ($row->getCells() as $cell) {
                foreach ($cell->getElements() as $cellElement) {
                    if ($cellElement instanceof TextRun) {
                        foreach ($cellElement->getElements() as $te) {
                            if ($te instanceof Text) {
                                $rowText .= $te->getText() . "\t";
                            }
                        }
                    } elseif ($cellElement instanceof Text) {
                        $rowText .= $cellElement->getText() . "\t";
                    }
                }
            }
            if (!empty(trim($rowText))) {
                $pdf->MultiCell(0, 5, utf8_decode(trim($rowText)));
            }
        }
        $pdf->Ln(3);
    }
    // Other element types are skipped
}

// Render the tool page
render_tool_page($tool_slug, $tool_config, $error_message ?? null);
