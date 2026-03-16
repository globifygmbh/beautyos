<?php
$pageTitle = 'Service bearbeiten';
require_once __DIR__ . '/../includes/header.php';
requireBusiness();

$db = getDB();
$user = currentUser();

$stmt = $db->prepare("SELECT * FROM businesses WHERE user_id = ? LIMIT 1");
$stmt->execute([$user['id']]);
$biz = $stmt->fetch();

if (!$biz) { header('Location: /dashboard/setup.php'); exit; }

$serviceId = (int)($_GET['id'] ?? 0);
$service = null;
$isNew = true;

if ($serviceId) {
    $stmt = $db->prepare("SELECT * FROM services WHERE id = ? AND business_id = ?");
    $stmt->execute([$serviceId, $biz['id']]);
    $service = $stmt->fetch();
    if ($service) $isNew = false;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Ungültige Anfrage.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = (int)($_POST['duration_minutes'] ?? 60);
        $price = (float)str_replace(',', '.', $_POST['price'] ?? '0');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name)) $errors[] = 'Name ist erforderlich.';
        if ($price <= 0) $errors[] = 'Preis muss größer als 0 sein.';
        if ($duration < 5) $errors[] = 'Dauer muss mindestens 5 Minuten sein.';

        if (empty($errors)) {
            if ($isNew) {
                $db->prepare("INSERT INTO services (business_id, name, description, duration_minutes, price, is_active) VALUES (?, ?, ?, ?, ?, ?)")
                   ->execute([$biz['id'], $name, $description, $duration, $price, $isActive]);
                setFlash('success', 'Service erstellt!');
            } else {
                $db->prepare("UPDATE services SET name = ?, description = ?, duration_minutes = ?, price = ?, is_active = ? WHERE id = ? AND business_id = ?")
                   ->execute([$name, $description, $duration, $price, $isActive, $serviceId, $biz['id']]);
                setFlash('success', 'Service aktualisiert!');
            }
            header('Location: /dashboard/index.php?tab=services');
            exit;
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && $service) {
    $db->prepare("DELETE FROM services WHERE id = ? AND business_id = ?")->execute([$serviceId, $biz['id']]);
    setFlash('success', 'Service gelöscht.');
    header('Location: /dashboard/index.php?tab=services');
    exit;
}
?>

<div class="dashboard-layout">
    <aside class="dashboard-sidebar">
        <div style="padding: 0 24px 24px;">
            <strong style="font-size:0.9rem;"><?= e($biz['name']) ?></strong>
        </div>
        <ul class="dashboard-nav">
            <li><a href="/dashboard/index.php"><i class="fas fa-chart-line" style="width:20px;"></i> Übersicht</a></li>
            <li><a href="/dashboard/index.php?tab=services" class="active"><i class="fas fa-list" style="width:20px;"></i> Services</a></li>
            <li><a href="/dashboard/settings.php"><i class="fas fa-cog" style="width:20px;"></i> Einstellungen</a></li>
        </ul>
    </aside>

    <div class="dashboard-content">
        <h2 style="margin-bottom: 24px;"><?= $isNew ? 'Neuer Service' : 'Service bearbeiten' ?></h2>

        <?php if ($errors): ?>
            <div class="flash flash-error"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <div class="card" style="padding: 32px; max-width: 600px;">
            <form method="POST">
                <?= csrfField() ?>
                <div class="form-group">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="form-control" value="<?= e($service['name'] ?? $_POST['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Beschreibung</label>
                    <textarea name="description" class="form-control" rows="3"><?= e($service['description'] ?? $_POST['description'] ?? '') ?></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Dauer (Minuten) *</label>
                        <input type="number" name="duration_minutes" class="form-control" value="<?= e($service['duration_minutes'] ?? $_POST['duration_minutes'] ?? '60') ?>" min="5" step="5" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preis (&euro;) *</label>
                        <input type="text" name="price" class="form-control" value="<?= e(isset($service) ? number_format($service['price'], 2, ',', '') : ($_POST['price'] ?? '')) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="filter-option">
                        <input type="checkbox" name="is_active" <?= ($service['is_active'] ?? 1) ? 'checked' : '' ?>> Aktiv
                    </label>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary"><?= $isNew ? 'Erstellen' : 'Speichern' ?></button>
                    <a href="/dashboard/index.php?tab=services" class="btn btn-ghost">Abbrechen</a>
                    <?php if (!$isNew): ?>
                        <a href="?id=<?= $serviceId ?>&delete=1" class="btn btn-ghost" style="color: var(--danger);" onclick="return confirm('Service wirklich löschen?')">Löschen</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
