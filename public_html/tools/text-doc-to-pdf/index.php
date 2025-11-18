<?php
/**
 * RTF/ODT to PDF Tool
 *
 * Converts RTF and ODT text documents to PDF using PhpWord and FPDF.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// PhpWord for document parsing
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\Element\ListItem;

// Tool configuration
$tool_slug = 'text-doc-to-pdf';
$tool_config = [
    'title' => 'RTF/ODT to PDF - Convert Text Documents to PDF Online',
    'description' => 'Convert RTF (Rich Text Format) and ODT (OpenDocument) files to PDF format online. Best-effort conversion preserving basic text formatting.',
    'keywords' => 'rtf to pdf, odt to pdf, convert rtf to pdf, rich text to pdf, opendocument to pdf, text document converter',
    'canonical' => '/tools/text-doc-to-pdf/',
    'og_title' => 'RTF & ODT to PDF Converter - Text Documents to PDF',
    'og_description' => 'Convert RTF and ODT text documents to PDF online. Free document converter with basic formatting support.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'RTF/ODT to PDF Converter',
        'description' => 'Convert RTF and ODT text documents to PDF format',
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
            'question' => 'What is RTF format?',
            'answer' => 'RTF (Rich Text Format) is a document format developed by Microsoft that supports basic text formatting. It\'s widely compatible across different word processors.'
        ],
        [
            'question' => 'What is ODT format?',
            'answer' => 'ODT (OpenDocument Text) is an open standard format used by LibreOffice Writer and other open-source word processors.'
        ],
        [
            'question' => 'Is all formatting preserved?',
            'answer' => 'Basic formatting like bold, italic, and paragraphs are preserved. Complex features like images, tables, and custom fonts may not render exactly.'
        ],
        [
            'question' => 'What is the maximum file size?',
            'answer' => 'You can upload documents up to 5MB. Most text documents are well under this limit.'
        ],
        [
            'question' => 'Are my files stored on your server?',
            'answer' => 'No. All uploaded files are processed in memory and deleted immediately after conversion. We do not store any documents.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="input_file" class="form-label">Upload Document</label>
            <input type="file" class="form-control" id="input_file" name="input_file" accept=".rtf,.odt" required>
            <div class="form-text">Supported formats: RTF, ODT. Max size: 5MB</div>
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
    'max_file_size' => 5 * 1024 * 1024,
    'allowed_extensions' => ['rtf', 'odt'],
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
            throw new Exception('File size exceeds maximum limit of 5MB.');
        }

        // Validate file extension
        $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $tool_config['allowed_extensions'])) {
            throw new Exception('Invalid file type. Only RTF and ODT files are allowed.');
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

        // Create PDF
        $pdf = new \FPDF();
        $pdf->SetMargins(15, 15);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', $fontSize);

        if ($ext === 'rtf') {
            // RTF Processing - Best effort text extraction
            $rtfContent = file_get_contents($inputFile);

            // Strip RTF formatting to get plain text
            $text = stripRtfTags($rtfContent);

            if (empty(trim($text))) {
                throw new Exception('Could not extract text from RTF file. The file may be empty or corrupted.');
            }

            // Render text to PDF
            $lines = explode("\n", $text);
            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    $pdf->Ln(3);
                    continue;
                }
                $pdf->MultiCell(0, 5, utf8_decode($line));
            }

        } elseif ($ext === 'odt') {
            // ODT Processing - Use PhpWord if supported
            try {
                $phpWord = WordIOFactory::load($inputFile, 'ODText');

                // Extract content
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        processTextDocElement($pdf, $element, $fontSize);
                    }
                }
            } catch (Exception $e) {
                // Fallback: Try to extract text from ODT as ZIP
                $text = extractOdtText($inputFile);

                if (empty(trim($text))) {
                    throw new Exception('Could not extract text from ODT file. The file may be corrupted or use unsupported features.');
                }

                $lines = explode("\n", $text);
                foreach ($lines as $line) {
                    if (empty(trim($line))) {
                        $pdf->Ln(3);
                        continue;
                    }
                    $pdf->MultiCell(0, 5, utf8_decode($line));
                }
            }
        }

        // Save PDF
        $outputFile = $tempDir . '/textdoc_pdf_' . $sessionId . '.pdf';
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
        header('Content-Disposition: attachment; filename="document.pdf"');
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
 * Strip RTF tags to extract plain text
 */
function stripRtfTags($rtf) {
    // Remove RTF groups
    $text = preg_replace('/\{[^{}]*\}/', '', $rtf);

    // Remove RTF control words
    $text = preg_replace('/\\\\[a-z]+\d*\s?/', '', $text);

    // Remove remaining backslashes
    $text = str_replace('\\', '', $text);

    // Convert common RTF escapes
    $text = str_replace(['\par', '\line'], "\n", $text);
    $text = str_replace('\tab', "\t", $text);

    // Clean up whitespace
    $text = preg_replace('/\n\s*\n/', "\n\n", $text);
    $text = trim($text);

    return $text;
}

/**
 * Extract text from ODT file (ZIP with content.xml)
 */
function extractOdtText($odtFile) {
    $text = '';

    $zip = new ZipArchive();
    if ($zip->open($odtFile) === true) {
        $content = $zip->getFromName('content.xml');
        $zip->close();

        if ($content) {
            // Strip XML tags
            $text = strip_tags($content);
            // Clean up whitespace
            $text = preg_replace('/\s+/', ' ', $text);
            $text = str_replace('. ', ".\n", $text);
        }
    }

    return $text;
}

/**
 * Process text document element and add to PDF
 */
function processTextDocElement($pdf, $element, $baseFontSize) {
    if ($element instanceof Title) {
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
        $text = $element->getText();
        $pdf->MultiCell(0, 5, utf8_decode($text));
        $pdf->Ln(2);
    } elseif ($element instanceof ListItem) {
        $text = $element->getText();
        $depth = $element->getDepth();
        $indent = 10 + ($depth * 5);
        $pdf->SetX($indent);
        $pdf->Cell(5, 5, chr(149));

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
    }
}

// Render the tool page
render_tool_page($tool_slug, $tool_config, $error_message ?? null);
