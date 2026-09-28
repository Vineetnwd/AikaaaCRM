<?php
use Core\Database;
use Core\Auth;

if (!Auth::check()) {
    header("Location: " . APP_URL . "/public/index.php/login");
    exit;
}

$db = Database::getInstance();
$company_id = Auth::companyId();
$is_admin = Auth::role() === 'admin' || Auth::role() === 'manager';
$is_executive = Auth::role() === 'executive';

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$selected_emp_id = $_GET['employee_id'] ?? (Auth::isExecutive() ? Auth::employeeId() : null);

// Data fetching
$query = "SELECT l.*, e.name as employee_name, e.employee_id as emp_code 
          FROM attendance_logs l 
          JOIN employees e ON l.employee_id = e.id 
          WHERE l.company_id = ? AND MONTH(l.work_date) = ? AND YEAR(l.work_date) = ?";
$params = [$company_id, $month, $year];

if (!$is_admin || $selected_emp_id) {
    $target_id = $is_admin ? $selected_emp_id : Auth::employeeId();
    $query .= " AND l.employee_id = ?";
    $params[] = $target_id;
}

$logs = $db->fetchAll($query, $params);

// Grouping and Stats
$stats = [];
$employee_logs = []; // For admin table: [emp_id => [date => log]]

foreach ($logs as $log) {
    $emp_id = $log['employee_id'];
    if (!isset($stats[$emp_id])) {
        $stats[$emp_id] = ['present' => 0, 'late' => 0, 'total_hours' => 0, 'name' => $log['employee_name'], 'code' => $log['emp_code']];
    }
    $stats[$emp_id]['present']++;
    
    if ($log['check_out']) {
        $start = new DateTime($log['check_in']);
        $end = new DateTime($log['check_out']);
        $diff = $start->diff($end);
        $stats[$emp_id]['total_hours'] += $diff->h + ($diff->i / 60);
    }
    
    $employee_logs[$emp_id][$log['work_date']] = $log;
}

// All employees for admin filter
$all_employees = $is_admin ? $db->fetchAll("SELECT id, name FROM employees WHERE company_id = ? AND status = 'active'", [$company_id]) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .report-header { background: white; border-radius: 1.25rem; padding: 1.5rem 2rem; border: 1px solid var(--border); margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .filter-group { display: flex; gap: 1rem; align-items: center; }
        
        /* Stats Cards */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 1.25rem; border: 1px solid var(--border); display: flex; align-items: center; gap: 1rem; }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
        .stat-info h4 { font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin: 0; }
        .stat-info p { font-size: 1.5rem; font-weight: 900; color: #0f172a; margin: 0; }

        /* Calendar Styles */
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #e2e8f0; border: 1px solid #e2e8f0; border-radius: 1rem; overflow: hidden; }
        .calendar-day-head { background: #f8fafc; padding: 0.75rem; text-align: center; font-weight: 800; font-size: 0.65rem; color: #64748b; text-transform: uppercase; }
        .calendar-day { background: white; min-height: 80px; padding: 0.5rem; position: relative; transition: all 0.2s; }
        .calendar-day:hover { background: #f8fafc; z-index: 1; }
        .calendar-day.empty { background: #f8fafc; }
        .day-num { font-weight: 800; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.25rem; display: block; }
        .attendance-tag { font-size: 0.55rem; font-weight: 800; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 2px; }
        .tag-present { background: #d1fae5; color: #065f46; }
        .tag-absent { background: #fee2e2; color: #991b1b; }
        .time-info { font-size: 0.6rem; color: #64748b; font-weight: 700; margin-top: 0.35rem; display: flex; flex-direction: column; gap: 1px; line-height: 1.2; }
        .time-info i { width: 10px; color: #94a3b8; font-size: 0.55rem; }

        /* Table Styles */
        .table-container { background: white; border-radius: 1.25rem; border: 1px solid var(--border); overflow-x: auto; position: relative; }
        .att-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 1200px; }
        .att-table th { background: #f8fafc; padding: 1rem; text-align: left; font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 10; }
        .att-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.8125rem; font-weight: 600; color: #1e293b; background: white; }
        
        /* Sticky Column for Name */
        .sticky-col { position: sticky; left: 0; z-index: 5; border-right: 1px solid #e2e8f0 !important; box-shadow: 2px 0 5px rgba(0,0,0,0.02); }
        .att-table th.sticky-col { background: #f8fafc; z-index: 11; }
        
        .day-status { width: 30px; height: 30px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800; margin: 0 auto; }
        .status-p { background: #d1fae5; color: #065f46; }
        .status-a { background: #fee2e2; color: #991b1b; }
        
        /* Stats column sticky at the end */
        .sticky-end { position: sticky; right: 0; z-index: 5; border-left: 1px solid #e2e8f0 !important; box-shadow: -2px 0 5px rgba(0,0,0,0.02); }
        .att-table th.sticky-end { background: #f8fafc; z-index: 11; }
        
        @media (max-width: 1024px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>
        <main class="main-content">
            <header class="header">
                <div style="display: flex; justify-content: space-between; align-items: flex-end; width: 100%;">
                    <div>
                        <h1 class="page-title">Attendance Insights</h1>
                        <p style="color: var(--text-muted); font-size: 0.8125rem; font-weight: 500;">Detailed monthly presence and work hours report</p>
                    </div>
                </div>
            </header>

            <div class="report-header">
                <div class="filter-group">
                    <form style="display: flex; gap: 0.75rem;">
                        <?php if ($is_admin): ?>
                        <select name="employee_id" class="form-input" style="width: 200px; margin: 0;">
                            <option value="">All Employees</option>
                            <?php foreach ($all_employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" <?= $selected_emp_id == $emp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($emp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        <select name="month" class="form-input" style="width: 140px; margin: 0;">
                            <?php for($m=1; $m<=12; $m++): ?>
                                <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $month == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="year" class="form-input" style="width: 100px; margin: 0;">
                            <?php for($y=date('Y'); $y>=date('Y')-2; $y--): ?>
                                <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn-primary" style="padding: 0 1.25rem;"><i class="fas fa-filter"></i> Apply</button>
                    </form>
                </div>
                <div class="actions">
                    <button onclick="window.print()" class="btn-ghost" style="border: 1px solid var(--border);"><i class="fas fa-print"></i> Export PDF</button>
                </div>
            </div>

            <?php if ($is_executive || $selected_emp_id): 
                $emp_id = $is_admin ? $selected_emp_id : Auth::employeeId();
                $cur_stats = $stats[$emp_id] ?? ['present' => 0, 'total_hours' => 0];
                $total_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $working_days = 0;
                for($d=1; $d<=$total_days; $d++) {
                    $w = date('w', strtotime("$year-$month-$d"));
                    if ($w != 0) $working_days++; // Exclude Sundays
                }
            ?>
            <!-- Employee Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #eef2ff; color: var(--primary, #6366f1);"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-info"><h4>Days Present</h4><p><?= $cur_stats['present'] ?> / <?= $working_days ?></p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-clock"></i></div>
                    <div class="stat-info"><h4>Work Hours</h4><p><?= round($cur_stats['total_hours'], 1) ?>h</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fff7ed; color: #f59e0b;"><i class="fas fa-user-clock"></i></div>
                    <div class="stat-info"><h4>Avg Day</h4><p><?= $cur_stats['present'] > 0 ? round($cur_stats['total_hours'] / $cur_stats['present'], 1) : 0 ?>h</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fef2f2; color: #ef4444;"><i class="fas fa-percentage"></i></div>
                    <div class="stat-info"><h4>Attendance</h4><p><?= round(($cur_stats['present'] / $working_days) * 100, 1) ?>%</p></div>
                </div>
            </div>

            <!-- Calendar View -->
            <div class="calendar-grid">
                <?php $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']; 
                foreach ($days as $d) echo "<div class='calendar-day-head'>$d</div>";
                
                $first_day = date('w', strtotime("$year-$month-01"));
                for($i=0; $i<$first_day; $i++) echo "<div class='calendar-day empty'></div>";
                
                for($d=1; $d<=$total_days; $d++): 
                    $date_str = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($d, 2, '0', STR_PAD_LEFT);
                    $day_log = $employee_logs[$emp_id][$date_str] ?? null;
                    $is_sunday = date('w', strtotime($date_str)) == 0;
                ?>
                <div class="calendar-day <?= $is_sunday ? 'empty' : '' ?>">
                    <span class="day-num"><?= $d ?></span>
                    <?php if ($day_log): ?>
                        <span class="attendance-tag tag-present">PRESENT</span>
                        <div class="time-info">
                            <span><i class="fas fa-sign-in-alt"></i> <?= date('h:i A', strtotime($day_log['check_in'])) ?></span>
                            <?php if ($day_log['check_out']): ?>
                                <span><i class="fas fa-sign-out-alt"></i> <?= date('h:i A', strtotime($day_log['check_out'])) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php elseif (!$is_sunday && strtotime($date_str) <= time()): ?>
                        <span class="attendance-tag tag-absent">ABSENT</span>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>

            <?php else: ?>
            <!-- Admin Tabular View -->
            <div class="table-container">
                <table class="att-table">
                    <thead>
                        <tr>
                            <th class="sticky-col" style="min-width: 220px;">Employee</th>
                            <?php 
                            $total_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                            for($d=1; $d<=$total_days; $d++) echo "<th style='text-align:center; padding: 0.5rem;'>$d</th>";
                            ?>
                            <th class="sticky-end" style="text-align:center; min-width: 100px;">Stats</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // If all employees or specific ones
                        $display_employees = $db->fetchAll("SELECT id, name, employee_id as code, mobile FROM employees WHERE company_id = ? AND status = 'active'", [$company_id]);
                        foreach ($display_employees as $emp): 
                            $e_id = $emp['id'];
                            $emp_stat = $stats[$e_id] ?? ['present' => 0, 'total_hours' => 0];
                        ?>
                        <tr>
                            <td class="sticky-col">
                                <div style="font-weight: 800; color: #0f172a; line-height: 1.2;"><?= htmlspecialchars($emp['name']) ?></div>
                                <div style="display: flex; gap: 8px; margin-top: 4px;">
                                    <span style="font-size: 0.65rem; color: #64748b; font-weight: 700; background: #f1f5f9; padding: 1px 4px; border-radius: 4px;"><?= htmlspecialchars($emp['code']) ?></span>
                                    <?php if ($emp['mobile']): ?>
                                        <span style="font-size: 0.65rem; color: var(--primary, #6366f1); font-weight: 700;"><i class="fas fa-phone" style="font-size: 0.6rem;"></i> <?= htmlspecialchars($emp['mobile']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php for($d=1; $d<=$total_days; $d++): 
                                $date_str = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($d, 2, '0', STR_PAD_LEFT);
                                $has_log = isset($employee_logs[$e_id][$date_str]);
                                $is_future = strtotime($date_str) > time();
                                $is_sun = date('w', strtotime($date_str)) == 0;
                            ?>
                            <td style="text-align:center;">
                                <?php if ($has_log): ?>
                                    <div class="day-status status-p" title="In: <?= date('h:i A', strtotime($employee_logs[$e_id][$date_str]['check_in'])) ?>">P</div>
                                <?php elseif (!$is_sun && !$is_future): ?>
                                    <div class="day-status status-a">A</div>
                                <?php else: ?>
                                    <div style="color: #e2e8f0;">-</div>
                                <?php endif; ?>
                            </td>
                            <?php endfor; ?>
                            <td class="sticky-end" style="text-align:center; background: #f8fafc;">
                                <div style="font-weight: 900; color: var(--primary, #6366f1);"><?= $emp_stat['present'] ?>/<?= $total_days ?></div>
                                <div style="font-size: 0.65rem; color: #64748b; font-weight: 700;"><?= round($emp_stat['total_hours'], 1) ?> hrs</div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
