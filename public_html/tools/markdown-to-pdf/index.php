<?php
/**
 * Markdown to PDF Tool
 *
 * Converts Markdown content to PDF document.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Tool configuration
$tool_slug = 'markdown-to-pdf';
$tool_config = [
    'title' => 'Markdown to PDF - Convert MD Files to PDF',
    'description' => 'Convert Markdown (.md) files to beautifully formatted PDF documents. Supports headers, bold, italic, lists, and more.',
    'keywords' => 'markdown to pdf, md to pdf, convert markdown to pdf, github markdown pdf, readme to pdf',
    'canonical' => '/tools/markdown-to-pdf/',
    'og_title' => 'Markdown to PDF Converter - MD to PDF Online',
    'og_description' => 'Convert Markdown files to PDF documents. Free online Markdown to PDF converter.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'Markdown to PDF Converter',
        'description' => 'Convert Markdown to PDF documents',
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
            'question' => 'What Markdown syntax is supported?',
            'answer' => 'We support: Headers (#, ##, ###), bold (**text**), italic (*text*), unordered lists (- or *), ordered lists (1. 2. 3.), and paragraphs.'
        ],
        [
            'question' => 'Are code blocks supported?',
            'answer' => 'Basic code formatting is not fully supported in this simple converter. Code will appear as plain text.'
        ],
        [
            'question' => 'Can I convert README files?',
            'answer' => 'Yes! Upload your README.md or any other Markdown file directly, or paste the content into the text area.'
        ],
        [
            'question' => 'Are images in Markdown supported?',
            'answer' => 'Currently, image references in Markdown are not rendered. Only text formatting is converted.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="md_file" class="form-label">Upload Markdown File (optional)</label>
            <input type="file" class="form-control" id="md_file" name="md_file" accept=".md,.markdown,.txt">
            <div class="form-text">Or paste your Markdown below</div>
        </div>
        <div class="mb-3">
            <label for="md_content" class="form-label">Markdown Content</label>
            <textarea class="form-control font-monospace" id="md_content" name="md_content" rows="12" placeholder="# My Document&#10;&#10;This is **bold** and *italic* text.&#10;&#10;## Section&#10;&#10;- List item 1&#10;- List item 2&#10;&#10;1. Numbered item&#10;2. Another item"></textarea>
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
    'file_input_name' => 'md_file',
    'max_file_size' => 1 * 1024 * 1024,
    'allowed_extensions' => ['md', 'markdown', 'txt'],
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

        $markdown = '';

        // Check for uploaded file first
        if (isset($_FILES['md_file']) && $_FILES['md_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['md_file'];

            // Validate file size
            if ($uploadedFile['size'] > $tool_config['max_file_size']) {
                throw new Exception('File size exceeds maximum limit of 1MB.');
            }

            $markdown = file_get_contents($uploadedFile['tmp_name']);
        } elseif (!empty($_POST['md_content'])) {
            $markdown = $_POST['md_content'];
        }

        if (empty(trim($markdown))) {
            throw new Exception('Please upload a Markdown file or enter Markdown content.');
        }

        // Get options
        $fontSize = (int)($_POST['font_size'] ?? 12);

        // Create temp directory
        $engine = new PDFEngine();
        $tempDir = $engine->normalize_temp_dir();
        $sessionId = bin2hex(random_bytes(8));

        // Convert to PDF
        $outputFile = $tempDir . '/markdown_' . $sessionId . '.pdf';

        $options = [
            'fontSize' => $fontSize,
            'margin' => 15,
        ];

        $result = $engine->markdown_to_pdf($markdown, $outputFile, $options);

        if (!$result || !file_exists($outputFile)) {
            throw new Exception('Failed to convert Markdown to PDF.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, strlen($markdown), $durationMs, 'success');

        // Send PDF file
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="markdown_document.pdf"');
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
