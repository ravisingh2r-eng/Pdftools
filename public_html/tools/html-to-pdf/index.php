<?php
/**
 * HTML to PDF Tool
 *
 * Converts HTML content to PDF document.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Tool configuration
$tool_slug = 'html-to-pdf';
$tool_config = [
    'title' => 'HTML to PDF - Convert HTML Code to PDF',
    'description' => 'Convert HTML markup to PDF document. Supports basic formatting tags like paragraphs, bold, italic, lists, and headings.',
    'keywords' => 'html to pdf, convert html to pdf, html pdf converter, webpage to pdf, html code to pdf',
    'canonical' => '/tools/html-to-pdf/',
    'og_title' => 'HTML to PDF Converter - Convert Markup to PDF',
    'og_description' => 'Convert HTML code to PDF documents. Free online HTML to PDF converter with basic formatting support.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'HTML to PDF Converter',
        'description' => 'Convert HTML markup to PDF documents',
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
            'question' => 'What HTML tags are supported?',
            'answer' => 'We support common tags: <p>, <b>, <strong>, <i>, <em>, <u>, <br>, <h1>-<h6>, <ul>, <ol>, and <li>. Complex CSS and JavaScript are not supported.'
        ],
        [
            'question' => 'Can I convert a full webpage?',
            'answer' => 'This tool is designed for simple HTML content. For full webpages with CSS and images, you may need a more advanced solution.'
        ],
        [
            'question' => 'Will my styles be preserved?',
            'answer' => 'Inline basic formatting (bold, italic, underline) is preserved. CSS stylesheets and complex styling are not supported.'
        ],
        [
            'question' => 'What about images in HTML?',
            'answer' => 'Currently, images in HTML are not supported. Only text content and formatting tags are converted.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="html_file" class="form-label">Upload HTML File (optional)</label>
            <input type="file" class="form-control" id="html_file" name="html_file" accept=".html,.htm">
            <div class="form-text">Or paste your HTML below</div>
        </div>
        <div class="mb-3">
            <label for="html_content" class="form-label">HTML Content</label>
            <textarea class="form-control font-monospace" id="html_content" name="html_content" rows="12" placeholder="<h1>My Document</h1>&#10;<p>This is a <b>bold</b> and <i>italic</i> example.</p>&#10;<ul>&#10;  <li>Item 1</li>&#10;  <li>Item 2</li>&#10;</ul>"></textarea>
        </div>
        <div class="mb-3">
            <label for="font_size" class="form-label">Base Font Size</label>
            <select class="form-select" id="font_size" name="font_size">
                <option value="10">10pt</option>
                <option value="12" selected>12pt</option>
                <option value="14">14pt</option>
            </select>
        </div>
    ',
    'accept_multiple' => false,
    'file_input_name' => 'html_file',
    'max_file_size' => 1 * 1024 * 1024,
    'allowed_extensions' => ['html', 'htm'],
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

        $html = '';

        // Check for uploaded file first
        if (isset($_FILES['html_file']) && $_FILES['html_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['html_file'];

            // Validate file size
            if ($uploadedFile['size'] > $tool_config['max_file_size']) {
                throw new Exception('File size exceeds maximum limit of 1MB.');
            }

            // Validate extension
            $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['html', 'htm'])) {
                throw new Exception('Invalid file type. Only .html and .htm files are allowed.');
            }

            $html = file_get_contents($uploadedFile['tmp_name']);
        } elseif (!empty($_POST['html_content'])) {
            $html = $_POST['html_content'];
        }

        if (empty(trim($html))) {
            throw new Exception('Please upload an HTML file or enter HTML content.');
        }

        // Get options
        $fontSize = (int)($_POST['font_size'] ?? 12);

        // Create temp directory
        $engine = new PDFEngine();
        $tempDir = $engine->normalize_temp_dir();
        $sessionId = bin2hex(random_bytes(8));

        // Convert to PDF
        $outputFile = $tempDir . '/html_' . $sessionId . '.pdf';

        $options = [
            'fontSize' => $fontSize,
            'margin' => 15,
        ];

        $result = $engine->html_to_pdf($html, $outputFile, $options);

        if (!$result || !file_exists($outputFile)) {
            throw new Exception('Failed to convert HTML to PDF.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, strlen($html), $durationMs, 'success');

        // Send PDF file
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="html_document.pdf"');
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
