<?php
$pageTitle = 'Admin';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// Stats
$totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalBusinesses = $db->query("SELECT COUNT(*) FROM businesses")->fetchColumn();
$activeBusinesses = $db->query("SELECT COUNT(*) FROM businesses WHERE status = 'active'")->fetchColumn();
$totalBookings = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$monthlyBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$totalRevenue = $db->query("SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE status = 'completed'")->fetchColumn();
$pendingBusinesses = $db->query("SELECT COUNT(*) FROM businesses WHERE status = 'pending'")->fetchColumn();

$activeTab = $_GET['tab'] ?? 'overview';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    $targetId = (int)($_POST['target_id'] ?? 0);

    if ($action === 'approve_business' && $targetId) {
        $db->prepare("UPDATE businesses SET status = 'active', is_verified = 1 WHERE id = ?")->execute([$targetId]);
        setFlash('success', 'Business freigegeben!');
    } elseif ($action === 'suspend_business' && $targetId) {
        $db->prepare("UPDATE businesses SET status = 'suspended' WHERE id = ?")->execute([$targetId]);
        setFlash('success', 'Business gesperrt.');
    } elseif ($action === 'activate_business' && $targetId) {
        $db->prepare("UPDATE businesses SET status = 'active' WHERE id = ?")->execute([$targetId]);
        setFlash('success', 'Business aktiviert.');
    } elseif ($action === 'delete_user' && $targetId) {
        $db->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$targetId]);
        setFlash('success', 'Benutzer gelöscht.');
    }

    header('Location: /admin/index.php?tab=' . urlencode($activeTab));
    exit;
}
?>

<div class="dashboard-layout">
    <aside class="dashboard-sidebar">
        <div style="padding: 0 24px 24px;">
            <strong style="font-size: 0.9rem; color: var(--primary-dark);"><i class="fas fa-shield-halved"></i> Admin Panel</strong>
        </div>
        <ul class="dashboard-nav">
            <li><a href="?tab=overview" class="<?= $activeTab === 'overview' ? 'active' : '' ?>"><i class="fas fa-chart-line" style="width:20px;"></i> Übersicht</a></li>
            <li><a href="?tab=businesses" class="<?= $activeTab === 'businesses' ? 'active' : '' ?>"><i class="fas fa-store" style="width:20px;"></i> Businesses <?php if ($pendingBusinesses): ?><span class="badge badge-warning" style="margin-left:4px;"><?= $pendingBusinesses ?></span><?php endif; ?></a></li>
            <li><a href="?tab=users" class="<?= $activeTab === 'users' ? 'active' : '' ?>"><i class="fas fa-users" style="width:20px;"></i> Benutzer</a></li>
            <li><a href="?tab=bookings" class="<?= $activeTab === 'bookings' ? 'active' : '' ?>"><i class="fas fa-calendar" style="width:20px;"></i> Buchungen</a></li>
            <li><a href="?tab=reviews" class="<?= $activeTab === 'reviews' ? 'active' : '' ?>"><i class="fas fa-star" style="width:20px;"></i> Bewertungen</a></li>
        </ul>
    </aside>

    <div class="dashboard-content">
        <?php if ($activeTab === 'overview'): ?>
        <h2 style="margin-bottom: 24px;">Dashboard</h2>
        <div class="stat-cards">
            <div class="stat-card">
                <span class="stat-label">Benutzer</span>
                <div class="stat-value"><?= $totalUsers ?></div>
            </div>
            <div class="stat-card">
                <span class="stat-label">Businesses</span>
                <div class="stat-value"><?= $totalBusinesses ?></div>
                <span class="stat-change"><?= $activeBusinesses ?> aktiv</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Buchungen (30 Tage)</span>
                <div class="stat-value"><?= $monthlyBookings ?></div>
                <span class="stat-change"><?= $totalBookings ?> gesamt</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Umsatz (gesamt)</span>
                <div class="stat-value"><?= number_format($totalRevenue, 0, ',', '.') ?>&euro;</div>
            </div>
        </div>

        <?php if ($pendingBusinesses > 0): ?>
        <div class="flash flash-warning" style="margin-bottom: 24px;">
            <i class="fas fa-exclamation-triangle"></i> <?= $pendingBusinesses ?> Business(es) warten auf Freigabe.
            <a href="?tab=businesses&filter=pending" style="color: inherit; font-weight: 600; margin-left: 8px;">Jetzt prüfen &rarr;</a>
        </div>
        <?php endif; ?>

        <!-- Recent businesses -->
        <h3 style="margin-bottom: 16px;">Neueste Businesses</h3>
        <?php
        $recent = $db->query("SELECT b.*, u.email as owner_email, sp.name as plan_name FROM businesses b JOIN users u ON b.user_id = u.id LEFT JOIN subscription_plans sp ON b.subscription_plan_id = sp.id ORDER BY b.created_at DESC LIMIT 5")->fetchAll();
        ?>
        <table class="data-table">
            <thead><tr><th>Name</th><th>Inhaber</th><th>Stadt</th><th>Plan</th><th>Status</th><th>Erstellt</th></tr></thead>
            <tbody>
                <?php foreach ($recent as $biz):
                    $statusMap = ['pending'=>['Ausstehend','badge-pending'],'active'=>['Aktiv','badge-success'],'suspended'=>['Gesperrt','badge-danger'],'closed'=>['Geschlossen','badge-warning']];
                    $s = $statusMap[$biz['status']] ?? ['?','badge-pending'];
                ?>
                <tr>
                    <td><strong><?= e($biz['name']) ?></strong></td>
                    <td><?= e($biz['owner_email']) ?></td>
                    <td><?= e($biz['city'] ?? '-') ?></td>
                    <td><?= e($biz['plan_name'] ?? '-') ?></td>
                    <td><span class="badge <?= $s[1] ?>"><?= $s[0] ?></span></td>
                    <td><?= date('d.m.Y', strtotime($biz['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif ($activeTab === 'businesses'): ?>
        <h2 style="margin-bottom: 24px;">Businesses verwalten</h2>
        <?php
        $filter = $_GET['filter'] ?? '';
        $bWhere = $filter === 'pending' ? "WHERE b.status = 'pending'" : '';
        $allBiz = $db->query("
            SELECT b.*, u.email as owner_email, u.first_name, u.last_name, sp.name as plan_name,
                   COALESCE(avg_r.avg_rating, 0) as avg_rating
            FROM businesses b
            JOIN users u ON b.user_id = u.id
            LEFT JOIN subscription_plans sp ON b.subscription_plan_id = sp.id
            LEFT JOIN (SELECT business_id, AVG(rating) as avg_rating FROM reviews GROUP BY business_id) avg_r ON b.id = avg_r.business_id
            $bWhere
            ORDER BY b.created_at DESC
            LIMIT 100
        ")->fetchAll();
        ?>
        <div style="margin-bottom: 16px;">
            <a href="?tab=businesses" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-ghost' ?>">Alle</a>
            <a href="?tab=businesses&filter=pending" class="btn btn-sm <?= $filter === 'pending' ? 'btn-primary' : 'btn-ghost' ?>">Ausstehend (<?= $pendingBusinesses ?>)</a>
        </div>
        <table class="data-table">
            <thead><tr><th>Name</th><th>Inhaber</th><th>Stadt</th><th>Plan</th><th>Bewertung</th><th>Status</th><th>Aktionen</th></tr></thead>
            <tbody>
                <?php foreach ($allBiz as $biz):
                    $statusMap = ['pending'=>['Ausstehend','badge-pending'],'active'=>['Aktiv','badge-success'],'suspended'=>['Gesperrt','badge-danger'],'closed'=>['Geschlossen','badge-warning']];
                    $s = $statusMap[$biz['status']] ?? ['?','badge-pending'];
                ?>
                <tr>
                    <td>
                        <strong><?= e($biz['name']) ?></strong>
                        <?php if ($biz['is_verified']): ?><i class="fas fa-check-circle" style="color:var(--success);font-size:0.8rem;"></i><?php endif; ?>
                    </td>
                    <td><?= e($biz['first_name']) ?> <?= e($biz['last_name']) ?><br><span style="font-size:0.8rem;color:var(--gray-500);"><?= e($biz['owner_email']) ?></span></td>
                    <td><?= e($biz['city'] ?? '-') ?></td>
                    <td><?= e($biz['plan_name'] ?? '-') ?></td>
                    <td><i class="fas fa-star" style="color:var(--warning);font-size:0.8rem;"></i> <?= number_format($biz['avg_rating'], 1) ?></td>
                    <td><span class="badge <?= $s[1] ?>"><?= $s[0] ?></span></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="target_id" value="<?= $biz['id'] ?>">
                            <?php if ($biz['status'] === 'pending'): ?>
                                <button name="action" value="approve_business" class="btn btn-sm btn-primary">Freigeben</button>
                            <?php elseif ($biz['status'] === 'active'): ?>
                                <button name="action" value="suspend_business" class="btn btn-sm btn-ghost" style="color:var(--danger);">Sperren</button>
                            <?php elseif ($biz['status'] === 'suspended'): ?>
                                <button name="action" value="activate_business" class="btn btn-sm btn-ghost">Aktivieren</button>
                            <?php endif; ?>
                        </form>
                        <a href="/business.php?slug=<?= e($biz['slug']) ?>" target="_blank" class="btn btn-sm btn-ghost"><i class="fas fa-external-link-alt"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif ($activeTab === 'users'): ?>
        <h2 style="margin-bottom: 24px;">Benutzer verwalten</h2>
        <?php $allUsers = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 100")->fetchAll(); ?>
        <table class="data-table">
            <thead><tr><th>Name</th><th>E-Mail</th><th>Rolle</th><th>Registriert</th><th>Aktionen</th></tr></thead>
            <tbody>
                <?php foreach ($allUsers as $u):
                    $roleMap = ['user'=>['Kunde','badge-info'],'business'=>['Business','badge-pending'],'admin'=>['Admin','badge-danger']];
                    $r = $roleMap[$u['role']] ?? ['?','badge-info'];
                ?>
                <tr>
                    <td><?= e($u['first_name']) ?> <?= e($u['last_name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge <?= $r[1] ?>"><?= $r[0] ?></span></td>
                    <td><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if ($u['role'] !== 'admin'): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Benutzer wirklich löschen?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                            <button name="action" value="delete_user" class="btn btn-sm btn-ghost" style="color:var(--danger);"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif ($activeTab === 'bookings'): ?>
        <h2 style="margin-bottom: 24px;">Alle Buchungen</h2>
        <?php
        $allBookings = $db->query("
            SELECT bk.*, u.first_name, u.last_name, b.name as business_name, s.name as service_name
            FROM bookings bk
            JOIN users u ON bk.user_id = u.id
            JOIN businesses b ON bk.business_id = b.id
            JOIN services s ON bk.service_id = s.id
            ORDER BY bk.created_at DESC
            LIMIT 100
        ")->fetchAll();
        ?>
        <table class="data-table">
            <thead><tr><th>Kunde</th><th>Business</th><th>Service</th><th>Datum</th><th>Preis</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($allBookings as $bk):
                    $statusMap = ['pending'=>['Ausstehend','badge-pending'],'confirmed'=>['Bestätigt','badge-success'],'cancelled'=>['Storniert','badge-danger'],'completed'=>['Fertig','badge-info'],'no_show'=>['No-Show','badge-warning']];
                    $s = $statusMap[$bk['status']] ?? ['?','badge-pending'];
                ?>
                <tr>
                    <td><?= e($bk['first_name']) ?> <?= e($bk['last_name']) ?></td>
                    <td><?= e($bk['business_name']) ?></td>
                    <td><?= e($bk['service_name']) ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($bk['booking_date'] . ' ' . $bk['start_time'])) ?></td>
                    <td><?= number_format($bk['total_price'], 2, ',', '.') ?>&euro;</td>
                    <td><span class="badge <?= $s[1] ?>"><?= $s[0] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif ($activeTab === 'reviews'): ?>
        <h2 style="margin-bottom: 24px;">Alle Bewertungen</h2>
        <?php
        $allReviews = $db->query("
            SELECT r.*, u.first_name, u.last_name, b.name as business_name
            FROM reviews r
            JOIN users u ON r.user_id = u.id
            JOIN businesses b ON r.business_id = b.id
            ORDER BY r.created_at DESC
            LIMIT 100
        ")->fetchAll();
        ?>
        <table class="data-table">
            <thead><tr><th>Benutzer</th><th>Business</th><th>Bewertung</th><th>Kommentar</th><th>Datum</th></tr></thead>
            <tbody>
                <?php foreach ($allReviews as $rev): ?>
                <tr>
                    <td><?= e($rev['first_name']) ?> <?= e($rev['last_name']) ?></td>
                    <td><?= e($rev['business_name']) ?></td>
                    <td>
                        <?php for ($i=1;$i<=5;$i++): ?><i class="fas fa-star" style="color:<?= $i<=$rev['rating']?'var(--warning)':'var(--gray-300)' ?>;font-size:0.75rem;"></i><?php endfor; ?>
                    </td>
                    <td><?= e(mb_substr($rev['comment'] ?? '', 0, 60)) ?><?= mb_strlen($rev['comment'] ?? '') > 60 ? '...' : '' ?></td>
                    <td><?= date('d.m.Y', strtotime($rev['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
