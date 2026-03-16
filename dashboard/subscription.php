<?php
$pageTitle = 'Abo-Verwaltung';
require_once __DIR__ . '/../includes/header.php';
requireBusiness();

$db = getDB();
$user = currentUser();

$stmt = $db->prepare("SELECT b.*, sp.name as plan_name FROM businesses b LEFT JOIN subscription_plans sp ON b.subscription_plan_id = sp.id WHERE b.user_id = ? LIMIT 1");
$stmt->execute([$user['id']]);
$biz = $stmt->fetch();

if (!$biz) { header('Location: /dashboard/setup.php'); exit; }

$plans = $db->query("SELECT * FROM subscription_plans ORDER BY sort_order")->fetchAll();

// Handle plan change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_plan'])) {
    if (verifyCsrf()) {
        $planId = (int)$_POST['plan_id'];
        $plan = $db->prepare("SELECT * FROM subscription_plans WHERE id = ?");
        $plan->execute([$planId]);
        $plan = $plan->fetch();

        if ($plan) {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            $db->prepare("UPDATE businesses SET subscription_plan_id = ?, subscription_expires_at = ?, status = 'active' WHERE id = ?")
               ->execute([$planId, $expiresAt, $biz['id']]);
            setFlash('success', 'Plan "' . $plan['name'] . '" aktiviert!');
            header('Location: /dashboard/subscription.php');
            exit;
        }
    }
}
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
            <li><a href="/dashboard/settings.php"><i class="fas fa-cog" style="width:20px;"></i> Einstellungen</a></li>
            <li><a href="/dashboard/subscription.php" class="active"><i class="fas fa-crown" style="width:20px;"></i> Abo-Verwaltung</a></li>
        </ul>
    </aside>

    <div class="dashboard-content">
        <h2 style="margin-bottom: 8px;">Abo-Verwaltung</h2>
        <p style="color: var(--gray-500); margin-bottom: 32px;">
            Aktueller Plan: <strong style="color: var(--gray-900);"><?= e($biz['plan_name'] ?? 'Kein Plan') ?></strong>
            <?php if ($biz['subscription_expires_at']): ?>
                &middot; Gültig bis <?= date('d.m.Y', strtotime($biz['subscription_expires_at'])) ?>
            <?php endif; ?>
        </p>

        <div class="pricing-grid">
            <?php foreach ($plans as $i => $plan):
                $features = json_decode($plan['features'], true) ?: [];
                $isPopular = $i === 1;
                $isCurrent = $biz['subscription_plan_id'] == $plan['id'];
            ?>
            <div class="pricing-card <?= $isPopular ? 'popular' : '' ?>" <?= $isCurrent ? 'style="border-color: var(--success);"' : '' ?>>
                <?php if ($isCurrent): ?>
                    <div style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--success);color:white;padding:4px 20px;border-radius:var(--radius-full);font-size:0.8rem;font-weight:600;">Aktuell</div>
                <?php endif; ?>
                <h3><?= e($plan['name']) ?></h3>
                <div class="pricing-amount">
                    <?= number_format($plan['price_monthly'], 2, ',', '.') ?>&euro;
                    <span>/Monat</span>
                </div>
                <ul class="pricing-features">
                    <?php foreach ($features as $f): ?>
                    <li><i class="fas fa-check"></i> <?= e($f) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!$isCurrent): ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                    <button type="submit" name="select_plan" class="btn <?= $isPopular ? 'btn-primary' : 'btn-outline' ?> w-100">Plan wählen</button>
                </form>
                <?php else: ?>
                <button class="btn btn-secondary w-100" disabled>Aktueller Plan</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
