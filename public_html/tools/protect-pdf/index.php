<?php
/**
 * Protect PDF Tool
 *
 * Add password protection to PDF files.
 */

require_once __DIR__ . '/../../config/config.php';

// Page meta information
$tool_name = 'Protect PDF';
$tool_description = 'Add password protection to your PDF files online for free. Secure your documents with encryption.';
$page_title = $tool_name . ' - ' . SITE_NAME;
$canonical_url = BASE_URL . '/tools/protect-pdf/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo h($tool_description); ?>">
    <meta name="robots" content="index, follow">

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo h($page_title); ?>">
    <meta property="og:description" content="<?php echo h($tool_description); ?>">
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

    <!-- Tool Page Content -->
    <main class="main-content tool-page">
        <div class="container">
            <div class="tool-header">
                <h1><?php echo h($tool_name); ?></h1>
                <p><?php echo h($tool_description); ?></p>
            </div>

            <div class="tool-content">
                <div class="coming-soon">
                    <h2>Coming Soon!</h2>
                    <p>We're working hard to bring you this tool. Check back soon!</p>
                    <a href="/" class="back-link">Back to Home</a>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer Include -->
    <div data-include="/partials/footer.html"></div>

    <!-- Include.js for dynamic includes -->
    <script src="/include.js"></script>
</body>
</html>
