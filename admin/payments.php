<?php
$pageTitle = 'Admin – Stripe & Zahlungen';
$adminPage = 'payments';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// Save Stripe config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['save_stripe'])) {
    $pubKey    = trim($_POST['stripe_publishable_key'] ?? '');
    $secretKey = trim($_POST['stripe_secret_key'] ?? '');
    $webhook   = trim($_POST['stripe_webhook_secret'] ?? '');

    $stripeConf = "<?php\n// Stripe Configuration - NEVER commit this file!\ndefine('STRIPE_PUBLISHABLE_KEY', " . var_export($pubKey, true) . ");\ndefine('STRIPE_SECRET_KEY', " . var_export($secretKey, true) . ");\ndefine('STRIPE_WEBHOOK_SECRET', " . var_export($webhook, true) . ");\n";
    file_put_contents(dirname(__DIR__) . '/config/stripe.php', $stripeConf);
    setFlash('success', 'Stripe-Konfiguration gespeichert!');
    header('Location: /admin/payments.php'); exit;
}

// Load current config
$stripeConf = [];
$stripeFile = dirname(__DIR__) . '/config/stripe.php';
if (file_exists($stripeFile)) {
    include $stripeFile;
    $stripeConf = [
        'pub'     => defined('STRIPE_PUBLISHABLE_KEY') ? STRIPE_PUBLISHABLE_KEY : '',
        'secret'  => defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '',
        'webhook' => defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '',
    ];
}

// Stats
$totalPaid    = $db->query("SELECT COALESCE(SUM(amount),0) FROM stripe_payments WHERE status='paid'")->fetchColumn();
$totalPending = $db->query("SELECT COUNT(*) FROM stripe_payments WHERE status='pending'")->fetchColumn();
$mrr = $db->query("SELECT COALESCE(SUM(sp.price_monthly),0) FROM businesses b JOIN subscription_plans sp ON b.subscription_plan_id = sp.id WHERE b.status='active' AND (b.subscription_expires_at IS NULL OR b.subscription_expires_at > NOW())")->fetchColumn();

$recentPayments = $db->query("
    SELECT p.*, u.first_name, u.last_name, u.email, sp.name as plan_name, b.name as biz_name
    FROM stripe_payments p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN subscription_plans sp ON p.plan_id = sp.id
    LEFT JOIN businesses b ON p.business_id = b.id
    ORDER BY p.created_at DESC LIMIT 50
")->fetchAll();

// Revenue by month
$revenueByMonth = $db->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as m, SUM(amount) as total
    FROM stripe_payments WHERE status='paid'
    GROUP BY DATE_FORMAT(created_at,'%Y-%m')
    ORDER BY m DESC LIMIT 6
")->fetchAll();

require_once __DIR__ . '/../includes/admin-nav.php';
?>

<div class="admin-page-header">
    <div>
        <h1><i class="fab fa-stripe" style="color:#635bff;margin-right:8px;"></i> Stripe & Zahlungen</h1>
        <p style="color:var(--gray-500);margin:4px 0 0;font-size:0.9rem;">Zahlungsübersicht und Konfiguration</p>
    </div>
</div>

<!-- KPIs -->
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="kpi-card">
        <div class="kpi-label">Gesamt-Umsatz (Stripe)</div>
        <div class="kpi-value">€<?= number_format($totalPaid,0,',','.') ?></div>
        <div class="kpi-change" style="color:var(--gray-400);">Alle bezahlten Transaktionen</div>
        <i class="fas fa-euro-sign kpi-icon"></i>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Monatl. Wiederk. Umsatz</div>
        <div class="kpi-value">€<?= number_format($mrr,0,',','.') ?></div>
        <div class="kpi-change" style="color:var(--gray-400);">MRR aus aktiven Abos</div>
        <i class="fas fa-sync kpi-icon"></i>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Offene Zahlungen</div>
        <div class="kpi-value"><?= $totalPending ?></div>
        <div class="kpi-change" style="color:var(--gray-400);">Status: pending</div>
        <i class="fas fa-clock kpi-icon"></i>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <!-- Stripe Config -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fab fa-stripe" style="color:#635bff;margin-right:6px;"></i> Stripe-Konfiguration</h3>
            <?php if (!empty($stripeConf['pub'])): ?>
            <span style="background:#d1fae5;color:#065f46;font-size:0.75rem;font-weight:600;padding:3px 10px;border-radius:10px;">Verbunden</span>
            <?php else: ?>
            <span style="background:#fee2e2;color:#991b1b;font-size:0.75rem;font-weight:600;padding:3px 10px;border-radius:10px;">Nicht konfiguriert</span>
            <?php endif; ?>
        </div>
        <div class="admin-card-body">
            <form method="POST">
                <?= csrfField() ?>
                <div style="margin-bottom:14px;">
                    <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">Publishable Key (pk_...)</label>
                    <input type="text" name="stripe_publishable_key" value="<?= e($stripeConf['pub'] ?? '') ?>" placeholder="pk_live_..." style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.85rem;font-family:monospace;">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">Secret Key (sk_...) 🔒</label>
                    <input type="password" name="stripe_secret_key" value="<?= e($stripeConf['secret'] ?? '') ?>" placeholder="sk_live_..." style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.85rem;font-family:monospace;">
                </div>
                <div style="margin-bottom:18px;">
                    <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">Webhook Secret (whsec_...) 🔒</label>
                    <input type="password" name="stripe_webhook_secret" value="<?= e($stripeConf['webhook'] ?? '') ?>" placeholder="whsec_..." style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.85rem;font-family:monospace;">
                    <div style="font-size:0.78rem;color:var(--gray-400);margin-top:6px;">
                        Webhook URL: <code style="background:var(--gray-100);padding:2px 6px;border-radius:4px;"><?= rtrim(APP_URL, '/') ?>/api/stripe-webhook.php</code>
                    </div>
                </div>
                <button type="submit" name="save_stripe" class="btn btn-primary w-100"><i class="fas fa-save"></i> Konfiguration speichern</button>
            </form>
            <div style="margin-top:16px;padding:12px;background:#f5f0ff;border-radius:var(--radius-md);font-size:0.8rem;color:#5b21b6;">
                <i class="fas fa-info-circle"></i>
                Keys findest du im <a href="https://dashboard.stripe.com/apikeys" target="_blank" style="color:#5b21b6;font-weight:600;">Stripe Dashboard → API Keys</a>.
                Erstelle einen Webhook auf <a href="https://dashboard.stripe.com/webhooks" target="_blank" style="color:#5b21b6;font-weight:600;">Stripe Webhooks</a> und trage die URL ein.
            </div>
        </div>
    </div>

    <!-- Revenue by Month -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Umsatz nach Monat</h3>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <?php if (empty($revenueByMonth)): ?>
            <p style="padding:40px;text-align:center;color:var(--gray-400);">Noch keine Stripe-Zahlungen</p>
            <?php endif; ?>
            <?php foreach ($revenueByMonth as $row): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-bottom:1px solid var(--gray-100);">
                <span style="font-size:0.9rem;"><?= date('F Y', strtotime($row['m'] . '-01')) ?></span>
                <strong style="color:var(--success);">€<?= number_format($row['total'],2,',','.') ?></strong>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Recent Payments -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Zahlungshistorie</h3>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Datum</th><th>Kunde</th><th>Unternehmen</th><th>Plan</th><th>Betrag</th><th>Status</th><th>Stripe Session</th></tr></thead>
            <tbody>
            <?php foreach ($recentPayments as $pay):
                $statusMap = ['paid'=>['Bezahlt','#10b981'],'pending'=>['Ausstehend','#f59e0b'],'failed'=>['Fehlgeschlagen','#ef4444'],'refunded'=>['Erstattet','#6b7280']];
                $s = $statusMap[$pay['status']] ?? ['?','#6b7280'];
            ?>
            <tr>
                <td style="font-size:0.82rem;"><?= date('d.m.Y H:i', strtotime($pay['created_at'])) ?></td>
                <td>
                    <div style="font-size:0.875rem;"><?= e($pay['first_name']) ?> <?= e($pay['last_name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--gray-400);"><?= e($pay['email']) ?></div>
                </td>
                <td style="font-size:0.875rem;"><?= e($pay['biz_name'] ?? '–') ?></td>
                <td style="font-size:0.875rem;"><?= e($pay['plan_name'] ?? '–') ?></td>
                <td style="font-weight:600;">€<?= number_format($pay['amount'],2,',','.') ?></td>
                <td><span style="background:<?= $s[1] ?>22;color:<?= $s[1] ?>;font-size:0.75rem;font-weight:600;padding:3px 10px;border-radius:10px;"><?= $s[0] ?></span></td>
                <td style="font-size:0.75rem;color:var(--gray-400);font-family:monospace;"><?= e(substr($pay['stripe_session_id'] ?? '–', 0, 20)) ?>...</td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentPayments)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--gray-400);padding:40px;">Noch keine Zahlungen</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
