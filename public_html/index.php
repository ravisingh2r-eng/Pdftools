<?php
/**
 * PDF Tools - Landing Page
 *
 * Main entry point displaying all available PDF tools.
 */

require_once __DIR__ . '/config/config.php';

// Load tools registry
$tools_registry = require __DIR__ . '/config/tools_registry.php';

// Page meta information
$page_title = SITE_NAME . ' - Free Online PDF Tools';
$page_description = SITE_DESCRIPTION;
$canonical_url = BASE_URL . '/';

// Category definitions with display names
$categories = [
    'basic' => [
        'name' => 'Basic PDF Tools',
        'description' => 'Essential tools for everyday PDF tasks'
    ],
    'compress' => [
        'name' => 'Compress & Optimize',
        'description' => 'Reduce file size and optimize PDFs'
    ],
    'convert' => [
        'name' => 'Convert PDF',
        'description' => 'Convert PDF to and from other formats'
    ],
    'security' => [
        'name' => 'PDF Security',
        'description' => 'Protect, unlock, and secure your PDFs'
    ],
    'edit' => [
        'name' => 'Edit PDF',
        'description' => 'Modify pages, add content, and more'
    ],
    'advanced' => [
        'name' => 'Advanced Tools',
        'description' => 'OCR, repair, and specialized tools'
    ],
];

// Get featured tools (active, featured, sorted)
$featured_tools = array_filter($tools_registry, function($tool) {
    return $tool['is_active'] && $tool['is_featured'];
});
uasort($featured_tools, function($a, $b) {
    return $a['sort_order'] <=> $b['sort_order'];
});
$featured_tools = array_slice($featured_tools, 0, 8, true);

// Get all active tools grouped by category
$tools_by_category = [];
foreach ($tools_registry as $slug => $tool) {
    if ($tool['is_active']) {
        $category = $tool['category'];
        if (!isset($tools_by_category[$category])) {
            $tools_by_category[$category] = [];
        }
        $tools_by_category[$category][$slug] = $tool;
    }
}

// Sort tools within each category
foreach ($tools_by_category as $category => &$tools) {
    uasort($tools, function($a, $b) {
        return $a['sort_order'] <=> $b['sort_order'];
    });
}
unset($tools);
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
            <!-- Featured Tools Section -->
            <?php if (!empty($featured_tools)): ?>
            <section class="tools-section">
                <h2>Popular PDF Tools</h2>
                <div class="tools-grid">
                    <?php foreach ($featured_tools as $slug => $tool): ?>
                    <a href="/tools/<?php echo h($slug); ?>/" class="tool-card">
                        <div class="tool-icon" style="background-color: <?php echo h($tool['color']); ?>">
                            <?php echo $tool['icon']; ?>
                        </div>
                        <h3><?php echo h($tool['name']); ?></h3>
                        <p><?php echo h($tool['short_description']); ?></p>
                        <?php if ($tool['is_premium']): ?>
                        <span class="premium-badge">Premium</span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Tools by Category -->
            <?php foreach ($categories as $category_key => $category_info): ?>
                <?php if (!empty($tools_by_category[$category_key])): ?>
                <section class="tools-section category-section" id="<?php echo h($category_key); ?>">
                    <h2><?php echo h($category_info['name']); ?></h2>
                    <p class="section-description"><?php echo h($category_info['description']); ?></p>
                    <div class="tools-grid">
                        <?php foreach ($tools_by_category[$category_key] as $slug => $tool): ?>
                        <a href="/tools/<?php echo h($slug); ?>/" class="tool-card">
                            <div class="tool-icon" style="background-color: <?php echo h($tool['color']); ?>">
                                <?php echo $tool['icon']; ?>
                            </div>
                            <h3><?php echo h($tool['name']); ?></h3>
                            <p><?php echo h($tool['short_description']); ?></p>
                            <?php if ($tool['is_premium']): ?>
                            <span class="premium-badge">Premium</span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            <?php endforeach; ?>

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
