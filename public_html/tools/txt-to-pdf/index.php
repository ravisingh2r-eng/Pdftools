<?php
/**
 * TXT to PDF Tool
 *
 * Converts plain text to PDF document.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Tool configuration
$tool_slug = 'txt-to-pdf';
$tool_config = [
    'title' => 'TXT to PDF - Convert Text Files to PDF',
    'description' => 'Convert plain text files or paste text content directly into a PDF document. Preserve formatting with customizable font settings.',
    'keywords' => 'txt to pdf, text to pdf, convert text to pdf, notepad to pdf, plain text pdf converter',
    'canonical' => '/tools/txt-to-pdf/',
    'og_title' => 'TXT to PDF Converter - Text to PDF Online',
    'og_description' => 'Convert text files or typed content to PDF. Free online text to PDF converter.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'TXT to PDF Converter',
        'description' => 'Convert plain text to PDF documents',
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
            'question' => 'Can I upload a text file or type directly?',
            'answer' => 'Both! You can upload a .txt file or paste/type your text directly in the text area. If you do both, the uploaded file takes priority.'
        ],
        [
            'question' => 'What fonts are available?',
            'answer' => 'You can choose from Courier (monospace), Arial (sans-serif), or Times (serif) fonts for your PDF output.'
        ],
        [
            'question' => 'Will my line breaks be preserved?',
            'answer' => 'Yes, all line breaks and paragraph spacing from your original text will be preserved in the PDF.'
        ],
        [
            'question' => 'What is the maximum text length?',
            'answer' => 'You can convert up to 1MB of text content, which is equivalent to approximately 500,000 characters.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="txt_file" class="form-label">Upload Text File (optional)</label>
            <input type="file" class="form-control" id="txt_file" name="txt_file" accept=".txt">
            <div class="form-text">Or paste your text below</div>
        </div>
        <div class="mb-3">
            <label for="text_content" class="form-label">Text Content</label>
            <textarea class="form-control" id="text_content" name="text_content" rows="10" placeholder="Paste or type your text here..."></textarea>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="font_family" class="form-label">Font</label>
                <select class="form-select" id="font_family" name="font_family">
                    <option value="Courier" selected>Courier (Monospace)</option>
                    <option value="Arial">Arial (Sans-serif)</option>
                    <option value="Times">Times (Serif)</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="font_size" class="form-label">Font Size</label>
                <select class="form-select" id="font_size" name="font_size">
                    <option value="10">10pt</option>
                    <option value="12" selected>12pt</option>
                    <option value="14">14pt</option>
                    <option value="16">16pt</option>
                </select>
            </div>
        </div>
    ',
    'accept_multiple' => false,
    'file_input_name' => 'txt_file',
    'max_file_size' => 1 * 1024 * 1024,
    'allowed_extensions' => ['txt'],
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

        $text = '';

        // Check for uploaded file first
        if (isset($_FILES['txt_file']) && $_FILES['txt_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['txt_file'];

            // Validate file size
            if ($uploadedFile['size'] > $tool_config['max_file_size']) {
                throw new Exception('File size exceeds maximum limit of 1MB.');
            }

            // Validate extension
            $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            if ($ext !== 'txt') {
                throw new Exception('Invalid file type. Only .txt files are allowed.');
            }

            $text = file_get_contents($uploadedFile['tmp_name']);
        } elseif (!empty($_POST['text_content'])) {
            $text = $_POST['text_content'];
        }

        if (empty(trim($text))) {
            throw new Exception('Please upload a text file or enter text content.');
        }

        // Get options
        $fontFamily = $_POST['font_family'] ?? 'Courier';
        $fontSize = (int)($_POST['font_size'] ?? 12);

        // Validate font family
        if (!in_array($fontFamily, ['Courier', 'Arial', 'Times'])) {
            $fontFamily = 'Courier';
        }

        // Create temp directory
        $engine = new PDFEngine();
        $tempDir = $engine->normalize_temp_dir();
        $sessionId = bin2hex(random_bytes(8));

        // Convert to PDF
        $outputFile = $tempDir . '/text_' . $sessionId . '.pdf';

        $options = [
            'fontFamily' => $fontFamily,
            'fontSize' => $fontSize,
            'lineHeight' => 5,
            'margin' => 15,
        ];

        $result = $engine->txt_to_pdf($text, $outputFile, $options);

        if (!$result || !file_exists($outputFile)) {
            throw new Exception('Failed to convert text to PDF.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, strlen($text), $durationMs, 'success');

        // Send PDF file
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="text_document.pdf"');
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
