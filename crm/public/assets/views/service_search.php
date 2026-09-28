<?php
use Core\Auth;

if (!Auth::check()) {
    header("Location: " . APP_URL . "/public/index.php/login");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Search | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .search-hero {
            background: linear-gradient(135deg, var(--primary, #6366f1) 0%, var(--primary-hover, #4f46e5) 100%);
            padding: 3rem 2rem;
            border-radius: 1.5rem;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 20px 25px -5px rgba(79, 70, 229, 0.2);
        }

        .filter-card {
            background: white;
            border-radius: 1.25rem;
            padding: 1.5rem;
            margin-top: -2rem;
            margin-left: 2rem;
            margin-right: 2rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1.5rem;
            align-items: end;
        }

        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            letter-spacing: 0.05em;
        }

        .select-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            border: 2px solid #f1f5f9;
            font-size: 0.9rem;
            font-weight: 600;
            background: #f8fafc;
            color: #1e293b;
            outline: none;
            appearance: auto;
        }

        .select-input:focus {
            border-color: var(--primary, #6366f1);
            background: white;
        }

        .results-container {
            margin-top: 2rem;
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
        }

        .results-table th {
            background: #f8fafc;
            padding: 1.25rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 2px solid #f1f5f9;
        }

        .results-table td {
            padding: 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e293b;
        }

        .results-table tr:hover {
            background: #fbfcfe;
        }

        .customer-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.75rem;
        }

        .btn-view {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 700;
            transition: all 0.2s;
        }

        .btn-view:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        .empty-state {
            padding: 5rem 2rem;
            text-align: center;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <?php include 'partials/topbar.php'; ?>

        <!--<div class="search-hero">-->
        <!--    <h1 style="font-weight: 800; font-size: 2rem; margin: 0; letter-spacing: -0.02em;">Service Intelligence</h1>-->
        <!--    <p style="opacity: 0.9; font-weight: 500; margin-top: 0.5rem;">Find customers based on the specific services they've engaged with.</p>-->
        <!--</div>-->

        <div class="filter-card" style='margin-top:20px'>
            <div class="form-group">
                <label>Predefined Services</label>
                <select id="predefinedSelect" class="select-input">
                    <option value="">— Select a Service —</option>
                </select>
            </div>
            <div class="form-group">
                <label>Custom Services</label>
                <select id="customSelect" class="select-input">
                    <option value="">— Select Custom Service —</option>
                </select>
            </div>
            <button onclick="handleSearch()" class="btn btn-primary" style="height: 44px; padding: 0 2rem; border-radius: 0.75rem; font-weight: 800;">
                <i class="fas fa-search" style="margin-right: 0.5rem;"></i> Search
            </button>
        </div>

        <div id="resultsWrapper" style="display: none;">
            <div style="margin: 2rem 2rem 1rem 2rem; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-weight: 800; color: #1e293b; margin: 0;">Found Customers</h3>
                <div style="position: relative; width: 300px;">
                    <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.85rem;"></i>
                    <input type="text" id="customerTableSearch" placeholder="Filter these results..." 
                        style="width: 100%; padding: 0.65rem 1rem 0.65rem 2.5rem; border-radius: 10px; border: 1.5px solid #e2e8f0; font-size: 0.85rem; font-weight: 600; outline: none;">
                </div>
            </div>
            
            <div class="results-container" style="margin-top: 0;">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Mobile Number</th>
                            <th>Email</th>
                            <th>Lead Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="resultsBody"></tbody>
                </table>
            </div>
        </div>

        <div id="initialState" class="results-container">
            <div class="empty-state">
                <i class="fas fa-filter"></i>
                <p style="font-weight: 700;">Select a service above to start searching</p>
            </div>
        </div>

    <!-- Quick Follow-up & History Modal -->
    <div id="quickFollowModal" class="modal-overlay" style="display: none;">
        <div class="modal-content"
            style="max-width: 800px; width: 95%; display: flex; flex-direction: row; overflow: hidden; height: 500px;">
            <!-- Left: Interaction Form -->
            <form id="quickFollowForm"
                style="flex: 1; padding: 1.5rem; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column;">
                <div
                    style="margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <h2 style="font-size: 1rem; font-weight: 800; color: var(--primary-hover, #4f46e5); margin: 0;">Quick Follow-up
                        </h2>
                        <p style="font-size: 0.65rem; color: #94a3b8; margin: 0.25rem 0 0 0;">For <span
                                id="qf_lead_name" style="color:#1e293b; font-weight:700;">Client</span></p>
                    </div>
                </div>

                <input type="hidden" name="lead_id" id="qf_lead_id">

                <div style="margin-bottom: 1rem;">
                    <label style="font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Next Follow-up Date</label>
                    <input type="date" name="follow_up_date" id="qf_date" required class="select-input"
                        style="height: 36px; padding: 0.5rem; font-size: 0.75rem; border-radius: 0.5rem;">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Interaction Remark</label>
                    <textarea name="remark" required class="select-input" rows="3"
                        placeholder="e.g. Discussed about services..."
                        style="font-size: 0.75rem; min-height: 80px; padding: 0.5rem; border-radius: 0.5rem;"></textarea>
                </div>

                <div style="margin-top: auto; display: flex; gap: 0.75rem;">
                    <button type="button" class="btn btn-view"
                        onclick="document.getElementById('quickFollowModal').style.display='none'"
                        style="flex: 1; text-align: center;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1.5; border-radius: 0.5rem;">Save Follow-up</button>
                </div>
            </form>

            <!-- Right: Engagement History -->
            <div style="flex: 1.2; background: #fcfcfd; display: flex; flex-direction: column; padding: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3
                        style="font-size: 0.8rem; font-weight: 800; color: #475569; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-history" style="color: var(--primary-hover, #4f46e5);"></i> Engagement History
                    </h3>
                    <button onclick="document.getElementById('quickFollowModal').style.display='none'" class="btn-view"
                        style="border:none; background:none; cursor:pointer; color:#94a3b8; padding: 0.2rem 0.5rem;"><i
                            class="fas fa-times"></i></button>
                </div>
                <div id="followupList" style="flex-grow: 1; overflow-y: auto; padding-right: 0.5rem;">
                    <!-- History items injected here -->
                </div>
            </div>
        </div>
    </div>
    </main>
</div>

<script>
const apiBase = '<?= APP_URL ?>/api/service_search.php';

async function loadServices() {
    try {
        const response = await fetch(`${apiBase}?action=services`);
        const data = await response.json();
        
        const predefined = document.getElementById('predefinedSelect');
        const custom = document.getElementById('customSelect');

        data.predefined.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            predefined.appendChild(opt);
        });

        data.custom.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s;
            opt.textContent = s;
            custom.appendChild(opt);
        });

        // Reset logic: if one is picked, clear other
        predefined.onchange = () => { if(predefined.value) custom.value = ''; };
        custom.onchange = () => { if(custom.value) predefined.value = ''; };

    } catch (e) {
        console.error('Error loading filters:', e);
    }
}

let currentResults = [];

async function handleSearch() {
    const serviceId = document.getElementById('predefinedSelect').value;
    const customName = document.getElementById('customSelect').value;
    const tbody = document.getElementById('resultsBody');
    const resultsWrapper = document.getElementById('resultsWrapper');
    const initialState = document.getElementById('initialState');

    if (!serviceId && !customName) {
        alert('Please select either a predefined service or a custom service name.');
        return;
    }

    initialState.style.display = 'none';
    resultsWrapper.style.display = 'block';
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding: 3rem;"><i class="fas fa-spinner fa-spin"></i> Finding customers...</td></tr>';

    try {
        let url = `${apiBase}?action=search`;
        if (serviceId) url += `&service_id=${serviceId}`;
        else if (customName) url += `&custom_name=${encodeURIComponent(customName)}`;

        const response = await fetch(url);
        currentResults = await response.json();

        renderResults(currentResults);

    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding: 3rem; color: #ef4444;">Error searching for customers.</td></tr>';
    }
}

function renderResults(list) {
    const tbody = document.getElementById('resultsBody');
    if (list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><i class="fas fa-search"></i><p style="font-weight: 700;">No customers found for this service</p></div></td></tr>';
        return;
    }

    tbody.innerHTML = list.map(r => `
        <tr>
            <td>
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <div class="customer-avatar">${r.name.charAt(0)}</div>
                    <span>${r.name}</span>
                </div>
            </td>
            <td>${r.mobile}</td>
            <td style="color: #64748b; font-size: 0.8rem;">${r.email || '—'}</td>
            <td style="font-size: 0.8rem; color: #94a3b8;">${new Date(r.lead_date).toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'})}</td>
            <td style="text-align: right; display: flex; gap: 0.4rem; justify-content: flex-end;">
                <a href="tel:${r.mobile}" class="btn-view" style="background: #e0f2fe; color: #0284c7; padding: 0.4rem 0.6rem; border-radius: 6px;" title="Call Customer">
                    <i class="fas fa-phone-alt"></i>
                </a>
                <a href="https://wa.me/91${r.mobile.replace(/[^0-9]/g, '')}" target="_blank" class="btn-view" style="background: #dcfce7; color: #16a34a; padding: 0.4rem 0.6rem; border-radius: 6px;" title="WhatsApp Customer">
                    <i class="fab fa-whatsapp" style="font-size: 1.05rem;"></i>
                </a>
                <a href="javascript:void(0)" onclick="openQuickFollowup(${r.id}, '${(r.name || 'Customer').replace(/'/g, `\\'`).replace(/\"/g, `&quot;`)}')" class="btn-view" style="background: #f1f5f9; color: #475569; padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 700; white-space: nowrap;" title="Quick Follow up">
                    Follow up <i class="fas fa-arrow-right" style="margin-left: 4px; font-size: 0.7rem;"></i>
                </a>
            </td>
        </tr>
    `).join('');
}

// Local filtering logic
document.getElementById('customerTableSearch').oninput = (e) => {
    const query = e.target.value.toLowerCase();
    const filtered = currentResults.filter(r => 
        r.name.toLowerCase().includes(query) || 
        r.mobile.toLowerCase().includes(query) ||
        (r.email && r.email.toLowerCase().includes(query))
    );
    renderResults(filtered);
};

async function fetchFollowups(lead_id) {
    const list = document.getElementById('followupList');
    list.innerHTML = '<div style="text-align:center; padding: 2rem; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

    try {
        const response = await fetch(`<?= APP_URL ?>/public/index.php/api/lead_followups.php?lead_id=${lead_id}`);
        const data = await response.json();

        const displayData = data.slice(0, 10);

        if (displayData.length === 0) {
            list.innerHTML = '<div style="text-align:center; padding:2rem; color:#94a3b8; font-size:0.75rem;"><i class="fas fa-comment-slash" style="display:block; font-size:1.5rem; margin-bottom:0.5rem; opacity:0.3;"></i>No engagement history yet</div>';
            return;
        }

        list.innerHTML = displayData.map(f => `
            <div style="background: white; border: 1px solid #f1f5f9; padding: 0.875rem; border-radius: 0.625rem; margin-bottom: 0.75rem; border-left: 3px solid ${f.status === 'pending' ? '#f59e0b' : '#10b981'};">
                <div style="display:flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.25rem;">
                    <span style="font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">
                        ${new Date(f.follow_up_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })} 
                        at 
                        ${new Date(`1970-01-01T${f.follow_up_time.slice(0, 5)}`).toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit', hour12: true })}
                    </span>
                    <span class="badge" style="font-size: 0.6rem; background: #f0fdf4; color:#166534; border: 1px solid #bbf7d0;">${(f.call_status || 'CONNECTED').toUpperCase()}</span>
                </div>
                <p style="font-size: 0.75rem; color: #334155; font-weight: 600; line-height: 1.4; margin: 0;">${f.remark}</p>
            </div>
        `).join('');
    } catch (error) {
        list.innerHTML = '<div style="text-align:center; color: #ef4444; font-size: 0.75rem;">Failed to load history.</div>';
    }
}

async function openQuickFollowup(id, name) {
    document.getElementById('qf_lead_id').value = id;
    document.getElementById('qf_lead_name').textContent = name;
    document.getElementById('qf_date').value = new Date().toISOString().split('T')[0];

    // Load History for this lead
    fetchFollowups(id);

    document.getElementById('quickFollowModal').style.display = 'flex';
}

document.getElementById('quickFollowForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());

    const triggerDate = new Date(data.follow_up_date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (triggerDate < today) {
        alert("Reminder date cannot be in the past.");
        return;
    }

    try {
        const response = await fetch('<?= APP_URL ?>/public/index.php/api/lead_followups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        if (response.ok) {
            document.getElementById('quickFollowModal').style.display = 'none';
            this.reset();
            // Refresh table
            handleSearch();
        } else {
            alert("Failed to save follow-up.");
        }
    } catch (error) {
        console.error("Error saving follow-up:", error);
        alert("Error saving follow-up.");
    }
});

loadServices();
</script>
</body>
</html>
