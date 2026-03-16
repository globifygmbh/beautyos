<?php
$pageTitle = 'Einstellungen';
require_once __DIR__ . '/../includes/header.php';
requireBusiness();

$db = getDB();
$user = currentUser();

$stmt = $db->prepare("SELECT * FROM businesses WHERE user_id = ? LIMIT 1");
$stmt->execute([$user['id']]);
$biz = $stmt->fetch();

if (!$biz) { header('Location: /dashboard/setup.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Ungültige Anfrage.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $shortDesc = trim($_POST['short_description'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $street = trim($_POST['street'] ?? '');
        $houseNumber = trim($_POST['house_number'] ?? '');
        $zipCode = trim($_POST['zip_code'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $accentColor = trim($_POST['accent_color'] ?? '#e8a0bf');

        if (empty($name)) $errors[] = 'Name ist erforderlich.';

        if (empty($errors)) {
            $stmt = $db->prepare("
                UPDATE businesses SET
                    name = ?, description = ?, short_description = ?, phone = ?, email = ?,
                    website = ?, street = ?, house_number = ?, zip_code = ?, city = ?,
                    accent_color = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $shortDesc, $phone, $email, $website, $street, $houseNumber, $zipCode, $city, $accentColor, $biz['id']]);

            // Update opening hours
            for ($d = 0; $d < 7; $d++) {
                $open = $_POST["hours_open_$d"] ?? '09:00';
                $close = $_POST["hours_close_$d"] ?? '18:00';
                $closed = isset($_POST["hours_closed_$d"]) ? 1 : 0;

                $db->prepare("
                    INSERT INTO opening_hours (business_id, day_of_week, open_time, close_time, is_closed)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE open_time = VALUES(open_time), close_time = VALUES(close_time), is_closed = VALUES(is_closed)
                ")->execute([$biz['id'], $d, $open, $close, $closed]);
            }

            // Update categories
            $db->prepare("DELETE FROM business_categories WHERE business_id = ?")->execute([$biz['id']]);
            $categoryIds = $_POST['categories'] ?? [];
            $catStmt = $db->prepare("INSERT INTO business_categories (business_id, category_id) VALUES (?, ?)");
            foreach ($categoryIds as $catId) {
                $catStmt->execute([$biz['id'], (int)$catId]);
            }

            setFlash('success', 'Einstellungen gespeichert!');
            header('Location: /dashboard/settings.php');
            exit;
        }
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();
$bizCats = $db->prepare("SELECT category_id FROM business_categories WHERE business_id = ?");
$bizCats->execute([$biz['id']]);
$selectedCats = array_column($bizCats->fetchAll(), 'category_id');

$hours = $db->prepare("SELECT * FROM opening_hours WHERE business_id = ? ORDER BY day_of_week");
$hours->execute([$biz['id']]);
$hoursByDay = [];
foreach ($hours->fetchAll() as $h) {
    $hoursByDay[$h['day_of_week']] = $h;
}

$dayNames = ['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];
?>

<div class="dashboard-layout">
    <aside class="dashboard-sidebar">
        <div style="padding: 0 24px 24px;">
            <strong style="font-size:0.9rem;"><?= e($biz['name']) ?></strong>
        </div>
        <ul class="dashboard-nav">
            <li><a href="/dashboard/index.php"><i class="fas fa-chart-line" style="width:20px;"></i> Übersicht</a></li>
            <li><a href="/dashboard/index.php?tab=bookings"><i class="fas fa-calendar" style="width:20px;"></i> Buchungen</a></li>
            <li><a href="/dashboard/index.php?tab=services"><i class="fas fa-list" style="width:20px;"></i> Services</a></li>
            <li><a href="/dashboard/index.php?tab=reviews"><i class="fas fa-star" style="width:20px;"></i> Bewertungen</a></li>
            <li><a href="/dashboard/settings.php" class="active"><i class="fas fa-cog" style="width:20px;"></i> Einstellungen</a></li>
            <li><a href="/dashboard/subscription.php"><i class="fas fa-crown" style="width:20px;"></i> Abo-Verwaltung</a></li>
        </ul>
    </aside>

    <div class="dashboard-content">
        <h2 style="margin-bottom: 24px;">Einstellungen</h2>

        <?php if ($errors): ?>
            <div class="flash flash-error"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>

            <div class="card" style="padding: 32px; margin-bottom: 24px;">
                <h3 style="margin-bottom: 20px;">Grunddaten</h3>
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= e($biz['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kurzbeschreibung</label>
                    <input type="text" name="short_description" class="form-control" value="<?= e($biz['short_description'] ?? '') ?>" maxlength="500">
                </div>
                <div class="form-group">
                    <label class="form-label">Beschreibung</label>
                    <textarea name="description" class="form-control"><?= e($biz['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategorien</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <?php foreach ($categories as $cat): ?>
                        <label class="filter-option">
                            <input type="checkbox" name="categories[]" value="<?= $cat['id'] ?>" <?= in_array($cat['id'], $selectedCats) ? 'checked' : '' ?>>
                            <?= e($cat['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card" style="padding: 32px; margin-bottom: 24px;">
                <h3 style="margin-bottom: 20px;">Kontakt & Adresse</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Telefon</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($biz['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">E-Mail</label>
                        <input type="email" name="email" class="form-control" value="<?= e($biz['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Website</label>
                    <input type="url" name="website" class="form-control" value="<?= e($biz['website'] ?? '') ?>">
                </div>
                <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Straße</label>
                        <input type="text" name="street" class="form-control" value="<?= e($biz['street'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hausnr.</label>
                        <input type="text" name="house_number" class="form-control" value="<?= e($biz['house_number'] ?? '') ?>">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">PLZ</label>
                        <input type="text" name="zip_code" class="form-control" value="<?= e($biz['zip_code'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stadt</label>
                        <input type="text" name="city" class="form-control" value="<?= e($biz['city'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="card" style="padding: 32px; margin-bottom: 24px;">
                <h3 style="margin-bottom: 20px;">Öffnungszeiten</h3>
                <?php for ($d = 0; $d < 7; $d++):
                    $h = $hoursByDay[$d] ?? null;
                ?>
                <div style="display: flex; align-items: center; gap: 16px; padding: 8px 0; border-bottom: 1px solid var(--gray-100);">
                    <span style="width: 120px; font-weight: 500; font-size: 0.9rem;"><?= $dayNames[$d] ?></span>
                    <input type="time" name="hours_open_<?= $d ?>" class="form-control" style="width: auto;" value="<?= $h ? substr($h['open_time'], 0, 5) : '09:00' ?>">
                    <span>-</span>
                    <input type="time" name="hours_close_<?= $d ?>" class="form-control" style="width: auto;" value="<?= $h ? substr($h['close_time'], 0, 5) : '18:00' ?>">
                    <label class="filter-option" style="margin-left: 16px;">
                        <input type="checkbox" name="hours_closed_<?= $d ?>" <?= ($h && $h['is_closed']) ? 'checked' : '' ?>> Geschlossen
                    </label>
                </div>
                <?php endfor; ?>
            </div>

            <div class="card" style="padding: 32px; margin-bottom: 24px;">
                <h3 style="margin-bottom: 20px;">Design</h3>
                <div class="form-group">
                    <label class="form-label">Akzentfarbe</label>
                    <input type="color" name="accent_color" value="<?= e($biz['accent_color'] ?? '#e8a0bf') ?>" style="width:60px;height:40px;border:none;cursor:pointer;">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Einstellungen speichern</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
