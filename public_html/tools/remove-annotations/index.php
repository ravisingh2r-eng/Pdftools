<?php
/**
 * Remove Annotations & Comments Tool
 *
 * Creates a clean copy of PDF without annotations, comments, or markup.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

use setasign\Fpdi\Fpdi;

// Tool configuration
$tool_slug = 'remove-annotations';
$tool_config = [
    'title' => 'Remove Annotations & Comments from PDF',
    'description' => 'Remove all annotations, comments, highlights, and markup from your PDF documents. Create a clean, professional version ready for sharing or printing.',
    'keywords' => 'remove pdf annotations, delete pdf comments, remove highlights pdf, clean pdf markup, strip pdf annotations, remove pdf notes',
    'canonical' => '/tools/remove-annotations/',
    'og_title' => 'Remove PDF Annotations - Strip Comments & Markup',
    'og_description' => 'Remove all annotations, comments, and highlights from PDF. Free online PDF annotation remover.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'PDF Annotation Remover',
        'description' => 'Remove annotations and comments from PDF documents',
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
            'question' => 'What types of annotations are removed?',
            'answer' => 'All types including text comments, highlights, underlines, strikethroughs, sticky notes, drawings, stamps, and form field annotations.'
        ],
        [
            'question' => 'Will the original content be affected?',
            'answer' => 'No, only annotations are removed. The original text, images, and layout of your document remain unchanged.'
        ],
        [
            'question' => 'Can I recover the annotations later?',
            'answer' => 'No, this creates a new PDF without annotations. Keep your original file if you need to preserve the annotations.'
        ],
        [
            'question' => 'Is this the same as flattening?',
            'answer' => 'Similar but different. Flattening merges annotations into the content. This tool removes them entirely, leaving clean pages.'
        ],
        [
            'question' => 'Why remove annotations?',
            'answer' => 'Common reasons include: preparing documents for final distribution, removing review comments, cleaning up drafts, or creating print-ready files.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="pdf_file" class="form-label">Upload PDF File</label>
            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
            <div class="form-text">Maximum file size: 20MB</div>
        </div>
        <div class="alert alert-info">
            <small><strong>Note:</strong> This will remove all annotations, comments, highlights, and markup. The original document content is preserved.</small>
        </div>
    ',
    'accept_multiple' => false,
    'file_input_name' => 'pdf_file',
    'max_file_size' => 20 * 1024 * 1024,
    'allowed_extensions' => ['pdf'],
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

        // Create PDF without annotations
        // FPDI naturally doesn't import annotations when using importPage()
        // This effectively strips all annotations from the document
        $pdf = new Fpdi();

        $pageCount = $pdf->setSourceFile($inputFile);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
        }

        // Save PDF
        $outputFile = $tempDir . '/no_annotations_' . $sessionId . '.pdf';
        $pdf->Output('F', $outputFile);

        if (!file_exists($outputFile)) {
            throw new Exception('Failed to create PDF without annotations.');
        }

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, $uploadedFile['size'], $durationMs, 'success');

        // Clean up input file
        @unlink($inputFile);

        // Send PDF file
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="no-annotations.pdf"');
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
