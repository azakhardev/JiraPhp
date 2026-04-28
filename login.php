<?php
require_once 'inc/config.php';

// Pokud uživatel už má v session svoje ID, nemá na stránce login co dělat
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

//echo(password_hash('123456Ab', PASSWORD_DEFAULT));
//die();

// Zpracování klasického přihlášení po odeslání formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    // Sanitizace e-mailu
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        $user = User::findByEmail($email);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // Bezpečnostní praxe: vygenerujeme nové ID pro session
            session_regenerate_id(true);

            // Přesměrování na dashboard
            header('Location: index.php');
            exit();
        } else {
            $error = 'Email or password is incorrect';
        }
    } else {
        $error = 'Please, Enter your email and password.';
    }
}

require_once 'inc/header.php';
?>
    <div class="row justify-content-center mt-5">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4"><i class="bi bi-kanban text-primary"></i> Login</h2>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="login.php">
                        <input type="hidden" name="action" value="login">

                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail address</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Login</button>
                        </div>
                    </form>

                    <div class="text-center my-4">
                        <span class="text-muted">or</span>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="google-auth.php" class="btn btn-outline-dark btn-lg">
                            <i class="bi bi-google text-danger me-2"></i> Login with Google
                        </a>
                    </div>

                    <div class="text-center mt-4">
                        <small class="text-muted">Dont have an accout? <a href="register.php">Register now!</a>.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
require_once 'inc/footer.php';
?>