<?php
/**
 * PDF to Word (DOCX) Tool
 *
 * Converts PDF documents to editable Word format using PhpWord.
 * Best-effort conversion - extracts text only, no layout preservation.
 *
 * Note: Requires phpoffice/phpword (composer require phpoffice/phpword)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// PhpWord for DOCX creation
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

// Tool configuration
$tool_slug = 'pdf-to-word';
$tool_config = [
    'title' => 'PDF to Word - Convert PDF to DOCX Online Free',
    'description' => 'Convert PDF documents to editable Word DOCX format online. Free PDF to Word converter extracts text content for easy editing in Microsoft Word.',
    'keywords' => 'pdf to word, pdf to docx, convert pdf to word, pdf word converter, pdf to doc online, extract text from pdf to word',
    'canonical' => '/tools/pdf-to-word/',
    'og_title' => 'PDF to Word Converter - PDF to DOCX Online Free',
    'og_description' => 'Convert PDF to editable Word documents online. Extract text from PDF to DOCX format for free.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'PDF to Word Converter',
        'description' => 'Convert PDF documents to editable Microsoft Word DOCX format',
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
            'answer' => 'This tool extracts text content only. Complex layouts, images, tables, and formatting are not preserved. For best results, use PDFs with simple text content.'
        ],
        [
            'question' => 'Does it work with scanned PDFs?',
            'answer' => 'No. Scanned PDFs and images are not supported because there is no OCR. You need a text-based PDF (where you can select and copy text) for best results.'
        ],
        [
            'question' => 'What is the maximum file size?',
            'answer' => 'You can upload PDF files up to 20MB. Larger files may take longer to process.'
        ],
        [
            'question' => 'Are my PDF files stored on your server?',
            'answer' => 'No. Your files are processed in memory and automatically deleted immediately after conversion. We do not store any uploaded documents.'
        ],
        [
            'question' => 'Can I edit the converted Word document?',
            'answer' => 'Yes! The output is a standard DOCX file that can be opened and edited in Microsoft Word, Google Docs, LibreOffice, or any compatible word processor.'
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

        // Create Word document
        $phpWord = new PhpWord();

        // Set document properties
        $properties = $phpWord->getDocInfo();
        $properties->setCreator('PDF Tools');
        $properties->setTitle('Converted from PDF');

        // Add content for each page
        foreach ($pageTexts as $pageNum => $text) {
            $section = $phpWord->addSection();

            // Add page header
            $section->addText('Page ' . ($pageNum + 1), ['bold' => true, 'size' => 14]);
            $section->addTextBreak();

            // Add page content
            // Split by paragraphs (double newlines)
            $paragraphs = preg_split('/\n\s*\n/', $text);
            foreach ($paragraphs as $para) {
                $para = trim($para);
                if (!empty($para)) {
                    // Replace single newlines with spaces within paragraphs
                    $para = preg_replace('/\n/', ' ', $para);
                    $section->addText($para, ['size' => 11]);
                    $section->addTextBreak();
                }
            }
        }

        // Save DOCX
        $outputFile = $tempDir . '/pdf_word_' . $sessionId . '.docx';
        $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($outputFile);

        if (!file_exists($outputFile)) {
            throw new Exception('Failed to create Word document.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, $uploadedFile['size'], $durationMs, 'success');

        // Clean up input file
        @unlink($inputFile);

        // Send DOCX file
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="converted-word.docx"');
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
 *
 * @param string $pdfFile Path to PDF file
 * @return array Array of text content indexed by page number
 */
function extractPdfTextByPage($pdfFile) {
    $pageTexts = [];

    // Read PDF content
    $content = file_get_contents($pdfFile);

    // Find all page objects
    preg_match_all('/(\d+)\s+0\s+obj[^>]*>>\s*stream\s*(.*?)\s*endstream/s', $content, $streamMatches, PREG_SET_ORDER);

    $pageNum = 0;
    foreach ($streamMatches as $match) {
        $streamData = $match[2];

        // Try to decompress if it's compressed
        $decoded = @gzuncompress($streamData);
        if ($decoded === false) {
            // Try without the first bytes (FlateDecode sometimes has extra header)
            $decoded = @gzuncompress(substr($streamData, 2));
        }
        if ($decoded === false) {
            $decoded = $streamData; // Use as-is if not compressed
        }

        // Check if this stream contains text operators
        if (preg_match('/BT\s.*?\sET/s', $decoded)) {
            $text = extractTextFromStream($decoded);
            if (!empty(trim($text))) {
                $pageTexts[$pageNum] = $text;
                $pageNum++;
            }
        }
    }

    // If no text found via streams, try alternative method
    if (empty($pageTexts)) {
        // Look for text in the raw content
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

    // Find all text blocks
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

    // Extract Tj operators (simple text)
    preg_match_all('/\((.*?)\)\s*Tj/s', $block, $tjMatches);
    foreach ($tjMatches[1] as $str) {
        $text .= decodePdfString($str);
    }

    // Extract TJ operators (array of text)
    preg_match_all('/\[(.*?)\]\s*TJ/s', $block, $tjArrayMatches);
    foreach ($tjArrayMatches[1] as $arr) {
        preg_match_all('/\((.*?)\)/', $arr, $strings);
        foreach ($strings[1] as $str) {
            $text .= decodePdfString($str);
        }
    }

    // Add space/newline for text positioning
    if (preg_match('/Td|TD|T\*|\'|"/', $block)) {
        $text .= ' ';
    }

    return $text;
}

/**
 * Decode PDF string escapes
 */
function decodePdfString($str) {
    // Handle basic escapes
    $str = str_replace(
        ['\\n', '\\r', '\\t', '\\\\', '\\(', '\\)'],
        ["\n", "\r", "\t", '\\', '(', ')'],
        $str
    );

    // Handle octal escapes
    $str = preg_replace_callback('/\\\\([0-7]{1,3})/', function($m) {
        return chr(octdec($m[1]));
    }, $str);

    return $str;
}

// Render the tool page
render_tool_page($tool_slug, $tool_config, $error_message ?? null);
