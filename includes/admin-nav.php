<?php
// Admin sidebar navigation include
$adminPage = $adminPage ?? 'overview';
$db = $db ?? getDB();
$_adminUser = currentUser();
$_pendingBusinesses = $db->query("SELECT COUNT(*) FROM businesses WHERE status='pending'")->fetchColumn();
?>
<style>
/* ============ ADMIN LAYOUT ============ */
.admin-layout {
    display: flex;
    min-height: calc(100vh - 72px);
    background: #f5f0ff;
}

/* ============ SIDEBAR ============ */
.admin-sidebar {
    width: 250px;
    flex-shrink: 0;
    background: #ffffff;
    display: flex;
    flex-direction: column;
    position: sticky;
    top: 72px;
    height: calc(100vh - 72px);
    overflow-y: auto;
    padding: 20px 0 0;
    box-shadow: 4px 0 20px rgba(0,0,0,0.04);
}
.admin-sidebar-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 20px 20px;
    border-bottom: 1px solid #f0ebff;
    margin-bottom: 8px;
}
.admin-sidebar-brand .brand-icon {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, var(--primary-dark), #c06090);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 0.9rem;
}
.admin-sidebar-brand .brand-name {
    font-size: 1rem; font-weight: 700;
    color: var(--gray-900);
}
.admin-sidebar-brand .brand-sub {
    font-size: 0.72rem; color: var(--gray-400);
}

.admin-nav-section {
    padding: 14px 20px 4px;
    font-size: 0.68rem;
    font-weight: 700;
    color: #b0a0c0;
    text-transform: uppercase;
    letter-spacing: 0.12em;
}
.admin-nav a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    margin: 1px 10px;
    border-radius: 10px;
    color: #7060a0;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.15s ease;
    position: relative;
}
.admin-nav a:hover {
    background: #f5f0ff;
    color: var(--primary-dark);
}
.admin-nav a.active {
    background: linear-gradient(135deg, var(--primary-dark), #c06090);
    color: #ffffff;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(180,80,120,0.3);
}
.admin-nav a.active .admin-badge { background: rgba(255,255,255,0.3); }
.admin-nav .nav-icon { width: 20px; height: 20px; text-align: center; font-size: 0.88rem; flex-shrink: 0; }
.admin-badge {
    margin-left: auto;
    background: var(--danger);
    color: white;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 20px;
    min-width: 18px;
    text-align: center;
    line-height: 1.4;
}

/* Upgrade Card at bottom */
.admin-sidebar-upgrade {
    margin: auto 12px 16px;
    background: linear-gradient(135deg, #2d1b4e, #4a2070);
    border-radius: 14px;
    padding: 16px;
    color: white;
    text-align: center;
}
.admin-sidebar-upgrade .upgrade-icon {
    width: 40px; height: 40px;
    background: rgba(255,255,255,0.15);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 10px;
    font-size: 1rem;
}
.admin-sidebar-upgrade h4 { font-size: 0.875rem; margin-bottom: 4px; }
.admin-sidebar-upgrade p { font-size: 0.75rem; color: rgba(255,255,255,0.7); margin-bottom: 12px; }
.admin-sidebar-upgrade a {
    display: block;
    background: linear-gradient(135deg, var(--primary-dark), #c06090);
    color: white;
    padding: 8px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: opacity 0.15s;
}
.admin-sidebar-upgrade a:hover { opacity: 0.9; }

/* ============ CONTENT ============ */
.admin-content {
    flex: 1;
    padding: 28px 32px;
    min-width: 0;
}

/* Page Header */
.admin-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.admin-page-header h1 {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--gray-900);
    margin: 0 0 4px;
}
.admin-page-header .page-subtitle {
    font-size: 0.85rem;
    color: #9080b0;
    margin: 0;
}

/* ============ KPI CARDS ============ */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 16px; margin-bottom: 24px; }
.kpi-card {
    background: white;
    border-radius: 16px;
    padding: 20px 22px;
    box-shadow: 0 2px 12px rgba(100,60,140,0.06);
    position: relative;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(100,60,140,0.1); }
.kpi-card .kpi-label {
    font-size: 0.78rem;
    color: #9080b0;
    font-weight: 600;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.kpi-card .kpi-value {
    font-size: 1.85rem;
    font-weight: 800;
    color: var(--gray-900);
    line-height: 1;
    margin-bottom: 8px;
}
.kpi-card .kpi-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 20px;
}
.kpi-card .kpi-badge.up   { background: #d1fae5; color: #065f46; }
.kpi-card .kpi-badge.down { background: #fee2e2; color: #991b1b; }
.kpi-card .kpi-badge.neutral { background: #f3f4f6; color: #6b7280; }
.kpi-card .kpi-sub { font-size: 0.78rem; color: #9080b0; margin-top: 6px; }
.kpi-card .kpi-icon-bg {
    position: absolute;
    right: 18px; top: 50%;
    transform: translateY(-50%);
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
}

/* ============ ADMIN CARDS ============ */
.admin-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(100,60,140,0.06);
    overflow: hidden;
    margin-bottom: 20px;
    transition: box-shadow 0.2s;
}
.admin-card:hover { box-shadow: 0 4px 20px rgba(100,60,140,0.1); }
.admin-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid #f5f0ff;
}
.admin-card-header h3 { margin: 0; font-size: 0.95rem; font-weight: 700; color: var(--gray-900); }
.admin-card-body { padding: 20px; }

/* ============ TABLE ============ */
.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table th {
    background: #faf8ff;
    padding: 10px 14px;
    text-align: left;
    font-size: 0.72rem;
    font-weight: 700;
    color: #9080b0;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    border-bottom: 1px solid #f0ebff;
    white-space: nowrap;
}
.data-table td { padding: 13px 14px; border-bottom: 1px solid #faf8ff; vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: #fdf9ff; }

/* ============ STATUS BADGES ============ */
.status-badge {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
}

/* ============ ALERT BANNER ============ */
.admin-alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 0.875rem;
}
.admin-alert.warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
.admin-alert.danger  { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
.admin-alert.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #065f46; }

/* Inputs in admin */
.admin-input {
    width: 100%;
    padding: 9px 14px;
    border: 1.5px solid #e8e0f0;
    border-radius: 10px;
    font-size: 0.9rem;
    font-family: inherit;
    transition: border-color 0.15s, box-shadow 0.15s;
    background: white;
}
.admin-input:focus {
    outline: none;
    border-color: var(--primary-dark);
    box-shadow: 0 0 0 3px rgba(180,80,120,0.12);
}
label.admin-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #6050a0;
    display: block;
    margin-bottom: 6px;
}
</style>

<div class="admin-layout">
<!-- SIDEBAR -->
<aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
        <div class="brand-icon"><i class="fas fa-shield-halved"></i></div>
        <div>
            <div class="brand-name">BeautyOS</div>
            <div class="brand-sub">Admin Panel</div>
        </div>
    </div>

    <nav class="admin-nav">
        <div class="admin-nav-section">Hauptmenü</div>
        <a href="/admin/index.php" class="<?= $adminPage==='overview' ? 'active' : '' ?>">
            <i class="fas fa-chart-line nav-icon"></i> Dashboard
        </a>
        <a href="/admin/stats.php" class="<?= $adminPage==='stats' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar nav-icon"></i> Statistiken
        </a>

        <div class="admin-nav-section">Verwaltung</div>
        <a href="/admin/businesses.php" class="<?= $adminPage==='businesses' ? 'active' : '' ?>">
            <i class="fas fa-store nav-icon"></i> Unternehmen
            <?php if ($_pendingBusinesses): ?><span class="admin-badge"><?= $_pendingBusinesses ?></span><?php endif; ?>
        </a>
        <a href="/admin/users.php" class="<?= $adminPage==='users' ? 'active' : '' ?>">
            <i class="fas fa-users nav-icon"></i> Benutzer
        </a>
        <a href="/admin/bookings.php" class="<?= $adminPage==='bookings' ? 'active' : '' ?>">
            <i class="fas fa-calendar nav-icon"></i> Buchungen
        </a>
        <a href="/admin/reviews.php" class="<?= $adminPage==='reviews' ? 'active' : '' ?>">
            <i class="fas fa-star nav-icon"></i> Bewertungen
        </a>

        <div class="admin-nav-section">Einstellungen</div>
        <a href="/admin/plans.php" class="<?= $adminPage==='plans' ? 'active' : '' ?>">
            <i class="fas fa-crown nav-icon"></i> Pakete & Preise
        </a>
        <a href="/admin/categories.php" class="<?= $adminPage==='categories' ? 'active' : '' ?>">
            <i class="fas fa-tags nav-icon"></i> Kategorien
        </a>
        <a href="/admin/payments.php" class="<?= $adminPage==='payments' ? 'active' : '' ?>">
            <i class="fab fa-stripe nav-icon"></i> Stripe
        </a>
        <a href="/admin/legal.php" class="<?= $adminPage==='legal' ? 'active' : '' ?>">
            <i class="fas fa-file-contract nav-icon"></i> Rechtliches
        </a>

        <div class="admin-nav-section">Links</div>
        <a href="/" target="_blank"><i class="fas fa-globe nav-icon"></i> Website</a>
        <a href="/logout.php"><i class="fas fa-sign-out-alt nav-icon"></i> Abmelden</a>
    </nav>

    <!-- User Card -->
    <div style="margin: auto 12px 0;padding:14px;background:#faf8ff;border-radius:12px;display:flex;align-items:center;gap:10px;margin-bottom:8px;">
        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary-dark),#c06090);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.8rem;flex-shrink:0;">
            <?= strtoupper(substr($_adminUser['first_name']??'A',0,1).substr($_adminUser['last_name']??'',0,1)) ?>
        </div>
        <div style="min-width:0;">
            <div style="font-size:0.8rem;font-weight:600;color:var(--gray-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e(($_adminUser['first_name']??'').' '.($_adminUser['last_name']??'')) ?></div>
            <div style="font-size:0.7rem;color:#9080b0;">Admin</div>
        </div>
    </div>
</aside>

<!-- CONTENT -->
<div class="admin-content">
