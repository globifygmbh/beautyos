<?php
$pageTitle = 'Newsletter abmelden';
require_once 'config/app.php';

$token  = trim($_GET['token'] ?? '');
$done   = false;
$error  = false;

if ($token) {
    $db    = getDB();
    // Find subscriber by token (md5 of email + salt)
    $subs  = $db->query("SELECT * FROM newsletter_subscribers WHERE unsubscribed_at IS NULL")->fetchAll();
    $found = null;
    foreach ($subs as $s) {
        if (md5($s['email'] . 'unsub_salt_beautyos') === $token) {
            $found = $s;
            break;
        }
    }
    if ($found) {
        $db->prepare("UPDATE newsletter_subscribers SET unsubscribed_at=NOW() WHERE id=?")->execute([$found['id']]);
        $done = true;
    } else {
        $error = true;
    }
}

require_once 'includes/header.php';
?>
<div class="container container-sm" style="padding-top:80px;padding-bottom:80px;text-align:center;">
    <?php if ($done): ?>
        <div style="background:#fff;border-radius:20px;padding:48px;box-shadow:0 4px 24px rgba(100,60,140,0.08);">
            <div style="width:72px;height:72px;border-radius:50%;background:var(--primary-50);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:1.8rem;color:var(--primary-dark);">
                <i class="fas fa-check"></i>
            </div>
            <h2 style="margin-bottom:12px;">Erfolgreich abgemeldet</h2>
            <p style="color:var(--gray-500);">Du wirst keine weiteren Newsletter von BeautyOS erhalten.</p>
            <a href="/" class="btn btn-primary" style="margin-top:24px;">Zur Startseite</a>
        </div>
    <?php elseif ($error): ?>
        <div style="background:#fff;border-radius:20px;padding:48px;box-shadow:0 4px 24px rgba(100,60,140,0.08);">
            <h2 style="margin-bottom:12px;">Link ungültig</h2>
            <p style="color:var(--gray-500);">Dieser Abmelde-Link ist ungültig oder wurde bereits verwendet.</p>
            <a href="/" class="btn btn-outline" style="margin-top:24px;">Zur Startseite</a>
        </div>
    <?php else: ?>
        <div style="background:#fff;border-radius:20px;padding:48px;box-shadow:0 4px 24px rgba(100,60,140,0.08);">
            <h2 style="margin-bottom:12px;">Ungültiger Link</h2>
            <p style="color:var(--gray-500);">Kein gültiger Abmelde-Token gefunden.</p>
        </div>
    <?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
