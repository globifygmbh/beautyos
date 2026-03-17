<?php
$pageTitle = 'Admin – Bewertungen';
$adminPage = 'reviews';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['review_id'] ?? 0);

    if ($action === 'delete' && $id) {
        $db->prepare("DELETE FROM reviews WHERE id=?")->execute([$id]);
        setFlash('success', 'Bewertung gelöscht.');
    } elseif ($action === 'hide' && $id) {
        // Add hidden column if not exists
        try { $db->query("ALTER TABLE reviews ADD COLUMN hidden TINYINT(1) DEFAULT 0 AFTER replied_at"); } catch(Exception $e){}
        $db->prepare("UPDATE reviews SET hidden=1 WHERE id=?")->execute([$id]);
        setFlash('success', 'Bewertung versteckt.');
    } elseif ($action === 'show' && $id) {
        $db->prepare("UPDATE reviews SET hidden=0 WHERE id=?")->execute([$id]);
        setFlash('success', 'Bewertung wieder sichtbar.');
    }
    header('Location: /admin/reviews.php'); exit;
}

$filter = $_GET['filter'] ?? '';
$search = trim($_GET['q'] ?? '');
$minRating = (int)($_GET['min_rating'] ?? 0);

$where = ['1=1'];
$params = [];
if ($minRating) { $where[] = "r.rating <= ?"; $params[] = $minRating; }
if ($search) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR b.name LIKE ? OR r.comment LIKE ?)";
    $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
}
try {
    $db->query("SELECT hidden FROM reviews LIMIT 1");
    if ($filter === 'hidden') { $where[] = "r.hidden = 1"; }
    else { $where[] = "(r.hidden IS NULL OR r.hidden = 0)"; }
} catch(Exception $e) {}

$whereStr = implode(' AND ', $where);

$reviews = $db->prepare("
    SELECT r.*, u.first_name, u.last_name, u.email, b.name as biz_name, b.slug as biz_slug
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN businesses b ON r.business_id = b.id
    WHERE $whereStr
    ORDER BY r.created_at DESC LIMIT 200
");
$reviews->execute($params);
$reviews = $reviews->fetchAll();

$avgRating = $db->query("SELECT COALESCE(AVG(rating),0) FROM reviews")->fetchColumn();
$totalReviews = $db->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
$distribution = $db->query("SELECT rating, COUNT(*) as cnt FROM reviews GROUP BY rating ORDER BY rating DESC")->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/../includes/admin-nav.php';
?>

<div class="admin-page-header">
    <div>
        <h1>Bewertungen verwalten</h1>
        <p class="page-subtitle"><?= $totalReviews ?> Bewertungen · Ø <?= number_format($avgRating,1) ?> Sterne</p>
    </div>
</div>

<!-- Rating Distribution -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-body">
        <div style="display:flex;gap:40px;align-items:center;flex-wrap:wrap;">
            <div style="text-align:center;">
                <div style="font-size:3rem;font-weight:800;color:var(--gray-900);line-height:1;"><?= number_format($avgRating,1) ?></div>
                <div style="color:#f59e0b;font-size:1.2rem;margin:6px 0;">
                    <?php for($i=1;$i<=5;$i++): ?><i class="fas fa-star" style="color:<?= $i<=round($avgRating)?'#f59e0b':'#e5e0f0' ?>;"></i><?php endfor; ?>
                </div>
                <div style="font-size:0.82rem;color:#9080b0;"><?= $totalReviews ?> Bewertungen</div>
            </div>
            <div style="flex:1;max-width:400px;">
                <?php for ($r=5;$r>=1;$r--): $cnt=$distribution[$r]??0; $pct=$totalReviews>0?round($cnt/$totalReviews*100):0; ?>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;font-size:0.85rem;">
                    <span style="min-width:16px;text-align:right;color:var(--gray-700);font-weight:600;"><?= $r ?></span>
                    <i class="fas fa-star" style="color:#f59e0b;font-size:0.7rem;"></i>
                    <div style="flex:1;height:10px;background:#f0ebff;border-radius:5px;overflow:hidden;">
                        <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--primary-dark),#c06090);border-radius:5px;transition:width 0.5s;"></div>
                    </div>
                    <span style="min-width:32px;color:#9080b0;font-size:0.8rem;"><?= $cnt ?></span>
                    <span style="min-width:36px;color:#9080b0;font-size:0.75rem;"><?= $pct ?>%</span>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="admin-card" style="margin-bottom:16px;">
    <div class="admin-card-body" style="padding:14px 20px;">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div>
                <label class="admin-label">Suche</label>
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Name, Business, Kommentar..." class="admin-input" style="width:250px;">
            </div>
            <div>
                <label class="admin-label">Max. Sterne (Negativ-Filter)</label>
                <select name="min_rating" class="admin-input" style="width:160px;">
                    <option value="">Alle Bewertungen</option>
                    <option value="1" <?= $minRating==1?'selected':'' ?>>⭐ Nur 1 Stern</option>
                    <option value="2" <?= $minRating==2?'selected':'' ?>>⭐⭐ Bis 2 Sterne</option>
                    <option value="3" <?= $minRating==3?'selected':'' ?>>⭐⭐⭐ Bis 3 Sterne</option>
                </select>
            </div>
            <div>
                <label class="admin-label">Sichtbarkeit</label>
                <select name="filter" class="admin-input" style="width:160px;">
                    <option value="">Sichtbare</option>
                    <option value="hidden" <?= $filter==='hidden'?'selected':'' ?>>Versteckte</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filtern</button>
            <a href="/admin/reviews.php" class="btn btn-ghost btn-sm">Reset</a>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Benutzer</th><th>Unternehmen</th><th>Bewertung</th><th>Kommentar</th><th>Datum</th><th>Aktionen</th></tr></thead>
            <tbody>
            <?php foreach ($reviews as $rev): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:30px;height:30px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;color:var(--primary-dark);flex-shrink:0;">
                            <?= strtoupper(substr($rev['first_name'],0,1).substr($rev['last_name'],0,1)) ?>
                        </div>
                        <div>
                            <div style="font-size:0.875rem;font-weight:500;"><?= e($rev['first_name']) ?> <?= e($rev['last_name']) ?></div>
                            <div style="font-size:0.72rem;color:#9080b0;"><?= e($rev['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <a href="/business.php?slug=<?= e($rev['biz_slug']) ?>" target="_blank" style="font-size:0.875rem;color:var(--primary-dark);"><?= e($rev['biz_name']) ?></a>
                </td>
                <td>
                    <div style="display:flex;gap:2px;">
                    <?php for($i=1;$i<=5;$i++): ?>
                    <i class="fas fa-star" style="font-size:0.75rem;color:<?= $i<=$rev['rating']?'#f59e0b':'#e5e0f0' ?>"></i>
                    <?php endfor; ?>
                    </div>
                    <div style="font-size:0.72rem;color:#9080b0;margin-top:2px;"><?= $rev['rating'] ?>/5</div>
                </td>
                <td style="max-width:250px;">
                    <?php if ($rev['comment']): ?>
                    <span style="font-size:0.85rem;color:var(--gray-700);"><?= e(mb_substr($rev['comment'], 0, 80)) ?><?= mb_strlen($rev['comment'])>80?'...':'' ?></span>
                    <?php else: ?>
                    <span style="color:#9080b0;font-size:0.82rem;font-style:italic;">Kein Kommentar</span>
                    <?php endif; ?>
                    <?php if ($rev['reply']): ?>
                    <div style="margin-top:4px;font-size:0.75rem;color:var(--success);"><i class="fas fa-reply"></i> Antwort vorhanden</div>
                    <?php endif; ?>
                </td>
                <td style="font-size:0.78rem;color:#9080b0;"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></td>
                <td>
                    <form method="POST" style="display:flex;gap:4px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                        <?php $hidden = $rev['hidden'] ?? 0; ?>
                        <?php if ($hidden): ?>
                        <button name="action" value="show" class="btn btn-sm btn-ghost" title="Wieder anzeigen" style="color:var(--success);"><i class="fas fa-eye"></i></button>
                        <?php else: ?>
                        <button name="action" value="hide" class="btn btn-sm btn-ghost" title="Verstecken" style="color:#9080b0;"><i class="fas fa-eye-slash"></i></button>
                        <?php endif; ?>
                        <button name="action" value="delete" class="btn btn-sm btn-ghost" title="Löschen" style="color:var(--danger);" onclick="return confirm('Bewertung wirklich löschen?')"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($reviews)): ?>
            <tr><td colspan="6" style="text-align:center;color:#9080b0;padding:40px;">Keine Bewertungen gefunden</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
