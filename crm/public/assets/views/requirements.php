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
    <title>Services/Goods Portfolio | AIKAAA CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        .portfolio-table-card {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-top: 1.5rem;
        }

        .portfolio-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .portfolio-table th {
            background: #f8fafc;
            padding: 0.5rem 1rem;
            text-align: left;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.65rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .portfolio-table td {
            padding: 0.5rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            font-weight: 600;
        }

        .portfolio-table tr:last-child td { border-bottom: none; }
        
        .portfolio-table tr:hover { background: #fbfcfe; }

        .service-name-cell {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .service-icon {
            width: 28px;
            height: 28px;
            background: #eff6ff;
            color: #2563eb;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .fee-badge {
            background: #f0f9ff;
            color: #0369a1;
            padding: 0.2rem 0.5rem;
            border-radius: 0.4rem;
            font-weight: 800;
            border: 1px solid #bae6fd;
            white-space: nowrap;
            font-size: 0.75rem;
        }

        .count-btn {
            background: #f5f3ff;
            color: var(--accent-hover, #7c3aed);
            padding: 0.25rem 0.6rem;
            border-radius: 0.4rem;
            font-weight: 800;
            border: 1px solid #ddd6fe;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
        }

        .count-btn:hover {
            background: #ddd6fe;
            transform: translateY(-1px);
        }

        .doc-list-text {
            color: #64748b;
            font-size: 0.8rem;
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Search Bar Refined */
        .search-container {
            position: relative;
            max-width: 450px;
        }

        .search-container i {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-input {
            width: 100%;
            padding: 0.875rem 1.25rem 0.875rem 3rem;
            border-radius: 1rem;
            border: 2px solid #e2e8f0;
            font-size: 0.9375rem;
            font-weight: 600;
            transition: all 0.2s;
            background: white;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary, #6366f1);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .modal-customers-list {
            max-height: 400px;
            overflow-y: auto;
            padding: 1rem;
        }

        .customer-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .customer-row:last-child { border-bottom: none; }
        .customer-row:hover { background: #f8fafc; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <?php include 'partials/topbar.php'; ?>
        
        <header class="header" style="margin-bottom: 2rem;">
            <div>
                <h1 class="page-title">Services/Goods Portfolio</h1>
                <p style="font-size: 0.875rem; color: #64748b; font-weight: 600;">Manage and track usage of your business services.</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openModal('add')" style="padding: 0.75rem 1.5rem; font-weight: 800; border-radius: 12px; background: linear-gradient(135deg, var(--primary, #6366f1) 0%, var(--primary-hover, #4f46e5) 100%);">
                    <i class="fas fa-plus"></i> Add New Service
                </button>
            </div>
        </header>

        <div class="search-container">
            <i class="fas fa-search"></i>
            <input type="text" id="serviceSearch" class="search-input" placeholder="Search services, descriptions, or requirements..." onkeyup="filterServices()">
        </div>

        <div class="portfolio-table-card">
            <table class="portfolio-table">
                <thead>
                    <tr>
                        <th>Service Name</th>
                        <th>Standard Fee</th>
                        <th>Required Documents</th>
                        <th>Customers</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="servicesContainer">
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 4rem; color: #94a3b8;">
                            <i class="fas fa-circle-notch fa-spin fa-2x"></i>
                            <p style="margin-top: 1rem; font-weight: 600;">Loading portfolio...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div id="paginationControls" style="padding: 1rem; border-top: 1px solid #e2e8f0; display: none; justify-content: space-between; align-items: center; background: white;">
            </div>
        </div>
    </main>
</div>

<!-- Add/Edit Service Modal -->
<div id="requirementModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 id="modalTitle" style="font-weight: 800; letter-spacing: -0.02em; margin: 0; color: #0f172a;">Add Service</h2>
                <p style="font-size: 0.75rem; color: #64748b; margin: 0.25rem 0 0 0;">Define service pricing and requirements.</p>
            </div>
            <button class="icon-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="requirementForm">
            <input type="hidden" id="requirementId">
            <div class="modal-body" style="padding: 2rem;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: 700; color: #475569; margin-bottom: 0.5rem; display: block;">Service Name</label>
                    <input type="text" id="name" name="name" class="form-input" placeholder="e.g. GST Registration" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" style="font-weight: 700; color: #475569; margin-bottom: 0.5rem; display: block;">Standard Charge (₹)</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-weight: 800; color: #94a3b8;">₹</span>
                        <input type="number" id="fee" name="fee" class="form-input" step="0.01" placeholder="0.00" required style="padding-left: 2rem; font-weight: 800;">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight: 700; color: #475569; margin-bottom: 0.5rem; display: block;">Internal Description</label>
                    <textarea id="description" name="description" class="form-input" rows="3" placeholder="Notes about this service..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight: 700; color: #475569; margin-bottom: 0.5rem; display: block;">Required Documents (Comma separated)</label>
                    <textarea id="req_docs" name="req_docs" class="form-input" rows="3" placeholder="Pan Card, Photo, etc."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 1.5rem; background: #f8fafc; border-top: 1px solid #f1f5f9;">
                <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2.5rem; border-radius: 12px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Customer List Modal -->
<div id="customerListModal" class="modal-overlay" style='z-index: 1001;'>
    <div class="modal-content" style="max-width: 550px; height: 90vh; display: flex; flex-direction: column;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
            <div>
                <h2 style="font-weight: 800; letter-spacing: -0.02em; margin: 0; color: #0f172a;">Service Customers</h2>
                <p id="serviceNameLabel" style="font-size: 0.75rem; color: var(--primary, #6366f1); font-weight: 700; margin: 0.25rem 0 0 0;"></p>
            </div>
            <button class="icon-btn" onclick="closeCustomerModal()"><i class="fas fa-times"></i></button>
        </div>
        
        <div style="padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; background: #f8fafc; flex-shrink: 0;">
            <div style="position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.85rem;"></i>
                <input type="text" id="modalCustomerSearch" placeholder="Filter customers by name or mobile..." 
                    style="width: 100%; padding: 0.65rem 1rem 0.65rem 2.5rem; border-radius: 10px; border: 1.5px solid #e2e8f0; font-size: 0.85rem; font-weight: 600; outline: none; transition: border-color 0.2s;">
            </div>
        </div>

        <div id="customersContainer" class="modal-customers-list" style="flex-grow: 1; overflow-y: auto;">
            <!-- Customers will be loaded here -->
        </div>
    </div>
</div>

<script>
const apiBase = '<?= APP_URL ?>/api/requirements.php';
let currentPage = 1;
let currentSearch = '';
let currentLimit = '15';
let searchTimeout = null;

async function fetchRequirements(page = 1) {
    currentPage = page;
    const perPageElem = document.getElementById('perPage');
    if (perPageElem) {
        currentLimit = perPageElem.value;
    }
    const container = document.getElementById('servicesContainer');
    container.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 4rem; color: #94a3b8;"><i class="fas fa-circle-notch fa-spin fa-2x"></i><p style="margin-top: 1rem; font-weight: 600;">Loading portfolio...</p></td></tr>';

    try {
        const response = await fetch(`${apiBase}?page=${page}&limit=${currentLimit}&search=${encodeURIComponent(currentSearch)}`);
        const result = await response.json();
        renderServices(result.data || []);
        renderPagination(result);
    } catch (error) {
        console.error('Error fetching requirements:', error);
        container.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 4rem; color: #ef4444;">Error loading data</td></tr>`;
    }
}

function renderPagination(result) {
    const controls = document.getElementById('paginationControls');
    if (!result.total_pages || result.total_pages <= 1) {
        controls.style.display = 'none';
        return;
    }
    controls.style.display = 'flex';
    
    let html = `
        <div style="display: flex; align-items: center; gap: 1rem;">
            <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Showing ${result.data.length} of ${result.total_records} services</span>
            <select id="perPage" onchange="fetchRequirements(1)" style="padding: 0.25rem 0.5rem; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 0.75rem; color: #475569; background: #f8fafc; cursor: pointer;">
                <option value="15" ${currentLimit == '15' ? 'selected' : ''}>15 per page</option>
                <option value="25" ${currentLimit == '25' ? 'selected' : ''}>25 per page</option>
                <option value="50" ${currentLimit == '50' ? 'selected' : ''}>50 per page</option>
                <option value="100" ${currentLimit == '100' ? 'selected' : ''}>100 per page</option>
                <option value="all" ${currentLimit === 'all' ? 'selected' : ''}>All</option>
            </select>
        </div>
    `;
    html += `<div style="display: flex; gap: 0.5rem; align-items: center;">`;
    
    if (currentPage > 1) {
        html += `<button onclick="fetchRequirements(${currentPage - 1})" class="btn btn-ghost" style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; color: #0f172a;"><i class="fas fa-chevron-left"></i></button>`;
    }
    
    let start_page = Math.max(1, currentPage - 2);
    let end_page = Math.min(result.total_pages, currentPage + 2);
    
    if (end_page - start_page < 4) {
        if (start_page === 1) {
            end_page = Math.min(result.total_pages, 5);
        } else if (end_page === result.total_pages) {
            start_page = Math.max(1, result.total_pages - 4);
        }
    }
    
    for (let i = start_page; i <= end_page; i++) {
        let isCurrent = i === currentPage;
        let btnClass = isCurrent ? 'btn-primary' : 'btn-ghost';
        let bgStyle = isCurrent ? `background: var(--primary); border: 1px solid var(--primary); color: white;` : `background: transparent; border: 1px solid #e2e8f0; color: #0f172a;`;
        html += `<button onclick="fetchRequirements(${i})" class="btn ${btnClass}" style="padding: 0.5rem 0.8rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; ${bgStyle}">${i}</button>`;
    }
    
    if (currentPage < result.total_pages) {
        html += `<button onclick="fetchRequirements(${currentPage + 1})" class="btn btn-ghost" style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; color: #0f172a;"><i class="fas fa-chevron-right"></i></button>`;
    }
    
    html += `</div>`;
    controls.innerHTML = html;
}

function renderServices(data) {
    const container = document.getElementById('servicesContainer');
    container.innerHTML = '';

    if (!data || data.length === 0) {
        container.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 4rem; color: #94a3b8;">No services found.</td></tr>`;
        return;
    }

    data.forEach(req => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div class="service-name-cell" style="gap: 0.5rem;">
                    <div class="service-icon"><i class="fas fa-concierge-bell"></i></div>
                    <div>
                        <div style="font-weight: 800; font-size: 0.8rem;">${req.name}</div>
                        <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 500;">ID: #${String(req.id).padStart(3, '0')}</div>
                    </div>
                </div>
            </td>
            <td><span class="fee-badge">₹${parseFloat(req.fee).toLocaleString('en-IN')}</span></td>
            <td><div class="doc-list-text" style="font-size: 0.75rem;" title="${req.req_docs || 'No documents listed'}">${req.req_docs || '<span style="color:#cbd5e1">No documents listed</span>'}</div></td>
            <td>
                <button onclick="showCustomers(${req.id}, '${req.name.replace(/'/g, "\\'")}')" class="count-btn">
                    <i class="fas fa-users"></i> ${req.customer_count}
                </button>
            </td>
            <td style="text-align: right;">
                <div style="display: flex; gap: 0.25rem; justify-content: flex-end;">
                    <button class="btn btn-ghost" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" onclick="editRequirement(${req.id})" title="Edit"><i class="fas fa-pen"></i></button>
                    <button class="btn btn-ghost delete" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: #ef4444;" onclick="deleteRequirement(${req.id})" title="Delete"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        `;
        container.appendChild(tr);
    });
}

async function showCustomers(id, name) {
    const modal = document.getElementById('customerListModal');
    const container = document.getElementById('customersContainer');
    document.getElementById('serviceNameLabel').innerText = name;
    
    container.innerHTML = '<div style="text-align:center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Loading customers...</div>';
    modal.style.display = 'flex';
    
    try {
        const response = await fetch(`${apiBase}?id=${id}&action=customers`);
        const customers = await response.json();
        
        if (customers.length === 0) {
            container.innerHTML = '<div style="text-align:center; padding: 3rem; color: #94a3b8;">No customers have taken this service yet.</div>';
            return;
        }
        
        const renderList = (list) => {
            if (list.length === 0) {
                container.innerHTML = '<div style="text-align:center; padding: 3rem; color: #94a3b8;">No matching customers found.</div>';
                return;
            }
            container.innerHTML = list.map(c => `
                <a href="<?= APP_URL ?>/public/index.php/customer_profile?id=${c.id}" class="customer-row">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-weight: 800; color: var(--primary, #6366f1);">
                        ${c.name.charAt(0)}
                    </div>
                    <div>
                        <div style="font-weight: 800; color: #1e293b; font-size: 0.9rem;">${c.name}</div>
                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">${c.mobile}</div>
                    </div>
                    <i class="fas fa-chevron-right" style="margin-left: auto; color: #cbd5e1; font-size: 0.8rem;"></i>
                </a>
            `).join('');
        };

        renderList(customers);

        // Search within the modal
        const searchInput = document.getElementById('modalCustomerSearch');
        searchInput.value = ''; // Reset search
        searchInput.oninput = (e) => {
            const query = e.target.value.toLowerCase();
            const filtered = customers.filter(c => 
                c.name.toLowerCase().includes(query) || 
                c.mobile.toLowerCase().includes(query)
            );
            renderList(filtered);
        };
        
        searchInput.focus();

    } catch (e) {
        container.innerHTML = '<div style="text-align:center; padding: 2rem; color: #ef4444;">Error loading customers.</div>';
    }
}

function closeCustomerModal() { document.getElementById('customerListModal').style.display = 'none'; }

function filterServices() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentSearch = document.getElementById('serviceSearch').value;
        fetchRequirements(1);
    }, 500);
}

function openModal(mode, data = null) {
    const modal = document.getElementById('requirementModal');
    const form = document.getElementById('requirementForm');
    const title = document.getElementById('modalTitle');
    form.reset();
    document.getElementById('requirementId').value = '';
    
    if (mode === 'edit' && data) {
        title.innerText = 'Refine Service';
        document.getElementById('requirementId').value = data.id;
        document.getElementById('name').value = data.name;
        document.getElementById('description').value = data.description || '';
        document.getElementById('req_docs').value = data.req_docs;
        document.getElementById('fee').value = data.fee;
    } else {
        title.innerText = 'Add New Service';
    }
    modal.style.display = 'flex';
}

function closeModal() { document.getElementById('requirementModal').style.display = 'none'; }

async function editRequirement(id) {
    try {
        const response = await fetch(`${apiBase}?id=${id}`);
        const data = await response.json();
        openModal('edit', data);
    } catch (error) {
        alert('Could not retrieve service details.');
    }
}

async function deleteRequirement(id) {
    if (!confirm('Are you sure? This cannot be undone.')) return;
    try {
        const response = await fetch(`${apiBase}?id=${id}`, { method: 'DELETE' });
        const result = await response.json();
        if (result.success) fetchRequirements();
    } catch (error) {
        console.error('Error:', error);
    }
}

document.getElementById('requirementForm').onsubmit = async (e) => {
    e.preventDefault();
    const id = document.getElementById('requirementId').value;
    const method = id ? 'PUT' : 'POST';
    const payload = {
        id: id,
        name: document.getElementById('name').value,
        description: document.getElementById('description').value,
        req_docs: document.getElementById('req_docs').value,
        fee: document.getElementById('fee').value
    };

    try {
        const response = await fetch(apiBase, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.success) {
            closeModal();
            fetchRequirements();
        } else {
            alert(result.error);
        }
    } catch (error) {
        alert('An error occurred.');
    }
};

window.onload = fetchRequirements;

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
}
</script>
</body>
</html>
