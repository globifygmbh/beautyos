<?php
$pageTitle = 'Impressum';
require_once 'includes/header.php';
$db = getDB();
$page = $db->prepare("SELECT * FROM legal_pages WHERE slug='impressum'")->execute() ? $db->prepare("SELECT * FROM legal_pages WHERE slug='impressum'") : null;
$stmt = $db->prepare("SELECT * FROM legal_pages WHERE slug='impressum'");
$stmt->execute();
$page = $stmt->fetch();
?>
<div class="container" style="max-width:760px;padding:60px 24px;">
    <nav style="font-size:0.85rem;color:var(--gray-400);margin-bottom:32px;">
        <a href="/" style="color:var(--gray-400);">Startseite</a> / <?= e($page['title'] ?? 'Impressum') ?>
    </nav>
    <?php if ($page): ?>
        <h1 style="margin-bottom:32px;"><?= e($page['title']) ?></h1>
        <div style="line-height:1.8;color:var(--gray-700);" class="legal-content">
            <?= $page['content'] ?>
        </div>
        <p style="margin-top:40px;font-size:0.82rem;color:var(--gray-400);">Zuletzt aktualisiert: <?= date('d.m.Y', strtotime($page['updated_at'])) ?></p>
    <?php else: ?>
        <h1>Impressum</h1>
        <p style="color:var(--gray-500);">Das Impressum wird gerade eingerichtet. Bitte schaue bald wieder vorbei.</p>
    <?php endif; ?>
</div>
<style>
.legal-content h2 { font-size:1.3rem; margin:28px 0 12px; }
.legal-content h3 { font-size:1.1rem; margin:20px 0 8px; }
.legal-content p  { margin-bottom:12px; }
.legal-content ul { padding-left:20px; margin-bottom:12px; }
.legal-content ul li { margin-bottom:4px; }
</style>
<?php require_once 'includes/footer.php'; ?>
