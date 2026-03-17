<?php
$pageTitle = 'Abo-Verwaltung';
require_once __DIR__ . '/../includes/header.php';
requireBusiness();

$db   = getDB();
$user = currentUser();

$stmt = $db->prepare("SELECT b.*, sp.name as plan_name, sp.price_monthly FROM businesses b LEFT JOIN subscription_plans sp ON b.subscription_plan_id = sp.id WHERE b.user_id = ? LIMIT 1");
$stmt->execute([$user['id']]);
$biz = $stmt->fetch();

if (!$biz) { header('Location: /dashboard/setup.php'); exit; }

$plans = $db->query("SELECT * FROM subscription_plans ORDER BY sort_order")->fetchAll();

// Load Stripe config
$stripeConf = __DIR__ . '/../config/stripe.php';
$stripeEnabled = false;
$stripePubKey  = '';
if (file_exists($stripeConf)) {
    include $stripeConf;
    $stripeEnabled = defined('STRIPE_SECRET_KEY') && STRIPE_SECRET_KEY && defined('STRIPE_PUBLISHABLE_KEY') && STRIPE_PUBLISHABLE_KEY;
    $stripePubKey  = defined('STRIPE_PUBLISHABLE_KEY') ? STRIPE_PUBLISHABLE_KEY : '';
}

// Create Stripe Checkout Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['stripe_checkout'])) {
    $planId = (int)$_POST['plan_id'];
    $planStmt = $db->prepare("SELECT * FROM subscription_plans WHERE id=?");
    $planStmt->execute([$planId]);
    $selectedPlan = $planStmt->fetch();

    if ($selectedPlan && $stripeEnabled) {
        // Create Stripe Checkout Session via cURL
        $secretKey = STRIPE_SECRET_KEY;
        $amountCents = (int)round($selectedPlan['price_monthly'] * 100);
        $returnUrl = APP_URL . '/dashboard/subscription.php?success=1&plan=' . $planId;
        $cancelUrl = APP_URL . '/dashboard/subscription.php?cancelled=1';

        $data = http_build_query([
            'payment_method_types[]'          => 'card',
            'line_items[0][price_data][currency]'           => 'eur',
            'line_items[0][price_data][unit_amount]'        => $amountCents,
            'line_items[0][price_data][product_data][name]' => 'BeautyOS ' . $selectedPlan['name'],
            'line_items[0][quantity]'                       => '1',
            'mode'                            => 'payment',
            'success_url'                     => $returnUrl,
            'cancel_url'                      => $cancelUrl,
            'metadata[business_id]'           => $biz['id'],
            'metadata[plan_id]'               => $planId,
            'metadata[user_id]'               => $user['id'],
        ]);

        // Use stripe_price_id if set
        if (!empty($selectedPlan['stripe_price_id'])) {
            $data = http_build_query([
                'line_items[0][price]'    => $selectedPlan['stripe_price_id'],
                'line_items[0][quantity]' => '1',
                'mode'                   => 'subscription',
                'success_url'            => $returnUrl,
                'cancel_url'             => $cancelUrl,
                'metadata[business_id]'  => $biz['id'],
                'metadata[plan_id]'      => $planId,
                'metadata[user_id]'      => $user['id'],
            ]);
        }

        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_USERPWD        => $secretKey . ':',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $session = json_decode($response, true);
        if (!empty($session['url'])) {
            header('Location: ' . $session['url']);
            exit;
        } else {
            setFlash('error', 'Stripe-Fehler: ' . ($session['error']['message'] ?? 'Unbekannt'));
        }
    }
}

// Direct plan change (if Stripe not configured)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['select_plan']) && !$stripeEnabled) {
    $planId = (int)$_POST['plan_id'];
    $planStmt = $db->prepare("SELECT * FROM subscription_plans WHERE id=?");
    $planStmt->execute([$planId]);
    $plan = $planStmt->fetch();
    if ($plan) {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $db->prepare("UPDATE businesses SET subscription_plan_id=?, subscription_expires_at=?, status='active' WHERE id=?")->execute([$planId, $expiresAt, $biz['id']]);
        setFlash('success', 'Plan "' . $plan['name'] . '" aktiviert!');
        header('Location: /dashboard/subscription.php'); exit;
    }
}

// Success from Stripe
if (isset($_GET['success'])) {
    $planId = (int)$_GET['plan'];
    if ($planId) {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $db->prepare("UPDATE businesses SET subscription_plan_id=?, subscription_expires_at=?, status='active' WHERE id=?")->execute([$planId, $expiresAt, $biz['id']]);
    }
    setFlash('success', 'Zahlung erfolgreich! Dein Plan wurde aktiviert.');
    header('Location: /dashboard/subscription.php'); exit;
}
?>

<?php require_once __DIR__ . '/../includes/dashboard-nav.php'; ?>

<div class="db-content">
    <div class="db-page-header">
        <div>
            <h1>Abo-Verwaltung</h1>
            <p class="db-subtitle">
                Aktueller Plan: <strong><?= e($biz['plan_name'] ?? 'Kein Plan') ?></strong>
                <?php if ($biz['subscription_expires_at']): ?>
                    · Gültig bis <strong><?= date('d.m.Y', strtotime($biz['subscription_expires_at'])) ?></strong>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if ($stripeEnabled): ?>
    <div style="background:linear-gradient(135deg,#f5f0ff,#fdf0f8);border:1px solid #e8d8f8;border-radius:14px;padding:14px 20px;margin-bottom:28px;display:flex;align-items:center;gap:12px;font-size:0.875rem;">
        <i class="fab fa-stripe" style="font-size:1.5rem;color:#635bff;"></i>
        <span>Sichere Zahlung via <strong>Stripe</strong>. Alle gängigen Kreditkarten werden akzeptiert.</span>
        <i class="fas fa-lock" style="margin-left:auto;color:#9080b0;"></i>
    </div>
    <?php endif; ?>

    <!-- Plan Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
    <?php foreach ($plans as $i => $plan):
        $features = json_decode($plan['features'], true) ?: [];
        $isPopular  = $i === 1;
        $isCurrent  = $biz['subscription_plan_id'] == $plan['id'];
        $isExpired  = $biz['subscription_expires_at'] && strtotime($biz['subscription_expires_at']) < time();
    ?>
    <div style="background:white;border-radius:18px;padding:28px;box-shadow:0 2px 16px rgba(100,60,140,<?= $isPopular?'0.12':'0.06' ?>);border:2px solid <?= $isCurrent?'var(--primary-dark)':($isPopular?'#e8d8f8':'#f0ebff') ?>;position:relative;transition:transform 0.2s,box-shadow 0.2s;" onmouseenter="this.style.transform='translateY(-4px)';this.style.boxShadow='0 8px 28px rgba(100,60,140,0.14)'" onmouseleave="this.style.transform='';this.style.boxShadow=''">

        <?php if ($isPopular): ?>
        <div style="position:absolute;top:-14px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,var(--primary-dark),#c06090);color:white;padding:4px 18px;border-radius:20px;font-size:0.75rem;font-weight:700;white-space:nowrap;">⭐ Beliebtester Plan</div>
        <?php endif; ?>
        <?php if ($isCurrent): ?>
        <div style="position:absolute;top:-14px;right:16px;background:#10b981;color:white;padding:4px 14px;border-radius:20px;font-size:0.72rem;font-weight:700;">Aktiv</div>
        <?php endif; ?>

        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:4px;"><?= e($plan['name']) ?></h3>
        <div style="font-size:2.2rem;font-weight:800;color:var(--gray-900);margin:12px 0 4px;line-height:1;">
            €<?= number_format($plan['price_monthly'],2,',','.') ?>
            <span style="font-size:0.9rem;font-weight:400;color:#9080b0;">/Monat</span>
        </div>

        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:20px;">
            <?php if ($plan['booking_system']): ?><span style="background:#d1fae5;color:#065f46;font-size:0.68rem;font-weight:700;padding:2px 8px;border-radius:10px;">Buchungen</span><?php endif; ?>
            <?php if ($plan['custom_design']): ?><span style="background:#ede9fe;color:#5b21b6;font-size:0.68rem;font-weight:700;padding:2px 8px;border-radius:10px;">Design</span><?php endif; ?>
            <?php if ($plan['priority_listing']): ?><span style="background:#fef3c7;color:#92400e;font-size:0.68rem;font-weight:700;padding:2px 8px;border-radius:10px;">Priority</span><?php endif; ?>
            <?php if ($plan['featured_badge']): ?><span style="background:#fee2e2;color:#991b1b;font-size:0.68rem;font-weight:700;padding:2px 8px;border-radius:10px;">Featured</span><?php endif; ?>
        </div>

        <ul style="list-style:none;padding:0;margin:0 0 24px;font-size:0.875rem;">
        <?php foreach ($features as $f): ?>
        <li style="display:flex;align-items:flex-start;gap:8px;padding:5px 0;border-bottom:1px solid #faf8ff;">
            <i class="fas fa-check" style="color:var(--primary-dark);font-size:0.75rem;margin-top:3px;flex-shrink:0;"></i>
            <?= e($f) ?>
        </li>
        <?php endforeach; ?>
        <li style="padding:5px 0;font-size:0.82rem;color:#9080b0;"><i class="fas fa-images" style="margin-right:6px;"></i> Max. <?= $plan['max_images'] ?> Bilder</li>
        </ul>

        <?php if ($isCurrent && !$isExpired): ?>
        <button class="btn btn-secondary w-100" disabled style="background:#f0f0f0;color:#9080b0;border:none;border-radius:12px;padding:12px;">Dein aktueller Plan</button>
        <?php else: ?>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
            <?php if ($stripeEnabled): ?>
            <button type="submit" name="stripe_checkout" class="btn <?= $isPopular?'btn-primary':'btn-outline' ?> w-100" style="border-radius:12px;padding:12px;font-weight:600;">
                <i class="fab fa-stripe" style="margin-right:6px;"></i> Jetzt buchen · €<?= number_format($plan['price_monthly'],2,',','.') ?>
            </button>
            <?php else: ?>
            <button type="submit" name="select_plan" class="btn <?= $isPopular?'btn-primary':'btn-outline' ?> w-100" style="border-radius:12px;padding:12px;">
                Plan wählen
            </button>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>

    <?php if (!$stripeEnabled): ?>
    <div style="background:#fefce8;border:1px solid #fde68a;border-radius:12px;padding:14px 20px;margin-top:24px;font-size:0.875rem;color:#92400e;">
        <i class="fas fa-info-circle"></i>
        <strong>Stripe nicht konfiguriert.</strong> Pläne werden direkt aktiviert (ohne Bezahlung). Konfiguriere Stripe im <a href="/admin/payments.php" style="color:#92400e;font-weight:600;">Admin-Bereich</a>.
    </div>
    <?php endif; ?>
</div>

</div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
