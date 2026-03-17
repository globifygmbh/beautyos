<?php
$pageTitle = 'Admin – Kategorien & Filter';
$adminPage = 'categories';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_category') {
        $id   = (int)($_POST['cat_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? 0);

        if ($id) {
            $db->prepare("UPDATE categories SET name=?,slug=?,icon=?,description=?,sort_order=? WHERE id=?")
               ->execute([$name,$slug,$icon,$desc,$sort,$id]);
            setFlash('success', 'Kategorie aktualisiert!');
        } else {
            $db->prepare("INSERT INTO categories (name,slug,icon,description,sort_order) VALUES (?,?,?,?,?)")
               ->execute([$name,$slug,$icon,$desc,$sort]);
            setFlash('success', 'Kategorie erstellt!');
        }
    } elseif ($action === 'delete_category') {
        $db->prepare("DELETE FROM categories WHERE id=?")->execute([(int)$_POST['cat_id']]);
        setFlash('success', 'Kategorie gelöscht.');
    }

    header('Location: /admin/categories.php'); exit;
}

$categories = $db->query("
    SELECT c.*, COUNT(bc.business_id) as business_count
    FROM categories c
    LEFT JOIN business_categories bc ON c.id = bc.category_id
    GROUP BY c.id
    ORDER BY c.sort_order, c.name
")->fetchAll();

$editCat = null;
if (isset($_GET['edit'])) {
    foreach ($categories as $c) { if ($c['id'] == $_GET['edit']) { $editCat = $c; break; } }
}

$faIcons = ['fa-scissors','fa-hand-sparkles','fa-spa','fa-cut','fa-leaf','fa-hands','fa-eye','fa-eye-dropper','fa-star','fa-heart','fa-magic','fa-paint-brush','fa-sun','fa-smile','fa-gem','fa-ribbon','fa-feather','fa-crown','fa-fire','fa-bolt'];

require_once __DIR__ . '/../includes/admin-nav.php';
?>

<div class="admin-page-header">
    <div>
        <h1>Kategorien & Filter</h1>
        <p style="color:var(--gray-500);margin:4px 0 0;font-size:0.9rem;"><?= count($categories) ?> Kategorien</p>
    </div>
    <a href="?new=1" class="btn btn-primary"><i class="fas fa-plus"></i> Neue Kategorie</a>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:32px;">
<?php foreach ($categories as $cat): ?>
<div class="admin-card" style="<?= ($editCat && $editCat['id']==$cat['id']) ? 'border-color:var(--primary-dark);' : '' ?>">
    <div class="admin-card-body" style="text-align:center;">
        <div style="width:52px;height:52px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:1.3rem;color:var(--primary-dark);">
            <i class="fas <?= e($cat['icon'] ?: 'fa-star') ?>"></i>
        </div>
        <div style="font-weight:600;margin-bottom:4px;"><?= e($cat['name']) ?></div>
        <div style="font-size:0.78rem;color:var(--gray-400);margin-bottom:4px;">/<?= e($cat['slug']) ?></div>
        <div style="font-size:0.78rem;color:var(--gray-500);margin-bottom:12px;"><?= $cat['business_count'] ?> Unternehmen</div>
        <div style="font-size:0.75rem;color:var(--gray-400);margin-bottom:12px;">Sortierung: <?= $cat['sort_order'] ?></div>
        <div style="display:flex;gap:6px;justify-content:center;">
            <a href="?edit=<?= $cat['id'] ?>" class="btn btn-sm btn-ghost"><i class="fas fa-edit"></i></a>
            <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                <button name="action" value="delete_category" class="btn btn-sm btn-ghost" style="color:var(--danger);" onclick="return confirm('Kategorie löschen?')"><i class="fas fa-trash"></i></button>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Edit/Create Form -->
<?php if ($editCat || isset($_GET['new'])): ?>
<div class="admin-card">
    <div class="admin-card-header">
        <h3><?= $editCat ? 'Kategorie bearbeiten: ' . e($editCat['name']) : 'Neue Kategorie' ?></h3>
        <a href="/admin/categories.php" class="btn btn-sm btn-ghost"><i class="fas fa-times"></i></a>
    </div>
    <div class="admin-card-body">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="cat_id" value="<?= $editCat['id'] ?? 0 ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <div>
                    <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">Name *</label>
                    <input type="text" name="name" value="<?= e($editCat['name'] ?? '') ?>" required style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.9rem;">
                </div>
                <div>
                    <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">Slug *</label>
                    <input type="text" name="slug" value="<?= e($editCat['slug'] ?? '') ?>" required style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.9rem;">
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:8px;">Icon (FontAwesome)</label>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                <?php foreach ($faIcons as $icon): ?>
                <label style="cursor:pointer;">
                    <input type="radio" name="icon" value="<?= $icon ?>" <?= ($editCat['icon'] ?? '') === $icon ? 'checked' : '' ?> style="display:none;" class="icon-radio">
                    <div class="icon-option" style="width:40px;height:40px;border:2px solid var(--gray-200);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;transition:all 0.15s;font-size:1rem;color:var(--gray-500);">
                        <i class="fas <?= $icon ?>"></i>
                    </div>
                </label>
                <?php endforeach; ?>
                </div>
                <input type="text" name="icon" id="iconInput" value="<?= e($editCat['icon'] ?? '') ?>" placeholder="fa-scissors" style="padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.9rem;width:200px;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
                <div>
                    <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">Beschreibung</label>
                    <textarea name="description" rows="3" style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.875rem;"><?= e($editCat['description'] ?? '') ?></textarea>
                </div>
                <div>
                    <label style="font-size:0.82rem;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">Sortierung</label>
                    <input type="number" name="sort_order" value="<?= $editCat['sort_order'] ?? 0 ?>" style="padding:8px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.9rem;width:100%;">
                </div>
            </div>

            <div style="display:flex;gap:12px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Speichern</button>
                <a href="/admin/categories.php" class="btn btn-ghost">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
<script>
document.querySelectorAll('.icon-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.icon-option').forEach(o => o.style.borderColor='var(--gray-200)');
        this.nextElementSibling.style.borderColor='var(--primary-dark)';
        document.getElementById('iconInput').value = this.value;
    });
    if (radio.checked) radio.nextElementSibling.style.borderColor='var(--primary-dark)';
});
</script>
<?php endif; ?>

</div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
