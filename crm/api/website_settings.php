<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

use Core\Auth;
use Core\Database;

header('Content-Type: application/json');

if (!Auth::isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied. Super Admin role required.']);
    exit;
}

$db  = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET — return all settings as a single flat object ───────────────
if ($method === 'GET') {
    $rows = $db->query("SELECT setting_key, setting_value FROM website_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

    $json_keys = ['features', 'core_values', 'pricing_plans', 'nav_links'];
    $result = [];
    foreach ($rows as $k => $v) {
        $result[$k] = in_array($k, $json_keys) ? (json_decode($v, true) ?: []) : $v;
    }

    echo json_encode($result);
    exit;
}

// ── POST — upsert all settings ────────────────────────────────────
if ($method !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if ($input === null) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

// Keys stored as plain strings
$scalar_keys = [
    'site_name', 'site_tagline', 'site_description', 'logo_url', 'favicon_url',
    'hero_badge', 'hero_title', 'hero_subtext', 'hero_cta_text', 'hero_cta_url',
    'contact_email', 'contact_phone', 'contact_address', 'contact_hours', 'contact_whatsapp',
    'social_facebook', 'social_twitter', 'social_linkedin', 'social_instagram',
    'theme_color_hex', 'theme_color_secondary_hex'
];
// Keys stored as JSON arrays
$json_keys = ['features', 'core_values', 'pricing_plans', 'nav_links'];

$stmt = $db->prepare("
    INSERT INTO website_settings (setting_key, setting_value)
    VALUES (:k, :v)
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
");

try {
    $db->beginTransaction();

    foreach ($scalar_keys as $key) {
        if (array_key_exists($key, $input)) {
            $stmt->execute([':k' => $key, ':v' => (string)($input[$key] ?? '')]);
        }
    }
    foreach ($json_keys as $key) {
        if (array_key_exists($key, $input)) {
            $stmt->execute([':k' => $key, ':v' => json_encode($input[$key] ?? [], JSON_UNESCAPED_UNICODE)]);
        }
    }

    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
