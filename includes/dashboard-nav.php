<?php
// Business Dashboard sidebar - SalesHub style
$dashPage = $dashPage ?? '';
$_dbUser  = currentUser();

// Get business for sidebar
$_dbBiz = null;
if (isset($biz)) {
    $_dbBiz = $biz;
} else {
    $_db2 = getDB();
    $_s = $_db2->prepare("SELECT b.*,sp.name as plan_name FROM businesses b LEFT JOIN subscription_plans sp ON b.subscription_plan_id=sp.id WHERE b.user_id=? LIMIT 1");
    $_s->execute([$_dbUser['id']]);
    $_dbBiz = $_s->fetch();
}

$_pendingBookings = 0;
if ($_dbBiz) {
    $_db3 = getDB();
    $_pendingBookings = $_db3->prepare("SELECT COUNT(*) FROM bookings WHERE business_id=? AND status='pending'")->execute([$_dbBiz['id']]) ? $_db3->prepare("SELECT COUNT(*) FROM bookings WHERE business_id=? AND status='pending'") : null;
    $_pbStmt = getDB()->prepare("SELECT COUNT(*) FROM bookings WHERE business_id=? AND status='pending'");
    $_pbStmt->execute([$_dbBiz['id'] ?? 0]);
    $_pendingBookings = $_pbStmt->fetchColumn();
}
?>
<style>
/* Dashboard Layout - SalesHub Style */
.db-layout { display:flex; min-height:calc(100vh - 72px); background:#f8f5ff; }

.db-sidebar {
    width: 240px; flex-shrink:0;
    background: white;
    display:flex; flex-direction:column;
    position:sticky; top:72px;
    height:calc(100vh - 72px);
    overflow-y:auto;
    padding:20px 0 0;
    box-shadow: 4px 0 20px rgba(0,0,0,0.04);
}
.db-sidebar-brand {
    display:flex; align-items:center; gap:10px;
    padding:0 18px 18px;
    border-bottom:1px solid #f0ebff;
    margin-bottom:6px;
}
.db-sidebar-brand .db-biz-avatar {
    width:38px; height:38px; border-radius:10px;
    background:linear-gradient(135deg,var(--primary-dark),#c06090);
    display:flex; align-items:center; justify-content:center;
    color:white; font-weight:700; font-size:1rem; flex-shrink:0;
}
.db-sidebar-brand .db-biz-name { font-size:0.875rem; font-weight:700; color:var(--gray-900); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.db-sidebar-brand .db-biz-plan { font-size:0.7rem; color:#9080b0; }

.db-nav-section { padding:12px 18px 4px; font-size:0.68rem; font-weight:700; color:#b0a0c0; text-transform:uppercase; letter-spacing:0.12em; }

.db-nav a {
    display:flex; align-items:center; gap:10px;
    padding:9px 12px; margin:1px 8px;
    border-radius:10px;
    color:#7060a0; text-decoration:none;
    font-size:0.875rem; font-weight:500;
    transition:all 0.15s;
    position:relative;
}
.db-nav a:hover { background:#f5f0ff; color:var(--primary-dark); }
.db-nav a.active {
    background:linear-gradient(135deg,var(--primary-dark),#c06090);
    color:white; font-weight:600;
    box-shadow:0 4px 12px rgba(180,80,120,0.25);
}
.db-nav .nav-icon { width:18px; text-align:center; font-size:0.85rem; flex-shrink:0; }
.db-nav .db-badge { margin-left:auto; background:var(--danger); color:white; font-size:0.68rem; font-weight:700; padding:2px 7px; border-radius:20px; }
.db-nav a.active .db-badge { background:rgba(255,255,255,0.3); }

/* View Profile button */
.db-sidebar-bottom { margin:auto 10px 12px; }
.db-sidebar-bottom a {
    display:flex; align-items:center; justify-content:center; gap:8px;
    padding:10px; border-radius:12px;
    background:#f5f0ff; color:var(--primary-dark);
    text-decoration:none; font-size:0.82rem; font-weight:600;
    transition:background 0.15s;
}
.db-sidebar-bottom a:hover { background:#ebe5ff; }

/* Content Area */
.db-content { flex:1; padding:28px 32px; min-width:0; }
.db-page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.db-page-header h1 { font-size:1.5rem; font-weight:700; color:var(--gray-900); margin:0 0 4px; }
.db-subtitle { font-size:0.85rem; color:#9080b0; margin:0; }

/* DB Cards */
.db-card { background:white; border-radius:16px; box-shadow:0 2px 12px rgba(100,60,140,0.06); overflow:hidden; margin-bottom:20px; }
.db-card-header { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid #f5f0ff; }
.db-card-header h3 { margin:0; font-size:0.95rem; font-weight:700; }
.db-card-body { padding:20px; }

/* DB KPI */
.db-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:24px; }
.db-kpi {
    background:white; border-radius:14px; padding:18px 20px;
    box-shadow:0 2px 10px rgba(100,60,140,0.06);
    position:relative; overflow:hidden;
    transition:transform 0.2s;
}
.db-kpi:hover { transform:translateY(-2px); }
.db-kpi .kpi-label { font-size:0.75rem; color:#9080b0; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:8px; }
.db-kpi .kpi-value { font-size:1.7rem; font-weight:800; color:var(--gray-900); line-height:1; }
.db-kpi .kpi-sub { font-size:0.78rem; color:#9080b0; margin-top:6px; }
.db-kpi .kpi-icon { position:absolute; right:16px; top:50%; transform:translateY(-50%); width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; }

/* Tables */
.db-table { width:100%; border-collapse:collapse; font-size:0.875rem; }
.db-table th { background:#faf8ff; padding:10px 14px; text-align:left; font-size:0.72rem; font-weight:700; color:#9080b0; text-transform:uppercase; letter-spacing:0.06em; border-bottom:1px solid #f0ebff; }
.db-table td { padding:12px 14px; border-bottom:1px solid #faf8ff; vertical-align:middle; }
.db-table tbody tr:last-child td { border-bottom:none; }
.db-table tbody tr:hover { background:#fdf9ff; }
</style>

<div class="db-layout">
<aside class="db-sidebar">
    <div class="db-sidebar-brand">
        <div class="db-biz-avatar"><?= strtoupper(substr($_dbBiz['name'] ?? 'B', 0, 1)) ?></div>
        <div style="min-width:0;">
            <div class="db-biz-name"><?= e($_dbBiz['name'] ?? 'Mein Business') ?></div>
            <div class="db-biz-plan"><i class="fas fa-crown" style="color:var(--warning);font-size:0.6rem;"></i> <?= e($_dbBiz['plan_name'] ?? 'Kein Plan') ?></div>
        </div>
    </div>

    <nav class="db-nav">
        <div class="db-nav-section">Hauptmenü</div>
        <a href="/dashboard/index.php" class="<?= $dashPage==='overview'?'active':'' ?>">
            <i class="fas fa-chart-line nav-icon"></i> Übersicht
        </a>
        <a href="/dashboard/index.php?tab=bookings" class="<?= $dashPage==='bookings'?'active':'' ?>">
            <i class="fas fa-calendar nav-icon"></i> Buchungen
            <?php if ($_pendingBookings): ?><span class="db-badge"><?= $_pendingBookings ?></span><?php endif; ?>
        </a>
        <a href="/dashboard/index.php?tab=services" class="<?= $dashPage==='services'?'active':'' ?>">
            <i class="fas fa-list nav-icon"></i> Services
        </a>
        <a href="/dashboard/index.php?tab=reviews" class="<?= $dashPage==='reviews'?'active':'' ?>">
            <i class="fas fa-star nav-icon"></i> Bewertungen
        </a>

        <div class="db-nav-section">Business</div>
        <a href="/dashboard/settings.php" class="<?= $dashPage==='settings'?'active':'' ?>">
            <i class="fas fa-cog nav-icon"></i> Einstellungen
        </a>
        <a href="/dashboard/subscription.php" class="<?= $dashPage==='subscription'?'active':'' ?>">
            <i class="fas fa-crown nav-icon"></i> Abo & Plan
        </a>

        <div class="db-nav-section">Links</div>
        <a href="/" target="_blank"><i class="fas fa-globe nav-icon"></i> Website</a>
        <a href="/logout.php"><i class="fas fa-sign-out-alt nav-icon"></i> Abmelden</a>
    </nav>

    <div class="db-sidebar-bottom">
        <?php if ($_dbBiz && $_dbBiz['slug']): ?>
        <a href="/business.php?slug=<?= e($_dbBiz['slug']) ?>" target="_blank">
            <i class="fas fa-external-link-alt"></i> Profil ansehen
        </a>
        <?php endif; ?>
    </div>
</aside>
<div class="db-content">
