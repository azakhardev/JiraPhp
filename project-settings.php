    <?php
    require_once 'inc/config.php';
    require_once 'inc/auth_check.php';

    $projectId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$projectId) { header('Location: index.php'); exit(); }

    $project = Project::getById($projectId);
    $userId = $_SESSION['user_id'];

    // --- AUTORIZACE: Může nastavení měnit jen admin? ---
    $members = Project::getMembers($projectId);
    $isAdmin = false;
    foreach ($members as $m) {
        if ($m['id'] == $userId && $m['role'] === 'admin') $isAdmin = true;
    }
    if (!$isAdmin) { die("Not enough rights to edit this project."); }

    $error = '';
    $success = '';

    // --- ZPRACOVÁNÍ AKCÍ (POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'update_info') {
                Project::update($projectId, $_POST['name'], $_POST['description'], $_POST['color_hex']);
                $_SESSION['flash_success'] = "Information updated.";
            }
            elseif ($action === 'delete_project') {
                Project::delete($projectId);
                header('Location: index.php?msg=deleted');
                exit();
            }
            elseif ($action === 'add_tag') {
                Tag::add($projectId, trim($_POST['tag_name']));
                $_SESSION['flash_success'] = "Tag added.";
            }
            elseif ($action === 'delete_tag') {
                Tag::delete((int)$_POST['tag_id']);
                $_SESSION['flash_success'] = "Tag deleted.";
            }
            elseif ($action === 'add_status') {
                Status::add($projectId, trim($_POST['status_name']));
                $_SESSION['flash_success'] = "Status added.";
            }
            elseif ($action === 'delete_status') {
                Status::delete((int)$_POST['status_id']);
                $_SESSION['flash_success'] = "Status deleted.";
            }
            elseif ($action === 'add_member') {
                $emailToAdd = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                $userToInvite = User::findByEmail($emailToAdd);

                if ($userToInvite) {
                    $isAlreadyMember = false;
                    foreach ($members as $existingMember) {
                        if ($existingMember['id'] === $userToInvite['id']) {
                            $isAlreadyMember = true; break;
                        }
                    }

                    if ($isAlreadyMember) {
                        $_SESSION['flash_error'] = "This user is already a member of the project.";
                    } else {
                        Project::addMember($projectId, $userToInvite['id'], $_POST['role']);
                        $_SESSION['flash_success'] = "User added successfully.";
                    }
                } else {
                    $_SESSION['flash_error'] = "User with this email was not found.";
                }
            }
            elseif ($action === 'remove_member') {
                if ((int)$_POST['member_id'] !== $userId) {
                    Project::removeMember($projectId, (int)$_POST['member_id']);
                    $_SESSION['flash_success'] = "Member removed.";
                }
            }
            elseif ($action === 'change_role') {
                $memberId = (int)$_POST['member_id'];
                $newRole = $_POST['new_role'];

                // Security: Prevent the user from changing their own role to avoid locking themselves out
                if ($memberId !== $userId && in_array($newRole, ['admin', 'editor', 'viewer'])) {
                    Project::updateMemberRole($projectId, $memberId, $newRole);
                    $_SESSION['flash_success'] = "User role updated successfully.";
                }
            }

            // PRG VZOR: Po dokončení POST akce uděláme redirect na aktuální stránku (GET)
            header("Location: project-settings.php?id=" . $projectId);
            exit();

        } catch (\Throwable $e) {
            // I chybu uložíme do session a uděláme redirect
            $_SESSION['flash_error'] = "Error: " . $e->getMessage();
            header("Location: project-settings.php?id=" . $projectId);
            exit();
        }
    }

    // --- VÝBĚR FLASH MESSAGES ZE SESSION ---
    // Pokud jsme po redirectu, vytáhneme hlášky ze session a hned je smažeme
    $success = $_SESSION['flash_success'] ?? '';
    $error = $_SESSION['flash_error'] ?? '';
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);

    // Načtení dat pro výpis
    $tags = Tag::getByProject($projectId);
    $statuses = Status::getByProject($projectId);
    $members = Project::getMembers($projectId);
    $allAvailableUsers = User::getAllExcept($userId);

    require_once 'inc/header.php';
    ?>

        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-gear-fill"></i> Project settings: <?= htmlspecialchars($project['name']) ?></h2>
                <a href="project-detail.php?id=<?= $projectId ?>" class="btn btn-outline-secondary">Back to project</a>
            </div>

            <?php if ($success): ?> <div class="alert alert-success alert-dismissible fade show"><?= $success ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php endif; ?>
            <?php if ($error): ?> <div class="alert alert-danger alert-dismissible fade show"><?= $error ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white fw-bold">Basic information</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_info">
                                <div class="mb-3">
                                    <label class="form-label">Project name</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($project['name']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($project['description']) ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Project color</label>
                                    <input type="color" name="color_hex" class="form-control form-control-color w-100" value="<?= htmlspecialchars($project['color_hex']) ?>">
                                </div>
                                <button type="submit" class="btn btn-primary">Save changes</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white fw-bold">Project Members</div>
                        <div class="card-body d-flex flex-column justify-content-between">

                            <div class="overflow-auto pe-2 flex-grow-1" style="max-height: 250px;">
                                <ul class="list-group list-group-flush mb-0">
                                    <?php foreach ($members as $m): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <div>
                                                <strong><?= htmlspecialchars($m['username']) ?></strong> <br>
                                                <small class="text-muted"><?= htmlspecialchars($m['email']) ?></small>
                                            </div>

                                            <div class="d-flex align-items-center">
                                                <?php if ($m['id'] != $userId && $m['id'] != $project['created_by']): ?>
                                                    <form method="POST" class="me-2">
                                                        <input type="hidden" name="action" value="change_role">
                                                        <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                                                        <select name="new_role" class="form-select form-select-sm" onchange="this.form.submit()">
                                                            <option value="viewer" <?= $m['role'] === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                                                            <option value="editor" <?= $m['role'] === 'editor' ? 'selected' : '' ?>>Editor</option>
                                                            <option value="admin" <?= $m['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                        </select>
                                                    </form>
                                                    <form method="POST" onsubmit="return confirm('Are you sure you want to remove this member?')">
                                                        <input type="hidden" name="action" value="remove_member">
                                                        <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                                                        <button type="submit" class="btn btn-link text-danger p-0" title="Remove Member"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                <?php elseif($m['id'] == $project['created_by']): ?>
                                                    <span class="badge bg-danger">Owner</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">Admin (You)</span>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>

                            <form method="POST" class="row g-2 mt-3 pt-3 border-top">
                                <input type="hidden" name="action" value="add_member">
                                <div class="col-7">
                                    <input type="email" name="email" class="form-control form-control-sm" list="userEmails" placeholder="Start typing email..." required autocomplete="off">

                                    <datalist id="userEmails">
                                        <?php foreach ($allAvailableUsers as $availableUser): ?>
                                            <option value="<?= htmlspecialchars($availableUser['email']) ?>">
                                                <?= htmlspecialchars($availableUser['username']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <div class="col-3">
                                    <select name="role" class="form-select form-select-sm">
                                        <option value="viewer">Viewer</option>
                                        <option value="editor">Editor</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <div class="col-2">
                                    <button type="submit" class="btn btn-sm btn-success w-100" title="Add member"><i class="bi bi-plus"></i></button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold">Workflow configuration (Tags and Statuses)</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 border-end">
                                    <h6>Statuses</h6>
                                    <div class="mb-3">
                                        <?php foreach ($statuses as $s): ?>
                                            <span class="badge bg-light text-dark border p-2 mb-1">
                                        <?= htmlspecialchars($s['name']) ?>
                                        <form method="POST" class="d-inline ms-1">
                                            <input type="hidden" name="action" value="delete_status">
                                            <input type="hidden" name="status_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn-close" style="font-size: 0.6rem;"></button>
                                        </form>
                                    </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <form method="POST" class="input-group input-group-sm">
                                        <input type="hidden" name="action" value="add_status">
                                        <input type="text" name="status_name" class="form-control" placeholder="New status..." required>
                                        <button type="submit" class="btn btn-success">Add</button>
                                    </form>
                                </div>
                                <div class="col-md-6">
                                    <h6>Tags</h6>
                                    <div class="mb-3">
                                        <?php foreach ($tags as $t): ?>
                                            <span class="badge bg-info text-white p-2 mb-1">
                                        <i class="bi bi-tag"></i> <?= htmlspecialchars($t['name']) ?>
                                        <form method="POST" class="d-inline ms-1">
                                            <input type="hidden" name="action" value="delete_tag">
                                            <input type="hidden" name="tag_id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn-close btn-close-white" style="font-size: 0.6rem;"></button>
                                        </form>
                                    </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <form method="POST" class="input-group input-group-sm">
                                        <input type="hidden" name="action" value="add_tag">
                                        <input type="text" name="tag_name" class="form-control" placeholder="New tag..." required>
                                        <button type="submit" class="btn btn-success">Add</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-danger mb-5">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="text-danger mb-0">Danger zone</h5>
                                <small class="text-muted">By deleting this project you will remove every task including comments.</small>
                            </div>
                            <form method="POST" onsubmit="return confirm('ARE YOU SURE YOU WANT TO DELETE WHOLE PROJECT? This action is irreversible.')">
                                <input type="hidden" name="action" value="delete_project">
                                <button type="submit" class="btn btn-outline-danger">Delete project</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php require_once 'inc/footer.php'; ?>