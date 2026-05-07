<?php
require_once 'inc/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: login.php');
    exit();
}

// Hash the token from the URL to compare with the database
$tokenHash = hash('sha256', $token);
$user = User::findByResetToken($tokenHash);

if (!$user) {
    die("<div class='container mt-5'><div class='alert alert-danger'>This password reset link is invalid or has expired. Please <a href='forgot-password.php'>request a new one</a>.</div></div>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) >= 6) {
        if ($newPassword === $confirmPassword) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            User::updatePassword($user['id'], $newHash);

            // Clear the token so it cannot be used again
            User::clearResetToken($user['id']);

            $_SESSION['flash_success'] = "Your password has been successfully reset. You can now login.";
            header('Location: login.php');
            exit();
        } else {
            $error = "Passwords do not match.";
        }
    } else {
        $error = "Password must be at least 6 characters long.";
    }
}

require_once 'inc/header.php';
?>
    <div class="row justify-content-center mt-5">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4">Set New Password</h2>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required autofocus>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php require_once 'inc/footer.php'; ?>