<?php
use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$company_id = Auth::companyId();
$isExecutive = Auth::isExecutive();
$executiveEmployeeId = Auth::employeeId();

$filter = $_GET['filter'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$stage_filter = $_GET['stage'] ?? 'all';
$today = date('Y-m-d');

$employees = $db->fetchAll("SELECT id, name FROM employees WHERE company_id = ? ORDER BY name", [$company_id]);
$users = $db->fetchAll("SELECT id, name FROM users WHERE company_id = ?", [$company_id]);

$month = $_GET['month'] ?? '';
$year = $_GET['year'] ?? '';

// Base conditions for Tasks
$whereClause = "l.company_id = :company_id AND (l.assigned_employee_id IS NOT NULL OR l.status = 'won') AND l.status != 'lost'";
$params = [':company_id' => $company_id];

if (!empty($month)) {
    $whereClause .= " AND l.month = :month";
    $params[':month'] = $month;
}
if (!empty($year)) {
    $whereClause .= " AND l.year = :year";
    $params[':year'] = $year;
}
if ($isExecutive) {
    $whereClause .= " AND (l.assigned_employee_id = :emp_id OR l.assigned_to = :user_id)";
    $params[':emp_id'] = $executiveEmployeeId ?: 0;
    $params[':user_id'] = Auth::userId();
}

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $whereClause .= " AND (l.name LIKE :search1 OR l.mobile LIKE :search2)";
    $params[':search1'] = "%$search%";
    $params[':search2'] = "%$search%";
}

// Normalized status and date expressions for SQL
$normStatusExpr = "CASE WHEN l.task_status = 'done' THEN 'work_done' WHEN l.task_status = 'delay' THEN 'work_pending' WHEN l.task_status = 'pending' THEN 'not_started' WHEN l.task_status IS NULL OR l.task_status = '' THEN 'not_started' ELSE l.task_status END";
$dateExpr = "COALESCE(l.expected_delivery_date, l.follow_up_date)";

// Calculate counts efficiently in one query (for the tabs)
$countQuery = "SELECT 
    COUNT(*) as all_count,
    SUM(CASE WHEN $dateExpr = '$today' AND $normStatusExpr != 'work_done' THEN 1 ELSE 0 END) as today_count,
    SUM(CASE WHEN $dateExpr < '$today' AND $normStatusExpr != 'work_done' THEN 1 ELSE 0 END) as overdue_count,
    SUM(CASE WHEN $dateExpr > '$today' AND $normStatusExpr != 'work_done' THEN 1 ELSE 0 END) as upcoming_count,
    SUM(CASE WHEN $normStatusExpr = 'work_done' THEN 1 ELSE 0 END) as completed_count,
    
    SUM(CASE WHEN $normStatusExpr = 'not_started' THEN 1 ELSE 0 END) as not_started_count,
    SUM(CASE WHEN $normStatusExpr = 'work_in_progress' THEN 1 ELSE 0 END) as wip_count,
    SUM(CASE WHEN $normStatusExpr = 'work_pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN $normStatusExpr = 'work_done' THEN 1 ELSE 0 END) as done_count,
    
    SUM(CASE WHEN EXISTS(SELECT 1 FROM invoices WHERE lead_id = l.id) THEN 1 ELSE 0 END) as invoiced_count,
    SUM(CASE WHEN EXISTS(SELECT 1 FROM quotations WHERE lead_id = l.id) AND NOT EXISTS(SELECT 1 FROM invoices WHERE lead_id = l.id) THEN 1 ELSE 0 END) as quotation_count,
    SUM(CASE WHEN NOT EXISTS(SELECT 1 FROM quotations WHERE lead_id = l.id) AND NOT EXISTS(SELECT 1 FROM invoices WHERE lead_id = l.id) THEN 1 ELSE 0 END) as lead_stage_count
FROM leads l WHERE $whereClause";

$counts = $db->fetchOne($countQuery, $params);

$counts = [
    'all_count' => (int) ($counts['all_count'] ?? 0),
    'today_count' => (int) ($counts['today_count'] ?? 0),
    'overdue_count' => (int) ($counts['overdue_count'] ?? 0),
    'upcoming_count' => (int) ($counts['upcoming_count'] ?? 0),
    'completed_count' => (int) ($counts['completed_count'] ?? 0),
    
    'not_started_count' => (int) ($counts['not_started_count'] ?? 0),
    'wip_count' => (int) ($counts['wip_count'] ?? 0),
    'pending_count' => (int) ($counts['pending_count'] ?? 0),
    'done_count' => (int) ($counts['done_count'] ?? 0),
    
    'invoiced_count' => (int) ($counts['invoiced_count'] ?? 0),
    'quotation_count' => (int) ($counts['quotation_count'] ?? 0),
    'lead_stage_count' => (int) ($counts['lead_stage_count'] ?? 0),
];

// Apply top filter
if ($filter === 'today') {
    $whereClause .= " AND $dateExpr = '$today' AND $normStatusExpr != 'work_done'";
} elseif ($filter === 'overdue') {
    $whereClause .= " AND $dateExpr < '$today' AND $normStatusExpr != 'work_done'";
} elseif ($filter === 'upcoming') {
    $whereClause .= " AND $dateExpr > '$today' AND $normStatusExpr != 'work_done'";
} elseif ($filter === 'completed') {
    $whereClause .= " AND $normStatusExpr = 'work_done'";
}

// Apply status filter
if ($status_filter !== 'all') {
    if ($status_filter === 'not_started') {
        $whereClause .= " AND $normStatusExpr = 'not_started'";
    } elseif ($status_filter === 'work_in_progress') {
        $whereClause .= " AND $normStatusExpr = 'work_in_progress'";
    } elseif ($status_filter === 'work_pending') {
        $whereClause .= " AND $normStatusExpr = 'work_pending'";
    } elseif ($status_filter === 'work_done') {
        $whereClause .= " AND $normStatusExpr = 'work_done'";
    }
}

// Apply stage filter
if ($stage_filter !== 'all') {
    if ($stage_filter === 'invoiced') {
        $whereClause .= " AND EXISTS(SELECT 1 FROM invoices WHERE lead_id = l.id)";
    } elseif ($stage_filter === 'quotation') {
        $whereClause .= " AND EXISTS(SELECT 1 FROM quotations WHERE lead_id = l.id) AND NOT EXISTS(SELECT 1 FROM invoices WHERE lead_id = l.id)";
    } elseif ($stage_filter === 'lead_stage') {
        $whereClause .= " AND NOT EXISTS(SELECT 1 FROM quotations WHERE lead_id = l.id) AND NOT EXISTS(SELECT 1 FROM invoices WHERE lead_id = l.id)";
    }
}

// Pagination setup
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit_val = $_GET['limit'] ?? '15';
$limit = ($limit_val === 'all') ? 1000000 : max(1, (int)$limit_val);
$offset = ($page - 1) * $limit;

// Calculate total items with ALL filters applied
$total_items = (int) ($db->fetchOne("SELECT COUNT(*) as cnt FROM leads l WHERE $whereClause", $params)['cnt'] ?? 0);
$total_pages = max(1, ceil($total_items / $limit));

$orderByClause = $isExecutive ? "l.updated_at DESC" : "l.status = 'won' DESC, l.follow_up_date ASC, l.created_at DESC";

$sql = "
    SELECT l.*, e.name as assigned_user,
    (SELECT remark FROM lead_followups WHERE lead_id = l.id AND remark LIKE 'Task Status changed to %' ORDER BY created_at DESC LIMIT 1) as latest_task_remark,
    (SELECT u.name FROM lead_followups lf JOIN users u ON lf.user_id = u.id WHERE lf.lead_id = l.id AND lf.remark LIKE 'Task Status changed to %' ORDER BY lf.created_at DESC LIMIT 1) as latest_task_user_name,
    (CASE 
        WHEN EXISTS(SELECT 1 FROM invoices WHERE lead_id = l.id) THEN 'Invoiced'
        WHEN EXISTS(SELECT 1 FROM quotations WHERE lead_id = l.id) THEN 'Quotation'
        ELSE 'Lead Stage'
    END) as invoice_stage,
    (SELECT i.id
     FROM invoices i
     WHERE i.lead_id = l.id AND i.company_id = l.company_id
     ORDER BY i.created_at DESC, i.id DESC
     LIMIT 1) as latest_invoice_id,
    (SELECT GROUP_CONCAT(i.description SEPARATOR '||')
     FROM invoices i
     WHERE i.lead_id = l.id AND i.company_id = l.company_id AND i.description IS NOT NULL AND i.description != '') as invoice_descriptions,
    (SELECT GROUP_CONCAT(r.name SEPARATOR ', ') FROM requirements r JOIN lead_requirements lr ON r.id = lr.requirement_id WHERE lr.lead_id = l.id) as service_names
    FROM leads l
    LEFT JOIN employees e ON l.assigned_employee_id = e.id
    WHERE $whereClause
    ORDER BY $orderByClause
    LIMIT $limit OFFSET $offset
";

$display_items = $db->fetchAll($sql, $params);

// If there's no follow-up date, we just consider it an open task, but for today/overdue filters we use follow_up_date.
function normalizedTaskStatus(?string $status): string
{
    return [
        'done' => 'work_done',
        'delay' => 'work_pending',
        'pending' => 'not_started',
        '' => 'not_started',
    ][$status ?? ''] ?? $status;
}

function taskStatusLabel(?string $status): string
{
    return [
        'work_done' => 'WORK DONE',
        'work_in_progress' => 'WORK IN PROGRESS',
        'work_pending' => 'WORK PENDING',
        'not_started' => 'NOT STARTED',
    ][normalizedTaskStatus($status)] ?? strtoupper(str_replace('_', ' ', $status ?? 'not_started'));
}

function taskStatusStyle(?string $status): string
{
    return [
        'work_done' => 'background: #dcfce7; color: #166534;',
        'work_in_progress' => 'background: #fef3c7; color: #92400e;',
        'work_pending' => 'background: #fee2e2; color: #991b1b;',
        'not_started' => 'background: #ffffff; color: #334155;',
    ][normalizedTaskStatus($status)] ?? 'background: #ffffff; color: #334155;';
}

function taskRowStyle(?string $status): string
{
    return [
        'work_done' => 'background: #ffffff; border-left: 4px solid #22c55e;',
        'work_in_progress' => 'background: #ffffff; border-left: 4px solid #f59e0b;',
        'work_pending' => 'background: #ffffff; border-left: 4px solid #ef4444;',
        'not_started' => 'background: #ffffff; border-left: 4px solid #cbd5e1;',
    ][normalizedTaskStatus($status)] ?? 'background: #ffffff; border-left: 4px solid #cbd5e1;';
}

function taskPendingRemark(?string $remark): string
{
    $remark = trim($remark ?? '');
    if ($remark === '') {
        return '';
    }

    if (preg_match('/^Task Status changed to WORK PENDING:\s*(.*)$/i', $remark, $matches)) {
        return trim($matches[1]);
    }

    return $remark;
}

function taskServiceNames(array $item): array
{
    $services = [];

    if (!empty($item['invoice_descriptions'])) {
        foreach (explode('||', $item['invoice_descriptions']) as $description) {
            $invoiceItems = json_decode($description, true);
            if (!is_array($invoiceItems)) {
                continue;
            }

            foreach ($invoiceItems as $invoiceItem) {
                if (is_array($invoiceItem)) {
                    $name = trim($invoiceItem['name'] ?? $invoiceItem['service_name'] ?? $invoiceItem['title'] ?? $invoiceItem['label'] ?? '');
                    if ($name !== '') {
                        $services[] = $name;
                    }
                } elseif (is_string($invoiceItem) && trim($invoiceItem) !== '') {
                    $services[] = trim($invoiceItem);
                }
            }
        }
    }

    if (!empty($item['service_names'])) {
        foreach (explode(',', $item['service_names']) as $service) {
            $service = trim($service);
            if ($service !== '') {
                $services[] = $service;
            }
        }
    }

    if (!empty($item['custom_services'])) {
        $customServices = json_decode($item['custom_services'], true);
        if (is_array($customServices)) {
            foreach ($customServices as $service) {
                if (is_array($service)) {
                    $name = trim($service['name'] ?? $service['service_name'] ?? $service['title'] ?? $service['label'] ?? '');
                    if ($name !== '') {
                        $services[] = $name;
                    }
                } elseif (is_string($service) && trim($service) !== '') {
                    $services[] = trim($service);
                }
            }
        }
    }

    if (empty($services) && !empty($item['other_service_name'])) {
        $services[] = trim($item['other_service_name']);
    }

    return array_values(array_unique(array_filter($services)));
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        .task-row {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f1f5f9;
        }

        .task-row td {
            padding: 0.4rem 0.75rem !important;
            vertical-align: middle;
        }

        .task-row:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            opacity: 0.9;
        }

        .filter-tab {
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .filter-tab:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .avatar {
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            color: var(--primary, #4338ca);
        }

        /* Remark Modal Styles */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }

        .modal-container {
            background: white;
            border-radius: 1rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .modal-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
        }

        .modal-body {
            padding: 1.25rem;
        }

        .modal-footer {
            padding: 1rem 1.25rem;
            background: #f8fafc;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'partials/topbar.php'; ?>
            <header class="header">
                <div>
                    <h1 class="page-title">Task Management</h1>
                    <p style="color: var(--text-muted); font-size: 0.8125rem; font-weight: 500;">Monitor employee tasks
                        and lead requirements</p>
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <form method="GET" style="position:relative; margin:0;"
                        onsubmit="event.preventDefault(); window.location.href = '?search=' + encodeURIComponent(this.search.value) + '&filter=<?= $filter ?>&status=<?= $status_filter ?>&stage=<?= $stage_filter ?>&month=<?= $month ?>&year=<?= $year ?>'">
                        <i class="fas fa-search"
                            style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); font-size: 0.75rem; color:#94a3b8;"></i>
                        <input type="text" name="search" id="taskSearch"
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search tasks..."
                            class="form-input"
                            style="padding-left: 2.25rem; width: 240px; font-size: 0.75rem; height: 36px; margin:0;">
                    </form>
                    <div
                        style="display: flex; gap: 0.5rem; align-items: center; background: white; padding: 0.25rem 0.5rem; border: 1px solid var(--border); border-radius: 0.5rem;">
                        <select id="filterMonth" class="form-input"
                            style="height: 30px; padding: 0 0.5rem; width: 110px; border: none; font-size: 0.75rem; font-weight: 600; appearance: auto;"
                            onchange="updateFilters()">
                            <option value="">All Months</option>
                            <?php for ($m = 1; $m <= 12; $m++)
                                echo "<option value='" . str_pad($m, 2, '0', STR_PAD_LEFT) . "' " . ($month == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '') . ">" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>"; ?>
                        </select>
                        <select id="filterYear" class="form-input"
                            style="height: 30px; padding: 0 0.5rem; width: 80px; border: none; font-size: 0.75rem; font-weight: 600; appearance: auto;"
                            onchange="updateFilters()">
                            <option value="">All Years</option>
                            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--)
                                echo "<option value='$y' " . ($year == $y ? 'selected' : '') . ">$y</option>"; ?>
                        </select>
                    </div>
                    <div
                        style="background: #fef2f2; border: 1px solid #fee2e2; padding: 0.5rem 1rem; border-radius: 0.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span style="height: 8px; width:8px; background: #ef4444; border-radius: 50%;"></span>
                        <span
                            style="font-size: 0.75rem; font-weight: 800; color: #991b1b;"><?= $counts['overdue_count'] ?>
                            OVERDUE</span>
                    </div>
                    <div
                        style="background: #f0fdf4; border: 1px solid #dcfce7; padding: 0.5rem 1rem; border-radius: 0.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <span style="height: 8px; width:8px; background: #22c55e; border-radius: 50%;"></span>
                        <span
                            style="font-size: 0.75rem; font-weight: 800; color: #166534;"><?= $counts['today_count'] ?>
                            FOR TODAY</span>
                    </div>
                    <?php if (!$isExecutive): ?>
                        <button class="btn btn-ghost" onclick="toggleModal('exportTasksModal')"
                            style="height: 36px; border: 1px solid var(--border); margin:0; padding: 0 0.75rem; font-size: 0.75rem; font-weight: 700;">
                            <i class="fas fa-file-export"></i> Bulk Export
                        </button>
                    <?php endif; ?>
                </div>
            </header>

            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap;">
                
                <!-- Time Filter -->
                <div style="display: flex; flex-direction: column; gap: 0.25rem; min-width: 180px;">
                    <label style="font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Time Filter</label>
                    <select id="filterTime" onchange="updateFilters()" class="form-input" style="height: 36px; font-size: 0.8rem; font-weight: 600; padding: 0 0.75rem; border-radius: 0.5rem; appearance: auto; cursor: pointer; border-color: #e2e8f0; width: 100%;">
                        <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All Tasks (<?= $counts['all_count'] ?>)</option>
                        <option value="today" <?= $filter == 'today' ? 'selected' : '' ?>>Today (<?= $counts['today_count'] ?>)</option>
                        <option value="overdue" <?= $filter == 'overdue' ? 'selected' : '' ?>>Overdue (<?= $counts['overdue_count'] ?>)</option>
                        <option value="upcoming" <?= $filter == 'upcoming' ? 'selected' : '' ?>>Upcoming (<?= $counts['upcoming_count'] ?>)</option>
                        <option value="completed" <?= $filter == 'completed' ? 'selected' : '' ?>>Completed (<?= $counts['completed_count'] ?>)</option>
                    </select>
                </div>

                <!-- Invoice Stage -->
                <div style="display: flex; flex-direction: column; gap: 0.25rem; min-width: 180px;">
                    <label style="font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Invoice Stage</label>
                    <select id="filterStage" onchange="updateFilters()" class="form-input" style="height: 36px; font-size: 0.8rem; font-weight: 600; padding: 0 0.75rem; border-radius: 0.5rem; appearance: auto; cursor: pointer; border-color: #e2e8f0; width: 100%;">
                        <option value="all" <?= $stage_filter == 'all' ? 'selected' : '' ?>>All Stages</option>
                        <option value="lead_stage" <?= $stage_filter == 'lead_stage' ? 'selected' : '' ?>>Lead Stage (<?= $counts['lead_stage_count'] ?>)</option>
                        <option value="quotation" <?= $stage_filter == 'quotation' ? 'selected' : '' ?>>Quotation (<?= $counts['quotation_count'] ?>)</option>
                        <option value="invoiced" <?= $stage_filter == 'invoiced' ? 'selected' : '' ?>>Invoiced (<?= $counts['invoiced_count'] ?>)</option>
                    </select>
                </div>

                <!-- Task Status Tabs -->
                <div style="display: flex; flex-direction: column; gap: 0.25rem; flex: 1;">
                    <label style="font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Task Status</label>
                    <div style="display: flex; align-items: center; gap: 0.75rem; overflow-x: auto;">
                        <a href="?filter=<?= $filter ?>&status=all&stage=<?= $stage_filter ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&month=<?= $month ?>&year=<?= $year ?>" class="filter-tab"
                            style="text-decoration: none; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; border: 1px solid <?= $status_filter == 'all' ? '#64748b' : '#e2e8f0' ?>; background: <?= $status_filter == 'all' ? '#64748b' : '#f8fafc' ?>; color: <?= $status_filter == 'all' ? 'white' : '#475569' ?>; transition: all 0.2s; height: 36px; display: flex; align-items: center; white-space: nowrap;">
                            All Statuses
                        </a>
                        <a href="?filter=<?= $filter ?>&status=not_started&stage=<?= $stage_filter ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&month=<?= $month ?>&year=<?= $year ?>" class="filter-tab"
                            style="text-decoration: none; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; border: 1px solid <?= $status_filter == 'not_started' ? '#94a3b8' : '#e2e8f0' ?>; background: <?= $status_filter == 'not_started' ? '#f1f5f9' : '#f8fafc' ?>; color: <?= $status_filter == 'not_started' ? '#334155' : '#475569' ?>; transition: all 0.2s; height: 36px; display: flex; align-items: center; white-space: nowrap;">
                            Not Started (<?= $counts['not_started_count'] ?>)
                        </a>
                        <a href="?filter=<?= $filter ?>&status=work_in_progress&stage=<?= $stage_filter ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&month=<?= $month ?>&year=<?= $year ?>" class="filter-tab"
                            style="text-decoration: none; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; border: 1px solid <?= $status_filter == 'work_in_progress' ? '#fcd34d' : '#e2e8f0' ?>; background: <?= $status_filter == 'work_in_progress' ? '#fef3c7' : '#f8fafc' ?>; color: <?= $status_filter == 'work_in_progress' ? '#92400e' : '#475569' ?>; transition: all 0.2s; height: 36px; display: flex; align-items: center; white-space: nowrap;">
                            Work In Progress (<?= $counts['wip_count'] ?>)
                        </a>
                        <a href="?filter=<?= $filter ?>&status=work_pending&stage=<?= $stage_filter ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&month=<?= $month ?>&year=<?= $year ?>" class="filter-tab"
                            style="text-decoration: none; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; border: 1px solid <?= $status_filter == 'work_pending' ? '#fca5a5' : '#e2e8f0' ?>; background: <?= $status_filter == 'work_pending' ? '#fee2e2' : '#f8fafc' ?>; color: <?= $status_filter == 'work_pending' ? '#991b1b' : '#475569' ?>; transition: all 0.2s; height: 36px; display: flex; align-items: center; white-space: nowrap;">
                            Work Pending (<?= $counts['pending_count'] ?>)
                        </a>
                        <a href="?filter=<?= $filter ?>&status=work_done&stage=<?= $stage_filter ?>&search=<?= urlencode($_GET['search'] ?? '') ?>&month=<?= $month ?>&year=<?= $year ?>" class="filter-tab"
                            style="text-decoration: none; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; border: 1px solid <?= $status_filter == 'work_done' ? '#86efac' : '#e2e8f0' ?>; background: <?= $status_filter == 'work_done' ? '#dcfce7' : '#f8fafc' ?>; color: <?= $status_filter == 'work_done' ? '#166534' : '#475569' ?>; transition: all 0.2s; height: 36px; display: flex; align-items: center; white-space: nowrap;">
                            Work Done (<?= $counts['done_count'] ?>)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Bulk Action Bar -->
            <?php if (!$isExecutive): ?>
                <div id="bulkActionBar"
                    style="display: none; position: fixed; top: 5.5rem; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 0.75rem 1.5rem; border-radius: 1rem; box-shadow: var(--shadow-xl); z-index: 1000; align-items: center; gap: 1.5rem; animation: slideDown 0.3s ease;">
                    <div style="font-size: 0.875rem; font-weight: 700;">
                        <span id="selectedCount">0</span> Items Selected
                    </div>
                    <div style="height: 24px; width: 1px; background: rgba(255,255,255,0.2);"></div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span style="font-size: 0.75rem; font-weight: 600; opacity: 0.8;">Assign Owner:</span>
                        <select id="bulkAssignUser" class="form-input"
                            style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); height: 32px; font-size: 0.75rem; width: 140px; appearance: auto;">
                            <option value="" style="color: black;">-- Owner --</option>
                            <?php foreach ($users as $u)
                                echo "<option value='{$u['id']}' style='color: black;'>" . htmlspecialchars($u['name']) . "</option>"; ?>
                        </select>

                        <span style="font-size: 0.75rem; font-weight: 600; opacity: 0.8;">Assign Staff:</span>
                        <select id="bulkAssignEmployee" class="form-input"
                            style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); height: 32px; font-size: 0.75rem; width: 140px; appearance: auto;">
                            <option value="" style="color: black;">-- Staff --</option>
                            <?php foreach ($employees as $emp)
                                echo "<option value='{$emp['id']}' style='color: black;'>" . htmlspecialchars($emp['name']) . "</option>"; ?>
                        </select>
                        <button onclick="bulkAssign()" class="btn btn-primary"
                            style="height: 32px; font-size: 0.75rem; background: var(--primary); border: none;">
                            Apply
                        </button>
                    </div>
                    <button onclick="clearSelection()" class="btn-ghost"
                        style="color: white; opacity: 0.6; font-size: 0.75rem;"><i class="fas fa-times"></i> Cancel</button>
                </div>
            <?php endif; ?>

            <div class="card" style="padding: 0; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0;">
                            <th style="width: 40px; padding: 0.5rem 1rem;"><input type="checkbox" id="selectAllTasks"
                                    onchange="toggleSelectAll(this)"></th>
                            <th
                                style="text-align: left; padding: 0.5rem 1rem; font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; width: 45%; max-width: 500px;">
                                Task / Service</th>
                            <th
                                style="text-align: left; padding: 0.5rem 1rem; font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase;">
                                Task Status</th>
                            <th
                                style="text-align: left; padding: 0.5rem 1rem; font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase;">
                                Invoice Stage</th>
                            <?php if (!$isExecutive): ?>
                                <th
                                    style="text-align: left; padding: 0.5rem 1rem; font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase;">
                                    Assigned Staff</th>
                            <?php endif; ?>
                            <th
                                style="text-align: right; padding: 0.5rem 1rem; font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase;">
                                Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($display_items)): ?>
                            <tr>
                                <td colspan="5" style="padding: 4rem; text-align: center; color: #94a3b8;">
                                    <i class="fas fa-tasks"
                                        style="font-size: 2.5rem; margin-bottom: 1rem; display: block; opacity: 0.2;"></i>
                                    <p style="font-size: 0.875rem; font-weight: 600;">No records found.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($display_items as $item):
                                $taskStatus = normalizedTaskStatus($item['task_status']);
                                $pendingRemark = taskPendingRemark($item['latest_task_remark'] ?? '');
                                $is_overdue = $item['follow_up_date'] != null && $item['follow_up_date'] < $today && $taskStatus !== 'work_done';
                                $is_today = $item['follow_up_date'] == $today;
                                ?>
                                <tr class="task-row" style="<?= taskRowStyle($taskStatus) ?>">
                                    <td style="padding: 0.5rem 1rem; width: 40px;">
                                        <?php if (!$isExecutive): ?>
                                            <input type="checkbox" class="task-checkbox" value="<?= $item['id'] ?>"
                                                onchange="updateBulkBar()" onclick="event.stopPropagation()">
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.5rem 1rem; width: 45%; max-width: 500px;">
                                        <div style="display:flex; flex-direction:column; gap:0.25rem; margin-bottom: 0.25rem;">
                                            <div style="display:flex; flex-wrap: wrap; align-items:center; gap:0.5rem;">
                                                <span style="font-weight: 700; color: #0f172a; font-size: 0.875rem;">
                                                    <?= htmlspecialchars($item['name']) ?>
                                                </span>
                                                <?php if (!empty($item['mobile'])): ?>
                                                    <span
                                                        style="font-size: 0.75rem; color: #64748b; font-weight: 500;">
                                                        <i class="fas fa-phone-alt" style="font-size: 0.65rem;"></i>
                                                        <?= htmlspecialchars($item['mobile']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($item['status'] == 'won'): ?>
                                                    <?php if ($taskStatus === 'work_done'): ?>
                                                        <span
                                                            style="font-size: 0.55rem; background: #dcfce7; color: #166534; padding: 2px 6px; border-radius: 4px; border: 1px solid #bbf7d0; font-weight:700; letter-spacing: 0.5px;">COMPLETED
                                                            SERVICE</span>
                                                    <?php else: ?>
                                                        <span
                                                            style="font-size: 0.55rem; background: #e0e7ff; color: #3730a3; padding: 2px 6px; border-radius: 4px; border: 1px solid #c7d2fe; font-weight:700; letter-spacing: 0.5px;">ACTIVE
                                                            SERVICE</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php $serviceNames = taskServiceNames($item); ?>
                                        <?php if (!empty($serviceNames)): ?>
                                            <div style="font-size: 0.75rem; color: #475569; font-weight: 600; max-width: 480px; display: flex; gap: 6px; margin-top: 0.4rem;" title="<?= htmlspecialchars(implode(', ', $serviceNames)) ?>">
                                                <i class="fas fa-concierge-bell" style="margin-top: 3px; flex-shrink: 0; color: var(--primary, #6366f1);"></i>
                                                <span style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; white-space: normal; line-height: 1.4; word-break: break-word; flex: 1;"><?= htmlspecialchars(implode(', ', $serviceNames)) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php
                                        $display_date = $item['expected_delivery_date'] ?: $item['follow_up_date'];
                                        $is_overdue = $display_date != null && $display_date < $today && $taskStatus !== 'work_done';
                                        $is_today = $display_date == $today;
                                        ?>
                                        <?php if ($display_date): ?>
                                            <div
                                                style="font-size: 0.7rem; color: <?= $is_overdue ? '#b91c1c' : ($is_today ? '#166534' : '#475569') ?>; font-weight: 700; margin-top: 4px; display: inline-block; padding: 3px 8px; border-radius: 4px; background: <?= $is_overdue ? '#fef2f2' : ($is_today ? '#f0fdf4' : '#f8fafc') ?>; border: 1px solid <?= $is_overdue ? '#fecaca' : ($is_today ? '#bbf7d0' : '#e2e8f0') ?>;">
                                                <i
                                                    class="fas <?= $item['expected_delivery_date'] ? 'fa-truck-loading' : 'fa-calendar-alt' ?>"></i>
                                                <?= $item['expected_delivery_date'] ? 'Delivery:' : 'Follow-up:' ?>
                                                <?= date('d M, Y', strtotime($display_date)) ?>
                                                <?= $is_overdue ? ' <span style="font-size: 0.6rem; background: #ef4444; color: white; padding: 1px 4px; border-radius: 2px; margin-left: 4px;">OVERDUE</span>' : '' ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($item['requirement']): ?>
                                            <div style="font-size: 0.7rem; color: #64748b; font-style: italic; margin-top: 2px;">
                                                <i class="fas fa-comment-dots"></i> <?= htmlspecialchars($item['requirement']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.5rem 1rem;">
                                        <div style="margin-bottom: 0.5rem;">
                                            <?php
                                            // Executives can ONLY edit if they are the assigned technician
                                            $canEditStatus = !$isExecutive || ($item['assigned_employee_id'] == $executiveEmployeeId);
                                            ?>
                                            <div style="display: flex; align-items: center; gap: 0.4rem; position: relative;">
                                                <button <?= $canEditStatus ? 'onclick="updateTaskStatus(' . $item['id'] . ', \'' . $taskStatus . '\', \'' . $item['expected_delivery_date'] . '\', \'' . addslashes($item['name']) . '\')"' : 'disabled title="Only the assigned technician or admin can update status"' ?>
                                                    class="badge"
                                                    style="background: <?= $statusColor ?>; color: <?= $statusText ?>; border: 1px solid <?= $statusBorder ?>; font-size: 0.6rem; font-weight: 800; padding: 0.35rem 0.6rem; border-radius: 4px; cursor: <?= $canEditStatus ? 'pointer' : 'not-allowed' ?>; min-width: 130px; line-height: 1.2; <?= $canEditStatus ? '' : 'opacity: 0.7;' ?> text-transform: uppercase; display: flex; justify-content: space-between; align-items: center;">
                                                    <span><?= $taskStatus === 'not_started' ? 'NOT STARTED' : ($taskStatus === 'work_in_progress' ? 'WORK IN PROGRESS' : ($taskStatus === 'work_pending' ? 'WORK PENDING' : 'WORK DONE')) ?></span>
                                                    <i class="fas fa-chevron-down" style="font-size: 0.5rem; opacity: 0.7;"></i>
                                                </button>
                                                <button onclick="openTaskHistory(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')" class="icon-btn" style="background: #f1f5f9; color: #475569; border: none; padding: 0.3rem 0.4rem; border-radius: 4px; cursor: pointer;" title="Remark History">
                                                    <i class="fas fa-history" style="font-size: 0.7rem;"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php
                                        $showRemark = in_array($taskStatus, ['work_pending', 'work_in_progress']) && $pendingRemark !== '';
                                        $adderName = $item['latest_task_user_name'] ?? 'System';
                                        ?>
                                        <div id="pending-remark-<?= $item['id'] ?>" class="pending-remark"
                                            style="<?= $showRemark ? 'display: block;' : 'display: none;' ?> margin-top: 0.5rem; padding: 0.5rem; background: <?= $taskStatus === 'work_pending' ? '#fee2e2' : '#fef3c7' ?>; border-radius: 4px; border: 1px solid <?= $taskStatus === 'work_pending' ? '#fecaca' : '#fde68a' ?>;">
                                            <div
                                                style="font-size: 0.65rem; font-weight: 700; color: <?= $taskStatus === 'work_pending' ? '#991b1b' : '#92400e' ?>; margin-bottom: 0.25rem; display: flex; justify-content: space-between;">
                                                <span><?= $taskStatus === 'work_pending' ? 'PENDING REASON' : 'PROGRESS REMARK' ?>:</span>
                                                <?php if (!$isExecutive): ?>
                                                    <span style="font-style: italic; opacity: 0.8;">- by
                                                        <?= htmlspecialchars($adderName) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div id="pending-remark-text-<?= $item['id'] ?>"
                                                style="font-size: 0.7rem; color: <?= $taskStatus === 'work_pending' ? '#7f1d1d' : '#78350f' ?>; line-height: 1.4;">
                                                <?= htmlspecialchars($pendingRemark) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 0.5rem 1rem;">
                                        <?php if ($item['invoice_stage'] === 'Invoiced' && !empty($item['latest_invoice_id'])): ?>
                                            <a href="<?= APP_URL ?>/public/assets/views/invoice_print.php?id=<?= $item['latest_invoice_id'] ?>"
                                                class="badge"
                                                style="background: #e0e7ff; color: var(--primary, #4338ca); border: 1px solid #c7d2fe; font-size: 0.6rem; padding: 2px 8px; border-radius: 4px; text-decoration: none; display: inline-block; font-weight:700;">
                                                <?= $item['invoice_stage'] ?>
                                            </a>
                                        <?php else: ?>
                                            <div class="badge"
                                                style="background: <?= $item['invoice_stage'] == 'Quotation' ? '#ede9fe' : '#f1f5f9' ?>; color: <?= $item['invoice_stage'] == 'Quotation' ? '#6d28d9' : '#475569' ?>; border: 1px solid <?= $item['invoice_stage'] == 'Quotation' ? '#ddd6fe' : '#e2e8f0' ?>; font-size: 0.6rem; padding: 2px 8px; border-radius: 4px; font-weight:700;">
                                                <?= $item['invoice_stage'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php if (!$isExecutive): ?>
                                        <td style="padding: 0.5rem 1rem;">
                                            <select
                                                onchange="reassignTask(<?= $item['id'] ?>, this, '<?= $item['expected_delivery_date'] ?>')"
                                                data-current-staff="<?= htmlspecialchars((string) ($item['assigned_employee_id'] ?? ''), ENT_QUOTES) ?>"
                                                class="form-input"
                                                style="font-size: 0.7rem; padding: 0.15rem; width: 120px; appearance: auto;">
                                                <option value="" <?= empty($item['assigned_employee_id']) ? 'selected' : '' ?>>--
                                                    Unassigned --</option>
                                                <?php foreach ($employees as $emp): ?>
                                                    <option value="<?= $emp['id'] ?>" <?= $item['assigned_employee_id'] == $emp['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($emp['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    <?php endif; ?>
                                    <td style="padding: 0.5rem 1rem; text-align: right;">
                                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                            <button onclick="viewLead(<?= $item['id'] ?>)"
                                                style="padding: 0.4rem; font-size: 0.9rem; border: none; background: transparent; color: #64748b; cursor: pointer; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-hover, #4f46e5)'" onmouseout="this.style.color='#64748b'" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1): ?>
                <div style="padding: 1rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: white;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Showing <?= count($display_items) ?> of <?= $total_items ?> tasks</span>
                        <form method="GET" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                            <?php foreach($_GET as $k => $v): if($k !== 'limit' && $k !== 'page'): ?>
                                <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                            <?php endif; endforeach; ?>
                            <select name="limit" onchange="this.form.submit()" style="padding: 0.25rem 0.5rem; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 0.75rem; color: #475569; background: #f8fafc; cursor: pointer;">
                                <option value="15" <?= $limit_val == '15' ? 'selected' : '' ?>>15 per page</option>
                                <option value="25" <?= $limit_val == '25' ? 'selected' : '' ?>>25 per page</option>
                                <option value="50" <?= $limit_val == '50' ? 'selected' : '' ?>>50 per page</option>
                                <option value="100" <?= $limit_val == '100' ? 'selected' : '' ?>>100 per page</option>
                                <option value="all" <?= $limit_val === 'all' ? 'selected' : '' ?>>All</option>
                            </select>
                        </form>
                    </div>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    if ($end_page - $start_page < 4) {
                        if ($start_page == 1) {
                            $end_page = min($total_pages, 5);
                        } elseif ($end_page == $total_pages) {
                            $start_page = max(1, $total_pages - 4);
                        }
                    }
                    $urlParams = "&filter={$filter}&status={$status_filter}&stage={$stage_filter}&month={$month}&year={$year}&search=" . urlencode($_GET['search'] ?? '') . "&limit=" . urlencode($limit_val);
                    ?>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= $urlParams ?>" class="btn btn-ghost" style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; text-decoration: none; color: #0f172a;"><i class="fas fa-chevron-left"></i></a>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?= $i ?><?= $urlParams ?>" 
                               class="btn <?= $i == $page ? 'btn-primary' : 'btn-ghost' ?>" 
                               style="padding: 0.5rem 0.8rem; border: 1px solid <?= $i == $page ? 'var(--primary)' : '#e2e8f0' ?>; border-radius: 6px; font-size: 0.75rem; text-decoration: none; color: <?= $i == $page ? 'white' : '#0f172a' ?>; font-weight: 700; <?= $i == $page ? 'background: var(--primary);' : '' ?>">
                               <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?><?= $urlParams ?>" class="btn btn-ghost" style="padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; text-decoration: none; color: #0f172a;"><i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>

    <!-- Task History Modal -->
    <div id="taskHistoryModal" class="modal-overlay" style="display: none; z-index: 1050;">
        <div class="modal-content" style="max-width: 600px; width: 95%; display: flex; flex-direction: column; height: 500px; padding: 0;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); background: white; border-radius: 12px 12px 0 0;">
                <div>
                    <h2 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-history" style="color: var(--primary);"></i> Task Remark History
                    </h2>
                    <p style="font-size: 0.7rem; color: #64748b; margin: 0.25rem 0 0 0;">For: <span id="th_task_name" style="font-weight: 700; color: #334155;">Task</span></p>
                </div>
                <button onclick="document.getElementById('taskHistoryModal').style.display='none'" class="btn-ghost" style="padding: 0.4rem; border-radius: 4px;"><i class="fas fa-times"></i></button>
            </div>
            <div id="taskHistoryList" style="flex: 1; overflow-y: auto; padding: 1.5rem; background: #f8fafc; border-radius: 0 0 12px 12px;">
                <!-- History items injected here -->
            </div>
        </div>
    </div>
    </div>

    <!-- Remark Modal -->
    <div id="remarkModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2 style="font-size: 1rem; font-weight: 800; color: #0f172a;">Task Update</h2>
                <button onclick="closeRemarkModal()" class="btn-ghost" style="padding: 0.25rem;"><i
                        class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.7rem; font-weight: 800; color: #64748b; margin-bottom: 0.25rem;">TASK STATUS</label>
                    <select id="modalStatusSelect" class="form-input" style="width: 100%; font-weight: 700;">
                        <option value="not_started">NOT STARTED</option>
                        <option value="work_in_progress">WORK IN PROGRESS</option>
                        <option value="work_pending">WORK PENDING</option>
                        <option value="work_done">WORK DONE</option>
                    </select>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label
                        style="display: block; font-size: 0.7rem; font-weight: 800; color: #64748b; margin-bottom: 0.25rem;">EXPECTED
                        DELIVERY DATE</label>
                    <input type="date" id="deliveryDate" class="form-input" style="width: 100%;">
                </div>
                <p style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.75rem; font-weight: 600;">Status Update
                    Remark:</p>
                <textarea id="remarkText" class="form-input"
                    style="width: 100%; height: 100px; resize: none; padding: 0.75rem; font-size: 0.875rem;"
                    placeholder="e.g. Discussed with client, they agreed to..."></textarea>
            </div>
            <div class="modal-footer">
                <button onclick="closeRemarkModal()" class="btn btn-ghost"
                    style="font-size: 0.8125rem; font-weight: 700;">Cancel</button>
                <button id="submitRemarkBtn" class="btn btn-primary"
                    style="font-size: 0.8125rem; font-weight: 700;">Update Status</button>
            </div>
        </div>
    </div>

    <!-- Assign Task Modal -->
    <div id="assignModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2 style="font-size: 1rem; font-weight: 800; color: #0f172a;">Assign Task</h2>
                <button onclick="closeAssignModal(true)" class="btn-ghost" style="padding: 0.25rem;"><i
                        class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p id="assignStaffText"
                    style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.75rem; font-weight: 600;"></p>
                <label
                    style="display: block; font-size: 0.7rem; font-weight: 800; color: #64748b; margin-bottom: 0.25rem;">ESTIMATED
                    DELIVERY DATE</label>
                <input type="date" id="assignDeliveryDate" class="form-input" style="width: 100%;">
            </div>
            <div class="modal-footer">
                <button onclick="closeAssignModal(true)" class="btn btn-ghost"
                    style="font-size: 0.8125rem; font-weight: 700;">Cancel</button>
                <button id="submitAssignBtn" class="btn btn-primary"
                    style="font-size: 0.8125rem; font-weight: 700;">Assign Task</button>
            </div>
        </div>
    </div>

    <script>
        async function openTaskHistory(taskId, taskName) {
            document.getElementById('th_task_name').textContent = taskName;
            const list = document.getElementById('taskHistoryList');
            list.innerHTML = '<div style="text-align:center; padding: 2rem; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            document.getElementById('taskHistoryModal').style.display = 'flex';

            try {
                const response = await fetch(`<?= APP_URL ?>/public/index.php/api/lead_followups.php?lead_id=${taskId}`);
                const data = await response.json();

                // Filter for task remarks
                const taskRemarks = data.filter(f => f.remark && f.remark.toLowerCase().includes('task status'));

                if (taskRemarks.length === 0) {
                    list.innerHTML = '<div style="text-align:center; padding:2rem; color:#94a3b8; font-size:0.8rem;"><i class="fas fa-comment-slash" style="display:block; font-size:2rem; margin-bottom:1rem; opacity:0.2;"></i>No task remarks found.</div>';
                    return;
                }

                list.innerHTML = taskRemarks.map(f => {
                    let statusLabel = '';
                    let mainRemark = f.remark;
                    const match = f.remark.match(/^Task Status changed to ([^:]+):\s*(.*)$/i);
                    if (match) {
                        statusLabel = match[1].trim();
                        mainRemark = match[2].trim();
                    } else {
                        const match2 = f.remark.match(/^Task Status changed to (.*)$/i);
                        if (match2) {
                            statusLabel = match2[1].trim();
                            mainRemark = 'Status updated';
                        }
                    }

                    let bgColor = '#f1f5f9', color = '#475569';
                    if (statusLabel === 'WORK PENDING') { bgColor = '#fee2e2'; color = '#991b1b'; }
                    else if (statusLabel === 'WORK IN PROGRESS') { bgColor = '#fef3c7'; color = '#92400e'; }
                    else if (statusLabel === 'WORK DONE') { bgColor = '#dcfce7'; color = '#166534'; }

                    return `
                    <div style="background: white; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 0.5rem; margin-bottom: 0.75rem; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                        <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <span style="font-size: 0.65rem; font-weight: 800; color: #64748b;">
                                    <i class="far fa-calendar-alt"></i> ${new Date(f.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })} 
                                    at ${new Date(f.created_at).toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit', hour12: true })}
                                </span>
                            </div>
                            ${statusLabel ? `<span class="badge" style="background: ${bgColor}; color: ${color}; font-size: 0.6rem; font-weight: 700; padding: 2px 6px;">${statusLabel}</span>` : ''}
                        </div>
                        <p style="font-size: 0.8rem; color: #1e293b; font-weight: 500; margin: 0; line-height: 1.5;">${mainRemark}</p>
                        ${f.user_name ? `<div style="margin-top: 0.5rem; font-size: 0.65rem; color: #94a3b8; font-style: italic;">By: ${f.user_name}</div>` : ''}
                    </div>
                    `;
                }).join('');

            } catch (error) {
                list.innerHTML = '<div style="text-align:center; color: #ef4444; font-size: 0.8rem;">Failed to load history.</div>';
            }
        }

        let currentUpdatingId = null;
        let currentTargetStatus = null;
        let currentAssignTaskId = null;
        let currentAssignSelect = null;
        let currentAssignStaffId = null;
        let currentAssignPreviousStaffId = null;

        const taskStatusLabels = {
            not_started: 'NOT STARTED',
            work_in_progress: 'WORK IN PROGRESS',
            work_pending: 'WORK PENDING',
            work_done: 'WORK DONE'
        };

        function closeRemarkModal() {
            document.getElementById('remarkModal').style.display = 'none';
            document.getElementById('remarkText').value = '';
            // If cancelled, we might want to revert the select value, but easier to just reload if needed or leave as is since the user can re-select.
        }

        function closeAssignModal(restoreSelection) {
            document.getElementById('assignModal').style.display = 'none';
            document.getElementById('assignDeliveryDate').value = '';

            if (restoreSelection && currentAssignSelect) {
                currentAssignSelect.value = currentAssignPreviousStaffId || '';
            }

            currentAssignTaskId = null;
            currentAssignSelect = null;
            currentAssignStaffId = null;
            currentAssignPreviousStaffId = null;
        }

        function closeLeadModal() {
            document.getElementById('leadDetailsModal').style.display = 'none';
        }

        async function viewLead(id) {
            const modal = document.getElementById('leadDetailsModal');
            const content = document.getElementById('leadDetailsContent');
            modal.style.display = 'flex';
            content.innerHTML = '<div style="text-align:center; padding: 2rem;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

            try {
                const res = await fetch('<?= APP_URL ?>/public/index.php/api/leads.php?id=' + id);
                const data = await res.json();

                if (data.error) throw new Error(data.error);

                content.innerHTML = `
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div>
                            <label style="display:block; font-size:0.65rem; color:#64748b; font-weight:800; text-transform:uppercase; margin-bottom:0.25rem;">Client Name</label>
                            <div style="font-weight:700; color:#0f172a;">${data.name || 'N/A'}</div>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.65rem; color:#64748b; font-weight:800; text-transform:uppercase; margin-bottom:0.25rem;">Mobile Number</label>
                            <div style="font-weight:700; color:#0f172a;">${data.mobile || 'N/A'}</div>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.65rem; color:#64748b; font-weight:800; text-transform:uppercase; margin-bottom:0.25rem;">Email ID</label>
                            <div style="font-weight:600; color:#0f172a;">${data.email || 'N/A'}</div>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.65rem; color:#64748b; font-weight:800; text-transform:uppercase; margin-bottom:0.25rem;">Location</label>
                            <div style="font-weight:600; color:#0f172a;">${data.address || 'N/A'}</div>
                        </div>
                    </div>
                    <div style="margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid #e2e8f0;">
                        <label style="display:block; font-size:0.65rem; color:#64748b; font-weight:800; text-transform:uppercase; margin-bottom:0.5rem;">Services / Requirements</label>
                        <div style="font-size:0.875rem; line-height:1.5; color:#334155;">${data.requirement || 'No notes provided.'}</div>
                    </div>
                    ${data.services && data.services.length > 0 ? `
                        <div style="margin-top:1rem; display:flex; flex-wrap:wrap; gap:0.5rem;">
                            ${data.services.map(s => `<span class="badge" style="background:#eff6ff; color:#1e40af; border:1.5px solid #bfdbfe; font-size:0.65rem;">${s.name}</span>`).join('')}
                        </div>
                    ` : ''}
                `;
            } catch (err) {
                content.innerHTML = `<div style="color:red; padding:1rem;">Error: ${err.message}</div>`;
            }
        }

        function updateTaskStatus(id, currentStatus, deliveryDate, taskName) {
            currentTaskId = id;
            document.getElementById('modalStatusSelect').value = currentStatus || 'not_started';
            document.getElementById('deliveryDate').value = deliveryDate || '';
            document.getElementById('remarkText').value = '';

            const headerName = taskName ? ' - ' + taskName : '';
            document.querySelector('#remarkModal .modal-header h2').textContent = 'Update Task' + headerName;
            document.getElementById('remarkText').placeholder = 'Add a remark for this update...';

            document.getElementById('remarkModal').style.display = 'flex';
            document.getElementById('remarkText').focus();
        }

        function saveRemark(id) {
            // Not used natively anymore, handled by submitRemarkBtn
        }

        async function submitStatusUpdate(id, status, remark, delivery_date) {
            try {
                const payload = {
                    id: id,
                    status: status,
                    remark: remark
                };

                // Only include delivery date if it's not empty
                if (delivery_date && delivery_date !== '') {
                    payload.expected_delivery_date = delivery_date;
                }

                console.log('Sending payload:', payload);

                const res = await fetch('<?= APP_URL ?>/public/index.php/api/tasks.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const responseText = await res.text();
                console.log('Response status:', res.status);
                console.log('Response text:', responseText);

                if (res.ok) {
                    try {
                        const data = JSON.parse(responseText);
                        if (data.success) {
                            // Show remark if it's work_pending or work_in_progress status
                            if ((status === 'work_pending' || status === 'work_in_progress') && remark) {
                                const remarkDiv = document.getElementById('pending-remark-' + id);
                                const remarkTextDiv = document.getElementById('pending-remark-text-' + id);
                                const remarkLabelDiv = remarkDiv.querySelector('div:first-child span:first-child');

                                remarkDiv.style.display = 'block';
                                remarkTextDiv.textContent = remark;

                                if (status === 'work_pending') {
                                    remarkDiv.style.background = '#fee2e2';
                                    remarkDiv.style.borderColor = '#fecaca';
                                    remarkLabelDiv.textContent = 'PENDING REASON:';
                                    remarkLabelDiv.style.color = '#991b1b';
                                    remarkTextDiv.style.color = '#7f1d1d';
                                } else {
                                    remarkDiv.style.background = '#fef3c7';
                                    remarkDiv.style.borderColor = '#fde68a';
                                    remarkLabelDiv.textContent = 'PROGRESS REMARK:';
                                    remarkLabelDiv.style.color = '#92400e';
                                    remarkTextDiv.style.color = '#78350f';
                                }
                            }
                            alert('Task updated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'Unknown error'));
                        }
                    } catch (e) {
                        console.error('Failed to parse response:', e);
                        alert('Task updated but response parsing failed');
                        location.reload();
                    }
                } else {
                    try {
                        const errorData = JSON.parse(responseText);
                        alert('Error updating status: ' + (errorData.error || 'Unknown error'));
                    } catch (e) {
                        alert('Error updating status (HTTP ' + res.status + '): ' + responseText);
                    }
                }
            } catch (error) {
                console.error('Fetch error:', error);
                alert('Error updating status: ' + error.message);
            }
        }

        async function saveTaskAssignment(id, selectEl, staffId, deliveryDate) {
            const previousStaffId = selectEl.dataset.currentStaff || '';
            const payload = { id, assigned_employee_id: staffId };

            if (deliveryDate) {
                payload.expected_delivery_date = deliveryDate;
            }

            try {
                const res = await fetch('<?= APP_URL ?>/public/index.php/api/leads.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                if (res.ok) {
                    location.reload();
                    return true;
                }

                selectEl.value = previousStaffId;
                alert('Error reassigning task');
            } catch (e) {
                selectEl.value = previousStaffId;
                alert('Error reassigning task');
            }

            return false;
        }

        async function reassignTask(id, selectEl, currentDeliveryDate) {
            const staffId = selectEl.value;
            const previousStaffId = selectEl.dataset.currentStaff || '';

            if (!staffId) {
                if (!confirm('Unassign this task?')) {
                    selectEl.value = previousStaffId;
                    return;
                }

                await saveTaskAssignment(id, selectEl, staffId, '');
                return;
            }

            currentAssignTaskId = id;
            currentAssignSelect = selectEl;
            currentAssignStaffId = staffId;
            currentAssignPreviousStaffId = previousStaffId;

            const selectedStaffName = selectEl.options[selectEl.selectedIndex]?.text?.trim() || 'selected staff';
            document.getElementById('assignStaffText').textContent = 'Assign this task to ' + selectedStaffName + ' and choose the estimated delivery date.';
            document.getElementById('assignDeliveryDate').value = currentDeliveryDate || '';
            document.getElementById('assignModal').style.display = 'flex';
            document.getElementById('assignDeliveryDate').focus();
        }

        document.getElementById('submitAssignBtn').addEventListener('click', async function () {
            const deliveryDate = document.getElementById('assignDeliveryDate').value;
            if (!deliveryDate) {
                alert('Please choose an estimated delivery date.');
                return;
            }

            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';

            const saved = await saveTaskAssignment(currentAssignTaskId, currentAssignSelect, currentAssignStaffId, deliveryDate);

            if (!saved) {
                this.disabled = false;
                this.innerHTML = 'Assign Task';
                return;
            }

            closeAssignModal(false);
            this.disabled = false;
            this.innerHTML = 'Assign Task';
        });

        document.getElementById('submitRemarkBtn').addEventListener('click', async function () {
            const remark = document.getElementById('remarkText').value.trim();
            const delivery_date = document.getElementById('deliveryDate').value;
            const status = document.getElementById('modalStatusSelect').value;

            if (!remark) {
                alert('Please enter a remark for this status change.');
                return;
            }

            const id = currentTaskId;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            await submitStatusUpdate(id, status, remark, delivery_date);

            closeRemarkModal();
            this.disabled = false;
            this.innerHTML = 'Update Status';
        });

        function updateFilters() {
            const filter = document.getElementById('filterTime') ? document.getElementById('filterTime').value : '<?= $filter ?>';
            const status = document.getElementById('filterStatus') ? document.getElementById('filterStatus').value : '<?= $status_filter ?>';
            const stage = document.getElementById('filterStage') ? document.getElementById('filterStage').value : '<?= $stage_filter ?>';
            const month = document.getElementById('filterMonth').value;
            const year = document.getElementById('filterYear').value;
            
            const searchParams = new URLSearchParams(window.location.search);
            const search = searchParams.get('search') || '';

            let url = `?filter=${filter}&status=${status}&stage=${stage}&month=${month}&year=${year}`;
            if(search) url += `&search=${encodeURIComponent(search)}`;
            
            window.location.href = url;
        }

        function toggleModal(id) {
            const modal = document.getElementById(id);
            modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
        }

        async function exportTasks() {
            const month = document.getElementById('exportMonth').value;
            const year = document.getElementById('exportYear').value;
            window.location.href = `<?= APP_URL ?>/public/index.php/api/leads_bulk.php?action=export_tasks&month=${month}&year=${year}`;
            toggleModal('exportTasksModal');
        }

        function toggleSelectAll(master) {
            const checkboxes = document.querySelectorAll('.task-checkbox');
            checkboxes.forEach(cb => cb.checked = master.checked);
            updateBulkBar();
        }

        function updateBulkBar() {
            const selected = document.querySelectorAll('.task-checkbox:checked');
            const bar = document.getElementById('bulkActionBar');
            const count = document.getElementById('selectedCount');

            if (selected.length > 0) {
                bar.style.display = 'flex';
                count.textContent = selected.length;
            } else {
                bar.style.display = 'none';
                document.getElementById('selectAllTasks').checked = false;
            }
        }

        function clearSelection() {
            document.querySelectorAll('.task-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAllTasks').checked = false;
            updateBulkBar();
        }

        async function bulkAssign() {
            const selected = document.querySelectorAll('.task-checkbox:checked');
            const ids = Array.from(selected).map(cb => cb.value);
            const employeeId = document.getElementById('bulkAssignEmployee').value;
            const userId = document.getElementById('bulkAssignUser').value;

            if (ids.length === 0) {
                alert('Please select tasks to assign');
                return;
            }

            if (!employeeId && !userId) {
                alert('Please select either a Lead Owner or a Task Staff to assign.');
                return;
            }

            const msg = userId && employeeId
                ? `Assign ${ids.length} items to the selected owner and staff?`
                : (userId ? `Assign ${ids.length} leads to the selected owner?` : `Assign ${ids.length} tasks to the selected staff?`);

            if (!confirm(msg)) return;

            const btn = event.target;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>...';
            btn.disabled = true;

            try {
                const payload = { ids };
                if (employeeId) payload.employee_id = employeeId;
                if (userId) payload.user_id = userId;

                const res = await fetch(`<?= APP_URL ?>/public/index.php/api/leads_bulk.php?action=bulk_assign`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    alert('Items assigned successfully!');
                    location.reload();
                } else {
                    alert('Assignment failed: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                alert('An error occurred during bulk assignment');
            } finally {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        }

        // Search logic
        document.getElementById('taskSearch').addEventListener('input', function (e) {
            const query = e.target.value.toLowerCase();
            document.querySelectorAll('.task-row').forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    </script>

    <!-- Export Tasks Modal -->
    <div id="exportTasksModal" class="modal-overlay">
        <div class="modal-container" style="max-width: 400px;">
            <div class="modal-header">
                <h2 style="font-size: 1rem; font-weight: 800; color: #0f172a;">Bulk Export Tasks</h2>
                <button onclick="toggleModal('exportTasksModal')" class="btn-ghost" style="padding: 0.25rem;"><i
                        class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label
                            style="display: block; font-size: 0.7rem; font-weight: 800; color: #64748b; margin-bottom: 0.25rem;">MONTH</label>
                        <select id="exportMonth" class="form-input" style="appearance: auto; width: 100%;">
                            <?php for ($m = 1; $m <= 12; $m++)
                                echo "<option value='" . str_pad($m, 2, '0', STR_PAD_LEFT) . "' " . (date('m') == $m ? 'selected' : '') . ">" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>"; ?>
                        </select>
                    </div>
                    <div>
                        <label
                            style="display: block; font-size: 0.7rem; font-weight: 800; color: #64748b; margin-bottom: 0.25rem;">YEAR</label>
                        <select id="exportYear" class="form-input" style="appearance: auto; width: 100%;">
                            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--)
                                echo "<option value='$y'>$y</option>"; ?>
                        </select>
                    </div>
                </div>
                <button onclick="exportTasks()" class="btn btn-primary"
                    style="width: 100%; height: 42px; font-weight: 700;">
                    <i class="fas fa-download"></i> Download Task CSV
                </button>
            </div>
        </div>
    </div>
    <!-- Lead Details Modal -->
    <div id="leadDetailsModal" class="modal-overlay">
        <div class="modal-container" style="max-width: 500px;">
            <div class="modal-header">
                <h2 style="font-size: 1rem; font-weight: 800; color: #0f172a;">Lead Information</h2>
                <button onclick="closeLeadModal()" class="btn-ghost" style="padding: 0.25rem;"><i
                        class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="leadDetailsContent">
                <!-- Data loaded via JS -->
            </div>
            <div class="modal-footer">
                <button onclick="closeLeadModal()" class="btn btn-ghost"
                    style="font-size: 0.8125rem; font-weight: 700;">Close</button>
            </div>
        </div>
    </div>
</body>

</html>