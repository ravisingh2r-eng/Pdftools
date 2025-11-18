<?php
/**
 * PDF to PowerPoint (PPTX) Tool
 *
 * Converts PDF pages to PowerPoint slides using PhpPresentation.
 * Each PDF page becomes a slide with text content extracted.
 *
 * Note: Requires phpoffice/phppresentation (composer require phpoffice/phppresentation)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// PhpPresentation for PPTX creation
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\IOFactory as PresentationIOFactory;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;

// Tool configuration
$tool_slug = 'pdf-to-ppt';
$tool_config = [
    'title' => 'PDF to PowerPoint - Convert PDF to PPTX Online Free',
    'description' => 'Convert PDF documents to PowerPoint PPTX presentations online. Each PDF page becomes a slide with extracted text content.',
    'keywords' => 'pdf to ppt, pdf to pptx, convert pdf to powerpoint, pdf powerpoint converter, pdf to slides, pdf presentation',
    'canonical' => '/tools/pdf-to-ppt/',
    'og_title' => 'PDF to PowerPoint Converter - PDF to PPTX Online Free',
    'og_description' => 'Convert PDF to editable PowerPoint presentations online. Each page becomes a slide with text content.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'PDF to PowerPoint Converter',
        'description' => 'Convert PDF documents to editable Microsoft PowerPoint PPTX format',
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
            'question' => 'Does it preserve slide layouts and images?',
            'answer' => 'This tool extracts text content only and places it on slides. Original layouts, images, and graphics are not preserved. Each page becomes one slide.'
        ],
        [
            'question' => 'Does it work with scanned PDFs?',
            'answer' => 'No. Scanned PDFs and images are not supported because there is no OCR. You need a text-based PDF (where you can select and copy text) for best results.'
        ],
        [
            'question' => 'What is the maximum file size?',
            'answer' => 'You can upload PDF files up to 20MB. Large presentations may take longer to process.'
        ],
        [
            'question' => 'Are my PDF files stored on your server?',
            'answer' => 'No. Your files are processed in memory and automatically deleted immediately after conversion. We do not store any uploaded documents.'
        ],
        [
            'question' => 'Can I edit the converted presentation?',
            'answer' => 'Yes! The output is a standard PPTX file that can be opened and edited in Microsoft PowerPoint, Google Slides, or any compatible presentation software.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="pdf_file" class="form-label">Upload PDF File</label>
            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
            <div class="form-text">Maximum file size: 20MB. Text-based PDFs only.</div>
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

        // Create presentation
        $presentation = new PhpPresentation();

        // Set document properties
        $properties = $presentation->getDocumentProperties();
        $properties->setCreator('PDF Tools');
        $properties->setTitle('Converted from PDF');
        $properties->setDescription('PDF converted to PowerPoint');

        // Remove the default empty slide
        $presentation->removeSlideByIndex(0);

        // Create slides for each page
        foreach ($pageTexts as $pageNum => $text) {
            $slide = $presentation->createSlide();

            // Add slide number at top
            $shape = $slide->createRichTextShape()
                ->setHeight(30)
                ->setWidth(200)
                ->setOffsetX(650)
                ->setOffsetY(10);

            $shape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $textRun = $shape->createTextRun('Slide ' . ($pageNum + 1));
            $textRun->getFont()->setSize(10)->setColor(new Color('FF666666'));

            // Add title shape
            $titleShape = $slide->createRichTextShape()
                ->setHeight(60)
                ->setWidth(800)
                ->setOffsetX(50)
                ->setOffsetY(30);

            $titleShape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $titleRun = $titleShape->createTextRun('Page ' . ($pageNum + 1));
            $titleRun->getFont()->setBold(true)->setSize(24)->setColor(new Color('FF333333'));

            // Add content shape
            $contentShape = $slide->createRichTextShape()
                ->setHeight(400)
                ->setWidth(800)
                ->setOffsetX(50)
                ->setOffsetY(100);

            $contentShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_TOP);

            // Split text into paragraphs
            $paragraphs = preg_split('/\n\s*\n/', $text);
            $isFirst = true;

            foreach ($paragraphs as $para) {
                $para = trim($para);
                if (empty($para)) continue;

                // Replace newlines within paragraph with spaces
                $para = preg_replace('/\n/', ' ', $para);

                // Truncate very long paragraphs for slide readability
                if (strlen($para) > 500) {
                    $para = substr($para, 0, 497) . '...';
                }

                if (!$isFirst) {
                    $contentShape->createParagraph();
                }

                $textRun = $contentShape->createTextRun($para);
                $textRun->getFont()->setSize(14)->setColor(new Color('FF000000'));

                $isFirst = false;
            }
        }

        // Save PPTX
        $outputFile = $tempDir . '/pdf_ppt_' . $sessionId . '.pptx';
        $writer = PresentationIOFactory::createWriter($presentation, 'PowerPoint2007');
        $writer->save($outputFile);

        if (!file_exists($outputFile)) {
            throw new Exception('Failed to create PowerPoint file.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, $uploadedFile['size'], $durationMs, 'success');

        // Clean up input file
        @unlink($inputFile);

        // Send PPTX file
        header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
        header('Content-Disposition: attachment; filename="converted-presentation.pptx"');
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
