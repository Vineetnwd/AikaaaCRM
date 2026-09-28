<?php
use Core\Auth;
$company = Auth::company();
$isAuthorized = Auth::isAdmin() || Auth::isSuperAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | <?= $company['name'] ?? 'Aikaa CRM' ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-card { background: white; border-radius: 1rem; border: 1px solid var(--border); padding: 1.5rem; }
        .logo-preview { width: 120px; height: 120px; border-radius: 0.75rem; border: 2px dashed #e2e8f0; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f8fafc; cursor: pointer; position: relative; }
        .logo-preview img { width: 100%; height: 100%; object-fit: contain; }
        .logo-preview:hover { border-color: var(--primary); background: #f1f5f9; }
        .logo-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.4); color: white; display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.2s; }
        .logo-preview:hover .logo-overlay { opacity: 1; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div>
                    <h1 class="page-title">Enterprise Settings</h1>
                    <p style="color: var(--text-muted); font-size: 0.8125rem; font-weight: 500;">Manage branding and core business identity</p>
                </div>
            </header>

            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 1.5rem; align-items: start;">
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <div class="settings-card">
                        <h3 style="font-size: 0.9375rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-building" style="color: var(--primary);"></i> Company Profile
                        </h3>
                        
                        <div style="display: flex; gap: 2rem; align-items: flex-start; margin-bottom: 1.5rem;">
                            <div>
                                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; text-transform: uppercase;">Company Logo</label>
                                <div class="logo-preview" id="logo_container" onclick="document.getElementById('logoInput').click()">
                                    <?php if (!empty($company['logo_path'])): ?>
                                        <img src="<?= APP_URL ?>/public/<?= $company['logo_path'] ?>" id="logoImg">
                                    <?php else: ?>
                                        <i class="fas fa-cloud-upload-alt" style="font-size: 1.5rem; color: #94a3b8;" id="logoIcon"></i>
                                    <?php endif; ?>
                                    <div class="logo-overlay" id="logo_overlay"><i class="fas fa-camera"></i></div>
                                </div>
                                <input type="file" id="logoInput" style="display:none" accept="image/*" onchange="uploadAsset(this, 'logo')">
                                <p style="font-size: 0.6rem; color: #94a3b8; margin-top: 0.5rem; text-align: center;">Recommended: Square SVG or PNG</p>
                            </div>

                            <div>
                                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; text-transform: uppercase;">Signature</label>
                                <div class="logo-preview" id="signature_container" onclick="document.getElementById('signatureInput').click()">
                                    <?php if (!empty($company['signature_path'])): ?>
                                        <img src="<?= APP_URL ?>/public/<?= $company['signature_path'] ?>" id="signatureImg">
                                    <?php else: ?>
                                        <i class="fas fa-signature" style="font-size: 1.5rem; color: #94a3b8;" id="signatureIcon"></i>
                                    <?php endif; ?>
                                    <div class="logo-overlay" id="signature_overlay"><i class="fas fa-camera"></i></div>
                                </div>
                                <input type="file" id="signatureInput" style="display:none" accept="image/*" onchange="uploadAsset(this, 'signature')">
                                <p style="font-size: 0.6rem; color: #94a3b8; margin-top: 0.5rem; text-align: center;">Transparent PNG</p>
                            </div>

                            <div>
                                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; text-transform: uppercase;">Payment QR</label>
                                <div class="logo-preview" id="payment_qr_container" onclick="document.getElementById('qrInput').click()">
                                    <?php if (!empty($company['qr_code_path'])): ?>
                                        <img src="<?= APP_URL ?>/public/<?= $company['qr_code_path'] ?>" id="qrImg">
                                    <?php else: ?>
                                        <i class="fas fa-qrcode" style="font-size: 1.5rem; color: #94a3b8;" id="qrIcon"></i>
                                    <?php endif; ?>
                                    <div class="logo-overlay" id="payment_qr_overlay"><i class="fas fa-camera"></i></div>
                                </div>
                                <input type="file" id="qrInput" style="display:none" accept="image/*" onchange="uploadAsset(this, 'payment_qr')">
                                <p style="font-size: 0.6rem; color: #94a3b8; margin-top: 0.5rem; text-align: center;">Square QR Image</p>
                            </div>

                            <div style="flex: 1; display: flex; flex-direction: column; gap: 1rem;">
                                <div>
                                    <label>Company Display Name</label>
                                    <input type="text" id="comp_name" value="<?= htmlspecialchars($company['name']) ?>" class="form-input" <?= !$isAuthorized ? 'disabled' : '' ?>>
                                </div>
                                <div>
                                    <label>GSTIN / Tax ID</label>
                                    <input type="text" id="comp_gst" value="<?= htmlspecialchars($company['gst_number'] ?? '') ?>" placeholder="e.g. 29AAAAA0000A1Z5" class="form-input" <?= !$isAuthorized ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <div style="margin-bottom: 1.5rem;">
                            <label>Registered Business Address</label>
                            <textarea id="comp_address" class="form-input" style="height: 80px; resize: none;" <?= !$isAuthorized ? 'disabled' : '' ?>><?= htmlspecialchars($company['address'] ?? '') ?></textarea>
                        </div>

                        <div style="margin-bottom: 1.5rem;">
                            <label>Bank Details</label>
                            <textarea id="bank_details" class="form-input" style="height: 80px; resize: none;" <?= !$isAuthorized ? 'disabled' : '' ?> placeholder="Bank Name: XYZ Bank&#10;Account No: 123456789&#10;IFSC: XYZB0001234"><?= htmlspecialchars($company['bank_details'] ?? '') ?></textarea>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div>
                                <label>Invoice Terms & Conditions</label>
                                <textarea id="invoice_terms" class="form-input" style="height: 120px; resize: none; font-size: 0.8125rem;" <?= !$isAuthorized ? 'disabled' : '' ?> placeholder="Enter default invoice terms..."><?= htmlspecialchars($company['invoice_terms'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label>Quotation Terms & Conditions</label>
                                <textarea id="quotation_terms" class="form-input" style="height: 120px; resize: none; font-size: 0.8125rem;" <?= !$isAuthorized ? 'disabled' : '' ?> placeholder="Enter default quotation terms..."><?= htmlspecialchars($company['quotation_terms'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <?php if ($isAuthorized): ?>
                        <div style="display:flex; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid var(--border);">
                            <button class="btn btn-primary" onclick="updateProfile()" id="saveBtn">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="settings-card" style="margin-top: 1.5rem;">
                        <h3 style="font-size: 0.9375rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
                            <span style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-envelope-open-text" style="color: var(--primary);"></i> SMTP Mail Configuration
                            </span>
                            <span class="badge" style="background: #e0f2fe; color: #0369a1; font-size: 0.6rem; font-weight: 800;">Outgoing Mail</span>
                        </h3>
                        
                        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                            <div>
                                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.375rem; text-transform: uppercase;">SMTP Host</label>
                                <input type="text" id="smtp_host" value="<?= htmlspecialchars($company['smtp_host'] ?? '') ?>" placeholder="e.g. smtp.gmail.com" class="form-input" style="margin: 0;" <?= !$isAuthorized ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.375rem; text-transform: uppercase;">SMTP Port</label>
                                <input type="number" id="smtp_port" value="<?= htmlspecialchars($company['smtp_port'] ?? '587') ?>" placeholder="e.g. 587" class="form-input" style="margin: 0;" <?= !$isAuthorized ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.375rem; text-transform: uppercase;">Encryption</label>
                                <select id="smtp_secure" class="form-input" style="margin: 0; padding-top: 0.375rem; padding-bottom: 0.375rem;" <?= !$isAuthorized ? 'disabled' : '' ?>>
                                    <option value="tls" <?= ($company['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (STARTTLS)</option>
                                    <option value="ssl" <?= ($company['smtp_secure'] ?? 'tls') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="none" <?= ($company['smtp_secure'] ?? 'tls') === 'none' ? 'selected' : '' ?>>None (Plain)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                            <div>
                                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.375rem; text-transform: uppercase;">Sender Email / Username</label>
                                <input type="email" id="smtp_email" value="<?= htmlspecialchars($company['smtp_email'] ?? '') ?>" placeholder="e.g. billing@company.com" class="form-input" style="margin: 0;" <?= !$isAuthorized ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.375rem; text-transform: uppercase;">SMTP Password</label>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <input type="password" id="smtp_password" value="<?= htmlspecialchars($company['smtp_password'] ?? '') ?>" placeholder="Enter sender account password" class="form-input" style="margin: 0; padding-right: 2.5rem; width: 100%;" <?= !$isAuthorized ? 'disabled' : '' ?>>
                                    <button type="button" onclick="toggleSMTPPasswordVisibility()" style="position: absolute; right: 0.75rem; background: none; border: none; cursor: pointer; color: #94a3b8; outline: none; transition: 0.2s;" id="smtpPassToggle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($isAuthorized): ?>
                        <div style="display:flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid var(--border);">
                            <button class="btn btn-ghost" onclick="testSMTPConnection()" id="testSMTPBtn" style="border: 1.5px solid #e2e8f0; font-weight: 700; font-size: 0.75rem; color: #475569;">
                                <i class="fas fa-plug-circle-bolt" style="color: var(--primary, #6366f1);"></i> Test Connection
                            </button>
                            <button class="btn btn-primary" onclick="updateSMTPConfig()" id="saveSMTPBtn" style="font-weight: 700;">
                                <i class="fas fa-save"></i> Save Mail Settings
                            </button>
                        </div>
                        <?php endif; ?>

                        <!-- Trace logs display panel -->
                        <div id="smtpLogsPanel" style="display: none; margin-top: 1.25rem; background: #0f172a; border-radius: 0.75rem; padding: 1rem; font-family: monospace; font-size: 0.7rem; color: #e2e8f0; max-height: 250px; overflow-y: auto; border: 1px solid #1e293b; box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);">
                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #1e293b; padding-bottom: 0.5rem; margin-bottom: 0.5rem; color: #94a3b8; font-weight: 700; font-family: inherit;">
                                <span>SMTP COMMUNICATION LOGS</span>
                                <button type="button" onclick="document.getElementById('smtpLogsPanel').style.display='none'" style="background: none; border: none; color: #f87171; cursor: pointer; font-weight: 800; font-size: 0.65rem;">CLOSE</button>
                            </div>
                            <pre id="smtpLogsContent" style="margin: 0; white-space: pre-wrap; font-family: inherit; line-height: 1.5; color: #38bdf8;"></pre>
                        </div>
                    </div>

                    <div class="settings-card" style="margin-top: 1.5rem;">
                        <h3 style="font-size: 0.9375rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
                            <span style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fab fa-whatsapp" style="color: #25D366;"></i> WhatsApp API Integration
                            </span>
                            <span class="badge" style="background: #dcfce7; color: #16a34a; font-size: 0.6rem; font-weight: 800;">Cloud API</span>
                        </h3>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                            <div>
                                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.375rem; text-transform: uppercase;">WhatsApp Number</label>
                                <input type="text" id="wa_number" value="<?= htmlspecialchars($company['whatsapp_number'] ?? '') ?>" placeholder="e.g. +91 9876543210" class="form-input" style="margin: 0;" <?= !$isAuthorized ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.375rem; text-transform: uppercase;">Phone Number ID</label>
                                <input type="text" id="wa_phone_number_id" value="<?= htmlspecialchars($company['whatsapp_phone_number_id'] ?? '') ?>" placeholder="e.g. 104561842345678" class="form-input" style="margin: 0;" <?= !$isAuthorized ? 'disabled' : '' ?>>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                            <div>
                                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.375rem; text-transform: uppercase;">Business Account ID</label>
                                <input type="text" id="wa_business_account_id" value="<?= htmlspecialchars($company['whatsapp_business_account_id'] ?? '') ?>" placeholder="e.g. 103456789012345" class="form-input" style="margin: 0;" <?= !$isAuthorized ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.375rem; text-transform: uppercase;">Access Token</label>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <input type="password" id="wa_access_token" value="<?= htmlspecialchars($company['whatsapp_access_token'] ?? '') ?>" placeholder="Enter Meta API Access Token" class="form-input" style="margin: 0; padding-right: 2.5rem; width: 100%;" <?= !$isAuthorized ? 'disabled' : '' ?>>
                                    <button type="button" onclick="toggleWAPasswordVisibility()" style="position: absolute; right: 0.75rem; background: none; border: none; cursor: pointer; color: #94a3b8; outline: none; transition: 0.2s;" id="waPassToggle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div style="border-top: 1px dashed var(--border); padding-top: 1.25rem; margin-bottom: 1.5rem;">
                            <h4 style="font-size: 0.8rem; font-weight: 800; color: #475569; margin-bottom: 1rem;"><i class="fas fa-layer-group" style="margin-right: 0.3rem;"></i> Template Configuration</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 1rem;">
                                <div>
                                    <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.375rem; text-transform: uppercase;">Default Template Name</label>
                                    <input type="text" id="wa_default_template" value="<?= htmlspecialchars($company['whatsapp_default_template'] ?? '') ?>" placeholder="e.g. info_update779" class="form-input" style="margin: 0;" <?= !$isAuthorized ? 'disabled' : '' ?>>
                                </div>
                                <div>
                                    <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.375rem; text-transform: uppercase;">Variables Mapping (comma separated)</label>
                                    <input type="text" id="wa_default_mapping" value="<?= htmlspecialchars($company['whatsapp_default_mapping'] ?? '') ?>" placeholder="e.g. name, service, mobile" class="form-input" style="margin: 0;" <?= !$isAuthorized ? 'disabled' : '' ?>>
                                    <p style="font-size: 0.6rem; color: #94a3b8; margin-top: 0.25rem;">Available variables: <code>name</code>, <code>mobile</code>, <code>service</code>, <code>status</code>, <code>address</code>, <code>email</code>, <code>link</code>, <code>msg</code>, <code>msg2</code>, <code>value</code></p>
                                </div>
                                <div style="grid-column: 1 / -1;">
                                    <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.375rem; text-transform: uppercase;">Header Image URL (Optional)</label>
                                    <input type="text" id="wa_header_image" value="<?= htmlspecialchars($company['whatsapp_header_image'] ?? '') ?>" placeholder="e.g. https://example.com/image.jpg" class="form-input" style="margin: 0;" <?= !$isAuthorized ? 'disabled' : '' ?>>
                                    <p style="font-size: 0.6rem; color: #94a3b8; margin-top: 0.25rem;">If your template has an Image header, paste the public image URL here.</p>
                                </div>
                            </div>
                        </div>

                        <?php if ($isAuthorized): ?>
                        <div style="display:flex; justify-content: flex-end; align-items: center; padding-top: 1rem; border-top: 1px solid var(--border);">
                            <button class="btn btn-primary" onclick="updateWhatsAppConfig()" id="saveWABtn" style="font-weight: 700; background: #25D366; border-color: #25D366; color: white;">
                                <i class="fas fa-save"></i> Save WhatsApp Settings
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="settings-card" style="margin-top: 1.5rem;">
                        <h3 style="font-size: 0.9375rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-shield-alt" style="color: var(--primary);"></i> Account Security
                        </h3>
                        <p style="font-size: 0.8125rem; color: #64748b; margin-bottom: 1rem;">Two-factor authentication and login security policies.</p>
                        <button class="btn btn-ghost" style="color: var(--primary); font-weight: 700;">Configure MFA</button>
                    </div>
                </div>

                <div class="settings-card" style="background: linear-gradient(135deg, white 0%, #f8fafc 100%);">
                    <h3 style="font-size: 0.9375rem; font-weight: 800; margin-bottom: 1.5rem;">Subscription & Plan</h3>
                    <div style="padding: 1.25rem; border-radius: 0.75rem; background: white; border: 1px solid var(--border); margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Current Plan</span>
                            <span class="badge" style="background:#ede9fe; color:var(--accent-hover, #7c3aed); font-weight: 800;"><?= strtoupper($company['plan'] ?? 'TRIAL') ?></span>
                        </div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: #0f172a;">Active Status</div>
                        <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">
                            <?php if (($company['plan'] ?? 'trial') === 'trial'): ?>
                                Trial ends: <?= date('d M, Y', strtotime($company['trial_ends_at'] ?? '+7 days')) ?>
                            <?php else: ?>
                                Subscription ends: <?= date('d M, Y', strtotime($company['subscription_ends_at'] ?? '+30 days')) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; font-weight: 500; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 0.75rem;">
                            <span style="color: #64748b;">CRM Version</span>
                            <span style="color: var(--primary); font-weight: 700;">v2.5.0-SaaS</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; font-weight: 500;">
                            <span style="color: #64748b;">Database Node</span>
                            <span style="color: var(--success); font-weight: 700;">Primary (IN-DLH)</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const API = '<?= APP_URL ?>/public/index.php/api/settings.php';

        async function updateProfile() {
            const btn = document.getElementById('saveBtn');
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const payload = {
                name: document.getElementById('comp_name').value,
                gst_number: document.getElementById('comp_gst').value,
                address: document.getElementById('comp_address').value,
                bank_details: document.getElementById('bank_details').value,
                invoice_terms: document.getElementById('invoice_terms').value,
                quotation_terms: document.getElementById('quotation_terms').value
            };

            try {
                const res = await fetch(API, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                
                const text = await res.text();
                let result;
                try { result = JSON.parse(text); } catch(e) { throw new Error('Server returned invalid response: ' + text.substring(0, 100)); }

                if (result.success) {
                    location.reload();
                } else {
                    alert(result.error || 'Failed to update settings');
                }
            } catch (err) {
                alert('Error: ' + err.message);
            } finally {
                btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
            }
        }

        async function uploadAsset(input, type) {
            console.log('uploadAsset function triggered for', type);
            if (!input.files || !input.files[0]) {
                console.log('No file selected');
                return;
            }
            
            console.log('File selected:', input.files[0].name);
            const formData = new FormData();
            formData.append(type, input.files[0]);

            const overlay = document.getElementById(type + '_overlay');
            if (overlay) {
                overlay.style.opacity = '1';
                overlay.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            try {
                console.log('Sending request to:', API);
                const res = await fetch(API, {
                    method: 'POST',
                    body: formData
                });
                
                const text = await res.text();
                console.log('Raw response:', text);
                let result;
                try { result = JSON.parse(text); } catch(e) { throw new Error('Upload server error: ' + text.substring(0, 100)); }

                if (result.success) {
                    console.log('Upload success, reloading...');
                    location.reload();
                } else {
                    alert(result.error || 'Upload failed');
                }
            } catch (err) {
                console.error('Upload catch error:', err);
                alert('Error: ' + err.message);
            } finally {
                if (overlay) {
                    overlay.style.opacity = '';
                    overlay.innerHTML = '<i class="fas fa-camera"></i>';
                }
            }
        }

        function toggleSMTPPasswordVisibility() {
            const pwd = document.getElementById('smtp_password');
            const icon = document.querySelector('#smtpPassToggle i');
            if (pwd && icon) {
                if (pwd.type === 'password') {
                    pwd.type = 'text';
                    icon.className = 'fas fa-eye-slash';
                } else {
                    pwd.type = 'password';
                    icon.className = 'fas fa-eye';
                }
            }
        }

        async function updateSMTPConfig() {
            const btn = document.getElementById('saveSMTPBtn');
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const payload = {
                smtp_host: document.getElementById('smtp_host').value,
                smtp_port: parseInt(document.getElementById('smtp_port').value) || 587,
                smtp_secure: document.getElementById('smtp_secure').value,
                smtp_email: document.getElementById('smtp_email').value,
                smtp_password: document.getElementById('smtp_password').value
            };

            try {
                const res = await fetch(API, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                
                const text = await res.text();
                let result;
                try { result = JSON.parse(text); } catch(e) { throw new Error('Server returned invalid response: ' + text.substring(0, 100)); }

                if (result.success) {
                    alert('SMTP settings saved successfully!');
                    location.reload();
                } else {
                    alert(result.error || 'Failed to save SMTP settings');
                }
            } catch (err) {
                alert('Error: ' + err.message);
            } finally {
                btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Mail Settings';
            }
        }

        function toggleWAPasswordVisibility() {
            const pwd = document.getElementById('wa_access_token');
            const icon = document.querySelector('#waPassToggle i');
            if (pwd && icon) {
                if (pwd.type === 'password') {
                    pwd.type = 'text';
                    icon.className = 'fas fa-eye-slash';
                } else {
                    pwd.type = 'password';
                    icon.className = 'fas fa-eye';
                }
            }
        }

        async function updateWhatsAppConfig() {
            const btn = document.getElementById('saveWABtn');
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const payload = {
                whatsapp_number: document.getElementById('wa_number').value,
                whatsapp_phone_number_id: document.getElementById('wa_phone_number_id').value,
                whatsapp_business_account_id: document.getElementById('wa_business_account_id').value,
                whatsapp_access_token: document.getElementById('wa_access_token').value,
                whatsapp_default_template: document.getElementById('wa_default_template').value,
                whatsapp_default_mapping: document.getElementById('wa_default_mapping').value,
                whatsapp_header_image: document.getElementById('wa_header_image').value
            };

            try {
                const res = await fetch(API, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                
                const text = await res.text();
                let result;
                try { result = JSON.parse(text); } catch(e) { throw new Error('Server returned invalid response: ' + text.substring(0, 100)); }

                if (result.success) {
                    alert('WhatsApp settings saved successfully!');
                    location.reload();
                } else {
                    alert(result.error || 'Failed to save WhatsApp settings');
                }
            } catch (err) {
                alert('Error: ' + err.message);
            } finally {
                btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save WhatsApp Settings';
            }
        }

        async function testSMTPConnection() {
            const btn = document.getElementById('testSMTPBtn');
            const logsPanel = document.getElementById('smtpLogsPanel');
            const logsContent = document.getElementById('smtpLogsContent');
            
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
            if (logsPanel) logsPanel.style.display = 'none';

            const payload = {
                smtp_host: document.getElementById('smtp_host').value,
                smtp_port: parseInt(document.getElementById('smtp_port').value) || 587,
                smtp_secure: document.getElementById('smtp_secure').value,
                smtp_email: document.getElementById('smtp_email').value,
                smtp_password: document.getElementById('smtp_password').value
            };

            try {
                const res = await fetch(API + '?action=test_smtp', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                
                const text = await res.text();
                let result;
                try { result = JSON.parse(text); } catch(e) { throw new Error('Server returned invalid response: ' + text.substring(0, 100)); }

                if (logsContent && result.logs) {
                    logsContent.textContent = result.logs.join('\n');
                    if (logsPanel) logsPanel.style.display = 'block';
                }

                if (result.success) {
                    alert(result.message || 'SMTP Connection Test Passed successfully!');
                } else {
                    alert('SMTP Connection Test Failed: ' + (result.error || 'Unknown error. Check trace logs.'));
                }
            } catch (err) {
                alert('Network Error: ' + err.message);
            } finally {
                btn.disabled = false; btn.innerHTML = '<i class="fas fa-plug-circle-bolt" style="color: var(--primary, #6366f1);"></i> Test Connection';
            }
        }
    </script>
</body>
</html>
