<?php
require_once 'inc/config.php';
require_once 'inc/auth_check.php';

$projectId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$projectId) { header('Location: index.php'); exit(); }

$project = Project::getById($projectId);
if (!$project) { header('Location: index.php'); exit(); }

$userId = $_SESSION['user_id'];
$members = Project::getMembers($projectId);

// --- ZJIŠTĚNÍ ROLE UŽIVATELE ---
$userRole = 'none';
foreach ($members as $m) {
    if ($m['id'] == $userId) {
        $userRole = $m['role'];
        break;
    }
}

if ($userRole === 'none') {
    die("You do not have access to this project.");
}

$isAdmin = ($userRole === 'admin');
$canCreateTask = ($userRole === 'admin' || $userRole === 'editor');

// --- ZPRACOVÁNÍ MAZÁNÍ ÚKOLU (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_task') {
    $taskId = (int)$_POST['task_id'];
    $taskToDelete = Task::getById($taskId);

    // Smazat může admin NEBO tvůrce úkolu
    if ($taskToDelete && ($isAdmin || $taskToDelete['reporter_id'] == $userId)) {
        Task::delete($taskId);
        $_SESSION['flash_success'] = "Task was successfully deleted.";
    } else {
        $_SESSION['flash_error'] = "You don't have permission to delete this task.";
    }
    header("Location: project-detail.php?id=" . $projectId);
    exit();
}
// --- ZPRACOVÁNÍ VYTVOŘENÍ ÚKOLU (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_task') {
    if (!$canCreateTask) {
        die("You don't have permission to create tasks.");
    }

    try {
        $title = trim(htmlspecialchars($_POST['title'] ?? '')); // Validace vstupu
        $assigneeId = !empty($_POST['assignee_id']) ? (int)$_POST['assignee_id'] : null;
        $statusId = !empty($_POST['status_id']) ? (int)$_POST['status_id'] : null;

        if (!empty($title)) {
            // Get the first status of the project to use as default (e.g. "To Do")
            $projectStatuses = Status::getByProject($projectId);

            // Create the task
            $newTaskId = Task::create($projectId, $title, '', $userId, $statusId, $assigneeId);

            // PRG Redirect directly to the new task detail!
            header("Location: task-view.php?id=" . $newTaskId);
            exit();
        } else {
            $_SESSION['flash_error'] = "Task name is required.";
            header("Location: project-detail.php?id=" . $projectId);
            exit();
        }
    } catch (\Throwable $e) { // Záchyt případné databázové chyby
        $_SESSION['flash_error'] = "Error: " . $e->getMessage();
        header("Location: project-detail.php?id=" . $projectId);
        exit();
    }
}

// --- LOGIKA FILTRŮ (Ukládání a načítání z Cookies) ---
// Pokud byl odeslán formulář s filtry (GET parametr filter_submit)
if (isset($_GET['filter_submit'])) {
    $filters = [
        'status_id' => $_GET['status_id'] ?? '',
        'assignee_id' => $_GET['assignee_id'] ?? '',
        'priority_weight' => $_GET['priority_weight'] ?? '',
        'tag_id' => $_GET['tag_id'] ?? ''
    ];
    // Uložíme do cookies na 30 dní
    setcookie("proj_{$projectId}_status", $filters['status_id'], time() + 86400 * 30);
    setcookie("proj_{$projectId}_assignee", $filters['assignee_id'], time() + 86400 * 30);
    setcookie("proj_{$projectId}_priority", $filters['priority_weight'], time() + 86400 * 30);
    setcookie("proj_{$projectId}_tag", $filters['tag_id'], time() + 86400 * 30);
} else {
    // Načteme z cookies, pokud existují
    $filters = [
        'status_id' => $_COOKIE["proj_{$projectId}_status"] ?? '',
        'assignee_id' => $_COOKIE["proj_{$projectId}_assignee"] ?? '',
        'priority_weight' => $_COOKIE["proj_{$projectId}_priority"] ?? '',
        'tag_id' => $_COOKIE["proj_{$projectId}_tag"] ?? ''
    ];
}

// Resetování filtrů
if (isset($_GET['reset_filters'])) {
    $filters = ['status_id' => '', 'assignee_id' => '', 'priority_weight' => '', 'tag_id' => ''];
    setcookie("proj_{$projectId}_status", "", time() - 3600);
    setcookie("proj_{$projectId}_assignee", "", time() - 3600);
    setcookie("proj_{$projectId}_priority", "", time() - 3600);
    setcookie("proj_{$projectId}_tag", "", time() - 3600);
    header("Location: project-detail.php?id=" . $projectId);
    exit();
}

// --- NAČTENÍ DAT ---
$tasks = Task::getByProjectFiltered($projectId, $filters);
$statuses = Status::getByProject($projectId);
$tags = Tag::getByProject($projectId);

// Seskupení úkolů podle stavu (v PHP)
$groupedTasks = [];
// Inicializujeme skupiny, aby se zobrazily i prázdné sloupce stavů
foreach ($statuses as $s) {
    $groupedTasks[$s['name']] = [];
}
// Rozřazení úkolů (z DB už jdou abecedně seřazené)
foreach ($tasks as $t) {
    $statusName = $t['status_name'] ?? 'Uncategorized';
    if (!isset($groupedTasks[$statusName])) $groupedTasks[$statusName] = [];
    $groupedTasks[$statusName][] = $t;
}

// Výběr Flash zpráv
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once 'inc/header.php';
?>

    <div class="container py-4">

        <?php if ($success): ?> <div class="alert alert-success alert-dismissible fade show"><?= $success ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php endif; ?>
        <?php if ($error): ?> <div class="alert alert-danger alert-dismissible fade show"><?= $error ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php endif; ?>

    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($project['name']) ?></li>
        </ol>
    </nav>

        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom" style="border-bottom-color: <?= htmlspecialchars($project['color_hex']) ?> !important; border-bottom-width: 4px !important;">
            <div>
                <h2 class="mb-1"><?= htmlspecialchars($project['name']) ?></h2>
                <p class="text-muted mb-0"><?= htmlspecialchars($project['description']) ?></p>
            </div>
            <div>
                <?php if ($canCreateTask): ?>
                    <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                        <i class="bi bi-plus-lg"></i> New Task
                    </button>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <a href="project-settings.php?id=<?= $projectId ?>" class="btn btn-dark"><i class="bi bi-gear-fill"></i> Settings</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm mb-4 bg-light">
            <div class="card-body py-3">
                <form method="GET" class="row g-2 align-items-end">
                    <input type="hidden" name="id" value="<?= $projectId ?>">
                    <input type="hidden" name="filter_submit" value="1">

                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">Status</label>
                        <select name="status_id" class="form-select form-select-sm">
                            <option value="">-- All --</option>
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($filters['status_id'] == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Assignee</label>
                        <select name="assignee_id" class="form-select form-select-sm">
                            <option value="">-- All --</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= ($filters['assignee_id'] == $m['id']) ? 'selected' : '' ?>><?= htmlspecialchars($m['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">Priority</label>
                        <select name="priority_weight" class="form-select form-select-sm">
                            <option value="">-- All --</option>
                            <option value="5" <?= ($filters['priority_weight'] == '5') ? 'selected' : '' ?>>Critical (5)</option>
                            <option value="4" <?= ($filters['priority_weight'] == '4') ? 'selected' : '' ?>>High (4)</option>
                            <option value="3" <?= ($filters['priority_weight'] == '3') ? 'selected' : '' ?>>Medium (3)</option>
                            <option value="2" <?= ($filters['priority_weight'] == '2') ? 'selected' : '' ?>>Low (2)</option>
                            <option value="1" <?= ($filters['priority_weight'] == '1') ? 'selected' : '' ?>>Lowest (1)</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">Tag</label>
                        <select name="tag_id" class="form-select form-select-sm">
                            <option value="">-- All --</option>
                            <?php foreach ($tags as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($filters['tag_id'] == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <button type="submit" class="btn btn-sm btn-secondary w-100 mb-1">Apply Filters</button>
                        <a href="project-detail.php?id=<?= $projectId ?>&reset_filters=1" class="btn btn-sm btn-outline-danger w-100">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <?php foreach ($groupedTasks as $statusName => $tasksInStatus): ?>
                <div class="col-12 mb-4">
                    <h5 class="border-bottom pb-2 mb-3 text-secondary">
                        <i class="bi bi-circle-half"></i> <?= htmlspecialchars($statusName) ?>
                        <span class="badge bg-secondary rounded-pill"><?= count($tasksInStatus) ?></span>
                    </h5>

                    <?php if (empty($tasksInStatus)): ?>
                        <p class="text-muted small italic ms-3">No tasks in this status.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($tasksInStatus as $task): ?>
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">

                                    <div>
                                        <a href="task-view.php?id=<?= $task['id'] ?>" class="text-decoration-none text-dark fw-bold">
                                            <?= htmlspecialchars($task['title']) ?>
                                        </a>

                                        <div class="mt-1 small">
                                        <span class="text-muted me-3">
                                            <i class="bi bi-person"></i> <?= htmlspecialchars($task['assignee_name'] ?? 'Unassigned') ?>
                                        </span>
                                            <span class="text-muted me-3">
                                            <i class="bi bi-thermometer-half"></i> Priority: <?= $task['priority_weight'] ?>
                                        </span>
                                            <?php if (!empty($task['tags_string'])): ?>
                                                <span class="text-info">
                                                <i class="bi bi-tags"></i> <?= htmlspecialchars($task['tags_string']) ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div>
                                        <a href="task-view.php?id=<?= $task['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Detail"><i class="bi bi-eye"></i></a>
                                        <?php
                                        // Smazat může admin nebo ten, kdo úkol založil
                                        if ($isAdmin || $task['reporter_id'] == $userId):
                                            ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                                <input type="hidden" name="action" value="delete_task">
                                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Task"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <div class="modal fade" id="createTaskModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="project-detail.php?id=<?= $projectId ?>" method="POST" id="createTaskForm">
                        <input type="hidden" name="action" value="create_task">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Task Name *</label>
                            <input type="text" name="title" class="form-control" required autofocus placeholder="e.g. Design the login page">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Status *</label>
                            <select name="status_id" class="form-select" required>
                                <option value="">-- Undefined --</option>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Assignee</label>
                            <select name="assignee_id" class="form-select">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="createTaskForm" class="btn btn-primary">Create Task</button>
                </div>
            </div>
        </div>
    </div>

<?php require_once 'inc/footer.php'; ?>