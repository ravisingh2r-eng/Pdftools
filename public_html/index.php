<?php
/**
 * PDF Tools - Landing Page
 *
 * Main entry point displaying all available PDF tools.
 */

require_once __DIR__ . '/config/config.php';

// Page meta information
$page_title = SITE_NAME . ' - Free Online PDF Tools';
$page_description = SITE_DESCRIPTION;
$canonical_url = BASE_URL . '/';

// Tool definitions
$tools = [
    [
        'name' => 'Merge PDF',
        'slug' => 'merge-pdf',
        'description' => 'Combine multiple PDF files into one document',
        'icon' => '📑',
        'color' => '#4CAF50'
    ],
    [
        'name' => 'Split PDF',
        'slug' => 'split-pdf',
        'description' => 'Separate PDF pages into multiple files',
        'icon' => '✂️',
        'color' => '#2196F3'
    ],
    [
        'name' => 'Compress PDF',
        'slug' => 'compress-pdf',
        'description' => 'Reduce PDF file size while maintaining quality',
        'icon' => '🗜️',
        'color' => '#FF9800'
    ],
    [
        'name' => 'Rotate PDF',
        'slug' => 'rotate-pdf',
        'description' => 'Rotate PDF pages to any angle',
        'icon' => '🔄',
        'color' => '#9C27B0'
    ],
    [
        'name' => 'Protect PDF',
        'slug' => 'protect-pdf',
        'description' => 'Add password protection to your PDF files',
        'icon' => '🔒',
        'color' => '#F44336'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo h($page_description); ?>">
    <meta name="robots" content="index, follow">

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo h($page_title); ?>">
    <meta property="og:description" content="<?php echo h($page_description); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo h($canonical_url); ?>">

    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo h($canonical_url); ?>">

    <title><?php echo h($page_title); ?></title>

    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <!-- Header Include -->
    <div data-include="/partials/header.html"></div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Free Online PDF Tools</h1>
            <p class="hero-subtitle">All the tools you need to work with PDFs in one place. 100% free, no registration required.</p>
        </div>
    </section>

    <!-- Tools Grid -->
    <main class="main-content">
        <div class="container">
            <section class="tools-section">
                <h2>Popular PDF Tools</h2>
                <div class="tools-grid">
                    <?php foreach ($tools as $tool): ?>
                    <a href="/tools/<?php echo h($tool['slug']); ?>/" class="tool-card">
                        <div class="tool-icon" style="background-color: <?php echo h($tool['color']); ?>">
                            <?php echo $tool['icon']; ?>
                        </div>
                        <h3><?php echo h($tool['name']); ?></h3>
                        <p><?php echo h($tool['description']); ?></p>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Features Section -->
            <section class="features-section">
                <h2>Why Choose Our PDF Tools?</h2>
                <div class="features-grid">
                    <div class="feature">
                        <div class="feature-icon">🚀</div>
                        <h3>Fast & Easy</h3>
                        <p>Process your PDF files in seconds with our simple interface.</p>
                    </div>
                    <div class="feature">
                        <div class="feature-icon">🔒</div>
                        <h3>Secure</h3>
                        <p>Your files are automatically deleted after processing.</p>
                    </div>
                    <div class="feature">
                        <div class="feature-icon">💻</div>
                        <h3>Works Everywhere</h3>
                        <p>Access from any device - desktop, tablet, or mobile.</p>
                    </div>
                    <div class="feature">
                        <div class="feature-icon">💰</div>
                        <h3>100% Free</h3>
                        <p>No hidden costs, no registration required.</p>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Footer Include -->
    <div data-include="/partials/footer.html"></div>

    <!-- Include.js for dynamic includes -->
    <script src="/include.js"></script>
</body>
</html>
