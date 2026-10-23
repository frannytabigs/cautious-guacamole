<?php
// api/public_map.php
define('ITS_ME_JUSTTOVERIFY', true);
require_once 'database.php';
require_once 'logger.php';

try {
    $blockStmt = $pdo->query("
        SELECT
            b.block_id,
            b.block_name,
            b.block_type,
            b.coordinates,
            b.image_link,
            b.shape,
            b.custom
        FROM blocks b
        WHERE b.deleted_at IS NULL
        ORDER BY b.block_id
    ");
    $blocks = $blockStmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedBlocks = array_map(function ($b) {
        return [
            'block_id'    => (int) $b['block_id'],
            'block_name'  => $b['block_name'],
            'block_type'  => $b['block_type'],
            'coordinates' => $b['coordinates'],
            'image_link'  => publicSafeImageUrl($b['image_link'] ?? null),
            'shape'       => $b['shape'],
            'custom'      => $b['custom'] == 1,
        ];
    }, $blocks);

    Response::success("Map loaded", $formattedBlocks);
} catch (PDOException $e) {
    systemLog("Error: " . $e->getMessage(), 'System');
    Response::error("Please try again later.", 500);
}

/**
 * Returns an image URL only if it is safe to expose publicly.
 * Allows: http(s)://... and root-relative "/..."
 * Rejects: javascript:, data:, file:, protocol-relative //..., anything else.
 * Returns null for empty/invalid so the client can show a fallback.
 */
function publicSafeImageUrl($url)
{
    if (!is_string($url)) return null;
    $url = trim($url);
    if ($url === '') return null;

    if (preg_match('#^https?://#i', $url)) {
        $parts = parse_url($url);
        if (
            $parts !== false
            && !empty($parts['scheme'])
            && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            && !empty($parts['host'])
        ) {
            return $url;
        }
        return null;
    }

    // Root-relative, must start with a single slash (not "//")
    if (preg_match('#^/[^/\\\\]#', $url)) {
        return $url;
    }

    return null;
}
