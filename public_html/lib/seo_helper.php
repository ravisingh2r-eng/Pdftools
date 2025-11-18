<?php
/**
 * SEO Helper Functions
 *
 * Provides utilities for internal linking, related content,
 * and other SEO optimizations.
 */

/**
 * Get related tools for internal linking
 *
 * Returns tools from the same category, excluding the current tool.
 * Falls back to featured tools if no category matches found.
 *
 * @param string   $currentSlug  The current tool slug to exclude
 * @param int      $limit        Maximum number of tools to return
 * @param string|null $category  Optional category filter (auto-detected if null)
 * @return array                 Array of [slug, name] pairs
 */
function get_seo_related_tools(string $currentSlug, int $limit = 4, ?string $category = null): array {
    $related = [];

    // Try database first for most up-to-date data
    $dbTools = get_related_tools_from_db($currentSlug, $limit, $category);
    if (!empty($dbTools)) {
        return $dbTools;
    }

    // Fall back to registry
    return get_related_tools_from_registry($currentSlug, $limit, $category);
}

/**
 * Get related tools from database
 *
 * @param string      $currentSlug
 * @param int         $limit
 * @param string|null $category
 * @return array
 */
function get_related_tools_from_db(string $currentSlug, int $limit = 4, ?string $category = null): array {
    try {
        // Get database connection
        $pdo = get_seo_db_connection();
        if (!$pdo) {
            return [];
        }

        // Get current tool's category if not provided
        if ($category === null) {
            $stmt = $pdo->prepare('SELECT category FROM tools WHERE slug = ? AND is_active = 1');
            $stmt->execute([$currentSlug]);
            $category = $stmt->fetchColumn();

            if (!$category) {
                return [];
            }
        }

        // Get related tools from same category
        $stmt = $pdo->prepare('
            SELECT slug, name
            FROM tools
            WHERE category = ?
              AND slug != ?
              AND is_active = 1
            ORDER BY is_featured DESC, sort_order ASC, total_uses DESC
            LIMIT ?
        ');
        $stmt->execute([$category, $currentSlug, $limit]);
        $related = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If not enough tools in category, add featured tools from other categories
        if (count($related) < $limit) {
            $remaining = $limit - count($related);
            $excludeSlugs = array_column($related, 'slug');
            $excludeSlugs[] = $currentSlug;

            $placeholders = implode(',', array_fill(0, count($excludeSlugs), '?'));

            $stmt = $pdo->prepare("
                SELECT slug, name
                FROM tools
                WHERE slug NOT IN ($placeholders)
                  AND is_active = 1
                ORDER BY is_featured DESC, total_uses DESC
                LIMIT ?
            ");

            $params = array_merge($excludeSlugs, [$remaining]);
            $stmt->execute($params);
            $additional = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $related = array_merge($related, $additional);
        }

        return $related;

    } catch (PDOException $e) {
        error_log('SEO helper DB error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get related tools from registry file
 *
 * @param string      $currentSlug
 * @param int         $limit
 * @param string|null $category
 * @return array
 */
function get_related_tools_from_registry(string $currentSlug, int $limit = 4, ?string $category = null): array {
    // Load registry
    $registryPath = dirname(__DIR__) . '/config/tools_registry.php';
    if (!file_exists($registryPath)) {
        return [];
    }

    $registry = require $registryPath;

    // Get current tool's category if not provided
    if ($category === null && isset($registry[$currentSlug])) {
        $category = $registry[$currentSlug]['category'] ?? null;
    }

    $related = [];
    $fallback = [];

    foreach ($registry as $slug => $tool) {
        // Skip current tool and inactive tools
        if ($slug === $currentSlug || empty($tool['is_active'])) {
            continue;
        }

        $toolEntry = [
            'slug' => $slug,
            'name' => $tool['name']
        ];

        // Prioritize same category
        if ($category && ($tool['category'] ?? '') === $category) {
            $related[] = $toolEntry;
        } else {
            $fallback[] = $toolEntry;
        }
    }

    // Sort by featured status and sort_order
    usort($related, function ($a, $b) use ($registry) {
        $aFeatured = $registry[$a['slug']]['is_featured'] ?? false;
        $bFeatured = $registry[$b['slug']]['is_featured'] ?? false;
        if ($aFeatured !== $bFeatured) {
            return $bFeatured - $aFeatured;
        }
        $aOrder = $registry[$a['slug']]['sort_order'] ?? 100;
        $bOrder = $registry[$b['slug']]['sort_order'] ?? 100;
        return $aOrder - $bOrder;
    });

    // Add fallback tools if needed
    if (count($related) < $limit) {
        usort($fallback, function ($a, $b) use ($registry) {
            $aFeatured = $registry[$a['slug']]['is_featured'] ?? false;
            $bFeatured = $registry[$b['slug']]['is_featured'] ?? false;
            return $bFeatured - $aFeatured;
        });

        $related = array_merge($related, array_slice($fallback, 0, $limit - count($related)));
    }

    return array_slice($related, 0, $limit);
}

/**
 * Get popular tools for sidebar or footer
 *
 * @param int    $limit
 * @param string $excludeSlug
 * @return array
 */
function get_popular_tools(int $limit = 6, string $excludeSlug = ''): array {
    try {
        $pdo = get_seo_db_connection();
        if ($pdo) {
            $stmt = $pdo->prepare('
                SELECT slug, name
                FROM tools
                WHERE is_active = 1
                  AND slug != ?
                ORDER BY total_uses DESC, is_featured DESC
                LIMIT ?
            ');
            $stmt->execute([$excludeSlug, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log('SEO helper error: ' . $e->getMessage());
    }

    // Fallback to registry
    $registryPath = dirname(__DIR__) . '/config/tools_registry.php';
    if (!file_exists($registryPath)) {
        return [];
    }

    $registry = require $registryPath;
    $tools = [];

    foreach ($registry as $slug => $tool) {
        if ($slug === $excludeSlug || empty($tool['is_active'])) {
            continue;
        }
        $tools[] = [
            'slug' => $slug,
            'name' => $tool['name'],
            'featured' => $tool['is_featured'] ?? false
        ];
    }

    // Sort by featured
    usort($tools, function ($a, $b) {
        return ($b['featured'] ?? 0) - ($a['featured'] ?? 0);
    });

    return array_slice(array_map(function ($t) {
        return ['slug' => $t['slug'], 'name' => $t['name']];
    }, $tools), 0, $limit);
}

/**
 * Get tools by category for category pages
 *
 * @param string $category
 * @param int    $limit
 * @return array
 */
function get_tools_by_category(string $category, int $limit = 20): array {
    try {
        $pdo = get_seo_db_connection();
        if ($pdo) {
            $stmt = $pdo->prepare('
                SELECT slug, name, short_description, icon
                FROM tools
                WHERE category = ?
                  AND is_active = 1
                ORDER BY is_featured DESC, sort_order ASC
                LIMIT ?
            ');
            $stmt->execute([$category, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log('SEO helper error: ' . $e->getMessage());
    }

    // Fallback to registry
    $registryPath = dirname(__DIR__) . '/config/tools_registry.php';
    if (!file_exists($registryPath)) {
        return [];
    }

    $registry = require $registryPath;
    $tools = [];

    foreach ($registry as $slug => $tool) {
        if (($tool['category'] ?? '') !== $category || empty($tool['is_active'])) {
            continue;
        }
        $tools[] = [
            'slug' => $slug,
            'name' => $tool['name'],
            'short_description' => $tool['short_description'] ?? '',
            'icon' => $tool['icon'] ?? ''
        ];
    }

    return array_slice($tools, 0, $limit);
}

/**
 * Generate breadcrumb data for structured data
 *
 * @param string $toolSlug
 * @param string $toolName
 * @return array
 */
function get_breadcrumb_data(string $toolSlug, string $toolName): array {
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Home',
                'item' => $baseUrl . '/'
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'Tools',
                'item' => $baseUrl . '/tools/'
            ],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $toolName,
                'item' => $baseUrl . '/tools/' . $toolSlug . '/'
            ]
        ]
    ];
}

/**
 * Get database connection for SEO helpers
 *
 * @return PDO|null
 */
function get_seo_db_connection(): ?PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (function_exists('get_db_connection')) {
            $pdo = get_db_connection();
            return $pdo;
        }

        if (!defined('DB_HOST') || !defined('DB_NAME')) {
            return null;
        }

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

        return $pdo;

    } catch (PDOException $e) {
        error_log('SEO helper DB connection error: ' . $e->getMessage());
        return null;
    }
}
