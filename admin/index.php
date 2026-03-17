<?php
$pageTitle = 'Admin – Übersicht';
$adminPage = 'overview';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// KPIs
$totalUsers        = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$usersLastMonth    = $db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$usersMonthBefore  = $db->query("SELECT COUNT(*) FROM users WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

$totalBusinesses   = $db->query("SELECT COUNT(*) FROM businesses")->fetchColumn();
$activeBusinesses  = $db->query("SELECT COUNT(*) FROM businesses WHERE status = 'active'")->fetchColumn();
$pendingBusinesses = $db->query("SELECT COUNT(*) FROM businesses WHERE status = 'pending'")->fetchColumn();
$bizLastMonth      = $db->query("SELECT COUNT(*) FROM businesses WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

$totalBookings     = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$bookingsLastMonth = $db->query("SELECT COUNT(*) FROM bookings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$bookingsMonthBefore = $db->query("SELECT COUNT(*) FROM bookings WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

$totalRevenue      = $db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE status='completed'")->fetchColumn();
$revenueLastMonth  = $db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$revenueMonthBefore= $db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE status='completed' AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

$totalReviews      = $db->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
$avgRating         = $db->query("SELECT COALESCE(AVG(rating),0) FROM reviews")->fetchColumn();

// Stripe MRR (paid subscriptions)
$mrr = $db->query("SELECT COALESCE(SUM(sp.price_monthly),0) FROM businesses b JOIN subscription_plans sp ON b.subscription_plan_id = sp.id WHERE b.status='active' AND (b.subscription_expires_at IS NULL OR b.subscription_expires_at > NOW())")->fetchColumn();

// Chart data: bookings per day (last 30 days)
$bookingsChart = $db->query("
    SELECT DATE(created_at) as d, COUNT(*) as cnt
    FROM bookings
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY d
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Chart data: revenue per month (last 6 months)
$revenueChart = $db->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as m, COALESCE(SUM(total_price),0) as rev
    FROM bookings
    WHERE status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY m
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Chart data: new users per month (last 6 months)
$usersChart = $db->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as m, COUNT(*) as cnt
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY m
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Top cities
$topCities = $db->query("
    SELECT city, COUNT(*) as cnt
    FROM businesses
    WHERE status='active' AND city IS NOT NULL AND city != ''
    GROUP BY city ORDER BY cnt DESC LIMIT 8
")->fetchAll();

// Plan distribution
$planDistribution = $db->query("
    SELECT sp.name, COUNT(*) as cnt
    FROM businesses b JOIN subscription_plans sp ON b.subscription_plan_id = sp.id
    WHERE b.status='active'
    GROUP BY sp.id, sp.name ORDER BY cnt DESC
")->fetchAll();

// Recent activity
$recentBiz = $db->query("
    SELECT b.*, u.email as owner_email, sp.name as plan_name
    FROM businesses b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN subscription_plans sp ON b.subscription_plan_id = sp.id
    ORDER BY b.created_at DESC LIMIT 6
")->fetchAll();

$recentBookings = $db->query("
    SELECT bk.*, u.first_name, u.last_name, b.name as biz_name
    FROM bookings bk JOIN users u ON bk.user_id = u.id JOIN businesses b ON bk.business_id = b.id
    ORDER BY bk.created_at DESC LIMIT 5
")->fetchAll();

// Fill missing days for chart
$chartDays = [];
$chartBookings = [];
for ($i = 29; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $chartDays[] = date('d.m', strtotime($day));
    $chartBookings[] = (int)($bookingsChart[$day] ?? 0);
}

// Fill missing months
$chartMonths = [];
$chartRevenue = [];
$chartUsers = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $chartMonths[] = date('M Y', strtotime($month . '-01'));
    $chartRevenue[] = round((float)($revenueChart[$month] ?? 0), 2);
    $chartUsers[]   = (int)($usersChart[$month] ?? 0);
}

// helpers
function pct($now, $prev) {
    if ($prev == 0) return $now > 0 ? 100 : 0;
    return round(($now - $prev) / $prev * 100, 1);
}

require_once __DIR__ . '/../includes/admin-nav.php';
?>

<?php if ($pendingBusinesses > 0): ?>
<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:var(--radius-md);padding:12px 18px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
    <i class="fas fa-exclamation-triangle" style="color:#856404;"></i>
    <span><strong><?= $pendingBusinesses ?> Unternehmen</strong> warten auf Freigabe.</span>
    <a href="/admin/businesses.php?filter=pending" class="btn btn-sm btn-warning" style="margin-left:auto;">Jetzt prüfen</a>
</div>
<?php endif; ?>

<div class="admin-page-header">
    <div>
        <h1>Dashboard</h1>
        <p style="color:var(--gray-500);margin:4px 0 0;font-size:0.9rem;">Willkommen zurück! Hier ist eine Übersicht deiner Plattform.</p>
    </div>
    <span style="font-size:0.85rem;color:var(--gray-400);"><i class="fas fa-clock"></i> <?= date('d.m.Y H:i') ?></span>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <?php
    $kpis = [
        ['label' => 'Gesamt Benutzer', 'value' => number_format($totalUsers,0,',','.'), 'change' => pct($usersLastMonth,$usersMonthBefore), 'icon' => 'fa-users', 'sub' => '+' . $usersLastMonth . ' diesen Monat'],
        ['label' => 'Aktive Unternehmen', 'value' => number_format($activeBusinesses,0,',','.'), 'change' => pct($bizLastMonth,0), 'icon' => 'fa-store', 'sub' => $totalBusinesses . ' gesamt'],
        ['label' => 'Buchungen (30 Tage)', 'value' => number_format($bookingsLastMonth,0,',','.'), 'change' => pct($bookingsLastMonth,$bookingsMonthBefore), 'icon' => 'fa-calendar-check', 'sub' => $totalBookings . ' gesamt'],
        ['label' => 'Umsatz (30 Tage)', 'value' => '€' . number_format($revenueLastMonth,0,',','.'), 'change' => pct($revenueLastMonth,$revenueMonthBefore), 'icon' => 'fa-euro-sign', 'sub' => '€' . number_format($totalRevenue,0,',','.') . ' gesamt'],
        ['label' => 'Monatl. Abo-Umsatz', 'value' => '€' . number_format($mrr,0,',','.'), 'change' => 0, 'icon' => 'fa-crown', 'sub' => 'MRR aus aktiven Abos'],
        ['label' => 'Ø Bewertung', 'value' => number_format($avgRating,1,',','.') . ' ★', 'change' => 0, 'icon' => 'fa-star', 'sub' => $totalReviews . ' Bewertungen gesamt'],
    ];
    foreach ($kpis as $kpi):
        $chg = $kpi['change'];
        $dir = $chg > 0 ? 'up' : ($chg < 0 ? 'down' : '');
    ?>
    <div class="kpi-card">
        <div class="kpi-label"><?= $kpi['label'] ?></div>
        <div class="kpi-value"><?= $kpi['value'] ?></div>
        <?php if ($chg != 0): ?>
        <div class="kpi-change <?= $dir ?>">
            <i class="fas fa-arrow-<?= $dir == 'up' ? 'up' : 'down' ?>"></i> <?= abs($chg) ?>% vs. Vormonat
        </div>
        <?php else: ?>
        <div class="kpi-change" style="color:var(--gray-400);"><?= e($kpi['sub']) ?></div>
        <?php endif; ?>
        <i class="fas <?= $kpi['icon'] ?> kpi-icon"></i>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts Row -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;">
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-calendar" style="color:var(--primary-dark);margin-right:8px;"></i> Buchungen (letzte 30 Tage)</h3>
        </div>
        <div class="admin-card-body">
            <canvas id="bookingsChart" height="100"></canvas>
        </div>
    </div>
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-crown" style="color:var(--warning);margin-right:8px;"></i> Plan-Verteilung</h3>
        </div>
        <div class="admin-card-body">
            <canvas id="planChart" height="180"></canvas>
            <div style="margin-top:12px;">
            <?php foreach ($planDistribution as $pd): ?>
            <div style="display:flex;justify-content:space-between;font-size:0.85rem;padding:4px 0;border-bottom:1px solid var(--gray-100);">
                <span><?= e($pd['name']) ?></span>
                <strong><?= $pd['cnt'] ?> Unternehmen</strong>
            </div>
            <?php endforeach; ?>
            <?php if (empty($planDistribution)): ?>
            <p style="color:var(--gray-400);font-size:0.85rem;text-align:center;padding:20px 0;">Noch keine aktiven Abos</p>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-euro-sign" style="color:var(--success);margin-right:8px;"></i> Umsatz (6 Monate)</h3>
        </div>
        <div class="admin-card-body">
            <canvas id="revenueChart" height="130"></canvas>
        </div>
    </div>
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-user-plus" style="color:var(--secondary);margin-right:8px;"></i> Neue Benutzer (6 Monate)</h3>
        </div>
        <div class="admin-card-body">
            <canvas id="usersChart" height="130"></canvas>
        </div>
    </div>
</div>

<!-- Top Cities + Recent Activity -->
<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;margin-bottom:20px;">
    <div class="admin-card">
        <div class="admin-card-header"><h3><i class="fas fa-map-marker-alt" style="color:var(--danger);margin-right:8px;"></i> Top Städte</h3></div>
        <div class="admin-card-body" style="padding:0;">
            <?php if (empty($topCities)): ?>
            <p style="padding:20px;color:var(--gray-400);font-size:0.85rem;text-align:center;">Noch keine Daten</p>
            <?php endif; ?>
            <?php foreach ($topCities as $tc): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 20px;border-bottom:1px solid var(--gray-100);">
                <span style="font-size:0.9rem;"><?= e($tc['city']) ?></span>
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="height:6px;background:var(--primary-light);border-radius:3px;width:<?= min(80, $tc['cnt'] * 20) ?>px;"></div>
                    <strong style="font-size:0.85rem;min-width:24px;text-align:right;"><?= $tc['cnt'] ?></strong>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3><i class="fas fa-store" style="color:var(--primary-dark);margin-right:8px;"></i> Neueste Unternehmen</h3>
            <a href="/admin/businesses.php" class="btn btn-sm btn-ghost">Alle ansehen</a>
        </div>
        <div class="admin-card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>Unternehmen</th><th>Inhaber</th><th>Stadt</th><th>Plan</th><th>Status</th><th>Datum</th></tr></thead>
                <tbody>
                <?php foreach ($recentBiz as $biz):
                    $statusMap = ['pending'=>['Ausstehend','#f59e0b'],'active'=>['Aktiv','#10b981'],'suspended'=>['Gesperrt','#ef4444'],'closed'=>['Geschlossen','#6b7280']];
                    $s = $statusMap[$biz['status']] ?? ['?','#6b7280'];
                ?>
                <tr>
                    <td>
                        <strong><?= e($biz['name']) ?></strong>
                        <?php if ($biz['is_verified']): ?> <i class="fas fa-check-circle" style="color:var(--success);font-size:0.75rem;"></i><?php endif; ?>
                    </td>
                    <td style="font-size:0.82rem;color:var(--gray-500);"><?= e($biz['owner_email']) ?></td>
                    <td><?= e($biz['city'] ?? '-') ?></td>
                    <td><?= e($biz['plan_name'] ?? '-') ?></td>
                    <td><span style="background:<?= $s[1] ?>22;color:<?= $s[1] ?>;font-size:0.75rem;font-weight:600;padding:2px 8px;border-radius:10px;"><?= $s[0] ?></span></td>
                    <td style="font-size:0.82rem;color:var(--gray-500);"><?= date('d.m.Y', strtotime($biz['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentBiz)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--gray-400);padding:30px;">Noch keine Unternehmen</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Bookings -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3><i class="fas fa-calendar-check" style="color:var(--success);margin-right:8px;"></i> Letzte Buchungen</h3>
        <a href="/admin/bookings.php" class="btn btn-sm btn-ghost">Alle ansehen</a>
    </div>
    <div class="admin-card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Kunde</th><th>Unternehmen</th><th>Datum</th><th>Preis</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recentBookings as $bk):
                $statusMap = ['pending'=>['Ausstehend','#f59e0b'],'confirmed'=>['Bestätigt','#10b981'],'cancelled'=>['Storniert','#ef4444'],'completed'=>['Fertig','#6366f1'],'no_show'=>['No-Show','#f97316']];
                $s = $statusMap[$bk['status']] ?? ['?','#6b7280'];
            ?>
            <tr>
                <td><?= e($bk['first_name']) ?> <?= e($bk['last_name']) ?></td>
                <td><?= e($bk['biz_name']) ?></td>
                <td><?= date('d.m.Y', strtotime($bk['booking_date'])) ?></td>
                <td>€<?= number_format($bk['total_price'],2,',','.') ?></td>
                <td><span style="background:<?= $s[1] ?>22;color:<?= $s[1] ?>;font-size:0.75rem;font-weight:600;padding:2px 8px;border-radius:10px;"><?= $s[0] ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentBookings)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--gray-400);padding:30px;">Noch keine Buchungen</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div><!-- close admin-content + admin-layout -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = 'Inter, sans-serif';
Chart.defaults.color = '#6b7280';

// Bookings Chart
new Chart(document.getElementById('bookingsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartDays) ?>,
        datasets: [{
            label: 'Buchungen',
            data: <?= json_encode($chartBookings) ?>,
            backgroundColor: 'rgba(232,160,191,0.3)',
            borderColor: 'rgba(232,160,191,1)',
            borderWidth: 2,
            borderRadius: 4,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Revenue Chart
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartMonths) ?>,
        datasets: [{
            label: 'Umsatz €',
            data: <?= json_encode($chartRevenue) ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16,185,129,0.1)',
            tension: 0.4,
            fill: true,
            pointRadius: 4,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

// Users Chart
new Chart(document.getElementById('usersChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartMonths) ?>,
        datasets: [{
            label: 'Neue Benutzer',
            data: <?= json_encode($chartUsers) ?>,
            borderColor: '#d4a574',
            backgroundColor: 'rgba(212,165,116,0.1)',
            tension: 0.4,
            fill: true,
            pointRadius: 4,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Plan Chart
<?php $planColors = ['#e8a0bf','#d4a574','#b8a9c9','#10b981','#6366f1']; ?>
new Chart(document.getElementById('planChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($planDistribution, 'name')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($planDistribution, 'cnt')) ?>,
            backgroundColor: <?= json_encode(array_slice($planColors, 0, count($planDistribution))) ?>,
            borderWidth: 0,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 12 } } } },
        cutout: '65%'
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
