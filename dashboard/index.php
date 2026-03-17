<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireBusiness();

$db = getDB();
$user = currentUser();

// Get business
$stmt = $db->prepare("SELECT b.*, sp.name as plan_name FROM businesses b LEFT JOIN subscription_plans sp ON b.subscription_plan_id = sp.id WHERE b.user_id = ? LIMIT 1");
$stmt->execute([$user['id']]);
$biz = $stmt->fetch();

if (!$biz) {
    header('Location: /dashboard/setup.php');
    exit;
}

// Stats
$bookingsCount = $db->prepare("SELECT COUNT(*) FROM bookings WHERE business_id = ? AND booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$bookingsCount->execute([$biz['id']]);
$monthlyBookings = $bookingsCount->fetchColumn();

$reviewsCount = $db->prepare("SELECT COUNT(*) FROM reviews WHERE business_id = ?");
$reviewsCount->execute([$biz['id']]);
$totalReviews = $reviewsCount->fetchColumn();

$avgRating = $db->prepare("SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE business_id = ?");
$avgRating->execute([$biz['id']]);
$rating = $avgRating->fetchColumn();

$revenue = $db->prepare("SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE business_id = ? AND status = 'completed' AND booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$revenue->execute([$biz['id']]);
$monthlyRevenue = $revenue->fetchColumn();

// Upcoming bookings
$upcoming = $db->prepare("
    SELECT bk.*, u.first_name, u.last_name, s.name as service_name
    FROM bookings bk
    JOIN users u ON bk.user_id = u.id
    JOIN services s ON bk.service_id = s.id
    WHERE bk.business_id = ? AND bk.booking_date >= CURDATE() AND bk.status IN ('pending','confirmed')
    ORDER BY bk.booking_date, bk.start_time
    LIMIT 10
");
$upcoming->execute([$biz['id']]);
$upcoming = $upcoming->fetchAll();

$activeTab = $_GET['tab'] ?? 'overview';
?>

<div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="dashboard-sidebar">
        <div style="padding: 0 24px; margin-bottom: 24px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <?php if ($biz['logo']): ?>
                    <img src="<?= e($biz['logo']) ?>" style="width:40px;height:40px;border-radius:var(--radius-sm);object-fit:cover;">
                <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:var(--radius-sm);background:var(--primary-light);display:flex;align-items:center;justify-content:center;"><i class="fas fa-store" style="color:var(--primary-dark);"></i></div>
                <?php endif; ?>
                <div>
                    <strong style="font-size:0.9rem;"><?= e($biz['name']) ?></strong><br>
                    <span class="badge badge-info" style="font-size:0.7rem;"><?= e($biz['plan_name'] ?? 'Kein Plan') ?></span>
                </div>
            </div>
        </div>
        <ul class="dashboard-nav">
            <li><a href="?tab=overview" class="<?= $activeTab === 'overview' ? 'active' : '' ?>"><i class="fas fa-chart-line" style="width:20px;"></i> Übersicht</a></li>
            <li><a href="?tab=bookings" class="<?= $activeTab === 'bookings' ? 'active' : '' ?>"><i class="fas fa-calendar" style="width:20px;"></i> Buchungen</a></li>
            <li><a href="?tab=services" class="<?= $activeTab === 'services' ? 'active' : '' ?>"><i class="fas fa-list" style="width:20px;"></i> Services</a></li>
            <li><a href="?tab=reviews" class="<?= $activeTab === 'reviews' ? 'active' : '' ?>"><i class="fas fa-star" style="width:20px;"></i> Bewertungen</a></li>
            <li><a href="/dashboard/settings.php"><i class="fas fa-cog" style="width:20px;"></i> Einstellungen</a></li>
            <li><a href="/dashboard/subscription.php"><i class="fas fa-crown" style="width:20px;"></i> Abo-Verwaltung</a></li>
            <li><a href="/business.php?slug=<?= e($biz['slug']) ?>" target="_blank"><i class="fas fa-external-link-alt" style="width:20px;"></i> Profil ansehen</a></li>
        </ul>
    </aside>

    <!-- Content -->
    <div class="dashboard-content">
        <?php if ($activeTab === 'overview'): ?>
        <h2 style="margin-bottom: 24px;">Übersicht</h2>
        <div class="stat-cards">
            <div class="stat-card">
                <span class="stat-label">Buchungen (30 Tage)</span>
                <div class="stat-value"><?= $monthlyBookings ?></div>
            </div>
            <div class="stat-card">
                <span class="stat-label">Umsatz (30 Tage)</span>
                <div class="stat-value"><?= number_format($monthlyRevenue, 2, ',', '.') ?>&euro;</div>
            </div>
            <div class="stat-card">
                <span class="stat-label">Bewertungen</span>
                <div class="stat-value"><?= $totalReviews ?></div>
            </div>
            <div class="stat-card">
                <span class="stat-label">Durchschnitt</span>
                <div class="stat-value"><i class="fas fa-star" style="color:var(--warning);font-size:1rem;"></i> <?= number_format($rating, 1) ?></div>
            </div>
        </div>

        <h3 style="margin-bottom: 16px;">Nächste Termine</h3>
        <?php if (empty($upcoming)): ?>
            <div class="card" style="padding: 32px; text-align: center;">
                <p style="color: var(--gray-500);">Keine anstehenden Termine.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kunde</th>
                        <th>Service</th>
                        <th>Datum</th>
                        <th>Uhrzeit</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming as $bk): ?>
                    <tr>
                        <td><?= e($bk['first_name']) ?> <?= e($bk['last_name']) ?></td>
                        <td><?= e($bk['service_name']) ?></td>
                        <td><?= date('d.m.Y', strtotime($bk['booking_date'])) ?></td>
                        <td><?= date('H:i', strtotime($bk['start_time'])) ?></td>
                        <td>
                            <span class="badge <?= $bk['status'] === 'confirmed' ? 'badge-success' : 'badge-pending' ?>">
                                <?= $bk['status'] === 'confirmed' ? 'Bestätigt' : 'Ausstehend' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($bk['status'] === 'pending'): ?>
                                <a href="/dashboard/booking-action.php?id=<?= $bk['id'] ?>&action=confirm" class="btn btn-sm btn-primary">Bestätigen</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php elseif ($activeTab === 'bookings'): ?>
        <h2 style="margin-bottom: 24px;">Alle Buchungen</h2>
        <?php
        $allBookings = $db->prepare("
            SELECT bk.*, u.first_name, u.last_name, s.name as service_name
            FROM bookings bk
            JOIN users u ON bk.user_id = u.id
            JOIN services s ON bk.service_id = s.id
            WHERE bk.business_id = ?
            ORDER BY bk.booking_date DESC, bk.start_time DESC
            LIMIT 50
        ");
        $allBookings->execute([$biz['id']]);
        $allBookings = $allBookings->fetchAll();
        ?>
        <table class="data-table">
            <thead>
                <tr><th>Kunde</th><th>Service</th><th>Datum</th><th>Uhrzeit</th><th>Preis</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($allBookings as $bk):
                    $statusMap = ['pending'=>['Ausstehend','badge-pending'],'confirmed'=>['Bestätigt','badge-success'],'cancelled'=>['Storniert','badge-danger'],'completed'=>['Abgeschlossen','badge-info'],'no_show'=>['Nicht erschienen','badge-warning']];
                    $s = $statusMap[$bk['status']] ?? ['?','badge-pending'];
                ?>
                <tr>
                    <td><?= e($bk['first_name']) ?> <?= e($bk['last_name']) ?></td>
                    <td><?= e($bk['service_name']) ?></td>
                    <td><?= date('d.m.Y', strtotime($bk['booking_date'])) ?></td>
                    <td><?= date('H:i', strtotime($bk['start_time'])) ?></td>
                    <td><?= number_format($bk['total_price'], 2, ',', '.') ?>&euro;</td>
                    <td><span class="badge <?= $s[1] ?>"><?= $s[0] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif ($activeTab === 'services'): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h2>Services</h2>
            <a href="/dashboard/service-edit.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Neuer Service</a>
        </div>
        <?php
        $svcs = $db->prepare("SELECT * FROM services WHERE business_id = ? ORDER BY sort_order, name");
        $svcs->execute([$biz['id']]);
        $svcs = $svcs->fetchAll();
        ?>
        <table class="data-table">
            <thead><tr><th>Name</th><th>Dauer</th><th>Preis</th><th>Status</th><th>Aktionen</th></tr></thead>
            <tbody>
                <?php foreach ($svcs as $svc): ?>
                <tr>
                    <td><strong><?= e($svc['name']) ?></strong></td>
                    <td><?= $svc['duration_minutes'] ?> Min.</td>
                    <td><?= number_format($svc['price'], 2, ',', '.') ?>&euro;</td>
                    <td><span class="badge <?= $svc['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $svc['is_active'] ? 'Aktiv' : 'Inaktiv' ?></span></td>
                    <td><a href="/dashboard/service-edit.php?id=<?= $svc['id'] ?>" class="btn btn-ghost btn-sm"><i class="fas fa-edit"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif ($activeTab === 'reviews'): ?>
        <h2 style="margin-bottom: 24px;">Bewertungen</h2>
        <?php
        $revs = $db->prepare("SELECT r.*, u.first_name, u.last_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.business_id = ? ORDER BY r.created_at DESC");
        $revs->execute([$biz['id']]);
        $revs = $revs->fetchAll();
        ?>
        <?php foreach ($revs as $rev): ?>
        <div class="card" style="padding: 20px; margin-bottom: 16px;">
            <div class="review-header">
                <div class="review-avatar"><?= strtoupper(mb_substr($rev['first_name'],0,1)) ?></div>
                <div class="review-meta">
                    <strong><?= e($rev['first_name']) ?> <?= e(mb_substr($rev['last_name'],0,1)) ?>.</strong>
                    <br><span>
                        <?php for ($i=1;$i<=5;$i++): ?><i class="fas fa-star" style="color:<?= $i<=$rev['rating']?'var(--warning)':'var(--gray-300)' ?>;font-size:0.75rem;"></i><?php endfor; ?>
                        &middot; <?= date('d.m.Y', strtotime($rev['created_at'])) ?>
                    </span>
                </div>
            </div>
            <?php if ($rev['comment']): ?><p class="review-text mt-1"><?= nl2br(e($rev['comment'])) ?></p><?php endif; ?>
            <?php if ($rev['reply']): ?>
                <div style="margin-top:12px;padding:12px;background:var(--gray-50);border-radius:var(--radius-sm);font-size:0.85rem;">
                    <strong style="color:var(--primary-dark);">Deine Antwort:</strong><br><?= nl2br(e($rev['reply'])) ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/dashboard-footer.php'; ?>
