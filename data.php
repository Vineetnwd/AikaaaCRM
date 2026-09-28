<?php
$_crm_root = __DIR__ . '/crm/';

require_once $_crm_root . '/config/config.php';
require_once $_crm_root . '/core/Database.php';

// ── Fetch all settings from DB ─────────────────────────────────────
$data = [];
try {
    $db   = \Core\Database::getInstance()->getConnection();
    $rows = $db->query("SELECT setting_key, setting_value FROM website_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Keys whose values are stored as JSON arrays
    $json_keys = ['features', 'core_values', 'pricing_plans', 'nav_links'];

    foreach ($rows as $k => $v) {
        $data[$k] = in_array($k, $json_keys) ? (json_decode($v, true) ?: []) : $v;
    }
} catch (Exception $e) {
    // DB unavailable — silently use defaults so the website stays up
    $data = [];
}

// ── Site Identity ──────────────────────────────────────────────────
$site_name        = $data['site_name']        ?? 'AikoCRM';
$site_tagline     = $data['site_tagline']     ?? '';
$site_description = $data['site_description'] ?? '';
$logo_url         = $data['logo_url']         ?? '';
$favicon_url      = $data['favicon_url']      ?? '';

// ── Hero ───────────────────────────────────────────────────────────
$hero_badge    = $data['hero_badge']    ?? '';
$hero_title    = $data['hero_title']    ?? '';
$hero_subtext  = $data['hero_subtext']  ?? '';
$hero_cta_text = $data['hero_cta_text'] ?? 'Start Free Trial';
$hero_cta_url  = $data['hero_cta_url']  ?? 'signup.php';

// ── Contact ────────────────────────────────────────────────────────
$contact_email    = $data['contact_email']    ?? '';
$contact_phone    = $data['contact_phone']    ?? '';
$contact_address  = $data['contact_address']  ?? '';
$contact_hours    = $data['contact_hours']    ?? '';
$contact_whatsapp = $data['contact_whatsapp'] ?? '';

// ── Social ─────────────────────────────────────────────────────────
$social_facebook  = $data['social_facebook']  ?? '';
$social_twitter   = $data['social_twitter']   ?? '';
$social_linkedin  = $data['social_linkedin']  ?? '';
$social_instagram = $data['social_instagram'] ?? '';

// ── Dynamic Content Lists ──────────────────────────────────────────
$nav_links     = $data['nav_links']      ?? [];
$features      = $data['features']       ?? [];
$core_values   = $data['core_values']    ?? [];
$pricing_plans = $data['pricing_plans']  ?? [];
?>
