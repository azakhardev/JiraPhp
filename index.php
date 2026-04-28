<?php
require_once 'inc/config.php';
require_once 'inc/auth_check.php';

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// --- ZPRACOVÁNÍ FORMULÁŘE (Vytvoření projektu) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_project') {
    // Všechny vstupy od uživatele je nutné kontrolovat
    $name = trim(htmlspecialchars($_POST['name'] ?? ''));
    $description = trim(htmlspecialchars($_POST['description'] ?? ''));
    $color = $_POST['color_hex'] ?? '#3b82f6';
    $invitedUsers = $_POST['invites'] ?? []; // Pole IDček pozvaných uživatelů

    if (!empty($name)) {
        try {
            // 1. Vytvoření projektu (metoda z předchozího kroku)
            $newProjectId = Project::create($name, $description, $color, $userId);

            // 2. Přidání pozvaných uživatelů (pokud nějací jsou)
            if (!empty($invitedUsers) && is_array($invitedUsers)) {
                foreach ($invitedUsers as $invitedId) {
                    // Pozveme je s rolí editor, ať můžou přidávat úkoly
                    Project::addMember($newProjectId, (int)$invitedId, 'editor');
                }
            }

            // Přesměrování provádíme jen v situaci, že formulář neobsahoval chyby
            header("Location: index.php?success=1");
            exit();

        } catch (Exception $e) {
            $error = "Došlo k chybě při vytváření projektu: " . $e->getMessage();
        }
    } else {
        $error = "Název projektu je povinný.";
    }
}

// --- NAČTENÍ DAT PRO ZOBRAZENÍ ---
$allMyProjects = Project::getUserProjects($userId);
$allUsers = User::getAllExcept($userId); // Pro nabídku pozvánek v popupu

// Rozdělení projektů na "Moje" a "Sdílené"
$ownedProjects = [];
$sharedProjects = [];

foreach ($allMyProjects as $p) {
    if ($p['role'] === 'admin') { // Předpokládáme, že tvůrce je admin
        $ownedProjects[] = $p;
    } else {
        $sharedProjects[] = $p;
    }
}

// Kontrola zprávy o úspěchu v URL
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = "Projekt byl úspěšně vytvořen!";
}

require_once 'inc/header.php';
?>

    <div class="d-flex justify-content-between items-center mb-4 mt-4">
        <h2>Dashboard</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProjectModal">
            <i class="bi bi-plus-lg"></i> New project
        </button>
    </div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

    <ul class="nav nav-tabs mb-4" id="projectTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="owned-tab" data-bs-toggle="tab" data-bs-target="#owned" type="button" role="tab">
                My Projects (<?= count($ownedProjects) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="shared-tab" data-bs-toggle="tab" data-bs-target="#shared" type="button" role="tab">
                Other Projects (<?= count($sharedProjects) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="projectTabsContent">

        <div class="tab-pane fade show active" id="owned" role="tabpanel">
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php if (empty($ownedProjects)): ?>
                    <div class="col-12 text-center text-muted py-5">
                        <p>You don't have any project for now. Create your first!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($ownedProjects as $project): ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm border-0" style="border-top: 5px solid <?= htmlspecialchars($project['color_hex']) ?> !important;">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">
                                        <a href="project-detail.php?id=<?= $project['id'] ?>" class="text-decoration-none text-dark">
                                            <?= htmlspecialchars($project['name']) ?>
                                        </a>
                                    </h5>
                                    <p class="card-text text-muted small text-truncate">
                                        <?= htmlspecialchars($project['description'] ?: 'Bez popisu') ?>
                                    </p>
                                </div>
                                <div class="card-footer bg-transparent border-0 text-end pb-3">
                                    <a href="project-detail.php?id=<?= $project['id'] ?>" class="btn btn-sm btn-outline-secondary">Open <i class="bi bi-arrow-right"></i></a>
                                    <a href="project-settings.php?id=<?= $project['id'] ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-gear"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-pane fade" id="shared" role="tabpanel">
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php if (empty($sharedProjects)): ?>
                    <div class="col-12 text-center text-muted py-5">
                        <p>You are not a part of any project for now. Join one!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($sharedProjects as $project): ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm border-0" style="border-top: 5px solid <?= htmlspecialchars($project['color_hex']) ?> !important;">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">
                                        <a href="project-detail.php?id=<?= $project['id'] ?>" class="text-decoration-none text-dark">
                                            <?= htmlspecialchars($project['name']) ?>
                                        </a>
                                    </h5>
                                    <p class="card-text text-muted small text-truncate">
                                        <?= htmlspecialchars($project['description'] ?: 'Bez popisu') ?>
                                    </p>
                                </div>
                                <div class="card-footer bg-transparent border-0 text-end pb-3">
                                    <span class="badge bg-secondary float-start mt-1">Role: <?= htmlspecialchars($project['role']) ?></span>
                                    <a href="project-detail.php?id=<?= $project['id'] ?>" class="btn btn-sm btn-outline-secondary">Open <i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createProjectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create new project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
                </div>
                <div class="modal-body">
                    <form action="index.php" method="POST" id="createProjectForm">
                        <input type="hidden" name="action" value="create_project">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Project name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="My First Project">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="This is my first project in SimpleTeam Planner"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Color (stripe)</label>
                            <input type="color" name="color_hex" class="form-control form-control-color w-100" value="#3b82f6" title="Pick a color">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Invite user</label>
                            <select name="invites[]" class="form-select" multiple size="4">
                                <?php foreach ($allUsers as $u): ?>
                                    <option value="<?= $u['id'] ?>">
                                        <?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">For more users hold Ctrl (CMD on MAC)</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="createProjectForm" class="btn btn-primary">Create project</button>
                </div>
            </div>
        </div>
    </div>

<?php require_once 'inc/footer.php'; ?>