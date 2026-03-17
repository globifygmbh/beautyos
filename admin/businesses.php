<?php
$pageTitle = 'Admin – Unternehmen';
$adminPage = 'businesses';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action   = $_POST['action'] ?? '';
    $targetId = (int)($_POST['target_id'] ?? 0);

    $actions = [
        'approve'  => ["UPDATE businesses SET status='active', is_verified=1 WHERE id=?",  'Unternehmen freigegeben!'],
        'suspend'  => ["UPDATE businesses SET status='suspended' WHERE id=?",               'Unternehmen gesperrt.'],
        'activate' => ["UPDATE businesses SET status='active' WHERE id=?",                  'Unternehmen aktiviert.'],
        'delete'   => ["DELETE FROM businesses WHERE id=?",                                 'Unternehmen gelöscht.'],
        'verify'   => ["UPDATE businesses SET is_verified=1 WHERE id=?",                    'Verifizierung gesetzt.'],
        'unverify' => ["UPDATE businesses SET is_verified=0 WHERE id=?",                    'Verifizierung entfernt.'],
    ];

    if (isset($actions[$action]) && $targetId) {
        $db->prepare($actions[$action][0])->execute([$targetId]);
        setFlash('success', $actions[$action][1]);

        // Send email notifications
        if (in_array($action, ['approve', 'suspend', 'activate'])) {
            try {
                require_once __DIR__ . '/../includes/Mailer.php';
                $bizRow = $db->prepare("SELECT b.*, u.email, u.first_name, u.last_name FROM businesses b JOIN users u ON b.user_id=u.id WHERE b.id=?");
                $bizRow->execute([$targetId]);
                $bizData = $bizRow->fetch();
                if ($bizData) {
                    $user = ['email' => $bizData['email'], 'first_name' => $bizData['first_name']];
                    if ($action === 'approve') {
                        Mailer::sendBusinessApproved($bizData, $user);
                    } elseif ($action === 'suspend') {
                        Mailer::sendBusinessRejected($bizData, $user, 'Dein Profil wurde vorübergehend gesperrt. Bitte kontaktiere uns für weitere Informationen.');
                    }
                    // Process queue inline (try to send immediately)
                    (new Mailer())->processQueue(5);
                }
            } catch (Throwable $e) { error_log('[Mail] ' . $e->getMessage()); }
        }
    }

    header('Location: /admin/businesses.php?' . http_build_query(array_filter(['filter' => $_POST['filter'] ?? ''])));
    exit;
}

$filter = $_GET['filter'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = ['1=1'];
$params = [];

if ($filter === 'pending')   { $where[] = "b.status='pending'"; }
if ($filter === 'active')    { $where[] = "b.status='active'"; }
if ($filter === 'suspended') { $where[] = "b.status='suspended'"; }
if ($search) {
    $where[] = "(b.name LIKE ? OR b.city LIKE ? OR u.email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

$whereStr = implode(' AND ', $where);

$allBiz = $db->prepare("
    SELECT b.*, u.email as owner_email, u.first_name, u.last_name,
           sp.name as plan_name, sp.price_monthly,
           COALESCE(avg_r.avg_rating,0) as avg_rating,
           COALESCE(avg_r.review_count,0) as review_count,
           COALESCE(bk_count.cnt,0) as booking_count
    FROM businesses b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN subscription_plans sp ON b.subscription_plan_id = sp.id
    LEFT JOIN (SELECT business_id, AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews GROUP BY business_id) avg_r ON b.id = avg_r.business_id
    LEFT JOIN (SELECT business_id, COUNT(*) as cnt FROM bookings GROUP BY business_id) bk_count ON b.id = bk_count.business_id
    WHERE $whereStr
    ORDER BY b.created_at DESC
    LIMIT 200
");
$allBiz->execute($params);
$allBiz = $allBiz->fetchAll();

$counts = $db->query("SELECT status, COUNT(*) as cnt FROM businesses GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalCount = array_sum($counts);

require_once __DIR__ . '/../includes/admin-nav.php';
?>

<div class="admin-page-header">
    <div>
        <h1>Unternehmen verwalten</h1>
        <p style="color:var(--gray-500);margin:4px 0 0;font-size:0.9rem;"><?= $totalCount ?> Unternehmen gesamt</p>
    </div>
</div>

<!-- Filters -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
    <?php
    $tabs = [''=>'Alle ('.($totalCount).')','pending'=>'Ausstehend ('.($counts['pending']??0).')','active'=>'Aktiv ('.($counts['active']??0).')','suspended'=>'Gesperrt ('.($counts['suspended']??0).')'];
    foreach ($tabs as $val => $label):
    ?>
    <a href="?filter=<?= urlencode($val) ?>" class="btn btn-sm <?= $filter === $val ? 'btn-primary' : 'btn-ghost' ?>"><?= $label ?></a>
    <?php endforeach; ?>
    <form method="GET" style="margin-left:auto;display:flex;gap:8px;">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Name, Stadt, E-Mail..." style="padding:6px 12px;border:1px solid var(--gray-300);border-radius:var(--radius-md);font-size:0.875rem;width:220px;">
        <button class="btn btn-sm btn-ghost"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="admin-card">
    <div class="admin-card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Unternehmen</th>
                    <th>Inhaber</th>
                    <th>Stadt</th>
                    <th>Plan</th>
                    <th>Bewertung</th>
                    <th>Buchungen</th>
                    <th>Abo läuft ab</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($allBiz as $biz):
                $statusMap = ['pending'=>['Ausstehend','#f59e0b'],'active'=>['Aktiv','#10b981'],'suspended'=>['Gesperrt','#ef4444'],'closed'=>['Geschlossen','#6b7280']];
                $s = $statusMap[$biz['status']] ?? ['?','#6b7280'];
            ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:var(--radius-md);background:var(--gray-100);display:flex;align-items:center;justify-content:center;font-size:0.75rem;color:var(--gray-500);flex-shrink:0;">
                            <?php if ($biz['logo']): ?>
                                <img src="<?= e($biz['logo']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">
                            <?php else: ?>
                                <i class="fas fa-store"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong><?= e($biz['name']) ?></strong>
                            <?php if ($biz['is_verified']): ?><i class="fas fa-check-circle" style="color:var(--success);font-size:0.75rem;"></i><?php endif; ?>
                            <?php if ($biz['slug']): ?>
                            <div style="font-size:0.75rem;color:var(--gray-400);">/<?= e($biz['slug']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <div><?= e($biz['first_name']) ?> <?= e($biz['last_name']) ?></div>
                    <div style="font-size:0.78rem;color:var(--gray-400);"><?= e($biz['owner_email']) ?></div>
                </td>
                <td><?= e($biz['city'] ?? '-') ?></td>
                <td>
                    <?php if ($biz['plan_name']): ?>
                    <span style="font-size:0.82rem;">
                        <i class="fas fa-crown" style="color:var(--warning);font-size:0.7rem;"></i>
                        <?= e($biz['plan_name']) ?>
                        <div style="font-size:0.75rem;color:var(--gray-400);">€<?= number_format($biz['price_monthly'],2,',','.') ?>/Monat</div>
                    </span>
                    <?php else: ?>
                    <span style="color:var(--gray-400);font-size:0.82rem;">Kein Plan</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($biz['review_count'] > 0): ?>
                    <div style="font-size:0.85rem;">
                        <i class="fas fa-star" style="color:var(--warning);font-size:0.75rem;"></i>
                        <?= number_format($biz['avg_rating'],1) ?>
                        <span style="color:var(--gray-400);">(<?= $biz['review_count'] ?>)</span>
                    </div>
                    <?php else: ?>
                    <span style="color:var(--gray-300);font-size:0.82rem;">–</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:0.85rem;"><?= $biz['booking_count'] ?></td>
                <td style="font-size:0.82rem;color:<?= $biz['subscription_expires_at'] && strtotime($biz['subscription_expires_at']) < strtotime('+7 days') ? 'var(--danger)' : 'var(--gray-500)' ?>;">
                    <?= $biz['subscription_expires_at'] ? date('d.m.Y', strtotime($biz['subscription_expires_at'])) : '–' ?>
                </td>
                <td>
                    <span style="background:<?= $s[1] ?>22;color:<?= $s[1] ?>;font-size:0.75rem;font-weight:600;padding:3px 10px;border-radius:10px;">
                        <?= $s[0] ?>
                    </span>
                </td>
                <td>
                    <form method="POST" style="display:flex;gap:4px;flex-wrap:wrap;">
                        <?= csrfField() ?>
                        <input type="hidden" name="target_id" value="<?= $biz['id'] ?>">
                        <input type="hidden" name="filter" value="<?= e($filter) ?>">
                        <?php if ($biz['status'] === 'pending'): ?>
                            <button name="action" value="approve" class="btn btn-sm btn-primary" title="Freigeben"><i class="fas fa-check"></i></button>
                        <?php elseif ($biz['status'] === 'active'): ?>
                            <button name="action" value="suspend" class="btn btn-sm" style="background:#fee2e2;color:var(--danger);border:none;" title="Sperren"><i class="fas fa-ban"></i></button>
                        <?php elseif ($biz['status'] === 'suspended'): ?>
                            <button name="action" value="activate" class="btn btn-sm btn-ghost" title="Aktivieren"><i class="fas fa-play"></i></button>
                        <?php endif; ?>
                        <?php if ($biz['is_verified']): ?>
                            <button name="action" value="unverify" class="btn btn-sm btn-ghost" title="Verifizierung entfernen" style="color:var(--gray-400);"><i class="fas fa-shield-halved"></i></button>
                        <?php else: ?>
                            <button name="action" value="verify" class="btn btn-sm btn-ghost" title="Verifizieren" style="color:var(--success);"><i class="fas fa-shield-halved"></i></button>
                        <?php endif; ?>
                        <a href="/business.php?slug=<?= e($biz['slug']) ?>" target="_blank" class="btn btn-sm btn-ghost" title="Profil ansehen"><i class="fas fa-external-link-alt"></i></a>
                        <button name="action" value="delete" class="btn btn-sm btn-ghost" style="color:var(--gray-300);" title="Löschen" onclick="return confirm('Wirklich löschen? Alle Daten gehen verloren!')"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($allBiz)): ?>
            <tr><td colspan="9" style="text-align:center;color:var(--gray-400);padding:40px;">Keine Unternehmen gefunden</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
