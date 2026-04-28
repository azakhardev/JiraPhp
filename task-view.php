<?php
require_once 'inc/config.php';
require_once 'inc/auth_check.php';

// Get Task ID
$taskId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$taskId) { header('Location: index.php'); exit(); }

// Load Task, Project & Comments Data
try {
    $task = Task::getById($taskId);
    if (!$task) { header('Location: index.php'); exit(); }

    $projectId = $task['project_id'];
    $project = Project::getById($projectId);
    $members = Project::getMembers($projectId);
    $statuses = Status::getByProject($projectId);

    // NEW: Load comments for this task
    $comments = Comment::getByTask($taskId);
} catch (\Throwable $e) {
    die("Database error: " . $e->getMessage());
}

$userId = $_SESSION['user_id'];

// --- AUTHORIZATION ---
$userRole = 'none';
foreach ($members as $m) {
    if ($m['id'] == $userId) $userRole = $m['role'];
}
if ($userRole === 'none') { die("You do not have access to this task."); }

// Only admins and editors can edit the task itself (everyone can comment)
$canEdit = ($userRole === 'admin' || $userRole === 'editor');

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ACTION 1: UPDATE TASK
    if ($_POST['action'] === 'update_task') {
        if (!$canEdit) { die("Permission denied."); }

        try {
            $title = trim(htmlspecialchars($_POST['title'] ?? ''));
            $description = trim(htmlspecialchars($_POST['description'] ?? ''));
            $assigneeId = !empty($_POST['assignee_id']) ? (int)$_POST['assignee_id'] : null;
            $reporterId = (int)$_POST['reporter_id'];
            $statusId = !empty($_POST['status_id']) ? (int)$_POST['status_id'] : null;
            $priorityWeight = (int)($_POST['priority_weight'] ?? 3);
            $timeSpent = (int)($_POST['time_spent_minutes'] ?? 0);
            $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

            if (!empty($title)) {
                Task::update($taskId, $title, $description, $assigneeId, $reporterId, $statusId, $priorityWeight, $timeSpent, $dueDate);
                $_SESSION['flash_success'] = "Task was successfully updated.";
            } else {
                $_SESSION['flash_error'] = "Task title cannot be empty.";
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = "Error updating task: " . $e->getMessage();
        }
    }
    // ACTION 2: ADD COMMENT (Everyone in the project can do this)
    elseif ($_POST['action'] === 'add_comment') {
        try {
            // Sanitize content
            $content = trim(htmlspecialchars($_POST['content'] ?? ''));

            if (!empty($content)) {
                Comment::create($taskId, $userId, $content);
                $_SESSION['flash_success'] = "Comment posted.";
            } else {
                $_SESSION['flash_error'] = "Comment cannot be empty.";
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = "Error posting comment: " . $e->getMessage();
        }
    }
    // ACTION 3: DELETE COMMENT
    elseif ($_POST['action'] === 'delete_comment') {
        try {
            $commentId = (int)$_POST['comment_id'];
            $comment = Comment::getById($commentId);

            // Security check: Ensure the comment exists and the current user is the author
            if ($comment && $comment['user_id'] == $userId) {
                Comment::delete($commentId);
                $_SESSION['flash_success'] = "Comment deleted.";
            } else {
                $_SESSION['flash_error'] = "You do not have permission to delete this comment.";
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = "Error deleting comment: " . $e->getMessage();
        }
    }
    // ACTION 4: EDIT COMMENT
    elseif ($_POST['action'] === 'edit_comment') {
        try {
            $commentId = (int)$_POST['comment_id'];
            $content = trim(htmlspecialchars($_POST['content'] ?? ''));
            $comment = Comment::getById($commentId);

            // Security check: Ensure the current user is the author
            if ($comment && $comment['user_id'] == $userId) {
                if (!empty($content)) {
                    Comment::update($commentId, $content);
                    $_SESSION['flash_success'] = "Comment updated.";
                } else {
                    $_SESSION['flash_error'] = "Comment cannot be empty.";
                }
            } else {
                $_SESSION['flash_error'] = "You do not have permission to edit this comment.";
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = "Error updating comment: " . $e->getMessage();
        }
    }

    // PRG Redirect for both actions
    header("Location: task-view.php?id=" . $taskId);
    exit();
}

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once 'inc/header.php';
?>

    <div class="container py-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="project-detail.php?id=<?= $projectId ?>"><?= htmlspecialchars($project['name'] ?? 'Project') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Task Detail</li>
            </ol>
        </nav>

        <?php if ($success): ?> <div class="alert alert-success alert-dismissible fade show"><?= $success ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php endif; ?>
        <?php if ($error): ?> <div class="alert alert-danger alert-dismissible fade show"><?= $error ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php endif; ?>

        <div class="row">
            <div class="col-lg-8 mb-4">

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form id="taskForm" method="POST">
                            <input type="hidden" name="action" value="update_task">

                            <div class="mb-4">
                                <label class="form-label text-muted small fw-bold">Task Title</label>
                                <input type="text" name="title" class="form-control form-control-lg fw-bold" value="<?= htmlspecialchars($task['title'] ?? '') ?>" <?= $canEdit ? 'required' : 'disabled' ?>>
                            </div>

                            <div class="mb-4">
                                <label class="form-label text-muted small fw-bold">Description</label>
                                <textarea name="description" class="form-control" rows="8" <?= $canEdit ? '' : 'disabled' ?>><?= htmlspecialchars($task['description'] ?? '') ?></textarea>
                            </div>

                            <?php if ($canEdit): ?>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-bold">
                        <i class="bi bi-chat-left-text"></i> Comments (<?= count($comments) ?>)
                    </div>
                    <div class="card-body">

                        <div class="mb-4 overflow-auto pe-2" style="max-height: 400px;">
                            <?php if (empty($comments)): ?>
                                <p class="text-muted small fst-italic">No comments yet. Start the conversation!</p>
                            <?php else: ?>
                                <?php foreach ($comments as $c): ?>
                                    <div class="d-flex mb-3 pb-3 border-bottom">
                                        <div class="me-3">
                                            <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px; font-weight: bold;">
                                                <?= strtoupper(substr($c['username'], 0, 1)) ?>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <strong><?= htmlspecialchars($c['username']) ?></strong>

                                                <div class="text-end">
                                                    <small class="text-muted me-2"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></small>

                                                    <?php if ($c['user_id'] == $userId): ?>
                                                        <button type="button" class="btn btn-sm btn-link text-primary p-0 me-2" data-bs-toggle="collapse" data-bs-target="#editComment<?= $c['id'] ?>" title="Edit Comment"><i class="bi bi-pencil"></i></button>

                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                                            <input type="hidden" name="action" value="delete_comment">
                                                            <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Delete Comment"><i class="bi bi-trash"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="text-dark" style="word-break: break-word;">
                                                <?= nl2br(htmlspecialchars($c['content'])) ?>
                                            </div>

                                            <?php if ($c['user_id'] == $userId): ?>
                                                <div class="collapse mt-2" id="editComment<?= $c['id'] ?>">
                                                    <form method="POST" class="bg-light p-2 rounded border">
                                                        <input type="hidden" name="action" value="edit_comment">
                                                        <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                                        <div class="mb-2">
                                                            <textarea name="content" class="form-control form-control-sm" rows="2" required><?= htmlspecialchars($c['content']) ?></textarea>
                                                        </div>
                                                        <button type="submit" class="btn btn-sm btn-primary">Save changes</button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editComment<?= $c['id'] ?>">Cancel</button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>

                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="add_comment">
                            <div class="mb-3">
                                <textarea name="content" class="form-control" rows="3" placeholder="Write a comment... (e.g. what needs testing, updates on progress)" required></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-success"><i class="bi bi-send"></i> Post Comment</button>
                            </div>
                        </form>

                    </div>
                </div>

            </div>

            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-bold">Details</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1">Status</label>
                            <select name="status_id" class="form-select form-select-sm" form="taskForm" <?= $canEdit ? '' : 'disabled' ?>>
                                <option value="">-- No Status --</option>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= ($task['status_id'] == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1">Assignee</label>
                            <select name="assignee_id" class="form-select form-select-sm" form="taskForm" <?= $canEdit ? '' : 'disabled' ?>>
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= ($task['assignee_id'] == $m['id']) ? 'selected' : '' ?>><?= htmlspecialchars($m['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1">Reporter (Creator)</label>
                            <select name="reporter_id" class="form-select form-select-sm" form="taskForm" <?= $canEdit ? '' : 'disabled' ?>>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= ($task['reporter_id'] == $m['id']) ? 'selected' : '' ?>><?= htmlspecialchars($m['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1">Priority</label>
                            <select name="priority_weight" class="form-select form-select-sm" form="taskForm" <?= $canEdit ? '' : 'disabled' ?>>
                                <option value="5" <?= ($task['priority_weight'] == 5) ? 'selected' : '' ?>>Critical (5)</option>
                                <option value="4" <?= ($task['priority_weight'] == 4) ? 'selected' : '' ?>>High (4)</option>
                                <option value="3" <?= ($task['priority_weight'] == 3) ? 'selected' : '' ?>>Medium (3)</option>
                                <option value="2" <?= ($task['priority_weight'] == 2) ? 'selected' : '' ?>>Low (2)</option>
                                <option value="1" <?= ($task['priority_weight'] == 1) ? 'selected' : '' ?>>Lowest (1)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1">Due Date</label>
                            <input type="date" name="due_date" class="form-control form-control-sm" form="taskForm" value="<?= htmlspecialchars($task['due_date'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted mb-1">Time Spent (minutes)</label>
                            <input type="number" name="time_spent_minutes" class="form-control form-control-sm" form="taskForm" min="0" value="<?= (int)($task['time_spent_minutes'] ?? 0) ?>" <?= $canEdit ? '' : 'disabled' ?>>
                            <div class="form-text" style="font-size: 0.7rem;">(e.g. 120 = 2 hours)</div>
                        </div>

                    </div>
                </div>

                <div class="text-muted small mt-3 px-2">
                    Created: <?= date('d.m.Y H:i', strtotime($task['created_at'] ?: 'now')) ?>
                </div>
            </div>
        </div>
    </div>

<?php require_once 'inc/footer.php'; ?>