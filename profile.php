<?php
$pageTitle = 'Mein Profil';
require_once 'includes/header.php';
requireLogin();

$user = currentUser();
$db = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Ungültige Anfrage.';
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($firstName) || empty($lastName)) {
            $errors[] = 'Name ist erforderlich.';
        }

        if (empty($errors)) {
            $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$firstName, $lastName, $phone, $user['id']]);
            setFlash('success', 'Profil aktualisiert!');
            header('Location: /profile.php');
            exit;
        }
    }
}

// User bookings
$bookings = $db->prepare("
    SELECT bk.*, b.name as business_name, s.name as service_name
    FROM bookings bk
    JOIN businesses b ON bk.business_id = b.id
    JOIN services s ON bk.service_id = s.id
    WHERE bk.user_id = ?
    ORDER BY bk.booking_date DESC
    LIMIT 10
");
$bookings->execute([$user['id']]);
$bookings = $bookings->fetchAll();

// User favorites
$favorites = $db->prepare("
    SELECT b.*
    FROM favorites f
    JOIN businesses b ON f.business_id = b.id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");
$favorites->execute([$user['id']]);
$favorites = $favorites->fetchAll();
?>

<section class="section">
    <div class="container container-sm">
        <h2 style="margin-bottom: 32px;">Mein Profil</h2>

        <?php if ($errors): ?>
            <div class="flash flash-error">
                <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 32px; margin-bottom: 32px;">
            <h3 style="margin-bottom: 20px;">Persönliche Daten</h3>
            <form method="POST">
                <?= csrfField() ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Vorname</label>
                        <input type="text" name="first_name" class="form-control" value="<?= e($user['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nachname</label>
                        <input type="text" name="last_name" class="form-control" value="<?= e($user['last_name']) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">E-Mail</label>
                    <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                    <span class="form-text">E-Mail kann nicht geändert werden.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Telefon</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="+49 ...">
                </div>
                <button type="submit" class="btn btn-primary">Speichern</button>
            </form>
        </div>

        <!-- Bookings -->
        <div class="card" style="padding: 32px; margin-bottom: 32px;">
            <h3 style="margin-bottom: 20px;">Meine Buchungen</h3>
            <?php if (empty($bookings)): ?>
                <p style="color: var(--gray-500);">Noch keine Buchungen vorhanden.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Salon</th>
                            <th>Service</th>
                            <th>Datum</th>
                            <th>Uhrzeit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $bk): ?>
                        <tr>
                            <td><?= e($bk['business_name']) ?></td>
                            <td><?= e($bk['service_name']) ?></td>
                            <td><?= date('d.m.Y', strtotime($bk['booking_date'])) ?></td>
                            <td><?= date('H:i', strtotime($bk['start_time'])) ?></td>
                            <td>
                                <?php
                                $statusMap = [
                                    'pending' => ['Ausstehend', 'badge-pending'],
                                    'confirmed' => ['Bestätigt', 'badge-success'],
                                    'cancelled' => ['Storniert', 'badge-danger'],
                                    'completed' => ['Abgeschlossen', 'badge-info'],
                                    'no_show' => ['Nicht erschienen', 'badge-warning'],
                                ];
                                $s = $statusMap[$bk['status']] ?? ['Unbekannt', 'badge-pending'];
                                ?>
                                <span class="badge <?= $s[1] ?>"><?= $s[0] ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Favorites -->
        <div class="card" style="padding: 32px;">
            <h3 style="margin-bottom: 20px;">Meine Favoriten</h3>
            <?php if (empty($favorites)): ?>
                <p style="color: var(--gray-500);">Noch keine Favoriten gespeichert.</p>
            <?php else: ?>
                <?php foreach ($favorites as $fav): ?>
                <a href="/business.php?slug=<?= e($fav['slug']) ?>" style="display: flex; align-items: center; gap: 16px; padding: 12px 0; border-bottom: 1px solid var(--gray-100);">
                    <div style="width:48px;height:48px;border-radius:var(--radius-sm);background:var(--beige);display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-store" style="color:var(--primary);"></i>
                    </div>
                    <div>
                        <strong><?= e($fav['name']) ?></strong><br>
                        <span style="font-size:0.85rem;color:var(--gray-500);"><?= e($fav['city']) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
