<?php
$pageTitle = 'Anmelden';
require_once 'config/app.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Ungültige Anfrage.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $errors[] = 'Bitte E-Mail und Passwort eingeben.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                setFlash('success', 'Willkommen zurück, ' . $user['first_name'] . '!');

                if ($user['role'] === 'admin') {
                    header('Location: /admin/index.php');
                } elseif ($user['role'] === 'business') {
                    header('Location: /dashboard/index.php');
                } else {
                    header('Location: /');
                }
                exit;
            } else {
                $errors[] = 'E-Mail oder Passwort ist falsch.';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <a href="/" class="navbar-logo" style="justify-content: center; margin-bottom: 24px;">
            <span class="logo-dot"></span>
            Beauty<span>OS</span>
        </a>
        <h1>Willkommen zurück</h1>
        <p class="auth-subtitle">Melde dich in deinem Konto an</p>

        <?php if ($errors): ?>
            <div class="flash flash-error">
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label">E-Mail</label>
                <input type="email" name="email" class="form-control" placeholder="anna@beispiel.de" value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Passwort</label>
                <input type="password" name="password" class="form-control" placeholder="Dein Passwort" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">Anmelden</button>
        </form>

        <div class="auth-footer">
            Noch kein Konto? <a href="/register.php">Jetzt registrieren</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
