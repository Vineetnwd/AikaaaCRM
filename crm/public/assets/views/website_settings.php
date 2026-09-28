<?php
use Core\Auth;
if (!Auth::isSuperAdmin()) {
    header('Location: ' . APP_URL . '/public/index.php/dashboard');
    exit;
}

$json_file = __DIR__ . '/../../../website/data.json';
$data = [];
if (file_exists($json_file)) {
    $data = json_decode(file_get_contents($json_file), true) ?: [];
}
if (empty($data)) {
    $data = new stdClass();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Config Manager | AikoCRM Super Admin</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ─── Page Chrome ─────────────────────────────────────── */
        .cfg-page {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .cfg-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1.5rem;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 30;
        }

        .cfg-topbar-left {
            display: flex;
            align-items: center;
            gap: 0.875rem;
        }

        .cfg-logo-badge {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary, #6366f1), var(--accent, #8b5cf6));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .cfg-title {
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        .cfg-subtitle {
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .cfg-topbar-actions {
            display: flex;
            align-items: center;
            gap: 0.625rem;
        }

        /* ─── Status Toast ────────────────────────────────────── */
        #saveToast {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            background: #0f172a;
            color: white;
            padding: 0.6rem 1.25rem;
            border-radius: 10px;
            font-size: 0.8125rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
            transform: translateY(80px);
            opacity: 0;
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            pointer-events: none;
        }

        #saveToast.show {
            transform: translateY(0);
            opacity: 1;
        }

        #saveToast.success {
            background: #059669;
        }

        #saveToast.error {
            background: #dc2626;
        }

        /* ─── Tab Nav ─────────────────────────────────────────── */
        .cfg-tabs {
            display: flex;
            gap: 0;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 1.5rem;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .cfg-tabs::-webkit-scrollbar {
            display: none;
        }

        .cfg-tab {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #64748b;
            border: none;
            background: none;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.18s;
            white-space: nowrap;
            margin-bottom: -1px;
        }

        .cfg-tab:hover {
            color: var(--primary, #6366f1);
        }

        .cfg-tab.active {
            color: var(--primary, #6366f1);
            border-bottom-color: var(--primary, #6366f1);
        }

        .cfg-tab i {
            font-size: 0.8rem;
        }

        /* ─── Content Panels ──────────────────────────────────── */
        .cfg-body {
            padding: 1.5rem;
        }

        .cfg-panel {
            display: none;
        }

        .cfg-panel.active {
            display: block;
        }

        /* ─── Section Card ────────────────────────────────────── */
        .cfg-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .cfg-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            background: #fafbfc;
        }

        .cfg-card-header-left {
            display: flex;
            align-items: center;
            gap: 0.625rem;
        }

        .cfg-card-icon {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }

        .cfg-card-icon.indigo {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary, #6366f1);
        }

        .cfg-card-icon.emerald {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .cfg-card-icon.amber {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .cfg-card-icon.sky {
            background: rgba(14, 165, 233, 0.1);
            color: #0ea5e9;
        }

        .cfg-card-icon.rose {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .cfg-card-icon.violet {
            background: rgba(139, 92, 246, 0.1);
            color: var(--accent, #8b5cf6);
        }

        .cfg-card-title {
            font-size: 0.875rem;
            font-weight: 700;
            color: #1e293b;
        }

        .cfg-card-body {
            padding: 1.25rem;
        }

        /* ─── Form Grid ───────────────────────────────────────── */
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        .form-col-full {
            grid-column: 1 / -1;
        }

        .field-group {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }

        .field-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .field-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #0f172a;
            background: #fff;
            font-family: inherit;
            transition: all 0.2s;
        }

        .field-input:focus {
            outline: none;
            border-color: var(--primary, #6366f1);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }

        textarea.field-input {
            resize: vertical;
            min-height: 72px;
            line-height: 1.5;
        }

        select.field-input {
            cursor: pointer;
        }

        /* ─── Logo Upload ─────────────────────────────────────── */
        .logo-upload-area {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1rem 1.25rem;
        }

        .logo-preview-box {
            width: 72px;
            height: 72px;
            border-radius: 12px;
            border: 2px dashed #cbd5e1;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            cursor: pointer;
            transition: border-color 0.2s;
            position: relative;
        }

        .logo-preview-box:hover {
            border-color: var(--primary, #6366f1);
        }

        .logo-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .logo-preview-box .logo-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            color: #94a3b8;
        }

        .logo-preview-box .logo-placeholder i {
            font-size: 1.25rem;
        }

        .logo-preview-box .logo-placeholder span {
            font-size: 0.6rem;
            font-weight: 600;
        }

        .logo-upload-meta {
            flex: 1;
        }

        .logo-upload-meta h4 {
            font-size: 0.875rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .logo-upload-meta p {
            font-size: 0.75rem;
            color: #94a3b8;
            line-height: 1.4;
        }

        .logo-upload-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.625rem;
        }

        /* ─── Dynamic List ────────────────────────────────────── */
        .dyn-list {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }

        .dyn-item {
            display: flex;
            gap: 0.625rem;
            align-items: flex-start;
            background: #f8fafc;
            border: 1px solid #e9eff5;
            border-radius: 9px;
            padding: 0.75rem;
            transition: border-color 0.2s;
        }

        .dyn-item:hover {
            border-color: #cbd5e1;
        }

        .dyn-item-fields {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .dyn-item-fields.row {
            flex-direction: row;
            align-items: center;
        }

        .dyn-drag {
            color: #cbd5e1;
            cursor: grab;
            padding: 0.125rem 0.25rem;
            font-size: 0.8rem;
            align-self: center;
        }

        .btn-icon-del {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            flex-shrink: 0;
            background: rgba(239, 68, 68, 0.08);
            border: none;
            color: #ef4444;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            transition: all 0.18s;
            align-self: flex-start;
            margin-top: 0.125rem;
        }

        .btn-icon-del:hover {
            background: #ef4444;
            color: white;
        }

        /* ─── Buttons ─────────────────────────────────────────── */
        .btn-cfg-add {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.4rem 0.875rem;
            background: #fff;
            border: 1.5px dashed #cbd5e1;
            border-radius: 8px;
            color: #64748b;
            font-size: 0.775rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
        }

        .btn-cfg-add:hover {
            border-color: var(--primary, #6366f1);
            color: var(--primary, #6366f1);
            background: rgba(99, 102, 241, 0.04);
        }

        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.25rem;
            background: linear-gradient(135deg, var(--primary, #6366f1), var(--primary-hover, #4f46e5));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.8125rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
            transition: all 0.2s;
        }

        .btn-save:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.45);
        }

        .btn-save:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .btn-sm-outline {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.35rem 0.75rem;
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 7px;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.18s;
        }

        .btn-sm-outline:hover {
            border-color: #94a3b8;
            color: #334155;
        }

        .btn-sm-danger {
            border-color: rgba(239, 68, 68, 0.3);
            color: #dc2626;
        }

        .btn-sm-danger:hover {
            border-color: #dc2626;
            background: rgba(239, 68, 68, 0.05);
        }

        /* ─── Hero Section Preview ────────────────────────────── */
        .hero-preview-bar {
            background: linear-gradient(135deg, var(--primary, #6366f1) 0%, var(--accent, #8b5cf6) 100%);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .hero-preview-bar .preview-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.65);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.25rem;
        }

        .hero-preview-bar .preview-value {
            font-size: 1rem;
            font-weight: 800;
            color: white;
        }

        .hero-preview-badge {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 6px;
            padding: 0.375rem 0.75rem;
            font-size: 0.7rem;
            font-weight: 700;
            color: white;
        }

        /* ─── Empty State ─────────────────────────────────────── */
        .empty-dyn {
            text-align: center;
            padding: 1.5rem 1rem;
            color: #94a3b8;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .empty-dyn i {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            opacity: 0.4;
        }

        /* ─── Responsive ──────────────────────────────────────── */
        @media(max-width: 768px) {

            .form-grid-2,
            .form-grid-3 {
                grid-template-columns: 1fr;
            }

            .cfg-topbar {
                padding: 0.75rem 1rem;
            }

            .cfg-body {
                padding: 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content" style="padding: 0; background: #f8fafc;">
            <div class="cfg-page">

                <!-- Top Bar -->
                <div class="cfg-topbar">
                    <div class="cfg-topbar-left">
                        <div class="cfg-logo-badge">
                            <i class="fas fa-sliders-h"></i>
                        </div>
                        <div>
                            <div class="cfg-title">Config Manager</div>
                            <div class="cfg-subtitle">Website & public-facing content settings</div>
                        </div>
                    </div>
                    <div class="cfg-topbar-actions">
                        <button class="btn-sm-outline" onclick="window.open('<?= APP_URL ?>', '_blank')">
                            <i class="fas fa-external-link-alt"></i> Preview Site
                        </button>
                        <button class="btn-save" onclick="saveSettings()" id="saveBtn">
                            <i class="fas fa-cloud-upload-alt"></i> Publish Changes
                        </button>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="cfg-tabs">
                    <button class="cfg-tab active" onclick="switchTab('general', this)" id="tab-general">
                        <i class="fas fa-cog"></i> General
                    </button>
                    <button class="cfg-tab" onclick="switchTab('branding', this)" id="tab-branding">
                        <i class="fas fa-palette"></i> Branding & Hero
                    </button>
                    <button class="cfg-tab" onclick="switchTab('features', this)" id="tab-features">
                        <i class="fas fa-th-large"></i> Features
                    </button>
                    <button class="cfg-tab" onclick="switchTab('values', this)" id="tab-values">
                        <i class="fas fa-star"></i> Core Values
                    </button>
                    <button class="cfg-tab" onclick="switchTab('contact', this)" id="tab-contact">
                        <i class="fas fa-map-marker-alt"></i> Contact
                    </button>
                </div>

                <!-- Body -->
                <div class="cfg-body">

                    <!-- ══ GENERAL PANEL ══════════════════════════════════ -->
                    <div class="cfg-panel active" id="panel-general">
                        <div class="cfg-card">
                            <div class="cfg-card-header">
                                <div class="cfg-card-header-left">
                                    <div class="cfg-card-icon indigo"><i class="fas fa-info-circle"></i></div>
                                    <span class="cfg-card-title">Site Identity</span>
                                </div>
                            </div>
                            <div class="cfg-card-body">
                                <div class="form-grid-2">
                                    <div class="field-group">
                                        <label class="field-label">Site Name</label>
                                        <input id="site_name" class="field-input" type="text" placeholder="e.g. AikoCRM"
                                            value="">
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label">Tagline</label>
                                        <input id="site_tagline" class="field-input" type="text"
                                            placeholder="Short memorable tagline" value="">
                                    </div>
                                    <div class="field-group form-col-full">
                                        <label class="field-label">Site Description (SEO)</label>
                                        <textarea id="site_description" class="field-input"
                                            placeholder="A brief description of your platform shown in search results…"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="cfg-card">
                            <div class="cfg-card-header">
                                <div class="cfg-card-header-left">
                                    <div class="cfg-card-icon emerald"><i class="fas fa-share-alt"></i></div>
                                    <span class="cfg-card-title">Social Links</span>
                                </div>
                            </div>
                            <div class="cfg-card-body">
                                <div class="form-grid-2">
                                    <div class="field-group">
                                        <label class="field-label"><i class="fab fa-facebook"
                                                style="color:#1877f2;margin-right:4px;"></i>Facebook URL</label>
                                        <input id="social_facebook" class="field-input" type="url"
                                            placeholder="https://facebook.com/yourpage" value="">
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label"><i class="fab fa-twitter"
                                                style="color:#1da1f2;margin-right:4px;"></i>Twitter/X URL</label>
                                        <input id="social_twitter" class="field-input" type="url"
                                            placeholder="https://twitter.com/yourhandle" value="">
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label"><i class="fab fa-linkedin"
                                                style="color:#0a66c2;margin-right:4px;"></i>LinkedIn URL</label>
                                        <input id="social_linkedin" class="field-input" type="url"
                                            placeholder="https://linkedin.com/company/..." value="">
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label"><i class="fab fa-instagram"
                                                style="color:#e1306c;margin-right:4px;"></i>Instagram URL</label>
                                        <input id="social_instagram" class="field-input" type="url"
                                            placeholder="https://instagram.com/yourhandle" value="">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ BRANDING PANEL ════════════════════════════════ -->
                    <div class="cfg-panel" id="panel-branding">
                        <div class="cfg-card">
                            <div class="cfg-card-header">
                                <div class="cfg-card-header-left">
                                    <div class="cfg-card-icon violet"><i class="fas fa-image"></i></div>
                                    <span class="cfg-card-title">Logo, Favicon & Theme</span>
                                </div>
                            </div>

                            <!-- Logo Upload -->
                            <div class="logo-upload-area" style="border-bottom:1px solid #f1f5f9;">
                                <div class="logo-preview-box" id="logoPreviewBox"
                                    onclick="document.getElementById('logoFileInput').click()">
                                    <div class="logo-placeholder" id="logoPlaceholder">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Upload Logo</span>
                                    </div>
                                    <img id="logoPreviewImg" src="" style="display:none;" alt="Logo Preview">
                                </div>
                                <input type="file" id="logoFileInput" accept="image/*" style="display:none;"
                                    onchange="previewLogo(this)">
                                <div class="logo-upload-meta">
                                    <h4>Site Logo</h4>
                                    <p>Appears in the website header &amp; emails. Recommended: 200×60px PNG/SVG with
                                        transparent background.</p>
                                    <div class="logo-upload-actions">
                                        <button class="btn-sm-outline"
                                            onclick="document.getElementById('logoFileInput').click()">
                                            <i class="fas fa-upload"></i> Choose File
                                        </button>
                                        <button class="btn-sm-outline btn-sm-danger" onclick="clearLogo()"
                                            id="clearLogoBtn" style="display:none;">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Favicon Upload -->
                            <div class="logo-upload-area">
                                <div class="logo-preview-box" id="faviconPreviewBox"
                                    onclick="document.getElementById('faviconFileInput').click()">
                                    <div class="logo-placeholder" id="faviconPlaceholder">
                                        <i class="fas fa-star"></i>
                                        <span>Favicon</span>
                                    </div>
                                    <img id="faviconPreviewImg" src="" style="display:none;" alt="Favicon">
                                </div>
                                <input type="file" id="faviconFileInput" accept="image/*" style="display:none;"
                                    onchange="previewFavicon(this)">
                                <div class="logo-upload-meta">
                                    <h4>Favicon</h4>
                                    <p>Small icon shown in browser tabs. Recommended: 32×32px or 64×64px ICO/PNG format.
                                    </p>
                                    <div class="logo-upload-actions">
                                        <button class="btn-sm-outline"
                                            onclick="document.getElementById('faviconFileInput').click()">
                                            <i class="fas fa-upload"></i> Choose File
                                        </button>
                                        <button class="btn-sm-outline btn-sm-danger" onclick="clearFavicon()"
                                            id="clearFaviconBtn" style="display:none;">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Theme Color -->
                            <div class="logo-upload-area" style="border-top:1px solid #f1f5f9;">
                                <div class="logo-preview-box" id="themeColorBox"
                                    onclick="document.getElementById('theme_color').click()"
                                    style="background: #2563eb;">
                                    <i class="fas fa-palette" style="color: white; font-size: 1.5rem;"></i>
                                </div>
                                <input type="color" id="theme_color" style="opacity: 0; position: absolute; width: 0; height: 0; pointer-events: none;"
                                    oninput="document.getElementById('themeColorBox').style.background = this.value; document.getElementById('theme_color_hex').value = this.value;">
                                <div class="logo-upload-meta">
                                    <h4>Primary Theme Color</h4>
                                    <p>Select the primary color for your website's buttons, links, and main accents.</p>
                                    <div class="logo-upload-actions" style="align-items: center;">
                                        <div class="field-input" style="width: 120px; display: inline-flex; align-items: center; padding: 0.35rem 0.75rem;">
                                            <input type="text" id="theme_color_hex" value="#2563eb"
                                                style="border: none; outline: none; width: 100%; background: transparent; font-weight: 600; color: #1e293b; text-transform: uppercase;"
                                                oninput="let v = this.value; if(v.startsWith('#') && (v.length === 4 || v.length === 7)) { document.getElementById('theme_color').value = v; document.getElementById('themeColorBox').style.background = v; }">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Secondary Theme Color -->
                            <div class="logo-upload-area" style="border-top:1px solid #f1f5f9;">
                                <div class="logo-preview-box" id="themeSecondaryColorBox"
                                    onclick="document.getElementById('theme_color_secondary').click()"
                                    style="background: #06b6d4;">
                                    <i class="fas fa-fill-drip" style="color: white; font-size: 1.5rem;"></i>
                                </div>
                                <input type="color" id="theme_color_secondary" style="opacity: 0; position: absolute; width: 0; height: 0; pointer-events: none;"
                                    oninput="document.getElementById('themeSecondaryColorBox').style.background = this.value; document.getElementById('theme_color_secondary_hex').value = this.value;">
                                <div class="logo-upload-meta">
                                    <h4>Secondary (Accent) Color</h4>
                                    <p>Used for gradients, highlights, and secondary background tints.</p>
                                    <div class="logo-upload-actions" style="align-items: center;">
                                        <div class="field-input" style="width: 120px; display: inline-flex; align-items: center; padding: 0.35rem 0.75rem;">
                                            <input type="text" id="theme_color_secondary_hex" value="#06b6d4"
                                                style="border: none; outline: none; width: 100%; background: transparent; font-weight: 600; color: #1e293b; text-transform: uppercase;"
                                                oninput="let v = this.value; if(v.startsWith('#') && (v.length === 4 || v.length === 7)) { document.getElementById('theme_color_secondary').value = v; document.getElementById('themeSecondaryColorBox').style.background = v; }">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hero Section -->
                        <div class="cfg-card">
                            <div class="cfg-card-header">
                                <div class="cfg-card-header-left">
                                    <div class="cfg-card-icon sky"><i class="fas fa-home"></i></div>
                                    <span class="cfg-card-title">Hero Section (Home Page)</span>
                                </div>
                            </div>
                            <div class="cfg-card-body">
                                <div id="heroPreviewBar" class="hero-preview-bar">
                                    <div>
                                        <div class="preview-label">Hero Headline</div>
                                        <div class="preview-value" id="heroPreviewTitle">Your headline goes here</div>
                                    </div>
                                    <div class="hero-preview-badge" id="heroPreviewBadge">Live Preview</div>
                                </div>
                                <div class="form-grid-2">
                                    <div class="field-group">
                                        <label class="field-label">Hero Badge Text</label>
                                        <input id="hero_badge" class="field-input" type="text"
                                            placeholder="e.g. #1 Business Software" value="">
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label">CTA Button Text</label>
                                        <input id="hero_cta_text" class="field-input" type="text"
                                            placeholder="e.g. Start Free Trial" value="">
                                    </div>
                                    <div class="field-group form-col-full">
                                        <label class="field-label">Hero Headline</label>
                                        <input id="hero_title" class="field-input" type="text"
                                            placeholder="Main attention-grabbing headline" value=""
                                            oninput="document.getElementById('heroPreviewTitle').textContent = this.value || 'Your headline goes here'">
                                    </div>
                                    <div class="field-group form-col-full">
                                        <label class="field-label">Hero Sub-text</label>
                                        <textarea id="hero_subtext" class="field-input"
                                            placeholder="Supporting text beneath the headline…"></textarea>
                                    </div>
                                    <div class="field-group form-col-full">
                                        <label class="field-label">Hero CTA URL</label>
                                        <input id="hero_cta_url" class="field-input" type="text"
                                            placeholder="e.g. /register or https://..." value="">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ FEATURES PANEL ════════════════════════════════ -->
                    <div class="cfg-panel" id="panel-features">
                        <div class="cfg-card">
                            <div class="cfg-card-header">
                                <div class="cfg-card-header-left">
                                    <div class="cfg-card-icon amber"><i class="fas fa-th-large"></i></div>
                                    <span class="cfg-card-title">Feature Cards</span>
                                </div>
                                <button class="btn-cfg-add" onclick="addFeature()">
                                    <i class="fas fa-plus"></i> Add Feature
                                </button>
                            </div>
                            <div class="cfg-card-body">
                                <div class="dyn-list" id="featuresList"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ CORE VALUES PANEL ═════════════════════════════ -->
                    <div class="cfg-panel" id="panel-values">
                        <div class="cfg-card">
                            <div class="cfg-card-header">
                                <div class="cfg-card-header-left">
                                    <div class="cfg-card-icon emerald"><i class="fas fa-star"></i></div>
                                    <span class="cfg-card-title">Core Values</span>
                                </div>
                                <button class="btn-cfg-add" onclick="addCoreValue()">
                                    <i class="fas fa-plus"></i> Add Value
                                </button>
                            </div>
                            <div class="cfg-card-body">
                                <div class="dyn-list" id="valuesList"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ CONTACT PANEL ═════════════════════════════════ -->
                    <div class="cfg-panel" id="panel-contact">
                        <div class="cfg-card">
                            <div class="cfg-card-header">
                                <div class="cfg-card-header-left">
                                    <div class="cfg-card-icon rose"><i class="fas fa-envelope"></i></div>
                                    <span class="cfg-card-title">Contact Information</span>
                                </div>
                            </div>
                            <div class="cfg-card-body">
                                <div class="form-grid-2">
                                    <div class="field-group">
                                        <label class="field-label"><i class="fas fa-at"
                                                style="margin-right:4px;color:var(--primary, #6366f1);"></i>Contact Email</label>
                                        <input id="contact_email" class="field-input" type="email"
                                            placeholder="info@yourcompany.com" value="">
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label"><i class="fas fa-phone"
                                                style="margin-right:4px;color:#10b981;"></i>Contact Phone</label>
                                        <input id="contact_phone" class="field-input" type="text"
                                            placeholder="+91 00000 00000" value="">
                                    </div>
                                    <div class="field-group form-col-full">
                                        <label class="field-label"><i class="fas fa-map-marker-alt"
                                                style="margin-right:4px;color:#f59e0b;"></i>Office Address</label>
                                        <textarea id="contact_address" class="field-input"
                                            placeholder="Full office address…"></textarea>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label"><i class="fas fa-clock"
                                                style="margin-right:4px;color:#0ea5e9;"></i>Working Hours</label>
                                        <input id="contact_hours" class="field-input" type="text"
                                            placeholder="Mon–Fri, 9am–6pm" value="">
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label"><i class="fab fa-whatsapp"
                                                style="margin-right:4px;color:#25d366;"></i>WhatsApp Number</label>
                                        <input id="contact_whatsapp" class="field-input" type="text"
                                            placeholder="+91 00000 00000" value="">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /.cfg-body -->
            </div><!-- /.cfg-page -->
        </main>
    </div>

    <!-- Toast -->
    <div id="saveToast"><i class="fas fa-check-circle"></i> <span id="toastMsg">Saved!</span></div>

    <script>
        /* ── Data ──────────────────────────────────────────────────────── */
        let siteData = {};

        /* ── Load settings from DB via API ────────────────────────────── */
        async function loadSettings() {
            try {
                const res = await fetch('<?= APP_URL ?>/public/index.php/api/website_settings.php', {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' }
                });
                if (res.ok) {
                    const json = await res.json();
                    siteData = (json && typeof json === 'object' && !Array.isArray(json)) ? json : {};
                }
            } catch (e) {
                console.warn('Could not load settings from server:', e);
            }
            populateFields();
        }

        /* ── Tab Switching ─────────────────────────────────────────────── */
        function switchTab(name, btn) {
            document.querySelectorAll('.cfg-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.cfg-tab').forEach(t => t.classList.remove('active'));
            document.getElementById('panel-' + name).classList.add('active');
            btn.classList.add('active');
        }

        /* ── Populate general fields on load ──────────────────────────── */
        function populateFields() {
            const fields = [
                'site_name', 'site_tagline', 'site_description',
                'social_facebook', 'social_twitter', 'social_linkedin', 'social_instagram',
                'hero_badge', 'hero_cta_text', 'hero_title', 'hero_subtext', 'hero_cta_url',
                'contact_email', 'contact_phone', 'contact_address', 'contact_hours', 'contact_whatsapp',
                'theme_color_hex', 'theme_color_secondary_hex'
            ];
            fields.forEach(id => {
                const el = document.getElementById(id);
                if (el && siteData[id] !== undefined) {
                    el.value = siteData[id] || '';
                }
            });

            // Hero preview
            const ht = document.getElementById('heroPreviewTitle');
            const hb = document.getElementById('heroPreviewBadge');
            if (ht && siteData.hero_title) ht.textContent = siteData.hero_title;
            if (hb && siteData.hero_badge) hb.textContent = siteData.hero_badge;

            // Logo
            if (siteData.logo_url) {
                showLogo(siteData.logo_url);
            }
            // Favicon
            if (siteData.favicon_url) {
                showFavicon(siteData.favicon_url);
            }
            // Theme Color
            if (siteData.theme_color_hex) {
                const hex = siteData.theme_color_hex;
                const tc = document.getElementById('theme_color');
                const tcb = document.getElementById('themeColorBox');
                if (tc) tc.value = hex;
                if (tcb) tcb.style.background = hex;
            }
            if (siteData.theme_color_secondary_hex) {
                const hex = siteData.theme_color_secondary_hex;
                const tc = document.getElementById('theme_color_secondary');
                const tcb = document.getElementById('themeSecondaryColorBox');
                if (tc) tc.value = hex;
                if (tcb) tcb.style.background = hex;
            }

            renderFeatures();
            renderValues();
        }

        /* ── Collect fields back ───────────────────────────────────────── */
        function collectFields() {
            const fields = [
                'site_name', 'site_tagline', 'site_description',
                'social_facebook', 'social_twitter', 'social_linkedin', 'social_instagram',
                'hero_badge', 'hero_cta_text', 'hero_title', 'hero_subtext', 'hero_cta_url',
                'contact_email', 'contact_phone', 'contact_address', 'contact_hours', 'contact_whatsapp',
                'theme_color_hex', 'theme_color_secondary_hex'
            ];
            fields.forEach(id => {
                const el = document.getElementById(id);
                if (el) siteData[id] = el.value;
            });
        }

        /* ── Logo Handling ─────────────────────────────────────────────── */
        function showLogo(src) {
            const img = document.getElementById('logoPreviewImg');
            const ph = document.getElementById('logoPlaceholder');
            const clr = document.getElementById('clearLogoBtn');
            img.src = src; img.style.display = 'block';
            ph.style.display = 'none';
            clr.style.display = '';
            siteData.logo_url = src;
        }
        function clearLogo() {
            const img = document.getElementById('logoPreviewImg');
            const ph = document.getElementById('logoPlaceholder');
            const clr = document.getElementById('clearLogoBtn');
            img.style.display = 'none'; ph.style.display = '';
            clr.style.display = 'none';
            siteData.logo_url = '';
            document.getElementById('logoFileInput').value = '';
        }
        function previewLogo(input) {
            if (!input.files || !input.files[0]) return;
            const reader = new FileReader();
            reader.onload = e => showLogo(e.target.result);
            reader.readAsDataURL(input.files[0]);
        }
        function previewFavicon(input) {
            if (!input.files || !input.files[0]) return;
            const reader = new FileReader();
            reader.onload = e => showFavicon(e.target.result);
            reader.readAsDataURL(input.files[0]);
        }
        function showFavicon(src) {
            const img = document.getElementById('faviconPreviewImg');
            const ph = document.getElementById('faviconPlaceholder');
            const clr = document.getElementById('clearFaviconBtn');
            img.src = src; img.style.display = 'block';
            ph.style.display = 'none';
            if(clr) clr.style.display = '';
            siteData.favicon_url = src;
        }
        function clearFavicon() {
            const img = document.getElementById('faviconPreviewImg');
            const ph = document.getElementById('faviconPlaceholder');
            const clr = document.getElementById('clearFaviconBtn');
            img.style.display = 'none'; ph.style.display = '';
            if(clr) clr.style.display = 'none';
            siteData.favicon_url = '';
            document.getElementById('faviconFileInput').value = '';
        }

        /* ── Features ──────────────────────────────────────────────────── */
        function renderFeatures() {
            const list = document.getElementById('featuresList');
            const arr = siteData.features || [];
            if (!arr.length) {
                list.innerHTML = '<div class="empty-dyn"><i class="fas fa-th-large"></i>No features yet. Click "+ Add Feature" to get started.</div>';
                return;
            }
            list.innerHTML = arr.map((f, i) => `
        <div class="dyn-item">
            <div class="dyn-drag"><i class="fas fa-grip-vertical"></i></div>
            <div class="dyn-item-fields">
                <div style="display:flex;gap:0.5rem;">
                    <div class="field-group" style="flex:0 0 180px;">
                        <label class="field-label">Icon Class</label>
                        <input class="field-input" style="font-size:0.775rem;" value="${esc(f.icon || '')}" placeholder="fa-solid fa-star" oninput="siteData.features[${i}].icon=this.value">
                    </div>
                    <div class="field-group" style="flex:1;">
                        <label class="field-label">Title</label>
                        <input class="field-input" value="${esc(f.title || '')}" placeholder="Feature name" oninput="siteData.features[${i}].title=this.value">
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">Description</label>
                    <textarea class="field-input" style="min-height:56px;" placeholder="Brief description of this feature…" oninput="siteData.features[${i}].description=this.value">${esc(f.description || '')}</textarea>
                </div>
            </div>
            <button class="btn-icon-del" onclick="removeFeature(${i})" title="Remove"><i class="fas fa-trash"></i></button>
        </div>
    `).join('');
        }
        function addFeature() {
            if (!siteData.features) siteData.features = [];
            siteData.features.push({ icon: 'fa-solid fa-star', title: 'New Feature', description: '' });
            renderFeatures();
            document.getElementById('featuresList').lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        function removeFeature(i) {
            siteData.features.splice(i, 1);
            renderFeatures();
        }

        /* ── Core Values ───────────────────────────────────────────────── */
        function renderValues() {
            const list = document.getElementById('valuesList');
            const arr = siteData.core_values || [];
            if (!arr.length) {
                list.innerHTML = '<div class="empty-dyn"><i class="fas fa-star"></i>No core values yet. Click "+ Add Value" to get started.</div>';
                return;
            }
            list.innerHTML = arr.map((v, i) => `
        <div class="dyn-item">
            <div class="dyn-drag"><i class="fas fa-grip-vertical"></i></div>
            <div class="dyn-item-fields">
                <div class="field-group">
                    <label class="field-label">Value Title</label>
                    <input class="field-input" value="${esc(v.title || '')}" placeholder="e.g. Customer First" oninput="siteData.core_values[${i}].title=this.value">
                </div>
                <div class="field-group">
                    <label class="field-label">Description</label>
                    <textarea class="field-input" style="min-height:56px;" placeholder="What this value means to your company…" oninput="siteData.core_values[${i}].description=this.value">${esc(v.description || '')}</textarea>
                </div>
            </div>
            <button class="btn-icon-del" onclick="removeCoreValue(${i})" title="Remove"><i class="fas fa-trash"></i></button>
        </div>
    `).join('');
        }
        function addCoreValue() {
            if (!siteData.core_values) siteData.core_values = [];
            siteData.core_values.push({ title: 'New Value', description: '' });
            renderValues();
            document.getElementById('valuesList').lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        function removeCoreValue(i) {
            siteData.core_values.splice(i, 1);
            renderValues();
        }

        /* ── Utility ───────────────────────────────────────────────────── */
        function esc(str) {
            return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function showToast(msg, type = 'success') {
            const t = document.getElementById('saveToast');
            const m = document.getElementById('toastMsg');
            t.className = 'show ' + type;
            m.textContent = msg;
            setTimeout(() => { t.className = ''; }, 3500);
        }

        /* ── Save ──────────────────────────────────────────────────────── */
        async function saveSettings() {
            collectFields();
            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing…';

            try {
                const res = await fetch('<?= APP_URL ?>/public/index.php/api/website_settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(siteData)
                });
                const result = await res.json();
                if (result.success) {
                    showToast('Changes published successfully!', 'success');
                } else {
                    showToast(result.error || 'Failed to save settings', 'error');
                }
            } catch (err) {
                showToast('Network error: ' + err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Publish Changes';
            }
        }

        /* ── Init ──────────────────────────────────────────────────────── */
        document.addEventListener('DOMContentLoaded', loadSettings);
    </script>
</body>

</html>