<?php
/**
 * XML Sitemap Generator
 *
 * Generates a dynamic XML sitemap for search engines.
 * Lists homepage and all active tools with proper metadata.
 *
 * Access via: /sitemap.xml (with .htaccess rewrite)
 * Or directly: /sitemap.xml.php
 */

// Set XML content type
header('Content-Type: application/xml; charset=utf-8');

// Include config
require_once __DIR__ . '/config/config.php';

// Base URL
$baseUrl = rtrim(BASE_URL, '/');

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Homepage -->
    <url>
        <loc><?php echo htmlspecialchars($baseUrl . '/', ENT_XML1, 'UTF-8'); ?></loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

<?php
// Get tools from registry
$tools_registry = require __DIR__ . '/config/tools_registry.php';

// Try to get last modified dates from database
$tool_dates = [];
try {
    // Check if we have database access
    if (function_exists('get_db_connection') ||
        (defined('DB_HOST') && defined('DB_NAME'))) {

        // Get database connection
        if (function_exists('get_db_connection')) {
            $pdo = get_db_connection();
        } else {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        // Get tool content updated dates
        $stmt = $pdo->query('
            SELECT t.slug,
                   COALESCE(tc.updated_at, t.updated_at) as last_modified
            FROM tools t
            LEFT JOIN tool_content tc ON t.id = tc.tool_id
            WHERE t.is_active = 1
        ');

        while ($row = $stmt->fetch()) {
            $tool_dates[$row['slug']] = $row['last_modified'];
        }
    }
} catch (PDOException $e) {
    // Database not available - continue with registry only
    error_log('Sitemap: Database not available - ' . $e->getMessage());
} catch (Exception $e) {
    error_log('Sitemap error: ' . $e->getMessage());
}

// Output tool URLs
foreach ($tools_registry as $slug => $tool) {
    // Skip inactive tools
    if (empty($tool['is_active'])) {
        continue;
    }

    // Determine last modified date
    $lastmod = date('Y-m-d');
    if (isset($tool_dates[$slug])) {
        $lastmod = date('Y-m-d', strtotime($tool_dates[$slug]));
    }

    // Determine priority based on featured status
    $priority = '0.8';
    if (!empty($tool['is_featured'])) {
        $priority = '0.9';
    }

    // Determine change frequency
    $changefreq = 'weekly';
    ?>
    <url>
        <loc><?php echo htmlspecialchars($baseUrl . '/tools/' . $slug . '/', ENT_XML1, 'UTF-8'); ?></loc>
        <lastmod><?php echo $lastmod; ?></lastmod>
        <changefreq><?php echo $changefreq; ?></changefreq>
        <priority><?php echo $priority; ?></priority>
    </url>
<?php
}
?>
</urlset>
<?php
/*
 * .htaccess Rewrite Rule
 * ======================
 * Add this to your .htaccess file to make /sitemap.xml work:
 *
 * # XML Sitemap
 * RewriteEngine On
 * RewriteRule ^sitemap\.xml$ sitemap.xml.php [L]
 *
 * Or for the full .htaccess:
 *
 * RewriteEngine On
 * RewriteBase /
 *
 * # Sitemap
 * RewriteRule ^sitemap\.xml$ sitemap.xml.php [L]
 *
 * # Robots.txt (optional - create robots.txt with: Sitemap: https://yoursite.com/sitemap.xml)
 *
 */
