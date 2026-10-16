<?php
$modalCompany = $company ?? \Core\Auth::company();
$modalQuota = \Core\Auth::getWhatsAppQuota($modalCompany);
?>
<style>
/* Ensure modal styling is loaded */
.wa-modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    align-items: center; justify-content: center;
    z-index: 9999;
}
.wa-modal-overlay.active { display: flex; }
.wa-modal-container {
    background: white;
    width: 440px; max-width: 92%;
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}
.wa-modal-header {
    background: #f8fafc;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex; justify-content: space-between; align-items: center;
}
.wa-modal-body { padding: 1.5rem; max-height: 75vh; overflow-y: auto; }
.wa-modal-footer {
    padding: 1rem 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex; justify-content: flex-end; gap: 0.5rem;
}
.wa-variable-input {
    width: 100%;
    padding: 0.6rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    margin-bottom: 1rem;
    font-size: 0.85rem;
    box-sizing: border-box;
}
</style>

<div class="wa-modal-overlay" id="waTemplateModal">
    <div class="wa-modal-container">
        <div class="wa-modal-header">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <h3 style="margin: 0; font-size: 1rem; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                    <i class="fab fa-whatsapp" style="color:#25d366; font-size: 1.15rem;"></i> Send WhatsApp
                </h3>
                <span id="waModalQuotaBadge" style="font-size: 0.65rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 9999px; <?= $modalQuota['unlimited'] ? 'background:#dcfce7; color:#16a34a;' : ($modalQuota['remaining'] <= 0 ? 'background:#fee2e2; color:#dc2626;' : 'background:#dbeafe; color:#1e40af;') ?>">
                    <?php if ($modalQuota['unlimited']): ?>
                        <i class="fas fa-infinity"></i> Unlimited
                    <?php else: ?>
                        <?= (int)$modalQuota['remaining'] ?> left (<?= (int)$modalQuota['sent'] ?>/<?= (int)$modalQuota['limit'] ?>)
                    <?php endif; ?>
                </span>
            </div>
            <button type="button" onclick="closeWAModal()" style="background:none; border:none; cursor:pointer; font-size:1.2rem; color:#94a3b8;">&times;</button>
        </div>
        <div class="wa-modal-body">
            <div id="waQuotaAlert" style="<?= (!$modalQuota['unlimited'] && $modalQuota['remaining'] <= 0) ? 'display:block;' : 'display:none;' ?> background: #fef2f2; border: 1px solid #fecaca; border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.75rem; color: #991b1b;">
                <div style="font-weight: 700; display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.25rem;">
                    <i class="fas fa-exclamation-triangle"></i> Message Limit Reached
                </div>
                <div id="waQuotaAlertText">
                    You have sent <?= (int)$modalQuota['sent'] ?> of <?= (int)$modalQuota['limit'] ?> allowed WhatsApp messages. Upgrade to the Pro Plan to integrate your own WhatsApp API credentials for unlimited messaging!
                </div>
                <div style="margin-top: 0.5rem;">
                    <a href="<?= APP_URL ?>/public/index.php/renew" style="display: inline-block; background: #dc2626; color: white; padding: 0.3rem 0.75rem; border-radius: 4px; text-decoration: none; font-weight: 700; font-size: 0.7rem;">
                        <i class="fas fa-bolt"></i> Upgrade to Pro Plan
                    </a>
                </div>
            </div>

            <div id="waDirectChatBanner" style="display: none; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.5rem; padding: 0.65rem 0.85rem; margin-bottom: 1rem; justify-content: space-between; align-items: center; gap: 0.5rem;">
                <div>
                    <div style="font-size: 0.8rem; font-weight: 700; color: #166534; display: flex; align-items: center; gap: 0.35rem;">
                        <i class="fab fa-whatsapp" style="color: #22c55e;"></i> Direct WhatsApp Chat
                    </div>
                    <div style="font-size: 0.7rem; color: #15803d;" id="waDirectChatPhone">
                        Chat directly without template API
                    </div>
                </div>
                <a id="waDirectChatLink" href="#" target="_blank" class="btn" style="background: #25d366; color: white; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;">
                    <i class="fab fa-whatsapp"></i> Chat on Web
                </a>
            </div>

            <p id="waModalHint" style="font-size: 0.8rem; color: #64748b; margin-top: 0; margin-bottom: 1rem;">
                Review and edit the template variables before sending. You can type custom messages here.
            </p>
            
            <input type="hidden" id="waModalTargetType">
            <input type="hidden" id="waModalTargetId">
            
            <div id="waModalInputsContainer">
                <!-- Inputs injected dynamically -->
            </div>
            
            <div style="margin-top: 1rem;">
                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase;">Image (Optional)</label>
                <input type="file" id="waModalImage" accept="image/*" class="form-input" style="padding: 0.4rem; font-size: 0.85rem; width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
                <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.25rem;">If left empty, the default header image from settings will be sent.</div>
            </div>
            
        </div>
        <div class="wa-modal-footer">
            <button type="button" onclick="closeWAModal()" class="btn btn-ghost" style="padding: 0.5rem 1rem; background: #e2e8f0; color: #475569; border:none; border-radius:6px; cursor:pointer;">Cancel</button>
            <button type="button" id="waModalSubmitBtn" onclick="submitWAModal()" class="btn btn-primary" style="padding: 0.5rem 1rem; background: #25d366; color: white; border:none; border-radius:6px; cursor:pointer; display:flex; align-items:center; gap:5px;" <?= (!$modalQuota['unlimited'] && $modalQuota['remaining'] <= 0) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                <i class="fas fa-paper-plane"></i> Send
            </button>
        </div>
    </div>
</div>

<script>
    let globalWAMapping = <?= json_encode(array_map('trim', explode(',', $modalCompany['whatsapp_default_mapping'] ?? 'name,service,status'))) ?>;
    let currentWAQuota = <?= json_encode($modalQuota) ?>;

    function updateModalQuotaUI(quota) {
        if (!quota) return;
        currentWAQuota = quota;
        const badge = document.getElementById('waModalQuotaBadge');
        const alertBox = document.getElementById('waQuotaAlert');
        const alertText = document.getElementById('waQuotaAlertText');
        const submitBtn = document.getElementById('waModalSubmitBtn');

        if (quota.unlimited) {
            if (badge) {
                badge.style.background = '#dcfce7';
                badge.style.color = '#16a34a';
                badge.innerHTML = '<i class="fas fa-infinity"></i> Unlimited';
            }
            if (alertBox) alertBox.style.display = 'none';
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
        } else {
            const remaining = Math.max(0, quota.remaining);
            if (badge) {
                badge.style.background = remaining <= 0 ? '#fee2e2' : '#dbeafe';
                badge.style.color = remaining <= 0 ? '#dc2626' : '#1e40af';
                badge.textContent = `${remaining} left (${quota.sent}/${quota.limit})`;
            }
            if (remaining <= 0) {
                if (alertBox) alertBox.style.display = 'block';
                if (alertText) alertText.textContent = `You have reached your limit of ${quota.limit} WhatsApp messages. Upgrade to the Pro Plan to connect your own WhatsApp API credentials for unlimited messaging!`;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                    submitBtn.style.cursor = 'not-allowed';
                }
            } else {
                if (alertBox) alertBox.style.display = 'none';
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                }
            }
        }
    }

    function openWAModal(type, id, recordData = {}) {
        let isBulk = Array.isArray(id);
        const recipientCount = isBulk ? id.length : 1;
        document.getElementById('waModalTargetType').value = type;
        document.getElementById('waModalTargetId').value = isBulk ? JSON.stringify(id) : id;
        
        // Setup direct WhatsApp web banner if single recipient with mobile
        const directBanner = document.getElementById('waDirectChatBanner');
        const directLink = document.getElementById('waDirectChatLink');
        const directPhone = document.getElementById('waDirectChatPhone');

        let targetMobile = recordData.mobile || recordData.customer_mobile || recordData.client_mobile || '';
        if (!isBulk && targetMobile) {
            let cleanPhone = String(targetMobile).replace(/[^0-9]/g, '');
            if (cleanPhone.length === 10) cleanPhone = '91' + cleanPhone;
            if (directLink && directBanner) {
                directLink.href = `https://wa.me/${cleanPhone}`;
                if (directPhone) directPhone.textContent = `Direct: +${cleanPhone}`;
                directBanner.style.display = 'flex';
            }
        } else {
            if (directBanner) directBanner.style.display = 'none';
        }

        // Ensure quotation links ALWAYS use the quotation print URL
        if (type === 'quotation') {
            const quotationUrl = '<?= APP_URL ?>/public/index.php/quotation/print?id=' + (isBulk ? '' : id);
            if (!isBulk) {
                recordData.link = quotationUrl;
                recordData.invoice_link = quotationUrl;
            }
        }

        // Quota check alert
        const alertBox = document.getElementById('waQuotaAlert');
        const alertText = document.getElementById('waQuotaAlertText');
        const submitBtn = document.getElementById('waModalSubmitBtn');

        if (!currentWAQuota.unlimited) {
            if (currentWAQuota.remaining <= 0) {
                if (alertBox) alertBox.style.display = 'block';
                if (alertText) alertText.textContent = `WhatsApp message limit reached (${currentWAQuota.sent}/${currentWAQuota.limit} sent). Please upgrade to the Pro Plan to integrate your own WhatsApp API keys.`;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                    submitBtn.style.cursor = 'not-allowed';
                }
            } else if (isBulk && recipientCount > currentWAQuota.remaining) {
                if (alertBox) alertBox.style.display = 'block';
                if (alertText) alertText.textContent = `You selected ${recipientCount} recipients, but only ${currentWAQuota.remaining} message(s) remain in your quota. Please select fewer recipients or upgrade to Pro.`;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                    submitBtn.style.cursor = 'not-allowed';
                }
            } else {
                if (alertBox) alertBox.style.display = 'none';
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                }
            }
        }

        const hintEl = document.getElementById('waModalHint');
        if (isBulk) {
            hintEl.innerHTML = `<strong style="color:#0f172a;">Bulk Mode (${id.length} selected):</strong> Leave a variable blank (like 'name') to let the system automatically inject the correct value for each person!`;
        } else {
            hintEl.innerHTML = 'Review and edit the template variables before sending. You can type custom messages here.';
        }

        const fileInput = document.getElementById('waModalImage');
        if (fileInput) fileInput.value = '';

        const container = document.getElementById('waModalInputsContainer');
        container.innerHTML = '';
        
        globalWAMapping.forEach((varName, idx) => {
            if(!varName) return;
            
            const isLinkField = (varName === 'link' || varName === 'invoice_link' || varName.includes('invoice_print.php') || varName.includes('quotation_print.php') || varName.includes('quotation/print') || varName.includes('http') || varName.includes('token='));
            
            let val = '';
            if (!isBulk) {
                if (type === 'quotation' && isLinkField) {
                    val = recordData.link;
                } else if (isLinkField) {
                    val = recordData.link || recordData.invoice_link || '';
                } else {
                    val = (recordData[varName] !== undefined && recordData[varName] !== null) ? recordData[varName] : '';
                    if(!val && varName === 'name') val = recordData.name || recordData.customer_name || 'Customer';
                    if(!val && varName === 'service') val = recordData.service_name || recordData.service || 'Our Services';
                    if(!val && varName === 'status') val = recordData.status || (type === 'customer' ? 'Customer' : 'New');
                    if(!val && isLinkField) val = recordData.link || '';
                }
            }
            
            if (!isBulk && isLinkField && !val) {
                val = recordData.link || '';
            } else if (!isBulk && idx === 3 && !val) {
                val = recordData.link || '<?= htmlspecialchars($modalCompany['name'] ?? 'Company Name', ENT_QUOTES) ?>';
            }
            
            let displayName = varName;
            if (isLinkField) displayName = 'link';

            let placeholder = isBulk ? `Dynamic {{${displayName}}}` : '';
            let valStr = String(val ?? '');

            const div = document.createElement('div');
            div.innerHTML = `
                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase;">
                    Variable ${idx + 1} (${displayName})
                </label>
                <input type="text" class="wa-variable-input" id="wa_var_${idx}" value="${valStr.replace(/"/g, '&quot;')}" placeholder="${placeholder}">
            `;
            container.appendChild(div);
        });
        
        if (globalWAMapping.filter(v=>v).length === 0) {
            container.innerHTML = '<p style="color:#ef4444; font-size:0.85rem;">No variables mapped in Settings. Please configure WhatsApp Templates in Settings first.</p>';
        }

        document.getElementById('waTemplateModal').classList.add('active');
    }

    function closeWAModal() {
        document.getElementById('waTemplateModal').classList.remove('active');
    }

    async function submitWAModal() {
        const type = document.getElementById('waModalTargetType').value;
        const idVal = document.getElementById('waModalTargetId').value;
        let isBulk = idVal.startsWith('[');
        let id = isBulk ? JSON.parse(idVal) : idVal;
        
        const custom_variables = [];
        const inputs = document.querySelectorAll('.wa-variable-input');
        inputs.forEach(inp => {
            custom_variables.push(inp.value);
        });
        
        const payload = {};
        if (type === 'lead') {
            if (isBulk) payload.lead_ids = id; else payload.lead_id = id;
        } else if (type === 'invoice') {
            if (isBulk) payload.invoice_ids = id; else payload.invoice_id = id;
        } else if (type === 'quotation') {
            if (isBulk) payload.quotation_ids = id; else payload.quotation_id = id;
        } else {
            if (isBulk) payload.customer_ids = id; else payload.customer_id = id;
        }
        payload.custom_variables = custom_variables;
        
        const btn = document.getElementById('waModalSubmitBtn') || document.querySelector('#waTemplateModal .btn-primary');
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        btn.disabled = true;
        
        const fileInput = document.getElementById('waModalImage');
        const formData = new FormData();
        formData.append('payload', JSON.stringify(payload));
        if (fileInput && fileInput.files.length > 0) {
            formData.append('image', fileInput.files[0]);
        }
        
        try {
            const res = await fetch('<?= APP_URL ?>/api/whatsapp_send.php', {
                method: 'POST',
                body: formData
            });
            const text = await res.text();
            let result;
            try { result = JSON.parse(text); } catch(e) { throw new Error(text); }
            
            if (result.quota) {
                updateModalQuotaUI(result.quota);
            }

            if (result.success) {
                closeWAModal();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: result.message || 'Template sent successfully!',
                        confirmButtonColor: '#25d366'
                    });
                } else {
                    alert('Template sent successfully!');
                }
            } else if (result.quota_exceeded) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Quota Exceeded',
                        html: `<p style="font-size:0.9rem; color:#475569;">${result.error}</p><div style="margin-top:1.25rem;"><a href="<?= APP_URL ?>/public/index.php/renew" class="swal2-confirm swal2-styled" style="background:#16a34a; text-decoration:none; display:inline-block;"><i class="fas fa-crown"></i> Upgrade to Pro Plan</a></div>`,
                        showConfirmButton: false,
                        showCancelButton: true,
                        cancelButtonText: 'Close'
                    });
                } else {
                    alert(result.error);
                }
            } else {
                let errorMsg = result.error || 'Unknown error';
                if (result.details) {
                    errorMsg += '\nDetails: ' + JSON.stringify(result.details, null, 2);
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Failed to Send', text: errorMsg, confirmButtonColor: '#ef4444' });
                } else {
                    alert('Failed to send template: ' + errorMsg);
                }
            }
        } catch (err) {
            console.error("Fetch/Parse Error:", err);
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message, confirmButtonColor: '#ef4444' });
            } else {
                alert('Network/Server error: ' + err.message);
            }
        } finally {
            btn.innerHTML = origHtml;
            if (!currentWAQuota.unlimited && currentWAQuota.remaining <= 0) {
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
            } else {
                btn.disabled = false;
            }
        }
    }
</script>
