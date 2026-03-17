<?php
$pageTitle = 'E-Mail & Newsletter';
$adminPage = 'mail';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// Ensure tables exist
try {
    $db->query("CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `user_id` INT DEFAULT NULL,
        `token` VARCHAR(64) NOT NULL DEFAULT '',
        `confirmed` TINYINT(1) NOT NULL DEFAULT 1,
        `subscribed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `unsubscribed_at` DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    $db->query("CREATE TABLE IF NOT EXISTS `newsletters` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `subject` VARCHAR(255) NOT NULL,
        `preview_text` VARCHAR(255) DEFAULT NULL,
        `content_html` LONGTEXT NOT NULL,
        `status` ENUM('draft','sending','sent') NOT NULL DEFAULT 'draft',
        `recipient_count` INT NOT NULL DEFAULT 0,
        `sent_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    $db->query("CREATE TABLE IF NOT EXISTS `email_queue` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `to_email` VARCHAR(255) NOT NULL,
        `to_name` VARCHAR(255) DEFAULT NULL,
        `subject` VARCHAR(500) NOT NULL,
        `body_html` LONGTEXT NOT NULL,
        `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
        `attempts` INT NOT NULL DEFAULT 0,
        `last_error` TEXT DEFAULT NULL,
        `sent_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
} catch (Throwable $e) { /* already exist */ }

// ── SMTP config save ──────────────────────────────────────────────────────────
if ($_POST['action'] ?? '' === 'save_smtp') {
    if (!verifyCsrf()) die('Invalid CSRF');
    $config = "<?php\nreturn " . var_export([
        'host'       => trim($_POST['smtp_host']  ?? ''),
        'port'       => (int)($_POST['smtp_port'] ?? 587),
        'username'   => trim($_POST['smtp_user']  ?? ''),
        'password'   => trim($_POST['smtp_pass']  ?? ''),
        'from_email' => trim($_POST['from_email'] ?? 'noreply@beautyos.at'),
        'from_name'  => trim($_POST['from_name']  ?? 'BeautyOS'),
        'encryption' => trim($_POST['enc']        ?? 'tls'),
        'debug'      => false,
    ], true) . ";\n";
    file_put_contents(__DIR__ . '/../config/mail.php', $config);
    setFlash('success', 'SMTP-Einstellungen gespeichert.');
    header('Location: /admin/mail.php?tab=smtp');
    exit;
}

// ── Test email ────────────────────────────────────────────────────────────────
if ($_POST['action'] ?? '' === 'test_email') {
    if (!verifyCsrf()) die('Invalid CSRF');
    require_once __DIR__ . '/../includes/Mailer.php';
    $toEmail = trim($_POST['test_to'] ?? '');
    try {
        $m = new Mailer();
        $html = Mailer::templateWrap('Test', '<h1>Test-E-Mail ✅</h1><p>Die SMTP-Konfiguration funktioniert einwandfrei. 🎉</p>');
        $m->send($toEmail, 'Test', 'BeautyOS SMTP Test', $html);
        setFlash('success', "Test-E-Mail an {$toEmail} gesendet!");
    } catch (Throwable $e) {
        setFlash('error', 'Fehler: ' . $e->getMessage());
    }
    header('Location: /admin/mail.php?tab=smtp');
    exit;
}

// ── Create / send newsletter ──────────────────────────────────────────────────
if ($_POST['action'] ?? '' === 'save_newsletter') {
    if (!verifyCsrf()) die('Invalid CSRF');
    $subject  = trim($_POST['subject']   ?? '');
    $preview  = trim($_POST['preview']   ?? '');
    $content  = trim($_POST['content']   ?? '');
    $doSend   = isset($_POST['do_send']);

    if ($subject && $content) {
        $stmt = $db->prepare("INSERT INTO newsletters (subject, preview_text, content_html) VALUES (?,?,?)");
        $stmt->execute([$subject, $preview, $content]);
        $nlId = $db->lastInsertId();
        if ($doSend) {
            require_once __DIR__ . '/../includes/Mailer.php';
            // Queue to all active subscribers
            $subs = $db->query("SELECT email FROM newsletter_subscribers WHERE unsubscribed_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            $count = 0;
            foreach ($subs as $email) {
                $unsubToken = md5($email . 'unsub_salt_beautyos');
                $footer = "<hr style='border:none;border-top:1px solid #f0ebf5;margin:30px 0;'><p style='font-size:0.8rem;color:#9e9e9e;text-align:center;'>Du erhältst diesen Newsletter von BeautyOS. <a href='" . APP_URL . "/newsletter-unsubscribe.php?token={$unsubToken}' style='color:#d4809f;'>Abmelden</a></p>";
                $html = Mailer::templateWrap($subject, $content . $footer);
                Mailer::queue($email, '', $subject, $html);
                $count++;
            }
            $db->prepare("UPDATE newsletters SET status='sent', recipient_count=?, sent_at=NOW() WHERE id=?")->execute([$count, $nlId]);
            setFlash('success', "Newsletter an {$count} Abonnenten gesendet!");
        } else {
            setFlash('success', 'Newsletter als Entwurf gespeichert.');
        }
    } else {
        setFlash('error', 'Betreff und Inhalt erforderlich.');
    }
    header('Location: /admin/mail.php?tab=newsletter');
    exit;
}

// ── Delete newsletter ─────────────────────────────────────────────────────────
if ($_GET['del_nl'] ?? '') {
    $db->prepare("DELETE FROM newsletters WHERE id=?")->execute([(int)$_GET['del_nl']]);
    setFlash('success', 'Newsletter gelöscht.');
    header('Location: /admin/mail.php?tab=newsletter');
    exit;
}

// ── Delete subscriber ─────────────────────────────────────────────────────────
if ($_GET['del_sub'] ?? '') {
    $db->prepare("DELETE FROM newsletter_subscribers WHERE id=?")->execute([(int)$_GET['del_sub']]);
    setFlash('success', 'Abonnent entfernt.');
    header('Location: /admin/mail.php?tab=subscribers');
    exit;
}

// ── Process queue (manual trigger) ───────────────────────────────────────────
if ($_GET['process_queue'] ?? '') {
    require_once __DIR__ . '/../includes/Mailer.php';
    $m = new Mailer();
    $sent = $m->processQueue(50);
    setFlash('success', "{$sent} E-Mails gesendet.");
    header('Location: /admin/mail.php?tab=queue');
    exit;
}

// ── Data ──────────────────────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'smtp';

// SMTP config
$smtpCfg = file_exists(__DIR__ . '/../config/mail.php') ? require __DIR__ . '/../config/mail.php' : [];

// Subscriber stats
$subTotal = $db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE unsubscribed_at IS NULL")->fetchColumn();
$subTotal = (int)$subTotal;

// Newsletters
$newsletters = $db->query("SELECT * FROM newsletters ORDER BY created_at DESC LIMIT 50")->fetchAll();

// Subscribers list
$subscribers = $db->query("SELECT ns.*, u.first_name, u.last_name FROM newsletter_subscribers ns LEFT JOIN users u ON ns.user_id=u.id ORDER BY ns.subscribed_at DESC LIMIT 200")->fetchAll();

// Queue stats
$queuePending = (int)$db->query("SELECT COUNT(*) FROM email_queue WHERE status='pending'")->fetchColumn();
$queueSent    = (int)$db->query("SELECT COUNT(*) FROM email_queue WHERE status='sent'")->fetchColumn();
$queueFailed  = (int)$db->query("SELECT COUNT(*) FROM email_queue WHERE status='failed'")->fetchColumn();
$queueRecent  = $db->query("SELECT * FROM email_queue ORDER BY created_at DESC LIMIT 40")->fetchAll();

require_once __DIR__ . '/../includes/admin-nav.php';
?>

<div class="admin-page-header">
    <div>
        <h1>E-Mail & Newsletter</h1>
        <p class="page-subtitle">SMTP konfigurieren, Newsletter versenden, Abonnenten verwalten</p>
    </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:24px;background:white;border-radius:14px;padding:6px;box-shadow:0 2px 12px rgba(100,60,140,0.06);width:fit-content;">
    <?php
    $tabs = [
        'smtp'       => ['<i class="fas fa-server"></i> SMTP', 'smtp'],
        'newsletter' => ['<i class="fas fa-envelope-open-text"></i> Newsletter', 'newsletter'],
        'subscribers'=> ['<i class="fas fa-users"></i> Abonnenten <span style="background:#e8a0bf;color:#fff;font-size:0.7rem;padding:2px 7px;border-radius:20px;margin-left:4px;">' . $subTotal . '</span>', 'subscribers'],
        'queue'      => ['<i class="fas fa-paper-plane"></i> Queue <span style="background:' . ($queuePending ? 'var(--warning)' : 'var(--gray-300)') . ';color:#fff;font-size:0.7rem;padding:2px 7px;border-radius:20px;margin-left:4px;">' . $queuePending . '</span>', 'queue'],
    ];
    foreach ($tabs as $key => [$label, $t]):
    ?>
    <a href="?tab=<?= $key ?>" style="padding:8px 18px;border-radius:10px;font-size:0.875rem;font-weight:600;text-decoration:none;transition:all 0.15s;<?= $tab===$key ? 'background:linear-gradient(135deg,var(--primary-dark),#c06090);color:#fff;box-shadow:0 4px 12px rgba(180,80,120,0.25);' : 'color:#7060a0;' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<?php $flash = getFlash(); if ($flash): ?>
<div class="flash flash-<?= e($flash['type']) ?>" style="margin-bottom:16px;"><?= e($flash['message']) ?></div>
<?php endif; ?>

<!-- ══════════════ SMTP TAB ══════════════ -->
<?php if ($tab === 'smtp'): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">
    <div class="admin-card">
        <div class="admin-card-header"><h3><i class="fas fa-server" style="color:var(--primary-dark);margin-right:8px;"></i> SMTP-Einstellungen</h3></div>
        <div class="admin-card-body">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_smtp">
                <div class="form-group">
                    <label class="form-label">SMTP Host</label>
                    <input type="text" name="smtp_host" class="form-control" placeholder="smtp.gmail.com" value="<?= e($smtpCfg['host'] ?? '') ?>">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?= e($smtpCfg['port'] ?? 587) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Verschlüsselung</label>
                        <select name="enc" class="form-control">
                            <option value="tls" <?= ($smtpCfg['encryption']??'tls')==='tls'?'selected':'' ?>>STARTTLS (587)</option>
                            <option value="ssl" <?= ($smtpCfg['encryption']??'')==='ssl'?'selected':'' ?>>SSL (465)</option>
                            <option value="none" <?= ($smtpCfg['encryption']??'')==='none'?'selected':'' ?>>Keine</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Benutzername (E-Mail)</label>
                    <input type="text" name="smtp_user" class="form-control" placeholder="noreply@beautyos.at" value="<?= e($smtpCfg['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Passwort</label>
                    <input type="password" name="smtp_pass" class="form-control" placeholder="<?= empty($smtpCfg['password']) ? 'Passwort eingeben' : '••••••••' ?>">
                    <small style="color:#9080b0;">Leer lassen, um das gespeicherte Passwort zu behalten</small>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Absender-Name</label>
                        <input type="text" name="from_name" class="form-control" value="<?= e($smtpCfg['from_name'] ?? 'BeautyOS') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Absender-E-Mail</label>
                        <input type="email" name="from_email" class="form-control" value="<?= e($smtpCfg['from_email'] ?? 'noreply@beautyos.at') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Einstellungen speichern</button>
            </form>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px;">
        <!-- Test Email -->
        <div class="admin-card">
            <div class="admin-card-header"><h3><i class="fas fa-flask" style="color:var(--success);margin-right:8px;"></i> Test-E-Mail</h3></div>
            <div class="admin-card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="test_email">
                    <div class="form-group">
                        <label class="form-label">An E-Mail senden</label>
                        <input type="email" name="test_to" class="form-control" placeholder="deine@email.at" required>
                    </div>
                    <button type="submit" class="btn btn-outline"><i class="fas fa-paper-plane"></i> Test senden</button>
                </form>
            </div>
        </div>

        <!-- Status -->
        <div class="admin-card">
            <div class="admin-card-header"><h3><i class="fas fa-info-circle" style="color:var(--info);margin-right:8px;"></i> Status</h3></div>
            <div class="admin-card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:12px;">
                    <div style="text-align:center;background:#fdf2f8;border-radius:10px;padding:12px;">
                        <div style="font-size:1.5rem;font-weight:800;color:var(--warning);"><?= $queuePending ?></div>
                        <div style="font-size:0.72rem;color:#9080b0;font-weight:600;text-transform:uppercase;">Ausstehend</div>
                    </div>
                    <div style="text-align:center;background:#f0fff4;border-radius:10px;padding:12px;">
                        <div style="font-size:1.5rem;font-weight:800;color:var(--success);"><?= $queueSent ?></div>
                        <div style="font-size:0.72rem;color:#9080b0;font-weight:600;text-transform:uppercase;">Gesendet</div>
                    </div>
                    <div style="text-align:center;background:#fff5f5;border-radius:10px;padding:12px;">
                        <div style="font-size:1.5rem;font-weight:800;color:var(--danger);"><?= $queueFailed ?></div>
                        <div style="font-size:0.72rem;color:#9080b0;font-weight:600;text-transform:uppercase;">Fehlgeschlagen</div>
                    </div>
                </div>
                <p style="font-size:0.82rem;color:#9080b0;">SMTP-Konfiguration: <?= !empty($smtpCfg['host']) ? '<span style="color:var(--success);">✅ Konfiguriert</span>' : '<span style="color:var(--danger);">❌ Nicht konfiguriert</span>' ?></p>
            </div>
        </div>

        <!-- Transactional Email Overview -->
        <div class="admin-card">
            <div class="admin-card-header"><h3><i class="fas fa-list" style="color:var(--primary-dark);margin-right:8px;"></i> Transaktions-E-Mails</h3></div>
            <div class="admin-card-body">
                <table class="data-table" style="font-size:0.8rem;">
                    <thead><tr><th>Trigger</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php
                    $emails = [
                        ['Registrierung (Willkommen)',   true],
                        ['Buchungsbestätigung (Kunde)',  true],
                        ['Buchungs-Update (Kunde)',      true],
                        ['Neue Buchung (Business)',      true],
                        ['Business freigeschaltet',     true],
                        ['Business abgelehnt',          true],
                        ['Abo bestätigt',               true],
                        ['Passwort zurücksetzen',       true],
                        ['Newsletter',                  true],
                    ];
                    foreach ($emails as [$name, $active]):
                    ?>
                    <tr>
                        <td><?= $name ?></td>
                        <td><?= $active ? '<span style="color:var(--success);font-weight:700;">✅ Aktiv</span>' : '<span style="color:var(--gray-400);">— Inaktiv</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════ NEWSLETTER TAB ══════════════ -->
<?php elseif ($tab === 'newsletter'): ?>
<div style="display:grid;grid-template-columns:1fr 420px;gap:20px;align-items:start;">
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-edit" style="color:var(--primary-dark);margin-right:8px;"></i> Neuer Newsletter</h3>
        </div>
        <div class="admin-card-body">
            <form method="POST" id="nlForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_newsletter">
                <div class="form-group">
                    <label class="form-label">Betreff *</label>
                    <input type="text" name="subject" class="form-control" placeholder="🌸 BeautyOS News — März 2026" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Vorschau-Text (Inbox-Preview)</label>
                    <input type="text" name="preview" class="form-control" placeholder="Was erwartet dich diesen Monat...">
                </div>
                <div class="form-group">
                    <label class="form-label">Inhalt (HTML)</label>
                    <div style="display:flex;gap:8px;margin-bottom:8px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertHtml('<h2>', '</h2>')"><b>H2</b></button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertHtml('<h3>', '</h3>')"><b>H3</b></button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertHtml('<p>', '</p>')">P</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertHtml('<strong>', '</strong>')"><b>B</b></button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertHtml('<em>', '</em>')"><i>I</i></button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertHtml('<br>', '')">BR</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertHtml(&quot;<a href='URL' style='color:#d4809f;font-weight:700;'>&quot;, '</a>')">Link</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="insertHtml(&quot;<p style='text-align:center;'><a href='URL' style='display:inline-block;background:linear-gradient(135deg,#d4809f,#e8a0bf);color:#fff;padding:12px 28px;border-radius:12px;text-decoration:none;font-weight:700;'>&quot;, &quot;</a></p>&quot;)">CTA</button>
                    </div>
                    <textarea name="content" id="nlContent" class="form-control" rows="14" placeholder="<h2>Hallo Community! 👋</h2>&#10;<p>...</p>" required></textarea>
                </div>
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <button type="submit" name="do_save" class="btn btn-outline"><i class="fas fa-save"></i> Als Entwurf speichern</button>
                    <button type="submit" name="do_send" value="1" class="btn btn-primary" onclick="return confirm('Newsletter an alle <?= $subTotal ?> Abonnenten senden?')">
                        <i class="fas fa-paper-plane"></i> Jetzt senden (<?= $subTotal ?> Abonnenten)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview -->
    <div class="admin-card" style="position:sticky;top:80px;">
        <div class="admin-card-header">
            <h3><i class="fas fa-eye" style="color:var(--info);margin-right:8px;"></i> Vorschau</h3>
            <button class="btn btn-outline btn-sm" onclick="updatePreview()"><i class="fas fa-sync"></i></button>
        </div>
        <div style="padding:0;overflow:hidden;border-radius:0 0 16px 16px;">
            <iframe id="nlPreview" style="width:100%;height:500px;border:none;" srcdoc=""></iframe>
        </div>
    </div>
</div>

<!-- Sent newsletters -->
<div class="admin-card" style="margin-top:20px;">
    <div class="admin-card-header"><h3><i class="fas fa-history" style="color:var(--primary-dark);margin-right:8px;"></i> Gesendete Newsletter</h3></div>
    <div class="admin-card-body" style="padding:0;">
        <?php if (empty($newsletters)): ?>
        <p style="padding:24px;text-align:center;color:#9080b0;">Noch keine Newsletter erstellt.</p>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>Betreff</th><th>Status</th><th>Empfänger</th><th>Erstellt</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($newsletters as $nl): ?>
            <tr>
                <td style="font-weight:600;"><?= e($nl['subject']) ?></td>
                <td><?php
                    $statusColors = ['draft'=>'#9080b0','sending'=>'var(--warning)','sent'=>'var(--success)'];
                    $statusLabels = ['draft'=>'Entwurf','sending'=>'Sendet...','sent'=>'Gesendet'];
                    $c = $statusColors[$nl['status']] ?? '#9080b0';
                    $l = $statusLabels[$nl['status']] ?? $nl['status'];
                    echo "<span style='color:{$c};font-weight:700;'>{$l}</span>";
                ?></td>
                <td><?= $nl['recipient_count'] ?></td>
                <td style="color:#9080b0;font-size:0.82rem;"><?= date('d.m.Y H:i', strtotime($nl['created_at'])) ?></td>
                <td>
                    <a href="?del_nl=<?= $nl['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);" onclick="return confirm('Löschen?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════ SUBSCRIBERS TAB ══════════════ -->
<?php elseif ($tab === 'subscribers'): ?>
<div class="admin-card">
    <div class="admin-card-header">
        <h3><i class="fas fa-users" style="color:var(--primary-dark);margin-right:8px;"></i> Abonnenten (<?= $subTotal ?> aktiv)</h3>
        <div style="display:flex;gap:8px;">
            <a href="?export=csv" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> CSV Export</a>
        </div>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <?php if (empty($subscribers)): ?>
        <p style="padding:24px;text-align:center;color:#9080b0;">Noch keine Abonnenten.</p>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>E-Mail</th><th>Name</th><th>Status</th><th>Seit</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($subscribers as $sub): ?>
            <tr>
                <td><?= e($sub['email']) ?></td>
                <td style="color:#9080b0;"><?= $sub['first_name'] ? e($sub['first_name'] . ' ' . $sub['last_name']) : '—' ?></td>
                <td><?= $sub['unsubscribed_at']
                    ? '<span style="color:var(--danger);font-size:0.82rem;">Abgemeldet</span>'
                    : '<span style="color:var(--success);font-weight:700;">✓ Aktiv</span>' ?></td>
                <td style="color:#9080b0;font-size:0.82rem;"><?= date('d.m.Y', strtotime($sub['subscribed_at'])) ?></td>
                <td>
                    <a href="?del_sub=<?= $sub['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);" onclick="return confirm('Entfernen?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════ QUEUE TAB ══════════════ -->
<?php elseif ($tab === 'queue'): ?>
<div style="display:flex;gap:14px;margin-bottom:20px;">
    <?php foreach ([['Ausstehend', $queuePending, 'var(--warning)'], ['Gesendet', $queueSent, 'var(--success)'], ['Fehlgeschlagen', $queueFailed, 'var(--danger)']] as [$label, $count, $color]): ?>
    <div class="kpi-card" style="flex:1;">
        <div class="kpi-label"><?= $label ?></div>
        <div class="kpi-value" style="color:<?= $color ?>;"><?= $count ?></div>
    </div>
    <?php endforeach; ?>
    <div style="display:flex;align-items:center;">
        <a href="?process_queue=1" class="btn btn-primary" onclick="return confirm('Queue verarbeiten?')"><i class="fas fa-play"></i> Queue verarbeiten</a>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header"><h3><i class="fas fa-inbox" style="color:var(--primary-dark);margin-right:8px;"></i> E-Mail Queue</h3></div>
    <div class="admin-card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>An</th><th>Betreff</th><th>Status</th><th>Versuche</th><th>Erstellt</th></tr></thead>
            <tbody>
            <?php foreach ($queueRecent as $q): ?>
            <tr>
                <td><?= e($q['to_email']) ?></td>
                <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($q['subject']) ?></td>
                <td><?php
                    $colors = ['pending'=>'var(--warning)','sent'=>'var(--success)','failed'=>'var(--danger)'];
                    $labels = ['pending'=>'Ausstehend','sent'=>'Gesendet','failed'=>'Fehler'];
                    $c = $colors[$q['status']] ?? '#9080b0';
                    $l = $labels[$q['status']] ?? $q['status'];
                    echo "<span style='color:{$c};font-weight:700;'>{$l}</span>";
                    if ($q['last_error']) echo "<br><small style='color:var(--danger);font-size:0.72rem;'>" . e(substr($q['last_error'],0,60)) . "</small>";
                ?></td>
                <td style="text-align:center;"><?= $q['attempts'] ?></td>
                <td style="color:#9080b0;font-size:0.82rem;"><?= date('d.m.Y H:i', strtotime($q['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- end mail content -->

<script>
function insertHtml(open, close) {
    const ta = document.getElementById('nlContent');
    if (!ta) return;
    const start = ta.selectionStart, end = ta.selectionEnd;
    const sel = ta.value.substring(start, end);
    ta.value = ta.value.substring(0, start) + open + sel + close + ta.value.substring(end);
    ta.focus();
    ta.selectionStart = start + open.length;
    ta.selectionEnd   = start + open.length + sel.length;
    updatePreview();
}

function updatePreview() {
    const content = document.getElementById('nlContent')?.value ?? '';
    const subject = document.querySelector('[name="subject"]')?.value ?? 'Newsletter Vorschau';
    const frame = document.getElementById('nlPreview');
    if (!frame) return;
    const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body{margin:0;padding:0;background:#f5f0eb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#424242;}
.wrap{max-width:560px;margin:0 auto;}
.header{background:linear-gradient(135deg,#d4809f,#e8a0bf);padding:24px;text-align:center;color:#fff;font-size:1.3rem;font-weight:800;}
.body{background:#fff;padding:32px;}h1,h2{color:#212121;}p{line-height:1.7;color:#616161;}
a{color:#d4809f;}
</style></head><body><div class="wrap">
<div class="header">BeautyOS</div>
<div class="body">${content}</div>
</div></body></html>`;
    frame.srcdoc = html;
}

// Auto-preview on type
document.getElementById('nlContent')?.addEventListener('input', updatePreview);
document.addEventListener('DOMContentLoaded', updatePreview);
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
