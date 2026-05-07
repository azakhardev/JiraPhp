<?php
require_once 'inc/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if ($email) {
        $user = User::findByEmail($email);

        // Only process if user exists AND is a local account (OAuth users can't reset passwords here)
        if ($user && ($user['oauth_provider'] === 'local' || $user['oauth_provider'] === null)) {

            // Generate a secure random token
            $token = bin2hex(random_bytes(32));

            $tokenHash = hash('sha256', $token);

            // Set expiration (1 hour from now)
            $expiry = date('Y-m-d H:i:s', time() + 3600);

            User::setResetToken($user['id'], $tokenHash, $expiry);

            // Send the email using PHP's built-in mail() function
            $resetLink = "https://eso.vse.cz/~zaca06/task-planner/reset-password.php?token=" . $token;

            $subject = "Task Planner - Password Reset";
            $message = "Hello,\n\nYou requested a password reset. Click the link below to set a new password:\n\n";
            $message .= $resetLink . "\n\n";
            $message .= "This link is valid for 1 hour. If you didn't request this, you can safely ignore this email.";

            $headers = [
                'From' => 'noreply@eso.vse.cz',
                'Content-Type' => 'text/plain; charset=UTF-8'
            ];

            mail($email, $subject, $message, $headers);
        }
    }

    $_SESSION['flash_success'] = "If an account with that email exists, we have sent a password reset link to it.";
    header('Location: forgot-password.php');
    exit();
}

$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

require_once 'inc/header.php';
?>
    <div class="row justify-content-center mt-5">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4"><i class="bi bi-key text-warning"></i> Reset Password</h2>

                    <p class="text-muted text-center mb-4">Enter your email address and we will send you a link to reset your password.</p>

                    <?php if ($success): ?>
                        <div class="alert alert-success text-center">
                            <?= htmlspecialchars($success) ?> <br><br>
                            <a href="login.php" class="btn btn-sm btn-outline-success">Return to Login</a>
                        </div>
                    <?php else: ?>
                        <form method="post">
                            <div class="mb-4">
                                <label for="email" class="form-label">E-mail address</label>
                                <input type="email" class="form-control" id="email" name="email" required autofocus>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning btn-lg">Send Reset Link</button>
                            </div>
                        </form>
                        <div class="text-center mt-4">
                            <a href="login.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i> Back to login</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php require_once 'inc/footer.php'; ?>