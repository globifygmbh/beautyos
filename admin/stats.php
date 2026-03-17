<?php
$pageTitle = 'Admin – Statistiken';
$adminPage = 'stats';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// ── Booking Stats ──────────────────────────────────
$bookingsByDay = $db->query("
    SELECT DAYOFWEEK(booking_date) as dow, COUNT(*) as cnt
    FROM bookings GROUP BY DAYOFWEEK(booking_date) ORDER BY dow
")->fetchAll(PDO::FETCH_KEY_PAIR);

$bookingsByHour = $db->query("
    SELECT HOUR(start_time) as h, COUNT(*) as cnt
    FROM bookings GROUP BY HOUR(start_time) ORDER BY h
")->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Revenue ──────────────────────────────────────
$revenueByPlan = $db->query("
    SELECT sp.name, COUNT(*) as subscribers, COALESCE(SUM(sp.price_monthly),0) as mrr
    FROM businesses b JOIN subscription_plans sp ON b.subscription_plan_id = sp.id
    WHERE b.status='active'
    GROUP BY sp.id, sp.name ORDER BY mrr DESC
")->fetchAll();

$revenueMonthly = $db->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as m, COALESCE(SUM(total_price),0) as rev, COUNT(*) as cnt
    FROM bookings WHERE status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY m ORDER BY m
")->fetchAll();

// ── Users ───────────────────────────────────────
$userGrowth = $db->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as m, COUNT(*) as cnt
    FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY m ORDER BY m
")->fetchAll();

$usersByRole = $db->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll();

// ── Businesses ─────────────────────────────────
$bizByCity = $db->query("
    SELECT city, COUNT(*) as cnt FROM businesses
    WHERE status='active' AND city IS NOT NULL AND city != ''
    GROUP BY city ORDER BY cnt DESC LIMIT 10
")->fetchAll();

$bizByCategory = $db->query("
    SELECT c.name, COUNT(DISTINCT bc.business_id) as cnt
    FROM categories c LEFT JOIN business_categories bc ON c.id = bc.category_id
    GROUP BY c.id, c.name ORDER BY cnt DESC
")->fetchAll();

// ── Analytics ─────────────────────────────────
$topSearches = $db->query("
    SELECT query, COUNT(*) as cnt
    FROM search_logs WHERE query IS NOT NULL AND query != ''
    GROUP BY query ORDER BY cnt DESC LIMIT 10
")->fetchAll();

$topCategories = $db->query("
    SELECT category, COUNT(*) as cnt
    FROM search_logs WHERE category IS NOT NULL AND category != ''
    GROUP BY category ORDER BY cnt DESC LIMIT 8
")->fetchAll();

$topBusinessViews = $db->query("
    SELECT b.name, b.slug, COUNT(*) as views
    FROM analytics_events e JOIN businesses b ON e.entity_id = b.id
    WHERE e.event_type = 'business_view'
    GROUP BY b.id, b.name, b.slug ORDER BY views DESC LIMIT 10
")->fetchAll();

$recentSearches = $db->query("
    SELECT * FROM search_logs ORDER BY created_at DESC LIMIT 20
")->fetchAll();

// ── Reviews ────────────────────────────────────
$reviewsByRating = $db->query("SELECT rating, COUNT(*) as cnt FROM reviews GROUP BY rating ORDER BY rating DESC")->fetchAll(PDO::FETCH_KEY_PAIR);
$avgRating = $db->query("SELECT COALESCE(AVG(rating),0) FROM reviews")->fetchColumn();

// Prepare chart data
$dowLabels = ['Mo','Di','Mi','Do','Fr','Sa','So'];
$dowData = [];
for ($i = 2; $i <= 8; $i++) {
    $dowData[] = (int)($bookingsByDay[$i % 8 === 0 ? 1 : $i] ?? 0);
}

$hourLabels = [];
$hourData = [];
for ($h = 0; $h <= 23; $h++) {
    $hourLabels[] = $h . ':00';
    $hourData[] = (int)($bookingsByHour[$h] ?? 0);
}

$months12 = [];
$revenueData12 = [];
$bookingData12 = [];
$userGrowthData = [];
$growthMap = array_column($userGrowth, 'cnt', 'm');
$revenueMap = [];
foreach ($revenueMonthly as $r) { $revenueMap[$r['m']] = ['rev'=>$r['rev'],'cnt'=>$r['cnt']]; }
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $months12[] = date('M', strtotime($m.'-01'));
    $revenueData12[] = round($revenueMap[$m]['rev'] ?? 0, 2);
    $bookingData12[] = (int)($revenueMap[$m]['cnt'] ?? 0);
    $userGrowthData[] = (int)($growthMap[$m] ?? 0);
}

require_once __DIR__ . '/../includes/admin-nav.php';
?>

<div class="admin-page-header">
    <div>
        <h1>Statistiken & Analytics</h1>
        <p class="page-subtitle">Detaillierte Einblicke in deine Plattform</p>
    </div>
    <div style="font-size:0.82rem;color:#9080b0;background:white;padding:8px 14px;border-radius:10px;box-shadow:0 2px 8px rgba(100,60,140,0.06);">
        <i class="fas fa-clock"></i> Stand: <?= date('d.m.Y H:i') ?>
    </div>
</div>

<!-- Revenue + Bookings 12 months -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-header">
        <h3><i class="fas fa-chart-area" style="color:var(--primary-dark);margin-right:8px;"></i> Umsatz & Buchungen (12 Monate)</h3>
    </div>
    <div class="admin-card-body">
        <canvas id="mainChart" height="80"></canvas>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <!-- Bookings by Weekday -->
    <div class="admin-card">
        <div class="admin-card-header"><h3><i class="fas fa-calendar-week" style="color:#6366f1;margin-right:8px;"></i> Buchungen nach Wochentag</h3></div>
        <div class="admin-card-body">
            <canvas id="dowChart" height="160"></canvas>
        </div>
    </div>

    <!-- Bookings by Hour -->
    <div class="admin-card">
        <div class="admin-card-header"><h3><i class="fas fa-clock" style="color:#f59e0b;margin-right:8px;"></i> Stoßzeiten (nach Uhrzeit)</h3></div>
        <div class="admin-card-body">
            <canvas id="hourChart" height="160"></canvas>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:20px;">
    <!-- User Growth -->
    <div class="admin-card">
        <div class="admin-card-header"><h3><i class="fas fa-user-plus" style="color:#10b981;margin-right:8px;"></i> Benutzer-Wachstum</h3></div>
        <div class="admin-card-body">
            <canvas id="userChart" height="180"></canvas>
        </div>
    </div>

    <!-- Users by Role -->
    <div class="admin-card">
        <div class="admin-card-header"><h3><i class="fas fa-users" style="color:#8b5cf6;margin-right:8px;"></i> Benutzer nach Rolle</h3></div>
        <div class="admin-card-body">
            <canvas id="roleChart" height="160"></canvas>
            <div style="margin-top:12px;">
            <?php foreach ($usersByRole as $ur): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #faf8ff;font-size:0.85rem;">
                <span style="color:var(--gray-600);"><?= ucfirst(e($ur['role'])) ?></span>
                <strong><?= $ur['cnt'] ?></strong>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Reviews by Rating -->
    <div class="admin-card">
        <div class="admin-card-header"><h3><i class="fas fa-star" style="color:#f59e0b;margin-right:8px;"></i> Bewertungsverteilung</h3></div>
        <div class="admin-card-body">
            <div style="text-align:center;margin-bottom:16px;">
                <div style="font-size:2.5rem;font-weight:800;color:var(--gray-900);"><?= number_format($avgRating,1) ?></div>
                <div style="color:#f59e0b;font-size:1.1rem;margin:4px 0;">
                    <?php for($i=1;$i<=5;$i++): ?><i class="fas fa-star<?= $i <= round($avgRating) ? '' : '-half-alt' ?>"></i><?php endfor; ?>
                </div>
                <div style="font-size:0.8rem;color:#9080b0;">Ø Bewertung</div>
            </div>
            <?php for ($r = 5; $r >= 1; $r--): $cnt = $reviewsByRating[$r] ?? 0; $total = max(1, array_sum($reviewsByRating)); ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:0.82rem;">
                <span style="min-width:16px;text-align:right;color:var(--gray-600);"><?= $r ?></span>
                <i class="fas fa-star" style="color:#f59e0b;font-size:0.7rem;"></i>
                <div style="flex:1;height:8px;background:#f0ebff;border-radius:4px;overflow:hidden;">
                    <div style="height:100%;width:<?= round($cnt/$total*100) ?>%;background:linear-gradient(90deg,var(--primary-dark),#c06090);border-radius:4px;"></div>
                </div>
                <span style="min-width:28px;color:var(--gray-500);"><?= $cnt ?></span>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <!-- Businesses by City -->
    <div class="admin-card">
        <div class="admin-card-header"><h3><i class="fas fa-map-marker-alt" style="color:#ef4444;margin-right:8px;"></i> Unternehmen nach Stadt</h3></div>
        <div class="admin-card-body">
            <canvas id="cityChart" height="200"></canvas>
        </div>
    </div>

    <!-- By Category -->
    <div class="admin-card">
        <div class="admin-card-header"><h3><i class="fas fa-tags" style="color:#d4a574;margin-right:8px;"></i> Unternehmen nach Kategorie</h3></div>
        <div class="admin-card-body">
            <canvas id="catChart" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Revenue by Plan -->
<div class="admin-card" style="margin-bottom:20px;">
    <div class="admin-card-header"><h3><i class="fas fa-crown" style="color:#f59e0b;margin-right:8px;"></i> Umsatz nach Paket</h3></div>
    <div class="admin-card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Paket</th><th>Abonnenten</th><th>MRR (monatl. Umsatz)</th><th>Jahres-Umsatz</th></tr></thead>
            <tbody>
            <?php if (empty($revenueByPlan)): ?>
            <tr><td colspan="4" style="text-align:center;color:#9080b0;padding:30px;">Noch keine aktiven Abos</td></tr>
            <?php endif; ?>
            <?php foreach ($revenueByPlan as $rp): ?>
            <tr>
                <td><strong><?= e($rp['name']) ?></strong></td>
                <td><?= $rp['subscribers'] ?></td>
                <td><strong style="color:var(--success);">€<?= number_format($rp['mrr'],2,',','.') ?></strong></td>
                <td style="color:#9080b0;">€<?= number_format($rp['mrr']*12,0,',','.') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Search Analytics -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <div class="admin-card">
        <div class="admin-card-header"><h3><i class="fas fa-search" style="color:#6366f1;margin-right:8px;"></i> Top Suchanfragen</h3></div>
        <div class="admin-card-body" style="padding:0;">
            <?php if (empty($topSearches)): ?>
            <p style="padding:30px;text-align:center;color:#9080b0;font-size:0.85rem;">Noch keine Suchdaten</p>
            <?php endif; ?>
            <?php foreach ($topSearches as $i => $s): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:11px 20px;border-bottom:1px solid #faf8ff;">
                <span style="width:22px;height:22px;background:<?= $i===0?'linear-gradient(135deg,var(--primary-dark),#c06090)':($i===1?'#e8e0f8':($i===2?'#f0ebff':'#faf8ff')) ?>;color:<?= $i<3?($i===0?'white':'var(--primary-dark)'):'#9080b0' ?>;border-radius:50%;font-size:0.7rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><?= $i+1 ?></span>
                <span style="font-size:0.875rem;flex:1;"><?= e($s['query']) ?></span>
                <strong style="font-size:0.82rem;"><?= $s['cnt'] ?></strong>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header"><h3><i class="fas fa-eye" style="color:#10b981;margin-right:8px;"></i> Meist gesehene Unternehmen</h3></div>
        <div class="admin-card-body" style="padding:0;">
            <?php if (empty($topBusinessViews)): ?>
            <p style="padding:30px;text-align:center;color:#9080b0;font-size:0.85rem;">Noch keine Tracking-Daten</p>
            <?php endif; ?>
            <?php foreach ($topBusinessViews as $i => $bv): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:11px 20px;border-bottom:1px solid #faf8ff;">
                <span style="width:22px;height:22px;background:<?= $i===0?'linear-gradient(135deg,#10b981,#059669)':'#f0fdf4' ?>;color:<?= $i===0?'white':'#065f46' ?>;border-radius:50%;font-size:0.7rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><?= $i+1 ?></span>
                <a href="/business.php?slug=<?= e($bv['slug']) ?>" style="font-size:0.875rem;flex:1;color:var(--gray-900);text-decoration:none;"><?= e($bv['name']) ?></a>
                <strong style="font-size:0.82rem;"><?= $bv['views'] ?> <span style="font-weight:400;color:#9080b0;">Views</span></strong>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Recent Searches -->
<?php if (!empty($recentSearches)): ?>
<div class="admin-card">
    <div class="admin-card-header"><h3><i class="fas fa-history" style="color:#9080b0;margin-right:8px;"></i> Letzte Suchanfragen</h3></div>
    <div class="admin-card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Suchanfrage</th><th>Kategorie</th><th>Standort</th><th>Ergebnisse</th><th>Datum</th></tr></thead>
            <tbody>
            <?php foreach ($recentSearches as $s): ?>
            <tr>
                <td><?= e($s['query'] ?: '–') ?></td>
                <td><?= e($s['category'] ?: '–') ?></td>
                <td><?= e($s['location'] ?: '–') ?></td>
                <td><?= $s['results_count'] ?></td>
                <td style="font-size:0.8rem;color:#9080b0;"><?= date('d.m.Y H:i', strtotime($s['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = 'Inter, sans-serif';
Chart.defaults.color = '#9080b0';

const primaryGrad = (ctx) => {
    const g = ctx.chart.ctx.createLinearGradient(0,0,0,300);
    g.addColorStop(0, 'rgba(180,80,120,0.2)');
    g.addColorStop(1, 'rgba(180,80,120,0)');
    return g;
};

// Main 12-month Chart
new Chart(document.getElementById('mainChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($months12) ?>,
        datasets: [
            { label: 'Umsatz €', data: <?= json_encode($revenueData12) ?>, borderColor:'#b45078', backgroundColor: function(ctx){return primaryGrad(ctx)}, tension:0.4, fill:true, pointRadius:4, yAxisID:'y' },
            { label: 'Buchungen', data: <?= json_encode($bookingData12) ?>, borderColor:'#6366f1', backgroundColor:'rgba(99,102,241,0.05)', tension:0.4, fill:true, pointRadius:4, yAxisID:'y1' }
        ]
    },
    options: { responsive:true, interaction:{mode:'index',intersect:false}, scales:{y:{beginAtZero:true,position:'left'},y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false}}} }
});

// Day of Week
new Chart(document.getElementById('dowChart'), {
    type: 'bar',
    data: { labels: <?= json_encode($dowLabels) ?>, datasets:[{ data: <?= json_encode($dowData) ?>, backgroundColor:'rgba(99,102,241,0.2)', borderColor:'#6366f1', borderWidth:2, borderRadius:6 }] },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
});

// Hour Chart
new Chart(document.getElementById('hourChart'), {
    type: 'line',
    data: { labels: <?= json_encode($hourLabels) ?>, datasets:[{ data: <?= json_encode($hourData) ?>, borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,0.1)', tension:0.4, fill:true, pointRadius:0 }] },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
});

// User Growth
new Chart(document.getElementById('userChart'), {
    type: 'bar',
    data: { labels: <?= json_encode($months12) ?>, datasets:[{ data: <?= json_encode($userGrowthData) ?>, backgroundColor:'rgba(16,185,129,0.2)', borderColor:'#10b981', borderWidth:2, borderRadius:6 }] },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
});

// Role Doughnut
new Chart(document.getElementById('roleChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($usersByRole, 'role')) ?>,
        datasets: [{ data: <?= json_encode(array_column($usersByRole, 'cnt')) ?>, backgroundColor:['#b8a9c9','#e8a0bf','#ef4444'], borderWidth:0 }]
    },
    options: { responsive:true, cutout:'65%', plugins:{legend:{position:'bottom',labels:{boxWidth:10,padding:8,font:{size:11}}}} }
});

// City Bar
new Chart(document.getElementById('cityChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($bizByCity, 'city')) ?>,
        datasets: [{ data: <?= json_encode(array_column($bizByCity, 'cnt')) ?>, backgroundColor:'rgba(180,80,120,0.15)', borderColor:'var(--primary-dark)', borderWidth:2, borderRadius:6 }]
    },
    options: { indexAxis:'y', responsive:true, plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true,ticks:{stepSize:1}}} }
});

// Category Chart
new Chart(document.getElementById('catChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($bizByCategory, 'name')) ?>,
        datasets: [{ data: <?= json_encode(array_column($bizByCategory, 'cnt')) ?>, backgroundColor:['#e8a0bf','#d4a574','#b8a9c9','#a8d5c5','#f0c080','#c8b0e8','#f0a0b0','#b0c8f0'], borderWidth:0 }]
    },
    options: { responsive:true, cutout:'55%', plugins:{legend:{position:'bottom',labels:{boxWidth:10,padding:6,font:{size:11}}}} }
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
