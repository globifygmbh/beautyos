<?php
$pageTitle = 'Admin – Benutzer';
$adminPage = 'users';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action   = $_POST['action'] ?? '';
    $targetId = (int)($_POST['target_id'] ?? 0);

    if ($action === 'delete_user' && $targetId) {
        $db->prepare("DELETE FROM users WHERE id=? AND role != 'admin'")->execute([$targetId]);
        setFlash('success', 'Benutzer gelöscht.');
    } elseif ($action === 'make_admin' && $targetId) {
        $db->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$targetId]);
        setFlash('success', 'Benutzer zum Admin ernannt.');
    } elseif ($action === 'make_business' && $targetId) {
        $db->prepare("UPDATE users SET role='business' WHERE id=?")->execute([$targetId]);
        setFlash('success', 'Rolle auf Business geändert.');
    } elseif ($action === 'make_user' && $targetId) {
        $db->prepare("UPDATE users SET role='user' WHERE id=?")->execute([$targetId]);
        setFlash('success', 'Rolle auf Kunde geändert.');
    }

    header('Location: /admin/users.php'); exit;
}

$filter = $_GET['filter'] ?? '';
$search = trim($_GET['q'] ?? '');
$where  = ['1=1'];
$params = [];

if ($filter)  { $where[] = "u.role = ?"; $params[] = $filter; }
if ($search)  { $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }

$whereStr = implode(' AND ', $where);

$users = $db->prepare("
    SELECT u.*,
        (SELECT COUNT(*) FROM bookings WHERE user_id = u.id) as booking_count,
        (SELECT COUNT(*) FROM reviews WHERE user_id = u.id) as review_count,
        (SELECT COUNT(*) FROM businesses WHERE user_id = u.id) as business_count
    FROM users u
    WHERE $whereStr
    ORDER BY u.created_at DESC LIMIT 200
");
$users->execute($params);
$users = $users->fetchAll();

$roleCounts = $db->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/../includes/admin-nav.php';
?>

<div class="admin-page-header">
    <div>
        <h1>Benutzer verwalten</h1>
        <p style="color:var(--gray-500);margin:4px 0 0;font-size:0.9rem;"><?= array_sum($roleCounts) ?> Benutzer gesamt</p>
    </div>
</div>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
    <a href="?" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-ghost' ?>">Alle (<?= array_sum($roleCounts) ?>)</a>
    <a href="?filter=user" class="btn btn-sm <?= $filter==='user' ? 'btn-primary' : 'btn-ghost' ?>">Kunden (<?= $roleCounts['user']??0 ?>)</a>
    <a href="?filter=business" class="btn btn-sm <?= $filter==='business' ? 'btn-primary' : 'btn-ghost' ?>">Business (<?= $roleCounts['business']??0 ?>)</a>
    <a href="?filter=admin" class="btn btn-sm <?= $filter==='admin' ? 'btn-primary' : 'btn-ghost' ?>">Admins (<?= $roleCounts['admin']??0 ?>)</a>
    <form method="GET" style="margin-left:auto;display:flex;gap:8px;">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Name oder E-Mail..." style="padding:6px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.875rem;width:220px;">
        <button class="btn btn-sm btn-ghost"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="admin-card">
    <div class="admin-card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr><th>Benutzer</th><th>E-Mail</th><th>Rolle</th><th>Buchungen</th><th>Bewertungen</th><th>Registriert</th><th>Aktionen</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u):
                $roleMap = ['user'=>['Kunde','#6366f1'],'business'=>['Business','#d4a574'],'admin'=>['Admin','#ef4444']];
                $r = $roleMap[$u['role']] ?? ['?','#6b7280'];
            ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-weight:600;color:var(--primary-dark);font-size:0.85rem;flex-shrink:0;">
                            <?= strtoupper(substr($u['first_name'],0,1) . substr($u['last_name'],0,1)) ?>
                        </div>
                        <div>
                            <strong><?= e($u['first_name']) ?> <?= e($u['last_name']) ?></strong>
                            <?php if ($u['business_count'] > 0): ?>
                            <div style="font-size:0.75rem;color:var(--gray-400);"><?= $u['business_count'] ?> Unternehmen</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td style="font-size:0.875rem;"><?= e($u['email']) ?></td>
                <td><span style="background:<?= $r[1] ?>22;color:<?= $r[1] ?>;font-size:0.75rem;font-weight:600;padding:3px 10px;border-radius:10px;"><?= $r[0] ?></span></td>
                <td><?= $u['booking_count'] ?></td>
                <td><?= $u['review_count'] ?></td>
                <td style="font-size:0.82rem;color:var(--gray-500);"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <?php if ($u['role'] !== 'admin'): ?>
                    <form method="POST" style="display:flex;gap:4px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                        <select name="action" style="font-size:0.8rem;padding:4px 8px;border:1px solid var(--gray-300);border-radius:var(--radius-md);">
                            <option value="">Aktion...</option>
                            <?php if ($u['role'] !== 'admin'): ?><option value="make_admin">→ Admin</option><?php endif; ?>
                            <?php if ($u['role'] !== 'business'): ?><option value="make_business">→ Business</option><?php endif; ?>
                            <?php if ($u['role'] !== 'user'): ?><option value="make_user">→ Kunde</option><?php endif; ?>
                            <option value="delete_user">🗑 Löschen</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-ghost" onclick="return this.form.action.value === 'delete_user' ? confirm('Wirklich löschen?') : true">OK</button>
                    </form>
                    <?php else: ?>
                    <span style="font-size:0.8rem;color:var(--gray-400);">Geschützt</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--gray-400);padding:40px;">Keine Benutzer gefunden</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
