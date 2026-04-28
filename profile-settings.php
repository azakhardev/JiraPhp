<?php
require_once 'inc/config.php';
require_once 'inc/auth_check.php';

$userId = $_SESSION['user_id'];
$user = User::getById($userId);

// Check if the user is a local account or from OAuth
$isLocalAccount = ($user['oauth_provider'] === 'local' || $user['oauth_provider'] === null);

// --- ACTION PROCESSING (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_profile') {
            $newUsername = trim(htmlspecialchars($_POST['username'] ?? ''));

            if (!empty($newUsername)) {
                User::updateUsername($userId, $newUsername);
                // We must also update the name in the current session so it reflects immediately in the top menu
                $_SESSION['username'] = $newUsername;
                $_SESSION['flash_success'] = "Username successfully updated.";
            } else {
                $_SESSION['flash_error'] = "Username cannot be empty.";
            }
        }
        elseif ($action === 'update_password' && $isLocalAccount) {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Check current password
            if (password_verify($currentPassword, $user['password'])) {
                // Check new password length
                if (strlen($newPassword) >= 6) {
                    if ($newPassword === $confirmPassword) {
                        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        User::updatePassword($userId, $newHash);
                        $_SESSION['flash_success'] = "Password successfully changed.";
                    } else {
                        $_SESSION['flash_error'] = "New passwords do not match.";
                    }
                } else {
                    $_SESSION['flash_error'] = "New password must be at least 6 characters long.";
                }
            } else {
                $_SESSION['flash_error'] = "Current password is incorrect.";
            }
        }
        elseif ($action === 'delete_account') {
            User::delete($userId);
            // Destroy session and redirect to login
            $_SESSION = [];
            session_destroy();
            header('Location: login.php?msg=account_deleted');
            exit();
        }

        // PRG Redirect
        header("Location: profile-settings.php");
        exit();

    } catch (\Throwable $e) {
        $_SESSION['flash_error'] = "System error: " . $e->getMessage();
        header("Location: profile-settings.php");
        exit();
    }
}

// --- EXTRACT FLASH MESSAGES FROM SESSION ---
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require_once 'inc/header.php';
?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2 class="mb-4"><i class="bi bi-person-circle"></i> Profile Settings</h2>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show"><?= $success ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><?= $error ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white fw-bold">Basic Information</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="mb-3">
                                <label class="form-label text-muted">Email address (cannot be changed)</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                <?php if (!$isLocalAccount): ?>
                                    <div class="form-text text-info"><i class="bi bi-google"></i> Logged in via Google OAuth.</div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                            </div>

                            <button type="submit" class="btn btn-primary">Save name</button>
                        </form>
                    </div>
                </div>

                <?php if ($isLocalAccount): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold">Change Password</div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_password">

                                <div class="mb-3">
                                    <label class="form-label">Current password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">New password</label>
                                        <input type="password" name="new_password" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Confirm new password</label>
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-warning">Change password</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card shadow-sm mb-4 border-info">
                        <div class="card-body text-info">
                            <i class="bi bi-info-circle-fill"></i> Password change is unavailable because you are using an external provider (Google) to log in.
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card border-danger shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="text-danger mb-0">Delete Account</h5>
                            <small class="text-muted">This action is irreversible. You will lose access to all your projects.</small>
                        </div>
                        <form method="POST" onsubmit="return confirm('ARE YOU SURE YOU WANT TO DELETE YOUR ACCOUNT? This action is irreversible and you will be logged out.')">
                            <input type="hidden" name="action" value="delete_account">
                            <button type="submit" class="btn btn-outline-danger">Delete my account</button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

<?php require_once 'inc/footer.php'; ?>