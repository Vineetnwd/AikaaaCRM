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
    width: 400px; max-width: 90%;
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
.wa-modal-body { padding: 1.5rem; }
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
}
</style>

<div class="wa-modal-overlay" id="waTemplateModal">
    <div class="wa-modal-container">
        <div class="wa-modal-header">
            <h3 style="margin: 0; font-size: 1rem; color: #1e293b;"><i class="fab fa-whatsapp" style="color:#25d366; margin-right:5px;"></i> Send WhatsApp</h3>
            <button type="button" onclick="closeWAModal()" style="background:none; border:none; cursor:pointer; font-size:1.2rem; color:#94a3b8;">&times;</button>
        </div>
        <div class="wa-modal-body">
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
            <button type="button" onclick="submitWAModal()" class="btn btn-primary" style="padding: 0.5rem 1rem; background: #25d366; color: white; border:none; border-radius:6px; cursor:pointer; display:flex; align-items:center; gap:5px;">
                <i class="fas fa-paper-plane"></i> Send
            </button>
        </div>
    </div>
</div>

<script>
    let globalWAMapping = <?= json_encode(array_map('trim', explode(',', $company['whatsapp_default_mapping'] ?? 'name,service,status'))) ?>;

    function openWAModal(type, id, recordData = {}) {
        let isBulk = Array.isArray(id);
        document.getElementById('waModalTargetType').value = type;
        document.getElementById('waModalTargetId').value = isBulk ? JSON.stringify(id) : id;
        
        // Ensure quotation links ALWAYS use the quotation print URL
        if (type === 'quotation') {
            const quotationUrl = '<?= APP_URL ?>/public/index.php/quotation/print?id=' + (isBulk ? '' : id);
            if (!isBulk) {
                recordData.link = quotationUrl;
                recordData.invoice_link = quotationUrl;
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
                } else {
                    val = recordData[varName] || '';
                    if(!val && varName === 'name') val = recordData.name || recordData.customer_name || 'Customer';
                    if(!val && varName === 'service') val = recordData.service_name || 'Services';
                    if(!val && varName === 'status') val = recordData.status || '';
                    if(!val && isLinkField) val = recordData.link || '';
                }
            }
            
            if (!isBulk && (isLinkField || (idx === 3 && (!val || val.includes('http') || val.includes('id='))))) {
                val = recordData.link;
            } else if (!val && idx === 3) {
                val = '<?= htmlspecialchars($company['name'] ?? 'Company Name', ENT_QUOTES) ?>';
            }
            
            let displayName = varName;
            if (isLinkField) displayName = 'link';

            let placeholder = isBulk ? `Dynamic {{${displayName}}}` : '';

            const div = document.createElement('div');
            div.innerHTML = `
                <label style="display:block; font-size: 0.7rem; font-weight: 700; color: #64748b; margin-bottom: 0.25rem; text-transform: uppercase;">
                    Variable ${idx + 1} (${displayName})
                </label>
                <input type="text" class="wa-variable-input" id="wa_var_${idx}" value="${val.replace(/"/g, '&quot;')}" placeholder="${placeholder}">
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
        
        const btn = document.querySelector('#waTemplateModal .btn-primary');
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        btn.disabled = true;
        
        const fileInput = document.getElementById('waModalImage');
        const formData = new FormData();
        formData.append('payload', JSON.stringify(payload));
        if (fileInput.files.length > 0) {
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
            
            if (result.success) {
                alert('Template sent successfully!');
                closeWAModal();
            } else {
                let errorMsg = result.error || 'Unknown error';
                if (result.details) {
                    errorMsg += '\nDetails: ' + JSON.stringify(result.details, null, 2);
                }
                alert('Failed to send template: ' + errorMsg);
            }
        } catch (err) {
            console.error("Fetch/Parse Error:", err);
            alert('Network/Server error. Please check the console for details. Error: ' + err.message);
        } finally {
            btn.innerHTML = origHtml;
            btn.disabled = false;
        }
    }
</script>
