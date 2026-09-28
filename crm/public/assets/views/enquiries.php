<?php
use Core\Database;
use Core\Auth;

if (!Auth::isSuperAdmin()) {
    header("Location: " . APP_URL . "/public/index.php/dashboard");
    exit;
}

$db = Database::getInstance()->getConnection();
$enquiries = [];
try {
    $stmt = $db->query("SELECT * FROM contact_enquiries ORDER BY created_at DESC");
    if ($stmt) {
        $enquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // table might not exist yet if no submissions
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Enquiries | Super Admin</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-subtle);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            background: #f8fafc;
            padding: 0.75rem 1.25rem;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .data-table td {
            padding: 1rem 1.25rem;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #334155;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .data-table tr:hover td {
            background: #f8fafc;
        }
        .data-table tr:last-child td {
            border-bottom: none;
        }
        .msg-excerpt {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #64748b;
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <?php include 'partials/topbar.php'; ?>

        <div class="header">
            <h1 class="page-title">Website Enquiries</h1>
        </div>

        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($enquiries)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:2rem; color:#94a3b8;">
                            <i class="fas fa-inbox" style="font-size:2rem; margin-bottom:0.5rem; opacity:0.5; display:block;"></i>
                            No enquiries found yet.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($enquiries as $enq): ?>
                        <tr>
                            <td style="color:#64748b; font-size:0.75rem;">
                                <?= date('d M, Y', strtotime($enq['created_at'])) ?>
                            </td>
                            <td style="font-weight:700; color:#0f172a;">
                                <?= htmlspecialchars($enq['first_name'] . ' ' . $enq['last_name']) ?>
                            </td>
                            <td>
                                <a href="tel:<?= htmlspecialchars($enq['mobile']) ?>" style="color:var(--primary); text-decoration:none;">
                                    <?= htmlspecialchars($enq['mobile']) ?>
                                </a>
                            </td>
                            <td>
                                <a href="mailto:<?= htmlspecialchars($enq['email']) ?>" style="color:var(--primary); text-decoration:none;">
                                    <?= htmlspecialchars($enq['email']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge" style="background:#f1f5f9; color:#475569;">
                                    <?= htmlspecialchars($enq['subject'] ?: 'None') ?>
                                </span>
                            </td>
                            <td>
                                <div class="msg-excerpt"><?= htmlspecialchars($enq['message']) ?></div>
                            </td>
                            <td>
                                <button onclick="viewMessage(<?= htmlspecialchars(json_encode([
                                    'name' => $enq['first_name'] . ' ' . $enq['last_name'],
                                    'email' => $enq['email'],
                                    'subject' => $enq['subject'],
                                    'date' => date('d M, Y h:i A', strtotime($enq['created_at'])),
                                    'message' => $enq['message']
                                ])) ?>)" class="btn btn-ghost" style="padding:0.35rem 0.6rem;">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button onclick="openFollowup(<?= $enq['id'] ?>, '<?= htmlspecialchars(addslashes($enq['first_name'] . ' ' . $enq['last_name'])) ?>')" class="btn btn-ghost" style="padding:0.35rem 0.6rem; color:var(--primary);">
                                    <i class="fas fa-comment-dots"></i> Follow-up
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- View Message Modal -->
<div id="viewModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 500px;">
        <div style="padding:1.25rem 1.5rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.1rem; color:#0f172a;">Enquiry Details</h3>
            <button onclick="document.getElementById('viewModal').style.display='none'" style="background:none;border:none;font-size:1.2rem;color:#94a3b8;cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <div style="padding:1.5rem;">
            <div style="margin-bottom:1rem; display:flex; justify-content:space-between;">
                <div>
                    <div style="font-size:0.7rem; font-weight:700; color:#64748b; text-transform:uppercase;">From</div>
                    <div id="vName" style="font-weight:700; color:#0f172a;"></div>
                    <a id="vEmail" href="#" style="font-size:0.8rem; color:var(--primary); text-decoration:none;"></a>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:0.7rem; font-weight:700; color:#64748b; text-transform:uppercase;">Received</div>
                    <div id="vDate" style="font-size:0.8rem; color:#475569; font-weight:600;"></div>
                </div>
            </div>
            <div style="margin-bottom:1rem;">
                <div style="font-size:0.7rem; font-weight:700; color:#64748b; text-transform:uppercase;">Subject</div>
                <div id="vSubject" style="font-weight:600; color:#334155; padding:0.4rem; background:#f8fafc; border-radius:6px; margin-top:0.2rem;"></div>
            </div>
            <div>
                <div style="font-size:0.7rem; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:0.4rem;">Message</div>
                <div id="vMessage" style="font-size:0.9rem; color:#334155; line-height:1.6; white-space:pre-wrap; background:#f8fafc; padding:1rem; border-radius:8px; border:1px solid #e2e8f0;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Followup Modal -->
<div id="followupModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 480px; display:flex; flex-direction:column; overflow:hidden;">
        <!-- Header -->
        <div style="padding:1rem 1.25rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; background:#fff;">
            <div>
                <h3 style="margin:0; font-size:1.05rem; color:#0f172a; font-weight:700;">Manage Follow-ups</h3>
                <div id="fEnquiryName" style="font-size:0.75rem; font-weight:600; color:#64748b; margin-top:0.2rem;"></div>
            </div>
            <button onclick="document.getElementById('followupModal').style.display='none'" style="background:none;border:none;font-size:1.1rem;color:#94a3b8;cursor:pointer;transition:color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'"><i class="fas fa-times"></i></button>
        </div>
        
        <!-- List -->
        <div id="followupList" style="padding:1rem 1.25rem; max-height:300px; overflow-y:auto; display:flex; flex-direction:column; gap:0.75rem; background:#f8fafc;">
            <div style="text-align:center; color:#94a3b8; padding:1rem; font-size:0.85rem;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>

        <!-- Input Area -->
        <div style="padding:1rem 1.25rem; border-top:1px solid var(--border); background:#fff;">
            <form id="followupForm" onsubmit="submitFollowup(event)" style="margin:0; display:flex; flex-direction:column; gap:0.75rem;">
                <input type="hidden" id="fEnquiryId">
                <textarea id="fRemark" rows="2" placeholder="Write a follow-up remark..." required style="width:100%; padding:0.75rem 0.875rem; border:1px solid #cbd5e1; border-radius:8px; font-family:inherit; font-size:0.875rem; color:#334155; resize:none; outline:none; box-sizing:border-box; transition:all 0.2s; line-height:1.4;" onfocus="this.style.borderColor='var(--primary)'; this.style.boxShadow='0 0 0 3px var(--primary-light)'" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none'"></textarea>
                <div style="display:flex; justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary" style="padding:0.5rem 1.25rem; font-size:0.85rem; font-weight:600; border-radius:6px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">Save Follow-up</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewMessage(data) {
    document.getElementById('vName').textContent = data.name;
    document.getElementById('vEmail').textContent = data.email;
    document.getElementById('vEmail').href = 'mailto:' + data.email;
    document.getElementById('vSubject').textContent = data.subject || 'No Subject';
    document.getElementById('vDate').textContent = data.date;
    document.getElementById('vMessage').textContent = data.message;
    document.getElementById('viewModal').style.display = 'flex';
}

async function openFollowup(id, name) {
    document.getElementById('fEnquiryId').value = id;
    document.getElementById('fEnquiryName').textContent = "Enquiry from: " + name;
    document.getElementById('followupModal').style.display = 'flex';
    document.getElementById('followupList').innerHTML = '<div style="text-align:center; color:#94a3b8; padding:1rem;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    await loadFollowups(id);
}

async function loadFollowups(id) {
    try {
        const response = await fetch('<?= APP_URL ?>/public/index.php/api/enquiry_followups.php?enquiry_id=' + id);
        const data = await response.json();
        
        let html = '';
        if (data.length === 0) {
            html = '<div style="text-align:center; color:#94a3b8; font-size:0.875rem; padding:1rem;">No follow-ups recorded yet.</div>';
        } else {
            data.forEach(f => {
                const date = new Date(f.created_at).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute:'2-digit' });
                html += `
                    <div style="background:#fff; padding:1rem; border-radius:8px; border:1px solid #e2e8f0; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                        <div style="font-size:0.9rem; color:#334155; margin-bottom:0.5rem; white-space:pre-wrap;">${f.remark}</div>
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem;">
                            <span style="color:#64748b;"><i class="fas fa-user-circle" style="margin-right:0.25rem;"></i>${f.user_name || 'Admin'}</span>
                            <span style="color:#94a3b8;"><i class="far fa-clock" style="margin-right:0.25rem;"></i>${date}</span>
                        </div>
                    </div>
                `;
            });
        }
        document.getElementById('followupList').innerHTML = html;
    } catch(e) {
        document.getElementById('followupList').innerHTML = '<div style="color:red; text-align:center;">Failed to load follow-ups.</div>';
    }
}

async function submitFollowup(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    const ogText = btn.textContent;
    btn.textContent = "Saving...";
    btn.disabled = true;

    const enquiry_id = document.getElementById('fEnquiryId').value;
    const remark = document.getElementById('fRemark').value;

    try {
        const response = await fetch('<?= APP_URL ?>/public/index.php/api/enquiry_followups.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ enquiry_id, remark })
        });
        
        const data = await response.json();
        if (data.success) {
            document.getElementById('fRemark').value = '';
            await loadFollowups(enquiry_id);
        } else {
            alert(data.error || 'Failed to save follow-up');
        }
    } catch(e) {
        alert('An error occurred');
    }

    btn.textContent = ogText;
    btn.disabled = false;
}

// Close modals on outside click
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if(e.target === this) this.style.display = 'none';
    });
});
</script>
</body>
</html>
