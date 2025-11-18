<?php
/**
 * PowerPoint to PDF Tool
 *
 * Converts PPTX/PPT files to PDF using PhpPresentation and FPDF.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// PhpPresentation for PPTX parsing
use PhpOffice\PhpPresentation\IOFactory as PresentationIOFactory;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Shape\Drawing;

// Tool configuration
$tool_slug = 'ppt-to-pdf';
$tool_config = [
    'title' => 'PowerPoint to PDF - Convert PPTX/PPT to PDF Online',
    'description' => 'Convert Microsoft PowerPoint presentations (PPTX, PPT) to PDF format online. Each slide becomes a separate page in the PDF with text content preserved.',
    'keywords' => 'ppt to pdf, pptx to pdf, powerpoint to pdf, convert ppt to pdf, presentation to pdf, slides to pdf',
    'canonical' => '/tools/ppt-to-pdf/',
    'og_title' => 'PowerPoint to PDF Converter - PPTX to PDF Online Free',
    'og_description' => 'Convert PowerPoint presentations to PDF online. Supports PPTX files with text extraction.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'PowerPoint to PDF Converter',
        'description' => 'Convert Microsoft PowerPoint presentations to PDF format',
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
            'question' => 'What PowerPoint formats are supported?',
            'answer' => 'We primarily support PPTX files (PowerPoint 2007 and later). PPT files (older format) have limited support.'
        ],
        [
            'question' => 'Are slide layouts preserved?',
            'answer' => 'Text content from each slide is extracted and formatted on PDF pages. Complex layouts, animations, and transitions are not preserved.'
        ],
        [
            'question' => 'What about images and graphics?',
            'answer' => 'Currently, embedded images and graphics are not rendered. Only text content is extracted and converted to PDF.'
        ],
        [
            'question' => 'Is my presentation stored on your server?',
            'answer' => 'No. Your presentation is processed in memory and deleted immediately after conversion. We do not store any files permanently.'
        ],
        [
            'question' => 'How are speaker notes handled?',
            'answer' => 'Speaker notes can optionally be included in the PDF output below each slide\'s content.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="input_file" class="form-label">Upload PowerPoint File</label>
            <input type="file" class="form-control" id="input_file" name="input_file" accept=".pptx,.ppt" required>
            <div class="form-text">Supported formats: PPTX (recommended), PPT. Max size: 20MB</div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="font_size" class="form-label">Font Size</label>
                <select class="form-select" id="font_size" name="font_size">
                    <option value="10">10pt</option>
                    <option value="12" selected>12pt</option>
                    <option value="14">14pt</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="include_notes" class="form-label">Include Speaker Notes</label>
                <select class="form-select" id="include_notes" name="include_notes">
                    <option value="0" selected>No</option>
                    <option value="1">Yes</option>
                </select>
            </div>
        </div>
    ',
    'accept_multiple' => false,
    'file_input_name' => 'input_file',
    'max_file_size' => 20 * 1024 * 1024,
    'allowed_extensions' => ['pptx', 'ppt'],
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
            throw new Exception('File size exceeds maximum limit of 20MB.');
        }

        // Validate file extension
        $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $tool_config['allowed_extensions'])) {
            throw new Exception('Invalid file type. Only PPTX and PPT files are allowed.');
        }

        // Get options
        $fontSize = (int)($_POST['font_size'] ?? 12);
        $includeNotes = (bool)($_POST['include_notes'] ?? false);

        // Create temp directory
        $engine = new PDFEngine();
        $tempDir = $engine->normalize_temp_dir();
        $sessionId = bin2hex(random_bytes(8));

        // Move uploaded file
        $inputFile = $tempDir . '/' . $sessionId . '.' . $ext;
        move_uploaded_file($uploadedFile['tmp_name'], $inputFile);

        // Load PowerPoint presentation
        try {
            $presentation = PresentationIOFactory::load($inputFile);
        } catch (Exception $e) {
            throw new Exception('Failed to read PowerPoint file. The file may be corrupted or password-protected.');
        }

        // Create PDF in landscape orientation
        $pdf = new \FPDF('L', 'mm', 'A4');
        $pdf->SetMargins(15, 15);

        $slideNumber = 0;

        // Process each slide
        foreach ($presentation->getAllSlides() as $slide) {
            $slideNumber++;
            $pdf->AddPage();

            // Add slide number header
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetTextColor(128, 128, 128);
            $pdf->Cell(0, 5, 'Slide ' . $slideNumber, 0, 1, 'R');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(5);

            // Extract text from slide shapes
            $slideContent = [];

            foreach ($slide->getShapeCollection() as $shape) {
                if ($shape instanceof RichText) {
                    foreach ($shape->getParagraphs() as $paragraph) {
                        $text = '';
                        $isBold = false;
                        $isTitle = false;

                        foreach ($paragraph->getRichTextElements() as $element) {
                            $text .= $element->getText();

                            // Check if this is a title (usually larger font)
                            if ($element->getFont()) {
                                $font = $element->getFont();
                                if ($font->isBold()) $isBold = true;
                                if ($font->getSize() > 20) $isTitle = true;
                            }
                        }

                        if (!empty(trim($text))) {
                            $slideContent[] = [
                                'text' => $text,
                                'isTitle' => $isTitle,
                                'isBold' => $isBold
                            ];
                        }
                    }
                }
            }

            // Render slide content
            foreach ($slideContent as $content) {
                if ($content['isTitle']) {
                    $pdf->SetFont('Arial', 'B', $fontSize + 4);
                    $pdf->MultiCell(0, 8, utf8_decode($content['text']));
                    $pdf->Ln(3);
                } elseif ($content['isBold']) {
                    $pdf->SetFont('Arial', 'B', $fontSize);
                    $pdf->MultiCell(0, 6, utf8_decode($content['text']));
                } else {
                    $pdf->SetFont('Arial', '', $fontSize);
                    $pdf->MultiCell(0, 6, utf8_decode($content['text']));
                }
            }

            // Add speaker notes if requested
            if ($includeNotes && $slide->getNote()) {
                $noteText = '';
                foreach ($slide->getNote()->getShapeCollection() as $shape) {
                    if ($shape instanceof RichText) {
                        foreach ($shape->getParagraphs() as $paragraph) {
                            foreach ($paragraph->getRichTextElements() as $element) {
                                $noteText .= $element->getText();
                            }
                        }
                    }
                }

                if (!empty(trim($noteText))) {
                    $pdf->Ln(10);
                    $pdf->SetFont('Arial', 'I', $fontSize - 2);
                    $pdf->SetTextColor(100, 100, 100);
                    $pdf->Cell(0, 5, 'Speaker Notes:', 0, 1);
                    $pdf->SetDrawColor(200, 200, 200);
                    $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 260, $pdf->GetY());
                    $pdf->Ln(2);
                    $pdf->MultiCell(0, 5, utf8_decode($noteText));
                    $pdf->SetTextColor(0, 0, 0);
                }
            }
        }

        // Save PDF
        $outputFile = $tempDir . '/ppt_pdf_' . $sessionId . '.pdf';
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
        header('Content-Disposition: attachment; filename="presentation.pdf"');
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
