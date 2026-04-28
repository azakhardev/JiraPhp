<?php
require_once 'inc/config.php';

// Pokud je uživatel už přihlášený, přesměrujeme ho rovnou do aplikace
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

// Zpracování registrace po odeslání formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {

    // Sanitizace a získání dat z formuláře
    $username = trim(htmlspecialchars($_POST['username'] ?? ''));
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    // 1. Validace - jsou všechna pole vyplněna?
    if (!empty($username) && !empty($email) && !empty($password)) {

        // 2. Validace - je e-mail ve správném formátu?
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        }
        // 3. Validace - je heslo dostatečně dlouhé?
        elseif (strlen($password) < 6) {
            $error = 'Password must have at least 6 characters.';
        }
        else {
            // 4. Validace - neexistuje už náhodou uživatel s tímto e-mailem?
            $existingUser = User::findByEmail($email);

            if ($existingUser) {
                $error = 'Account with this email address already exists.';
            } else {
                // VŠECHNO JE V POŘÁDKU -> Jdeme ukládat do DB

                // Vygenerujeme bezpečný hash hesla
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Uložíme uživatele pomocí naší nové metody
                $newUserId = User::createLocal($username, $email, $passwordHash);

                if ($newUserId) {
                    // Po úspěšné registraci uživatele rovnou přihlásíme
                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['username'] = $username;

                    // Bezpečnostní reset session ID (ochrana proti Session Fixation)
                    session_regenerate_id(true);

                    // Přesměrování na dashboard
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'An error occurred while registering your account.';
                }
            }
        }
    } else {
        $error = 'Fill in all the required fields.';
    }
}

// Načtení hlavičky
require_once 'inc/header.php';
?>

    <div class="row justify-content-center mt-5">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4"><i class="bi bi-person-plus text-primary"></i>Registration</h2>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="register.php">
                        <input type="hidden" name="action" value="register">

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Password <small class="text-muted">(min. 6 characters)</small></label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Register</button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <small class="text-muted">Already have an account? <a href="login.php">Login</a>.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
require_once 'inc/footer.php';
?>