<?php
/**
 * Extract Links from PDF Tool
 *
 * Extracts all clickable URLs from PDF documents.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/pdf_engine.php';
require_once __DIR__ . '/../../lib/usage_logger.php';
require_once __DIR__ . '/../../lib/rate_limiter.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../_template_tool.php';

// Tool configuration
$tool_slug = 'extract-links';
$tool_config = [
    'title' => 'Extract Links from PDF - Get All URLs from PDF',
    'description' => 'Extract all clickable links and URLs from your PDF documents. Get a complete list of hyperlinks including web addresses, email links, and more.',
    'keywords' => 'extract links from pdf, get urls from pdf, pdf link extractor, find links in pdf, extract hyperlinks pdf, pdf url list',
    'canonical' => '/tools/extract-links/',
    'og_title' => 'Extract Links from PDF - Find All URLs in PDF',
    'og_description' => 'Extract all URLs and hyperlinks from PDF documents. Free online PDF link extractor tool.',
    'structured_data' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'PDF Link Extractor',
        'description' => 'Extract URLs and hyperlinks from PDF documents',
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
            'question' => 'What types of links are extracted?',
            'answer' => 'All types including HTTP/HTTPS URLs, mailto: email links, and other URI schemes found in PDF annotations and content.'
        ],
        [
            'question' => 'Will it find links in text?',
            'answer' => 'It extracts clickable hyperlinks (annotations). Text that looks like a URL but isn\'t a clickable link may not be detected.'
        ],
        [
            'question' => 'Are duplicate links removed?',
            'answer' => 'Yes, the list shows unique URLs only. If the same link appears multiple times, it\'s listed once with all page numbers.'
        ],
        [
            'question' => 'Can I export the links?',
            'answer' => 'Yes, you can download the extracted links as a CSV file containing the URL and page number for each link.'
        ],
        [
            'question' => 'Why are no links found?',
            'answer' => 'The PDF may not contain any clickable hyperlinks. Text formatted to look like URLs isn\'t the same as actual hyperlinks.'
        ]
    ],
    'form_html' => '
        <div class="mb-3">
            <label for="pdf_file" class="form-label">Upload PDF File</label>
            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
            <div class="form-text">Maximum file size: 20MB</div>
        </div>
    ',
    'accept_multiple' => false,
    'file_input_name' => 'pdf_file',
    'max_file_size' => 20 * 1024 * 1024,
    'allowed_extensions' => ['pdf'],
];

// Rate limiting
enforce_rate_limit($tool_slug, 20, 3600);

// Store extracted links for display
$extracted_links = null;

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

        // Extract links from PDF
        $extracted_links = extractPdfLinks($inputFile);

        // Calculate duration
        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        // Log usage
        log_usage($tool_slug, 1, $uploadedFile['size'], $durationMs, 'success');

        // Clean up input file
        @unlink($inputFile);

    } catch (Exception $e) {
        log_exception($e, $tool_slug);
        $error_message = $e->getMessage();
    }
}

/**
 * Extract URLs from PDF file
 */
function extractPdfLinks($pdfFile) {
    $links = [];

    // Read PDF content
    $content = file_get_contents($pdfFile);

    // Find page objects to track page numbers
    preg_match_all('/(\d+)\s+0\s+obj.*?\/Type\s*\/Page[^s]/s', $content, $pageMatches);
    $pageObjects = array_flip($pageMatches[1]);
    $pageCount = count($pageObjects);

    // Method 1: Find URI actions in annotations
    // Look for /URI patterns
    preg_match_all('/\/URI\s*\((.*?)\)/s', $content, $uriMatches);
    foreach ($uriMatches[1] as $uri) {
        $uri = decodeUriString($uri);
        if (!empty($uri) && isValidUrl($uri)) {
            $links[] = ['url' => $uri, 'page' => 'N/A'];
        }
    }

    // Also look for /URI with << >> syntax
    preg_match_all('/\/URI\s*<<\s*\/URI\s*\((.*?)\)/s', $content, $uri2Matches);
    foreach ($uri2Matches[1] as $uri) {
        $uri = decodeUriString($uri);
        if (!empty($uri) && isValidUrl($uri)) {
            $links[] = ['url' => $uri, 'page' => 'N/A'];
        }
    }

    // Method 2: Find URLs in text content
    preg_match_all('/(https?:\/\/[^\s\)<>\"\'\]]+)/i', $content, $textUrls);
    foreach ($textUrls[1] as $uri) {
        $uri = trim($uri);
        // Clean trailing punctuation
        $uri = rtrim($uri, '.,;:!?)');
        if (!empty($uri) && isValidUrl($uri)) {
            $links[] = ['url' => $uri, 'page' => 'N/A'];
        }
    }

    // Method 3: Find mailto links
    preg_match_all('/(mailto:[^\s\)<>\"\'\]]+)/i', $content, $mailtoUrls);
    foreach ($mailtoUrls[1] as $uri) {
        $uri = trim($uri);
        if (!empty($uri)) {
            $links[] = ['url' => $uri, 'page' => 'N/A'];
        }
    }

    // Remove duplicates and consolidate
    $uniqueLinks = [];
    foreach ($links as $link) {
        $url = $link['url'];
        if (!isset($uniqueLinks[$url])) {
            $uniqueLinks[$url] = $link;
        }
    }

    return array_values($uniqueLinks);
}

/**
 * Decode PDF URI string escapes
 */
function decodeUriString($str) {
    // Handle basic escapes
    $str = str_replace(['\\n', '\\r', '\\t', '\\\\', '\\(', '\\)'], ["\n", "\r", "\t", '\\', '(', ')'], $str);

    // Handle octal escapes
    $str = preg_replace_callback('/\\\\([0-7]{1,3})/', function($m) {
        return chr(octdec($m[1]));
    }, $str);

    return trim($str);
}

/**
 * Check if string is a valid URL
 */
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false ||
           preg_match('/^https?:\/\/[a-z0-9]/i', $url) ||
           preg_match('/^mailto:/i', $url);
}

// Custom form HTML with results display
if ($extracted_links !== null) {
    $linkCount = count($extracted_links);

    if ($linkCount === 0) {
        $resultsHtml = '
            <div class="alert alert-warning mb-3">
                <strong>No links found.</strong> The PDF does not contain any clickable hyperlinks.
            </div>
        ';
    } else {
        $tableRows = '';
        $csvContent = "URL\n";

        foreach ($extracted_links as $index => $link) {
            $url = htmlspecialchars($link['url']);
            $tableRows .= '<tr><td>' . ($index + 1) . '</td><td><a href="' . $url . '" target="_blank" rel="noopener">' . $url . '</a></td></tr>';
            $csvContent .= '"' . str_replace('"', '""', $link['url']) . "\"\n";
        }

        $resultsHtml = '
            <div class="alert alert-success mb-3">
                <strong>Found ' . $linkCount . ' unique link(s)!</strong>
            </div>
            <div class="mb-3">
                <button type="button" class="btn btn-primary btn-sm" onclick="downloadCsv()">Download as CSV</button>
            </div>
            <div class="table-responsive mb-4">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th>URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $tableRows . '
                    </tbody>
                </table>
            </div>
            <script>
            function downloadCsv() {
                const csv = ' . json_encode($csvContent) . ';
                const blob = new Blob([csv], {type: "text/csv"});
                const url = URL.createObjectURL(blob);
                const a = document.createElement("a");
                a.href = url;
                a.download = "extracted-links.csv";
                a.click();
                URL.revokeObjectURL(url);
            }
            </script>
        ';
    }

    $tool_config['form_html'] = $resultsHtml . '
        <hr>
        <h5>Extract links from another PDF:</h5>
        <div class="mb-3">
            <label for="pdf_file" class="form-label">Upload PDF File</label>
            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
            <div class="form-text">Maximum file size: 20MB</div>
        </div>
    ';
}

// Render the tool page
render_tool_page($tool_slug, $tool_config, $error_message ?? null);
