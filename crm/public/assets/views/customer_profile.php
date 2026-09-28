<?php
use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$company_id = Auth::companyId();
$customer_id = $_GET['id'] ?? null;

if (!$customer_id) {
    header("Location: " . APP_URL . "/public/index.php/customers");
    exit;
}

// Fetch initial data
$customer = $db->fetchOne("SELECT * FROM customers WHERE id = ? AND company_id = ?", [$customer_id, $company_id]);
if (!$customer) {
    die("Customer not found.");
}

// Executive Access Check
if (Auth::isExecutive()) {
    $userId = Auth::userId();
    $empId = Auth::employeeId();

    // Fetch ANY lead that matches this customer (by ID or mobile)
    $checkSql = "SELECT assigned_to, assigned_employee_id, created_by, mobile, customer_id 
                 FROM leads 
                 WHERE (customer_id = ? OR mobile = ?) 
                 AND company_id = ?";
    $leads = $db->fetchAll($checkSql, [$customer_id, $customer['mobile'] ?? '', $company_id]);

    foreach ($leads as $l) {
        if ($l['assigned_to'] == $userId || $l['assigned_employee_id'] == $empId) {
            $hasAccess = true;
            break;
        }
    }

    if (!$hasAccess) {
        die("Access denied to this customer profile. (Debug: UID=$userId, EMP=$empId, CID=$customer_id, LeadsChecked=" . count($leads) . ")");
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Profile | <?= htmlspecialchars($customer['name'] ?? '') ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, var(--primary, #6366f1) 0%, var(--primary-hover, #4f46e5) 100%);
            --glass: rgba(255, 255, 255, 0.95);
            --border: #e2e8f0;
            --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        body {
            background-color: #f8fafc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .profile-container {
            max-width: 95%;
            margin: 0 auto;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-header {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-radius: 2rem;
            padding: 2.5rem;
            display: flex;
            align-items: center;
            gap: 3rem;
            border: 1px solid white;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2.5rem;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: var(--primary-gradient);
            filter: blur(100px);
            opacity: 0.05;
            z-index: 0;
            pointer-events: none;
        }

        .profile-photo-wrapper {
            position: relative;
            width: 160px;
            height: 160px;
            z-index: 1;
        }

        .profile-photo {
            width: 100%;
            height: 100%;
            border-radius: 2.5rem;
            object-fit: cover;
            border: 4px solid white;
            background: #f1f5f9;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .profile-photo:hover {
            transform: scale(1.02);
        }

        .photo-actions {
            position: absolute;
            bottom: -5px;
            right: -5px;
            display: flex;
            gap: 0.75rem;
        }

        .photo-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .photo-btn.edit {
            background: var(--primary-gradient);
            color: white;
        }

        .photo-btn.delete {
            background: white;
            color: #ef4444;
            border: 1px solid #fee2e2;
        }

        .photo-btn:hover {
            transform: translateY(-2px) scale(1.1);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .section-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 2rem;
        }

        .section-card {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem;
            border: 1px solid #f1f5f9;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            transition: all 0.3s ease;
        }

        .section-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            border: 1.5px solid #f1f5f9;
            background: #f8fafc;
            font-weight: 600;
            color: #1e293b;
            transition: all 0.2s;
        }

        .form-input:focus {
            border-color: var(--primary, #6366f1);
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        .doc-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 1rem;
            margin-bottom: 1rem;
            border: 1.5px solid transparent;
            transition: all 0.2s;
        }

        .doc-item:hover {
            background: white;
            border-color: #e2e8f0;
            transform: translateX(4px);
        }

        .doc-icon {
            width: 44px;
            height: 44px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary, #6366f1);
            font-size: 1.25rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .btn-save {
            width: 100%;
            background: var(--primary-gradient);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            font-weight: 800;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1.5rem;
        }

        .modal-content {
            background: white;
            border-radius: 1.5rem;
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: modalPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes modalPop {
            from {
                transform: scale(0.9);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .btn-ledger-alt {
            background: #eff6ff;
            color: #2563eb;
            padding: 0.6rem 1.25rem;
            border-radius: 10px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            border: 1px solid #dbeafe;
        }

        .btn-ledger-alt:hover {
            background: #dbeafe;
            transform: translateY(-1px);
        }

        .customer-id-badge {
            font-size: 0.7rem;
            background: #f1f5f9;
            color: #64748b;
            padding: 2px 8px;
            border-radius: 6px;
            font-weight: 800;
            margin-top: 0.5rem;
            display: inline-block;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>
        <main class="main-content">
            <?php include 'partials/topbar.php'; ?>
            <header class="header" style="margin-bottom: 2rem;">
                <div style="display:flex; align-items:center; gap: 1.5rem;">
                    <div>
                        <h1 class="page-title" style="margin:0">Customer Profile</h1>
                        <p style="font-size: 0.8125rem; color: #64748b; font-weight: 600;">Manage client details and
                            documentation</p>
                    </div>
                </div>
            </header>

            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-photo-wrapper">
                        <img id="customerPhoto"
                            src="<?= !empty($customer['photo']) ? APP_URL . '/public/uploads/customers/photos/' . $customer['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($customer['name'] ?? 'Unknown') . '&size=160&background=' . ($hex ?? '6366f1') . '&color=ffffff&bold=true' ?>"
                            class="profile-photo">
                        <div class="photo-actions">
                            <button onclick="document.getElementById('photoInput').click()" class="photo-btn edit"
                                title="Change Photo"><i class="fas fa-camera"></i></button>
                            <?php if (!empty($customer['photo'])): ?>
                                <button onclick="deletePhoto()" class="photo-btn delete" title="Delete Photo"><i
                                        class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="photoInput" style="display:none" onchange="uploadPhoto(this)">
                    </div>
                    <div style="flex:1">
                        <div class="customer-id-badge">CLIENT ID: #<?= str_pad($customer['id'], 5, '0', STR_PAD_LEFT) ?>
                        </div>
                        <h2
                            style="font-size: 2.25rem; font-weight: 900; color: #0f172a; margin-top: 0.5rem; letter-spacing: -0.02em;">
                            <?= htmlspecialchars($customer['name'] ?? '') ?>
                        </h2>
                        <div style="display:flex; gap: 1.5rem; margin-top: 0.75rem;">
                            <p style="color: #475569; font-weight: 700; display:flex; align-items:center; gap:0.5rem;">
                                <i class="fas fa-phone-alt"
                                    style="color: var(--primary, #6366f1); font-size: 0.8rem;"></i>
                                <?= htmlspecialchars($customer['mobile'] ?? '') ?>
                            </p>
                            <?php if (!empty($customer['email'])): ?>
                                <p style="color: #475569; font-weight: 700; display:flex; align-items:center; gap:0.5rem;">
                                    <i class="fas fa-envelope"
                                        style="color: var(--primary, #6366f1); font-size: 0.8rem;"></i>
                                    <?= htmlspecialchars($customer['email'] ?? '') ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <a href="<?= APP_URL ?>/public/index.php/lead_ledger?id=<?= $customer_id ?>"
                            class="btn-ledger-alt">
                            <i class="fas fa-receipt"></i> Lead Ledger
                        </a>
                    </div>
                </div>

                <div class="section-grid">
                    <div>
                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-user-edit" style="color: var(--primary, #6366f1);"></i>
                                Edit Profile
                            </div>
                            <form id="profileForm" onsubmit="updateProfile(event)">
                                <div class="form-group">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="name" class="form-input"
                                        value="<?= htmlspecialchars($customer['name'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Mobile Number</label>
                                    <input type="text" name="mobile" class="form-input"
                                        value="<?= htmlspecialchars($customer['mobile'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-input"
                                        value="<?= htmlspecialchars($customer['email'] ?? '') ?>"
                                        placeholder="example@mail.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Physical Address</label>
                                    <textarea name="address" class="form-input" rows="3" style="resize:none"
                                        placeholder="Complete address..."><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                                </div>
                                <button type="submit" class="btn-save">Update Profile Information</button>
                            </form>
                        </div>
                    </div>

                    <div>
                        <div class="section-card">
                            <div
                                style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                                <div class="section-title" style="margin-bottom: 0;">
                                    <i class="fas fa-folder-open" style="color: var(--primary, #6366f1);"></i>
                                    Document Repository
                                </div>
                                <button onclick="showDocModal()" class="btn-ledger-alt"
                                    style="background: var(--primary-gradient); color: white; border: none;">
                                    <i class="fas fa-plus-circle"></i> New Document
                                </button>
                            </div>

                            <div id="docList" style="max-height: 400px; overflow-y: auto; padding-right: 4px;">
                                <!-- Loaded by JS -->
                                <div style="text-align:center; padding: 3rem; color: #94a3b8;">
                                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Notes Repository -->
                        <div class="section-card" style="margin-top: 1.5rem;">
                            <div
                                style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                                <div class="section-title" style="margin-bottom: 0;">
                                    <i class="fas fa-sticky-note" style="color: #f59e0b;"></i>
                                    Notes Repository
                                </div>
                                <button onclick="showNoteModal()" class="btn-ledger-alt"
                                    style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border: none;">
                                    <i class="fas fa-plus-circle"></i> Add Note
                                </button>
                            </div>

                            <div id="noteList" style="max-height: 400px; overflow-y: auto; padding-right: 4px;">
                                <div style="text-align:center; padding: 2rem; color: #94a3b8;">
                                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Doc Modal -->
    <div id="docModal" class="modal-overlay">
        <div class="modal-content">
            <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h3 style="font-size: 1.25rem; font-weight: 900; color: #0f172a;">Upload Document</h3>
                <button onclick="closeDocModal()"
                    style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.25rem;"><i
                        class="fas fa-times"></i></button>
            </div>
            <form id="docForm" onsubmit="addDoc(event)">
                <div class="form-group">
                    <label class="form-label">Document Title</label>
                    <input type="text" name="doc_name" class="form-input" placeholder="e.g. GST Certificate, ID Proof"
                        required>
                </div>
                <div class="form-group">
                    <label class="form-label">Select File</label>
                    <div style="position:relative;">
                        <input type="file" name="attachment" class="form-input" required
                            style="padding: 2rem; border: 2px dashed #e2e8f0; background: #f8fafc; text-align: center;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 2rem;">
                    <button type="button" onclick="closeDocModal()" class="btn-ledger-alt"
                        style="justify-content: center; background: #f1f5f9; color: #64748b; border: none;">Cancel</button>
                    <button type="submit" class="btn-save" style="padding: 0.75rem;">Upload Now</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div id="noteModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div
                style="padding: 1.5rem; border-bottom: 1px solid #f1f5f9; display:flex; justify-content: space-between; align-items: center;">
                <h3 style="font-weight: 800; color: #1e293b;">Add New Note</h3>
                <button onclick="closeNoteModal()" class="btn-ledger-alt"
                    style="width: 32px; height: 32px; padding: 0; justify-content: center; border-radius: 50%;"><i
                        class="fas fa-times"></i></button>
            </div>
            <form onsubmit="addNote(event)" style="padding: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Internal Note</label>
                    <textarea name="note" class="form-input" required rows="5" style="resize:none"
                        placeholder="Enter notes here..."></textarea>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 2rem;">
                    <button type="button" onclick="closeNoteModal()" class="btn-ledger-alt"
                        style="justify-content: center; background: #f1f5f9; color: #64748b; border: none;">Cancel</button>
                    <button type="submit" class="btn-save" style="padding: 0.75rem; background: #f59e0b;">Save
                        Note</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const customerId = <?= $customer_id ?>;
        const apiUrl = '<?= APP_URL ?>/api/customer_profile.php';

        async function loadProfile() {
            try {
                const res = await fetch(`${apiUrl}?id=${customerId}`);
                const data = await res.json();
                if (data.docs) renderDocs(data.docs);
                if (data.notes) renderNotes(data.notes);
            } catch (e) {
                console.error(e);
            }
        }

        function renderDocs(docs) {
            const list = document.getElementById('docList');
            if (docs.length === 0) {
                list.innerHTML = `
                    <div style="text-align:center; padding: 4rem 2rem; background: #f8fafc; border-radius: 1.5rem; border: 2px dashed #e2e8f0;">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; display: block;"></i>
                        <p style="color: #64748b; font-weight: 700;">No documents uploaded yet.</p>
                        <p style="color: #94a3b8; font-size: 0.8rem; margin-top: 0.5rem;">Keep all customer KYC and files in one place.</p>
                    </div>
                `;
                return;
            }
            list.innerHTML = docs.map(doc => `
                <div class="doc-item">
                    <div style="display:flex; align-items:center; gap: 1rem;">
                        <div class="doc-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div>
                            <div style="font-weight: 800; color: #1e293b; font-size: 0.95rem;">${doc.doc_name}</div>
                            <div style="color: #94a3b8; font-size: 0.75rem; font-weight: 700; margin-top: 0.1rem;">
                                <i class="far fa-calendar-alt"></i> ${new Date(doc.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; gap: 0.5rem;">
                        <a href="<?= APP_URL ?>/public/uploads/customers/docs/${doc.attachment}" target="_blank" class="photo-btn edit" style="background: #f1f5f9; color: var(--primary, #6366f1); box-shadow:none;" title="View Document">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="<?= APP_URL ?>/public/uploads/customers/docs/${doc.attachment}" download="${doc.doc_name}" class="photo-btn edit" style="background: #f1f5f9; color: #10b981; box-shadow:none;" title="Download Document">
                            <i class="fas fa-download"></i>
                        </a>
                        <button onclick="deleteDoc(${doc.id})" class="photo-btn delete" style="box-shadow:none;" title="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        }

        async function updateProfile(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const formData = new FormData(e.target);
            formData.append('action', 'update_profile');
            formData.append('customer_id', customerId);

            try {
                const res = await fetch(apiUrl, { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    alert('Profile updated successfully!');
                    location.reload();
                } else {
                    alert(data.error || 'Update failed');
                }
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        }

        async function uploadPhoto(input) {
            if (!input.files[0]) return;
            const formData = new FormData();
            formData.append('action', 'upload_photo');
            formData.append('customer_id', customerId);
            formData.append('photo', input.files[0]);

            const res = await fetch(apiUrl, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Upload failed');
            }
        }

        async function deletePhoto() {
            if (!confirm('Permanently remove profile photo?')) return;
            const formData = new FormData();
            formData.append('action', 'delete_photo');
            formData.append('customer_id', customerId);

            const res = await fetch(apiUrl, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                location.reload();
            }
        }

        function showDocModal() { document.getElementById('docModal').style.display = 'flex'; }
        function closeDocModal() { document.getElementById('docModal').style.display = 'none'; }

        async function addDoc(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

            const formData = new FormData(e.target);
            formData.append('action', 'add_doc');
            formData.append('customer_id', customerId);

            try {
                const res = await fetch(apiUrl, { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    closeDocModal();
                    loadProfile();
                } else {
                    alert(data.error || 'Upload failed');
                }
            } finally {
                btn.disabled = false;
                btn.textContent = 'Upload Now';
            }
        }

        function renderNotes(notes) {
            const list = document.getElementById('noteList');
            if (notes.length === 0) {
                list.innerHTML = `
                    <div style="text-align:center; padding: 3rem 1rem; background: #fffbeb; border-radius: 1.5rem; border: 2px dashed #fcd34d;">
                        <i class="fas fa-sticky-note" style="font-size: 2.5rem; color: #fef3c7; margin-bottom: 1rem; display: block;"></i>
                        <p style="color: #92400e; font-weight: 700;">No notes found.</p>
                        <p style="color: #d97706; font-size: 0.75rem; margin-top: 0.5rem;">Add internal observations or client history.</p>
                    </div>
                `;
                return;
            }
            list.innerHTML = notes.map(note => `
                <div class="doc-item" style="align-items: flex-start; background: #fffdfa; border-left: 4px solid #f59e0b;">
                    <div style="flex:1">
                        <div style="color: #475569; font-size: 0.9rem; line-height: 1.5; white-space: pre-wrap;">${note.note}</div>
                        <div style="color: #94a3b8; font-size: 0.7rem; font-weight: 700; margin-top: 0.75rem;">
                            <i class="far fa-clock"></i> ${new Date(note.created_at).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                        </div>
                    </div>
                    <button onclick="deleteNote(${note.id})" class="photo-btn delete" style="box-shadow:none; background: #fff5f5; color: #ef4444;" title="Delete Note">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `).join('');
        }

        function showNoteModal() { document.getElementById('noteModal').style.display = 'flex'; }
        function closeNoteModal() { document.getElementById('noteModal').style.display = 'none'; }

        async function addNote(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            btn.disabled = true;

            const formData = new FormData(e.target);
            formData.append('action', 'add_note');
            formData.append('customer_id', customerId);

            try {
                const res = await fetch(apiUrl, { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    closeNoteModal();
                    e.target.reset();
                    loadProfile();
                } else {
                    alert(data.error || 'Failed to add note');
                }
            } finally {
                btn.disabled = false;
            }
        }

        async function deleteNote(id) {
            if (!confirm('Are you sure you want to delete this note?')) return;
            const formData = new FormData();
            formData.append('action', 'delete_note');
            formData.append('customer_id', customerId);
            formData.append('note_id', id);

            const res = await fetch(apiUrl, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) loadProfile();
        }
        async function deleteDoc(docId) {
            if (!confirm('Delete this document?')) return;
            const formData = new FormData();
            formData.append('action', 'delete_doc');
            formData.append('customer_id', customerId);
            formData.append('doc_id', docId);

            const res = await fetch(apiUrl, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                loadProfile();
            }
        }

        loadProfile();
    </script>
</body>

</html>