<?php
$pageTitle = 'Admin – Pakete & Preise';
$adminPage = 'plans';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_plan') {
        $id       = (int)($_POST['plan_id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $slug     = trim($_POST['slug'] ?? '');
        $price    = (float)($_POST['price_monthly'] ?? 0);
        $maxImg   = (int)($_POST['max_images'] ?? 5);
        $custDesign  = isset($_POST['custom_design']) ? 1 : 0;
        $priority    = isset($_POST['priority_listing']) ? 1 : 0;
        $featured    = isset($_POST['featured_badge']) ? 1 : 0;
        $booking     = isset($_POST['booking_system']) ? 1 : 0;
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        $featuresRaw = trim($_POST['features_text'] ?? '');
        $features    = json_encode(array_filter(array_map('trim', explode("\n", $featuresRaw))));
        $stripeId    = trim($_POST['stripe_price_id'] ?? '');

        if ($id) {
            $db->prepare("UPDATE subscription_plans SET name=?, slug=?, price_monthly=?, max_images=?, custom_design=?, priority_listing=?, featured_badge=?, booking_system=?, sort_order=?, features=?, stripe_price_id=? WHERE id=?")
               ->execute([$name, $slug, $price, $maxImg, $custDesign, $priority, $featured, $booking, $sortOrder, $features, $stripeId, $id]);
            setFlash('success', 'Paket aktualisiert!');
        } else {
            $db->prepare("INSERT INTO subscription_plans (name,slug,price_monthly,max_images,custom_design,priority_listing,featured_badge,booking_system,sort_order,features,stripe_price_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$name, $slug, $price, $maxImg, $custDesign, $priority, $featured, $booking, $sortOrder, $features, $stripeId]);
            setFlash('success', 'Paket erstellt!');
        }
    } elseif ($action === 'delete_plan') {
        $id = (int)($_POST['plan_id'] ?? 0);
        $db->prepare("DELETE FROM subscription_plans WHERE id=?")->execute([$id]);
        setFlash('success', 'Paket gelöscht.');
    }

    header('Location: /admin/plans.php'); exit;
}

// Check if stripe_price_id column exists, add if not
try {
    $db->query("SELECT stripe_price_id FROM subscription_plans LIMIT 1");
} catch (Exception $e) {
    $db->query("ALTER TABLE subscription_plans ADD COLUMN stripe_price_id VARCHAR(100) DEFAULT NULL AFTER sort_order");
}

$plans = $db->query("SELECT *, (SELECT COUNT(*) FROM businesses WHERE subscription_plan_id = subscription_plans.id AND status='active') as active_subscribers FROM subscription_plans ORDER BY sort_order")->fetchAll();

$editPlan = null;
if (isset($_GET['edit'])) {
    foreach ($plans as $p) { if ($p['id'] == $_GET['edit']) { $editPlan = $p; break; } }
}

require_once __DIR__ . '/../includes/admin-nav.php';
?>

<div class="admin-page-header">
    <div>
        <h1>Pakete & Preise</h1>
        <p style="color:var(--gray-500);margin:4px 0 0;font-size:0.9rem;">Verwalte Abo-Pakete und Stripe-Preise</p>
    </div>
    <a href="?new=1" class="btn btn-primary"><i class="fas fa-plus"></i> Neues Paket</a>
</div>

<!-- Plan Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:32px;">
<?php foreach ($plans as $plan):
    $features = json_decode($plan['features'], true) ?: [];
    $isEditing = $editPlan && $editPlan['id'] == $plan['id'];
?>
<div class="admin-card" style="<?= $isEditing ? 'border-color:var(--primary-dark);box-shadow:0 0 0 3px var(--primary-light);' : '' ?>">
    <div class="admin-card-header">
        <div>
            <h3><?= e($plan['name']) ?></h3>
            <div style="font-size:0.8rem;color:var(--gray-400);">slug: <?= e($plan['slug']) ?></div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:1.5rem;font-weight:700;color:var(--primary-dark);">€<?= number_format($plan['price_monthly'],2,',','.') ?></div>
            <div style="font-size:0.75rem;color:var(--gray-400);">/Monat</div>
        </div>
    </div>
    <div class="admin-card-body">
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
            <?php if ($plan['booking_system']): ?><span style="background:#d1fae5;color:#065f46;font-size:0.7rem;padding:2px 8px;border-radius:10px;font-weight:600;">Buchungen</span><?php endif; ?>
            <?php if ($plan['custom_design']): ?><span style="background:#ede9fe;color:#5b21b6;font-size:0.7rem;padding:2px 8px;border-radius:10px;font-weight:600;">Design</span><?php endif; ?>
            <?php if ($plan['priority_listing']): ?><span style="background:#fef3c7;color:#92400e;font-size:0.7rem;padding:2px 8px;border-radius:10px;font-weight:600;">Priority</span><?php endif; ?>
            <?php if ($plan['featured_badge']): ?><span style="background:#fee2e2;color:#991b1b;font-size:0.7rem;padding:2px 8px;border-radius:10px;font-weight:600;">Featured</span><?php endif; ?>
        </div>
        <div style="font-size:0.82rem;color:var(--gray-500);margin-bottom:12px;">
            <i class="fas fa-images"></i> Max. <?= $plan['max_images'] ?> Bilder
            &nbsp;·&nbsp; <i class="fas fa-users"></i> <?= $plan['active_subscribers'] ?> aktiv
        </div>
        <?php if ($plan['stripe_price_id'] ?? ''): ?>
        <div style="font-size:0.75rem;color:var(--gray-400);font-family:monospace;background:var(--gray-50);padding:4px 8px;border-radius:4px;margin-bottom:12px;">
            Stripe: <?= e($plan['stripe_price_id']) ?>
        </div>
        <?php endif; ?>
        <div style="display:flex;gap:8px;">
            <a href="?edit=<?= $plan['id'] ?>" class="btn btn-sm btn-ghost" style="flex:1;text-align:center;"><i class="fas fa-edit"></i> Bearbeiten</a>
            <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                <button name="action" value="delete_plan" class="btn btn-sm btn-ghost" style="color:var(--danger);" onclick="return confirm('Paket wirklich löschen? Bestehende Abos bleiben erhalten.')"><i class="fas fa-trash"></i></button>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Edit/Create Form -->
<?php if ($editPlan || isset($_GET['new'])): ?>
<div class="admin-card" id="plan-form">
    <div class="admin-card-header">
        <h3><?= $editPlan ? 'Paket bearbeiten: ' . e($editPlan['name']) : 'Neues Paket erstellen' ?></h3>
        <a href="/admin/plans.php" class="btn btn-sm btn-ghost"><i class="fas fa-times"></i></a>
    </div>
    <div class="admin-card-body">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_plan">
            <input type="hidden" name="plan_id" value="<?= $editPlan['id'] ?? 0 ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
                <div>
                    <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">Paketname *</label>
                    <input type="text" name="name" value="<?= e($editPlan['name'] ?? '') ?>" required style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.9rem;">
                </div>
                <div>
                    <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">Slug *</label>
                    <input type="text" name="slug" value="<?= e($editPlan['slug'] ?? '') ?>" required style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.9rem;">
                </div>
                <div>
                    <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">Preis/Monat (€) *</label>
                    <input type="number" name="price_monthly" value="<?= $editPlan['price_monthly'] ?? '' ?>" step="0.01" min="0" required style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.9rem;">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <div>
                    <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">Max. Bilder</label>
                    <input type="number" name="max_images" value="<?= $editPlan['max_images'] ?? 5 ?>" min="1" style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.9rem;">
                </div>
                <div>
                    <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">Sortierung</label>
                    <input type="number" name="sort_order" value="<?= $editPlan['sort_order'] ?? 0 ?>" style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.9rem;">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:16px;">
                <?php $toggles = ['booking_system'=>'Buchungssystem','custom_design'=>'Design-Anpassung','priority_listing'=>'Priority-Listing','featured_badge'=>'Featured Badge']; ?>
                <?php foreach ($toggles as $field => $label): ?>
                <label style="display:flex;align-items:center;gap:8px;font-size:0.875rem;cursor:pointer;background:var(--gray-50);padding:10px 14px;border-radius:var(--radius-md);">
                    <input type="checkbox" name="<?= $field ?>" <?= ($editPlan[$field] ?? 0) ? 'checked' : '' ?> style="width:16px;height:16px;">
                    <?= $label ?>
                </label>
                <?php endforeach; ?>
            </div>

            <div style="margin-bottom:16px;">
                <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">
                    Features (eine pro Zeile)
                </label>
                <textarea name="features_text" rows="5" style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.875rem;resize:vertical;"><?php
                    $feats = json_decode($editPlan['features'] ?? '[]', true) ?: [];
                    echo e(implode("\n", $feats));
                ?></textarea>
            </div>

            <div style="margin-bottom:24px;">
                <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">
                    <i class="fab fa-stripe"></i> Stripe Price ID
                    <span style="font-size:0.75rem;font-weight:400;color:var(--gray-400);">(z.B. price_1abc...)</span>
                </label>
                <input type="text" name="stripe_price_id" value="<?= e($editPlan['stripe_price_id'] ?? '') ?>" placeholder="price_..." style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.9rem;font-family:monospace;">
            </div>

            <div style="display:flex;gap:12px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Speichern</button>
                <a href="/admin/plans.php" class="btn btn-ghost">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

</div></div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
