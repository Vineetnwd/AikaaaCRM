<?php
use Core\Auth;
if (!Auth::isSuperAdmin()) {
    header("Location: " . APP_URL . "/public/index.php/dashboard");
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
    <title>Plan Management | Super Admin</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ─── Page Chrome ─────────────────────────────────────── */
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
        .cfg-topbar-left { display: flex; align-items: center; gap: 0.875rem; }
        .cfg-logo-badge {
            width: 36px; height: 36px; border-radius: 8px;
            background: linear-gradient(135deg,var(--primary, #6366f1),var(--accent, #8b5cf6));
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 0.9rem; flex-shrink: 0;
        }
        .cfg-title { font-size: 1rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; }
        .cfg-subtitle { font-size: 0.75rem; color: #94a3b8; font-weight: 500; }
        .cfg-topbar-actions { display: flex; align-items: center; gap: 0.625rem; }

        /* ─── Toast ───────────────────────────────────────────── */
        #saveToast {
            position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
            background: #0f172a; color: white;
            padding: 0.6rem 1.25rem; border-radius: 10px;
            font-size: 0.8125rem; font-weight: 600;
            display: flex; align-items: center; gap: 0.5rem;
            box-shadow: 0 8px 24px rgba(0,0,0,0.18);
            transform: translateY(80px); opacity: 0;
            transition: all 0.35s cubic-bezier(0.34,1.56,0.64,1);
            pointer-events: none;
        }
        #saveToast.show { transform: translateY(0); opacity: 1; }
        #saveToast.success { background: #059669; }
        #saveToast.error { background: #dc2626; }

        /* ─── Body ────────────────────────────────────────────── */
        .plan-body { padding: 1.5rem; background: #f8fafc; min-height: calc(100vh - 65px); }

        /* ─── Toolbar ─────────────────────────────────────────── */
        .plan-toolbar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1.25rem;
        }
        .plan-toolbar-title { font-size: 0.875rem; font-weight: 700; color: #475569; }
        .plan-count-badge {
            display: inline-flex; align-items: center; gap: 0.25rem;
            background: rgba(99,102,241,0.1); color: var(--primary, #6366f1);
            padding: 0.2rem 0.625rem; border-radius: 20px;
            font-size: 0.72rem; font-weight: 700; margin-left: 0.5rem;
        }

        /* ─── Plan Grid ───────────────────────────────────────── */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }

        /* ─── Plan Card ───────────────────────────────────────── */
        .plan-card {
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            transition: box-shadow 0.2s, transform 0.2s, border-color 0.2s;
            position: relative;
        }
        .plan-card:hover {
            border-color: #c7d2fe;
            box-shadow: 0 8px 24px rgba(99,102,241,0.1);
            transform: translateY(-2px);
        }

        .plan-card-header {
            background: linear-gradient(135deg, var(--accent, var(--primary, #6366f1)) 0%, var(--accent-hover, var(--accent, #8b5cf6)) 100%);
            padding: 1rem 1.25rem 0.875rem;
            display: flex; align-items: center; justify-content: space-between;
        }
        .plan-card-header-label {
            font-size: 0.65rem; font-weight: 700;
            color: rgba(255,255,255,0.65); text-transform: uppercase; letter-spacing: 0.08em;
            margin-bottom: 0.2rem;
        }
        .plan-card-name-input {
            background: transparent; border: none; outline: none;
            color: white; font-size: 1.125rem; font-weight: 800;
            font-family: inherit; width: 100%; letter-spacing: -0.02em;
        }
        .plan-card-name-input::placeholder { color: rgba(255,255,255,0.4); }
        .btn-plan-del {
            width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
            background: rgba(255,255,255,0.15); border: none; color: rgba(255,255,255,0.7);
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; transition: all 0.18s;
        }
        .btn-plan-del:hover { background: rgba(239,68,68,0.8); color: white; }

        .plan-card-body { padding: 1.25rem; }

        .plan-row { display: flex; gap: 0.75rem; margin-bottom: 0.875rem; }
        .plan-row:last-child { margin-bottom: 0; }

        .field-group { display: flex; flex-direction: column; gap: 0.375rem; flex: 1; }
        .field-label {
            font-size: 0.68rem; font-weight: 700; color: #64748b;
            text-transform: uppercase; letter-spacing: 0.06em;
        }
        .field-input {
            width: 100%; padding: 0.5rem 0.75rem;
            border: 1.5px solid #e2e8f0; border-radius: 8px;
            font-size: 0.8125rem; font-weight: 500; color: #0f172a;
            background: #f8fafc; font-family: inherit;
            transition: all 0.2s;
        }
        .field-input:focus {
            outline: none; border-color: var(--primary, #6366f1); background: white;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
        }
        select.field-input { cursor: pointer; }
        textarea.field-input { resize: vertical; min-height: 80px; line-height: 1.5; }

        .price-wrap { position: relative; }
        .price-prefix {
            position: absolute; left: 0.75rem; top: 50%;
            transform: translateY(-50%); color: #94a3b8; font-weight: 700;
            font-size: 0.9rem; pointer-events: none;
        }
        .price-wrap .field-input { padding-left: 1.5rem; }

        .features-hint {
            font-size: 0.68rem; color: #94a3b8; font-weight: 500; margin-top: 0.2rem;
        }

        /* ─── Add Tier Card ───────────────────────────────────── */
        .plan-card-add {
            border: 2px dashed #cbd5e1; background: transparent;
            border-radius: 14px; cursor: pointer;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 0.5rem; min-height: 200px;
            color: #94a3b8; font-size: 0.875rem; font-weight: 600;
            transition: all 0.2s;
        }
        .plan-card-add:hover { border-color: var(--primary, #6366f1); color: var(--primary, #6366f1); background: rgba(99,102,241,0.03); }
        .plan-card-add i { font-size: 1.5rem; opacity: 0.6; }

        /* ─── Buttons ─────────────────────────────────────────── */
        .btn-save {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.5rem 1.25rem;
            background: linear-gradient(135deg,var(--primary, #6366f1),var(--primary-hover, #4f46e5));
            color: white; border: none; border-radius: 8px;
            font-size: 0.8125rem; font-weight: 700; cursor: pointer;
            font-family: inherit;
            box-shadow: 0 2px 8px rgba(99,102,241,0.35);
            transition: all 0.2s;
        }
        .btn-save:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(99,102,241,0.45);
        }
        .btn-save:disabled { opacity: 0.65; cursor: not-allowed; }

        /* ─── Empty State ─────────────────────────────────────── */
        .empty-plans {
            text-align: center; padding: 3rem 1rem;
            color: #94a3b8; font-size: 0.875rem;
        }
        .empty-plans i { display: block; font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.3; }

        @media(max-width:768px) {
            .plans-grid { grid-template-columns: 1fr; }
            .plan-body { padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'partials/sidebar.php'; ?>

    <main class="main-content" style="padding:0; background:#f8fafc;">

        <!-- Top Bar -->
        <div class="cfg-topbar">
            <div class="cfg-topbar-left">
                <div class="cfg-logo-badge">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div>
                    <div class="cfg-title">Plan Management</div>
                    <div class="cfg-subtitle">Configure subscription tiers and pricing</div>
                </div>
            </div>
            <div class="cfg-topbar-actions">
                <button class="btn-save" onclick="saveSettings()" id="saveBtn">
                    <i class="fas fa-cloud-upload-alt"></i> Save Plans
                </button>
            </div>
        </div>

        <!-- Body -->
        <div class="plan-body">
            <div class="plan-toolbar">
                <div class="plan-toolbar-title">
                    Subscription Tiers
                    <span class="plan-count-badge" id="planCount">0 plans</span>
                </div>
            </div>

            <div class="plans-grid" id="plansGrid"></div>
        </div>
    </main>
</div>

<!-- Toast -->
<div id="saveToast"><i class="fas fa-check-circle"></i> <span id="toastMsg">Saved!</span></div>

<script>
let siteData = {};

async function loadPlans() {
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
        console.warn('Could not load plans:', e);
    }
    renderPlans();
}

function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function updatePlanCount() {
    const c = (siteData.pricing_plans||[]).length;
    document.getElementById('planCount').textContent = c + (c===1?' plan':' plans');
}

function renderPlans() {
    const grid = document.getElementById('plansGrid');
    const arr = siteData.pricing_plans || [];

    const cards = arr.map((p, i) => `
        <div class="plan-card" id="plan-card-${i}">
            <div class="plan-card-header">
                <div style="flex:1;min-width:0;">
                    <div class="plan-card-header-label">Tier Name</div>
                    <input class="plan-card-name-input" type="text" value="${esc(p.name)}" placeholder="Plan name"
                        oninput="siteData.pricing_plans[${i}].name=this.value">
                </div>
                <button class="btn-plan-del" onclick="removePlan(${i})" title="Delete plan">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>

            <div class="plan-card-body">
                <!-- Price + Duration -->
                <div class="plan-row">
                    <div class="field-group" style="flex:1.2;">
                        <label class="field-label">Price (₹)</label>
                        <div class="price-wrap">
                            <span class="price-prefix">₹</span>
                            <input type="number" class="field-input" value="${esc(p.price)}" placeholder="0"
                                oninput="siteData.pricing_plans[${i}].price=this.value">
                        </div>
                    </div>
                    <div class="field-group" style="flex:0.8;">
                        <label class="field-label">Duration</label>
                        <input type="number" class="field-input" value="${esc(p.duration_value)}" placeholder="1"
                            oninput="updateDuration(${i},'duration_value',this.value)">
                    </div>
                    <div class="field-group" style="flex:1;">
                        <label class="field-label">Unit</label>
                        <select class="field-input" onchange="updateDuration(${i},'duration_unit',this.value)">
                            <option value="Days" ${p.duration_unit==='Days'?'selected':''}>Days</option>
                            <option value="Months" ${p.duration_unit==='Months'||!p.duration_unit?'selected':''}>Months</option>
                            <option value="Years" ${p.duration_unit==='Years'?'selected':''}>Years</option>
                        </select>
                    </div>
                </div>

                <!-- Features -->
                <div class="field-group">
                    <label class="field-label">Included Features</label>
                    <textarea class="field-input" placeholder="One feature per line, or comma-separated…"
                        oninput="siteData.pricing_plans[${i}].features=this.value.split(/[,\\n]/).map(s=>s.trim()).filter(Boolean)">${(p.features||[]).join('\n')}</textarea>
                    <div class="features-hint"><i class="fas fa-info-circle"></i> Separate with commas or new lines</div>
                </div>
            </div>
        </div>
    `).join('');

    grid.innerHTML = cards + `
        <div class="plan-card-add" onclick="addPlan()" role="button" tabindex="0" onkeypress="if(event.key==='Enter')addPlan()">
            <i class="fas fa-plus-circle"></i>
            Add New Tier
        </div>
    `;
    updatePlanCount();
}

function updateDuration(i, field, value) {
    siteData.pricing_plans[i][field] = value;
    const v = siteData.pricing_plans[i].duration_value || '';
    const u = siteData.pricing_plans[i].duration_unit || 'Months';
    siteData.pricing_plans[i].duration = v ? `/ ${v} ${u}`.trim() : '';
}

function addPlan() {
    if (!siteData.pricing_plans) siteData.pricing_plans = [];
    siteData.pricing_plans.push({
        name: 'New Plan',
        price: '0',
        duration_value: '1',
        duration_unit: 'Months',
        duration: '/ 1 Months',
        features: [],
        button_text: 'Get Started',
        button_class: 'btn-primary',
        button_url: '/signup'
    });
    renderPlans();
    // Scroll to new card (second-to-last element in grid)
    const cards = document.querySelectorAll('.plan-card');
    cards[cards.length-1]?.scrollIntoView({behavior:'smooth', block:'nearest'});
}

function removePlan(i) {
    if (!confirm('Remove this plan?')) return;
    siteData.pricing_plans.splice(i, 1);
    renderPlans();
}

function showToast(msg, type='success') {
    const t = document.getElementById('saveToast');
    const m = document.getElementById('toastMsg');
    t.className = 'show ' + type;
    m.textContent = msg;
    setTimeout(() => { t.className = ''; }, 3500);
}

async function saveSettings() {
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

    try {
        const res = await fetch('<?= APP_URL ?>/public/index.php/api/website_settings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(siteData)
        });
        const result = await res.json();
        if (result.success) {
            showToast('Plans saved successfully!', 'success');
        } else {
            showToast(result.error || 'Failed to save plans', 'error');
        }
    } catch (err) {
        showToast('Network error: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Save Plans';
    }
}

// Init
document.addEventListener('DOMContentLoaded', loadPlans);
</script>
</body>
</html>
