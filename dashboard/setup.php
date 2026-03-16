<?php
$pageTitle = 'Business einrichten';
require_once __DIR__ . '/../includes/header.php';
requireBusiness();

$db = getDB();
$user = currentUser();
$errors = [];

// Check if already has business
$existing = $db->prepare("SELECT id FROM businesses WHERE user_id = ?");
$existing->execute([$user['id']]);
if ($existing->fetch()) {
    header('Location: /dashboard/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Ungültige Anfrage.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $street = trim($_POST['street'] ?? '');
        $houseNumber = trim($_POST['house_number'] ?? '');
        $zipCode = trim($_POST['zip_code'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $categoryIds = $_POST['categories'] ?? [];

        if (empty($name)) $errors[] = 'Name ist erforderlich.';
        if (empty($city)) $errors[] = 'Stadt ist erforderlich.';

        if (empty($errors)) {
            $slug = slugify($name);
            // Ensure unique slug
            $checkSlug = $db->prepare("SELECT id FROM businesses WHERE slug = ?");
            $checkSlug->execute([$slug]);
            if ($checkSlug->fetch()) {
                $slug .= '-' . uniqid();
            }

            $stmt = $db->prepare("
                INSERT INTO businesses (user_id, name, slug, description, phone, email, street, house_number, zip_code, city)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user['id'], $name, $slug, $description, $phone, $email, $street, $houseNumber, $zipCode, $city]);
            $bizId = $db->lastInsertId();

            // Add categories
            if ($categoryIds) {
                $catStmt = $db->prepare("INSERT INTO business_categories (business_id, category_id) VALUES (?, ?)");
                foreach ($categoryIds as $catId) {
                    $catStmt->execute([$bizId, (int)$catId]);
                }
            }

            // Add default opening hours
            $hourStmt = $db->prepare("INSERT INTO opening_hours (business_id, day_of_week, open_time, close_time, is_closed) VALUES (?, ?, ?, ?, ?)");
            for ($d = 0; $d < 7; $d++) {
                $isClosed = $d === 6; // Sunday closed
                $hourStmt->execute([$bizId, $d, '09:00', '18:00', $isClosed ? 1 : 0]);
            }

            setFlash('success', 'Dein Business wurde erstellt! Wähle jetzt einen Abo-Plan.');
            header('Location: /dashboard/subscription.php');
            exit;
        }
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();
?>

<section class="section">
    <div class="container container-sm">
        <div class="text-center" style="margin-bottom: 40px;">
            <h1>Business einrichten</h1>
            <p style="color: var(--gray-500);">Erstelle dein Unternehmensprofil auf BeautyOS</p>
        </div>

        <?php if ($errors): ?>
            <div class="flash flash-error">
                <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 40px;">
            <form method="POST">
                <?= csrfField() ?>

                <div class="form-group">
                    <label class="form-label">Name deines Business *</label>
                    <input type="text" name="name" class="form-control" placeholder="z.B. Salon Elegance" value="<?= e($_POST['name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Beschreibung</label>
                    <textarea name="description" class="form-control" placeholder="Erzähle deinen Kunden von deinem Salon..."><?= e($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Kategorien</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <?php foreach ($categories as $cat): ?>
                        <label class="filter-option">
                            <input type="checkbox" name="categories[]" value="<?= $cat['id'] ?>">
                            <?= e($cat['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Telefon</label>
                        <input type="text" name="phone" class="form-control" placeholder="+49 ..." value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">E-Mail</label>
                        <input type="email" name="email" class="form-control" placeholder="salon@beispiel.de" value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <h4 style="margin: 24px 0 16px;">Adresse</h4>
                <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Straße</label>
                        <input type="text" name="street" class="form-control" value="<?= e($_POST['street'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hausnr.</label>
                        <input type="text" name="house_number" class="form-control" value="<?= e($_POST['house_number'] ?? '') ?>">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">PLZ</label>
                        <input type="text" name="zip_code" class="form-control" value="<?= e($_POST['zip_code'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stadt *</label>
                        <input type="text" name="city" class="form-control" value="<?= e($_POST['city'] ?? '') ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 mt-3">
                    <i class="fas fa-rocket"></i> Business erstellen
                </button>
            </form>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
