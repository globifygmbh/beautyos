<?php
$pageTitle = 'Registrieren';
require_once 'config/app.php';

$type = $_GET['type'] ?? 'user';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Ungültige Anfrage.';
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $role = $_POST['account_type'] === 'business' ? 'business' : 'user';

        if (empty($firstName)) $errors[] = 'Vorname ist erforderlich.';
        if (empty($lastName)) $errors[] = 'Nachname ist erforderlich.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Ungültige E-Mail-Adresse.';
        if (strlen($password) < 8) $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein.';
        if ($password !== $passwordConfirm) $errors[] = 'Passwörter stimmen nicht überein.';

        if (empty($errors)) {
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Diese E-Mail-Adresse wird bereits verwendet.';
            }
        }

        if (empty($errors)) {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $firstName,
                $lastName,
                $role,
            ]);

            $userId = $db->lastInsertId();
            $_SESSION['user_id'] = $userId;

            if ($role === 'business') {
                header('Location: /dashboard/setup.php');
            } else {
                setFlash('success', 'Willkommen bei BeautyOS!');
                header('Location: /');
            }
            exit;
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
        <h1><?= $type === 'business' ? 'Business-Konto' : 'Konto erstellen' ?></h1>
        <p class="auth-subtitle"><?= $type === 'business' ? 'Registriere dein Unternehmen' : 'Erstelle dein kostenloses Konto' ?></p>

        <?php if ($errors): ?>
            <div class="flash flash-error">
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="account_type" value="<?= e($type) ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label class="form-label">Vorname</label>
                    <input type="text" name="first_name" class="form-control" placeholder="Anna" value="<?= e($_POST['first_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nachname</label>
                    <input type="text" name="last_name" class="form-control" placeholder="Müller" value="<?= e($_POST['last_name'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">E-Mail</label>
                <input type="email" name="email" class="form-control" placeholder="anna@beispiel.de" value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Passwort</label>
                <input type="password" name="password" class="form-control" placeholder="Mindestens 8 Zeichen" required>
            </div>

            <div class="form-group">
                <label class="form-label">Passwort bestätigen</label>
                <input type="password" name="password_confirm" class="form-control" placeholder="Passwort wiederholen" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">Registrieren</button>
        </form>

        <div class="auth-footer">
            Bereits ein Konto? <a href="/login.php">Anmelden</a>
        </div>

        <?php if ($type !== 'business'): ?>
        <div class="auth-divider">oder</div>
        <a href="/register.php?type=business" class="btn btn-outline w-100">
            <i class="fas fa-store"></i> Als Business registrieren
        </a>
        <?php else: ?>
        <div class="auth-divider">oder</div>
        <a href="/register.php" class="btn btn-outline w-100">
            <i class="fas fa-user"></i> Als Kunde registrieren
        </a>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
