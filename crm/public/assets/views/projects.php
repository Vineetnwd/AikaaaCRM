<?php
use Core\Database;
use Core\Auth;

$db = Database::getInstance();
$company_id = Auth::companyId();

// Fetch Stages
$stages = $db->fetchAll("SELECT * FROM project_stages WHERE company_id = ? ORDER BY sort_order ASC", [$company_id]);

// Fetch Projects
$projects = $db->fetchAll("
    SELECT p.*, l.name as lead_name, ps.name as stage_name
    FROM projects p
    LEFT JOIN leads l ON p.lead_id = l.id
    LEFT JOIN project_stages ps ON p.current_stage_id = ps.id
    WHERE p.company_id = ?
    ORDER BY p.created_at DESC
", [$company_id]);

// Organize projects by stage
$board = [];
foreach ($stages as $stage) {
    $board[$stage['id']] = [
        'info' => $stage,
        'projects' => array_filter($projects, fn($p) => $p['current_stage_id'] == $stage['id'])
    ];
}

// Unassigned projects (if any)
$unassigned = array_filter($projects, fn($p) => empty($p['current_stage_id']));

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Pipeline | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .kanban-board {
            display: flex;
            gap: 1.5rem;
            overflow-x: auto;
            padding: 1rem 0;
            min-height: calc(100vh - 200px);
            align-items: flex-start;
        }

        .kanban-column {
            background: #f1f5f9;
            border-radius: 1rem;
            width: 320px;
            min-width: 320px;
            display: flex;
            flex-direction: column;
            max-height: 100%;
            border: 1px solid #e2e8f0;
        }

        .kanban-header {
            padding: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .kanban-list {
            padding: 0.75rem;
            flex-grow: 1;
            overflow-y: auto;
            min-height: 100px;
        }

        .project-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            cursor: grab;
            transition: all 0.2s;
        }

        .project-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
        }

        .project-card:active {
            cursor: grabbing;
        }

        .progress-bar {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin: 1rem 0 0.5rem 0;
        }

        .progress-fill {
            height: 100%;
            transition: width 0.3s ease;
        }

        .timeline-container {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            border: 1px solid #e2e8f0;
            margin-top: 1rem;
        }

        .timeline-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .timeline-bar-bg {
            flex-grow: 1;
            height: 32px;
            background: #f8fafc;
            border-radius: 16px;
            position: relative;
            overflow: hidden;
        }

        .timeline-bar {
            height: 100%;
            border-radius: 16px;
            display: flex;
            align-items: center;
            padding: 0 1rem;
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'partials/sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div>
                    <h1 class="page-title">Work Pipeline</h1>
                    <p style="color: var(--text-muted); font-size: 0.8125rem; font-weight: 500;">Track active projects
                        and execution stages</p>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <button class="btn btn-primary" onclick="openNewProjectModal()">
                        <i class="fas fa-plus"></i> New Project
                    </button>
                </div>
            </header>

            <div
                style="display: flex; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">
                <button onclick="switchView('kanban')" id="tab-kanban" class="btn btn-ghost active-tab"
                    style="font-weight: 800; font-size: 0.875rem;">
                    <i class="fas fa-columns" style="margin-right: 6px;"></i> Kanban Board
                </button>
                <button onclick="switchView('timeline')" id="tab-timeline" class="btn btn-ghost"
                    style="font-weight: 800; font-size: 0.875rem;">
                    <i class="fas fa-stream" style="margin-right: 6px;"></i> Timeline View
                </button>
            </div>

            <!-- Kanban View -->
            <div id="kanbanView" class="kanban-board">
                <?php foreach ($board as $stage_id => $data): ?>
                    <div class="kanban-column" ondragover="allowDrop(event)" ondrop="drop(event, <?= $stage_id ?>)">
                        <div class="kanban-header">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span
                                    style="height: 8px; width: 8px; background: <?= $data['info']['color'] ?>; border-radius: 50%;"></span>
                                <h3 style="font-size: 0.875rem; font-weight: 800; color: #1e293b;">
                                    <?= htmlspecialchars($data['info']['name']) ?></h3>
                                <span
                                    style="background: #e2e8f0; color: #64748b; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; font-weight: 800;">
                                    <?= count($data['projects']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="kanban-list" id="stage-<?= $stage_id ?>">
                            <?php foreach ($data['projects'] as $p): ?>
                                <div class="project-card" draggable="true" ondragstart="drag(event, <?= $p['id'] ?>)"
                                    id="project-<?= $p['id'] ?>">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                        <h4 style="font-size: 0.9375rem; font-weight: 800; color: #1e293b; line-height: 1.3;">
                                            <?= htmlspecialchars($p['title']) ?>
                                        </h4>
                                        <button class="btn-ghost" style="padding: 2px; color: #94a3b8;"><i
                                                class="fas fa-ellipsis-v"></i></button>
                                    </div>
                                    <?php if ($p['lead_name']): ?>
                                        <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; margin-bottom: 0.75rem;">
                                            <i class="fas fa-user" style="margin-right: 4px; opacity: 0.5;"></i>
                                            <?= htmlspecialchars($p['lead_name']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; font-size: 0.7rem; font-weight: 700; color: #64748b;">
                                        <span>Progress</span>
                                        <span style="color: var(--primary);"><?= $p['progress_percent'] ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill"
                                            style="width: <?= $p['progress_percent'] ?>%; background: <?= $data['info']['color'] ?>;">
                                        </div>
                                    </div>

                                    <div
                                        style="display: flex; justify-content: space-between; margin-top: 1rem; align-items: center;">
                                        <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 600;">
                                            <i class="far fa-calendar-alt"></i>
                                            <?= $p['end_date'] ? date('d M', strtotime($p['end_date'])) : 'No date' ?>
                                        </div>
                                        <div
                                            style="display: flex; -webkit-mask-image: linear-gradient(to right, transparent, black 20%);">
                                            <div class="avatar"
                                                style="width: 24px; height: 24px; border: 2px solid white; font-size: 0.6rem;">A
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Timeline View -->
            <div id="timelineView" style="display: none;">
                <div class="timeline-container">
                    <?php if (empty($projects)): ?>
                        <div style="text-align: center; padding: 3rem; color: #94a3b8;">
                            <i class="fas fa-stream" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.2;"></i>
                            <p>No projects found to display in timeline.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($projects as $p): ?>
                            <div class="timeline-row">
                                <div style="width: 200px; min-width: 200px;">
                                    <div style="font-weight: 800; color: #1e293b; font-size: 0.875rem;">
                                        <?= htmlspecialchars($p['title']) ?></div>
                                    <div style="font-size: 0.7rem; color: #64748b; font-weight: 600;">
                                        <?= $p['stage_name'] ?: 'No Stage' ?></div>
                                </div>
                                <div class="timeline-bar-bg">
                                    <?php
                                    // Simple mock calculation for bar position/width
                                    $width = max(10, $p['progress_percent']);
                                    $color = 'var(--primary-hover, #4f46e5)';
                                    foreach ($stages as $s)
                                        if ($s['id'] == $p['current_stage_id'])
                                            $color = $s['color'];
                                    ?>
                                    <div class="timeline-bar"
                                        style="width: <?= $width ?>%; background: <?= $color ?>; box-shadow: 0 4px 6px -1px <?= $color ?>40;">
                                        <?= $p['progress_percent'] ?>% Complete
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- New Project Modal (Hidden by default) -->
    <div id="projectModal" class="modal-overlay"
        style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div class="modal-container" style="background: white; border-radius: 1rem; width: 400px; padding: 1.5rem;">
            <h2 style="margin-bottom: 1rem;">New Project</h2>
            <form id="newProjectForm">
                <div style="margin-bottom: 1rem;">
                    <label
                        style="display: block; font-size: 0.75rem; font-weight: 800; color: #64748b; margin-bottom: 0.4rem;">Project
                        Title</label>
                    <input type="text" name="title" required class="form-input" style="width: 100%;">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label
                        style="display: block; font-size: 0.75rem; font-weight: 800; color: #64748b; margin-bottom: 0.4rem;">Link
                        to Lead (Optional)</label>
                    <select name="lead_id" class="form-input" style="width: 100%;">
                        <option value="">None</option>
                        <?php
                        $leads = $db->fetchAll("SELECT id, name FROM leads WHERE company_id = ? AND status = 'converted' LIMIT 50", [$company_id]);
                        foreach ($leads as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom: 1.5rem; display: flex; gap: 1rem;">
                    <div style="flex: 1;">
                        <label
                            style="display: block; font-size: 0.75rem; font-weight: 800; color: #64748b; margin-bottom: 0.4rem;">End
                            Date</label>
                        <input type="date" name="end_date" class="form-input" style="width: 100%;">
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" onclick="closeProjectModal()" class="btn btn-ghost">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Project</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchView(view) {
            const kanban = document.getElementById('kanbanView');
            const timeline = document.getElementById('timelineView');
            const tabKanban = document.getElementById('tab-kanban');
            const tabTimeline = document.getElementById('tab-timeline');

            if (view === 'kanban') {
                kanban.style.display = 'flex';
                timeline.style.display = 'none';
                tabKanban.classList.add('active-tab');
                tabTimeline.classList.remove('active-tab');
                tabKanban.style.color = 'var(--primary)';
                tabTimeline.style.color = 'var(--text-muted)';
            } else {
                kanban.style.display = 'none';
                timeline.style.display = 'block';
                tabKanban.classList.remove('active-tab');
                tabTimeline.classList.add('active-tab');
                tabTimeline.style.color = 'var(--primary)';
                tabKanban.style.color = 'var(--text-muted)';
            }
        }

        function openNewProjectModal() {
            document.getElementById('projectModal').style.display = 'flex';
        }
        function closeProjectModal() {
            document.getElementById('projectModal').style.display = 'none';
        }

        // Drag and Drop
        let draggedProjectId = null;

        function drag(ev, id) {
            draggedProjectId = id;
            ev.dataTransfer.setData("text", id);
            ev.target.style.opacity = "0.5";
        }

        function allowDrop(ev) {
            ev.preventDefault();
        }

        async function drop(ev, stageId) {
            ev.preventDefault();
            const projectId = draggedProjectId;
            if (!projectId) return;

            const card = document.getElementById('project-' + projectId);
            card.style.opacity = "1";

            // Optimistic UI update
            document.getElementById('stage-' + stageId).appendChild(card);

            try {
                const res = await fetch('<?= APP_URL ?>/public/index.php/api/projects.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: projectId, stage_id: stageId })
                });
                if (!res.ok) {
                    location.reload();
                }
            } catch (error) {
                location.reload();
            }
        }

        document.getElementById('newProjectForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());

            try {
                const res = await fetch('<?= APP_URL ?>/public/index.php/api/projects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                if (res.ok) {
                    location.reload();
                }
            } catch (error) {
                alert('Error creating project');
            }
        });
    </script>
</body>

</html>