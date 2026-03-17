<?php
$pageTitle = 'Admin – Rechtliches';
$adminPage = 'legal';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $slug    = $_POST['slug'] ?? '';
    $title   = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';

    $allowed = ['impressum','datenschutz','agb'];
    if (in_array($slug, $allowed)) {
        $db->prepare("INSERT INTO legal_pages (slug,title,content) VALUES (?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title), content=VALUES(content)")
           ->execute([$slug, $title, $content]);
        setFlash('success', '"' . $title . '" gespeichert!');
    }
    header('Location: /admin/legal.php?page=' . $slug); exit;
}

$activePage = $_GET['page'] ?? 'impressum';
$pages      = $db->query("SELECT * FROM legal_pages ORDER BY FIELD(slug,'impressum','datenschutz','agb')")->fetchAll();
$pageMap    = [];
foreach ($pages as $p) $pageMap[$p['slug']] = $p;

$currentPage = $pageMap[$activePage] ?? ['slug'=>$activePage,'title'=>ucfirst($activePage),'content'=>''];

$pageLabels = ['impressum'=>['Impressum','fa-building'],'datenschutz'=>['Datenschutz','fa-shield-halved'],'agb'=>['AGB','fa-file-contract']];

require_once __DIR__ . '/../includes/admin-nav.php';
?>

<div class="admin-page-header">
    <div>
        <h1>Rechtliche Seiten</h1>
        <p style="color:var(--gray-500);margin:4px 0 0;font-size:0.9rem;">Impressum, Datenschutz und AGB verwalten</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="/impressum" target="_blank" class="btn btn-ghost btn-sm"><i class="fas fa-external-link-alt"></i> Vorschau</a>
    </div>
</div>

<div style="display:flex;gap:8px;margin-bottom:24px;">
<?php foreach ($pageLabels as $slug => [$label, $icon]): ?>
<a href="?page=<?= $slug ?>" class="btn <?= $activePage === $slug ? 'btn-primary' : 'btn-ghost' ?>">
    <i class="fas <?= $icon ?>"></i> <?= $label ?>
</a>
<?php endforeach; ?>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3>
            <i class="fas <?= $pageLabels[$activePage][1] ?? 'fa-file' ?>" style="color:var(--primary-dark);margin-right:8px;"></i>
            <?= $pageLabels[$activePage][0] ?? ucfirst($activePage) ?> bearbeiten
        </h3>
        <a href="/<?= e($activePage) ?>" target="_blank" class="btn btn-sm btn-ghost"><i class="fas fa-external-link-alt"></i> Live ansehen</a>
    </div>
    <div class="admin-card-body">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="slug" value="<?= e($activePage) ?>">

            <div style="margin-bottom:16px;">
                <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">Seitentitel</label>
                <input type="text" name="title" value="<?= e($currentPage['title']) ?>" required style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.9rem;">
            </div>

            <div style="margin-bottom:8px;">
                <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">
                    Inhalt (HTML erlaubt)
                    <span style="font-size:0.75rem;font-weight:400;color:var(--gray-400);">Du kannst &lt;h2&gt;, &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;strong&gt; verwenden</span>
                </label>
                <textarea name="content" id="legalContent" rows="25" style="width:100%;padding:12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.875rem;font-family:monospace;resize:vertical;"><?= htmlspecialchars($currentPage['content'], ENT_QUOTES) ?></textarea>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;">
                <div style="font-size:0.82rem;color:var(--gray-400);">
                    <?php if ($currentPage['updated_at'] ?? ''): ?>
                    Zuletzt aktualisiert: <?= date('d.m.Y H:i', strtotime($currentPage['updated_at'])) ?>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Speichern</button>
            </div>
        </form>
    </div>
</div>

<!-- Info Box -->
<div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:var(--radius-lg);padding:16px 20px;margin-top:16px;">
    <h4 style="font-size:0.9rem;margin-bottom:8px;"><i class="fas fa-info-circle" style="color:var(--primary-dark);"></i> Öffentliche URLs</h4>
    <div style="display:flex;gap:16px;flex-wrap:wrap;">
        <a href="/impressum" target="_blank" style="font-size:0.85rem;color:var(--primary-dark);">/impressum</a>
        <a href="/datenschutz" target="_blank" style="font-size:0.85rem;color:var(--primary-dark);">/datenschutz</a>
        <a href="/agb" target="_blank" style="font-size:0.85rem;color:var(--primary-dark);">/agb</a>
    </div>
</div>

</div></div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
