<?php
/**
 * Tool Page Template
 *
 * Reusable template for all PDF tool pages.
 * Provides consistent SEO, layout, and user experience.
 *
 * @package PDFTools
 */

// Prevent direct access
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

// Include ads renderer
require_once __DIR__ . '/../lib/ads_renderer.php';

// Include SEO helper
require_once __DIR__ . '/../lib/seo_helper.php';

/**
 * Render a complete tool page
 *
 * @param array $config Configuration array with tool details
 *
 * Required keys:
 * - tool_slug: URL slug (e.g., 'merge-pdf')
 * - tool_name: Display name (e.g., 'Merge PDF')
 * - primary_keyword: Main SEO keyword
 * - meta_title: Page title for SEO
 * - meta_description: Meta description for SEO
 * - canonical_url: Full canonical URL
 * - author_name: Content author
 * - publish_date: ISO 8601 date
 * - modified_date: ISO 8601 date
 * - brand_name: Site/brand name
 * - og_image_url: Open Graph image URL
 * - short_intro_html: Brief introduction HTML
 * - how_it_works_html: How to use section HTML
 * - features_html: Key features section HTML
 * - faq_items: Array of ['q' => question, 'a' => answer]
 * - related_tools: Array of ['slug' => slug, 'name' => name]
 *
 * Optional keys:
 * - upload_accept: File input accept attribute (default: '.pdf')
 * - upload_multiple: Allow multiple files (default: false)
 * - upload_text: Upload area text
 * - button_text: Submit button text
 * - custom_head_html: Additional HTML for <head>
 * - custom_form_html: Additional form fields HTML
 * - custom_scripts_html: Additional scripts before </body>
 *
 * @return void Outputs HTML directly
 */
function render_tool_page(array $config): void
{
    // Set defaults
    $defaults = [
        'upload_accept' => '.pdf,application/pdf',
        'upload_multiple' => false,
        'upload_text' => 'Drop your PDF file here or click to browse',
        'button_text' => 'Start Processing',
        'custom_head_html' => '',
        'custom_form_html' => '',
        'custom_scripts_html' => '',
        'custom_footer_html' => '',
        'og_image_url' => '',
        'author_name' => 'PDF Tools Team',
        'publish_date' => date('c'),
        'modified_date' => date('c'),
    ];

    $config = array_merge($defaults, $config);

    // Extract variables for easier use
    extract($config);

    // Generate CSRF token
    $csrf_token = csrf_token();

    // Load all ad codes at once for efficiency
    $ad_slots = [
        'header_banner',
        'before_tool',
        'after_tool',
        'in_content',
        'footer_banner'
    ];
    $ad_codes = get_all_ad_codes($tool_slug, $ad_slots);

    // Auto-generate related tools if not provided
    if (empty($related_tools)) {
        $related_tools = get_seo_related_tools($tool_slug, 4);
    }

    // Build JSON-LD Schema
    $schema_webpage = [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => $tool_name,
        'description' => $meta_description,
        'url' => $canonical_url,
        'applicationCategory' => 'UtilityApplication',
        'operatingSystem' => 'Any',
        'offers' => [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'USD'
        ],
        'author' => [
            '@type' => 'Organization',
            'name' => $brand_name
        ],
        'datePublished' => $publish_date,
        'dateModified' => $modified_date
    ];

    // Build FAQ Schema if FAQs exist
    $schema_faq = null;
    if (!empty($faq_items)) {
        $faq_entities = [];
        foreach ($faq_items as $item) {
            $faq_entities[] = [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['a']
                ]
            ];
        }
        $schema_faq = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faq_entities
        ];
    }

    // Start output
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Primary Meta Tags -->
    <title><?php echo htmlspecialchars($meta_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="title" content="<?php echo htmlspecialchars($meta_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($primary_keyword, ENT_QUOTES, 'UTF-8'); ?>, PDF tools, online PDF">
    <meta name="author" content="<?php echo htmlspecialchars($author_name, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="index, follow">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($meta_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($og_image_url): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image_url, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="twitter:title" content="<?php echo htmlspecialchars($meta_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="twitter:description" content="<?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($og_image_url): ?>
    <meta property="twitter:image" content="<?php echo htmlspecialchars($og_image_url, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <!-- JSON-LD Schema -->
    <script type="application/ld+json">
    <?php echo json_encode($schema_webpage, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
    </script>
    <?php if ($schema_faq): ?>
    <script type="application/ld+json">
    <?php echo json_encode($schema_faq, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
    </script>
    <?php endif; ?>

    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/tool.css">

    <?php echo $custom_head_html; ?>
</head>
<body>
    <!-- Header Include -->
    <div data-include="/partials/header.html"></div>

    <!-- Ad: Header Top -->
    <?php if (!empty($ad_codes['header_banner'])): ?>
    <div id="ad_header_top" class="ad-slot ad-header-top">
        <?php echo $ad_codes['header_banner']; ?>
    </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="tool-hero">
        <div class="container">
            <h1><?php echo htmlspecialchars($tool_name, ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="tool-intro">
                <?php echo $short_intro_html; ?>
            </div>

            <!-- Upload Form -->
            <div class="tool-upload-wrapper">
                <form method="POST" enctype="multipart/form-data" id="toolForm" class="tool-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="upload-area" id="uploadArea">
                        <input
                            type="file"
                            name="pdf_files[]"
                            id="fileInput"
                            <?php echo $upload_multiple ? 'multiple' : ''; ?>
                            accept="<?php echo htmlspecialchars($upload_accept, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                        <div class="upload-icon">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                        </div>
                        <div class="upload-text"><?php echo htmlspecialchars($upload_text, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="upload-hint">Maximum file size: 50MB</div>
                    </div>

                    <!-- File List -->
                    <div class="file-list" id="fileList"></div>

                    <!-- Custom Form Fields -->
                    <?php echo $custom_form_html; ?>

                    <!-- Submit Button -->
                    <button type="submit" class="tool-btn" id="submitBtn" disabled>
                        <span class="btn-text"><?php echo htmlspecialchars($button_text, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="btn-loading" style="display: none;">
                            <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4 31.4" transform="rotate(-90 12 12)"/>
                            </svg>
                            Processing...
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Ad: Below Hero -->
    <?php if (!empty($ad_codes['before_tool'])): ?>
    <div id="ad_below_hero" class="ad-slot ad-below-hero">
        <?php echo $ad_codes['before_tool']; ?>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="tool-content">
        <div class="container">

            <!-- How It Works Section -->
            <section class="content-section how-it-works">
                <h2>How to <?php echo htmlspecialchars($tool_name, ENT_QUOTES, 'UTF-8'); ?> Online</h2>
                <div class="section-content">
                    <?php echo $how_it_works_html; ?>
                </div>
            </section>

            <!-- Ad: Below Tool -->
            <?php if (!empty($ad_codes['after_tool'])): ?>
            <div id="ad_below_tool" class="ad-slot ad-below-tool">
                <?php echo $ad_codes['after_tool']; ?>
            </div>
            <?php endif; ?>

            <!-- Key Features Section -->
            <section class="content-section features">
                <h2>Key Features</h2>
                <div class="section-content">
                    <?php echo $features_html; ?>
                </div>
            </section>

            <!-- FAQ Section -->
            <?php if (!empty($faq_items)): ?>
            <section class="content-section faq">
                <h2>Frequently Asked Questions</h2>
                <div class="faq-list">
                    <?php foreach ($faq_items as $index => $item): ?>
                    <div class="faq-item">
                        <button class="faq-question" aria-expanded="false" aria-controls="faq-answer-<?php echo $index; ?>">
                            <span><?php echo htmlspecialchars($item['q'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <svg class="faq-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </button>
                        <div class="faq-answer" id="faq-answer-<?php echo $index; ?>">
                            <p><?php echo htmlspecialchars($item['a'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Ad: Between FAQ -->
            <?php if (!empty($ad_codes['in_content'])): ?>
            <div id="ad_between_faq" class="ad-slot ad-between-faq">
                <?php echo $ad_codes['in_content']; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Related Tools Section -->
            <?php if (!empty($related_tools)): ?>
            <section class="content-section related-tools">
                <h2>You Might Also Like</h2>
                <div class="related-tools-grid">
                    <?php foreach ($related_tools as $tool): ?>
                    <a href="/tools/<?php echo htmlspecialchars($tool['slug'], ENT_QUOTES, 'UTF-8'); ?>/" class="related-tool-card">
                        <span class="related-tool-name"><?php echo htmlspecialchars($tool['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Custom Footer Content (e.g., Feedback Form) -->
            <?php if (!empty($custom_footer_html)): ?>
            <section class="content-section custom-footer-section">
                <?php echo $custom_footer_html; ?>
            </section>
            <?php endif; ?>

        </div>
    </main>

    <!-- Ad: Footer -->
    <?php if (!empty($ad_codes['footer_banner'])): ?>
    <div id="ad_footer" class="ad-slot ad-footer-top">
        <?php echo $ad_codes['footer_banner']; ?>
    </div>
    <?php endif; ?>

    <!-- Footer Include -->
    <div data-include="/partials/footer.html"></div>

    <!-- Include.js for dynamic includes -->
    <script src="/include.js"></script>

    <!-- Tool Page Scripts -->
    <script>
    (function() {
        'use strict';

        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        const submitBtn = document.getElementById('submitBtn');
        const toolForm = document.getElementById('toolForm');
        const isMultiple = <?php echo $upload_multiple ? 'true' : 'false'; ?>;

        let selectedFiles = [];

        // Click to open file dialog
        uploadArea.addEventListener('click', function(e) {
            if (e.target !== fileInput) {
                fileInput.click();
            }
        });

        // Handle file selection
        fileInput.addEventListener('change', handleFiles);

        // Drag and drop events
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.remove('dragover');

            const dt = e.dataTransfer;
            if (dt.files && dt.files.length) {
                handleFiles({ target: { files: dt.files } });
            }
        });

        function handleFiles(e) {
            const files = Array.from(e.target.files || []);

            // Filter for valid files
            const validFiles = files.filter(function(file) {
                const ext = file.name.toLowerCase().split('.').pop();
                return ext === 'pdf' || file.type === 'application/pdf';
            });

            if (validFiles.length !== files.length) {
                showNotification('Some files were skipped. Only PDF files are allowed.', 'warning');
            }

            if (isMultiple) {
                selectedFiles = selectedFiles.concat(validFiles);
            } else {
                selectedFiles = validFiles.slice(0, 1);
            }

            updateFileList();
            updateSubmitButton();
        }

        function updateFileList() {
            fileList.innerHTML = '';

            if (selectedFiles.length === 0) {
                fileList.style.display = 'none';
                return;
            }

            fileList.style.display = 'block';

            selectedFiles.forEach(function(file, index) {
                const item = document.createElement('div');
                item.className = 'file-item';

                const size = formatFileSize(file.size);

                item.innerHTML =
                    '<div class="file-info">' +
                        '<span class="file-name">' + escapeHtml(file.name) + '</span>' +
                        '<span class="file-size">' + size + '</span>' +
                    '</div>' +
                    '<button type="button" class="file-remove" data-index="' + index + '" aria-label="Remove file">' +
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                            '<line x1="18" y1="6" x2="6" y2="18"/>' +
                            '<line x1="6" y1="6" x2="18" y2="18"/>' +
                        '</svg>' +
                    '</button>';

                fileList.appendChild(item);
            });

            // Add remove event listeners
            fileList.querySelectorAll('.file-remove').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const index = parseInt(this.dataset.index);
                    selectedFiles.splice(index, 1);
                    updateFileList();
                    updateSubmitButton();
                    updateFileInput();
                });
            });

            updateFileInput();
        }

        function updateFileInput() {
            const dt = new DataTransfer();
            selectedFiles.forEach(function(file) {
                dt.items.add(file);
            });
            fileInput.files = dt.files;
        }

        function updateSubmitButton() {
            const minFiles = isMultiple ? 2 : 1;
            submitBtn.disabled = selectedFiles.length < minFiles;
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showNotification(message, type) {
            // Simple alert for now - can be replaced with toast notification
            alert(message);
        }

        // Form submission
        toolForm.addEventListener('submit', function(e) {
            const minFiles = isMultiple ? 2 : 1;

            if (selectedFiles.length < minFiles) {
                e.preventDefault();
                const msg = isMultiple
                    ? 'Please select at least ' + minFiles + ' PDF files.'
                    : 'Please select a PDF file.';
                showNotification(msg, 'error');
                return;
            }

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.querySelector('.btn-text').style.display = 'none';
            submitBtn.querySelector('.btn-loading').style.display = 'inline-flex';
        });

        // FAQ Accordion
        document.querySelectorAll('.faq-question').forEach(function(button) {
            button.addEventListener('click', function() {
                const expanded = this.getAttribute('aria-expanded') === 'true';
                const answer = this.nextElementSibling;

                // Close all other FAQs
                document.querySelectorAll('.faq-question').forEach(function(btn) {
                    btn.setAttribute('aria-expanded', 'false');
                    btn.nextElementSibling.style.maxHeight = null;
                });

                // Toggle current
                if (!expanded) {
                    this.setAttribute('aria-expanded', 'true');
                    answer.style.maxHeight = answer.scrollHeight + 'px';
                }
            });
        });

    })();
    </script>

    <?php echo $custom_scripts_html; ?>

</body>
</html>
    <?php
}
