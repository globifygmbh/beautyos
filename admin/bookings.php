<?php
$pageTitle = 'Admin – Buchungen';
$adminPage = 'bookings';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';

$where  = ['1=1'];
$params = [];

if ($status) { $where[] = "bk.status = ?"; $params[] = $status; }
if ($search) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR b.name LIKE ? OR s.name LIKE ?)";
    $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
}
if ($dateFrom) { $where[] = "bk.booking_date >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = "bk.booking_date <= ?"; $params[] = $dateTo; }

$whereStr = implode(' AND ', $where);

$bookings = $db->prepare("
    SELECT bk.*, u.first_name, u.last_name, u.email,
           b.name as biz_name, b.city, s.name as service_name
    FROM bookings bk
    JOIN users u ON bk.user_id = u.id
    JOIN businesses b ON bk.business_id = b.id
    JOIN services s ON bk.service_id = s.id
    WHERE $whereStr
    ORDER BY bk.created_at DESC LIMIT 200
");
$bookings->execute($params);
$bookings = $bookings->fetchAll();

$counts = $db->query("SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalRevenue = $db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE status='completed'")->fetchColumn();

require_once __DIR__ . '/../includes/admin-nav.php';
?>

<div class="admin-page-header">
    <div>
        <h1>Buchungen</h1>
        <p class="page-subtitle"><?= array_sum($counts) ?> Buchungen gesamt · €<?= number_format($totalRevenue,2,',','.') ?> Umsatz</p>
    </div>
</div>

<!-- Status Filters -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
    <?php
    $statuses = [''=> 'Alle ('.array_sum($counts).')','pending'=>'Ausstehend ('.($counts['pending']??0).')','confirmed'=>'Bestätigt ('.($counts['confirmed']??0).')','completed'=>'Fertig ('.($counts['completed']??0).')','cancelled'=>'Storniert ('.($counts['cancelled']??0).')'];
    foreach ($statuses as $val => $label):
    ?>
    <a href="?status=<?= urlencode($val) ?>" class="btn btn-sm <?= $status===$val ? 'btn-primary' : 'btn-ghost' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<!-- Search + Date Filter -->
<div class="admin-card" style="margin-bottom:16px;">
    <div class="admin-card-body" style="padding:14px 20px;">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <div>
                <label class="admin-label">Suche</label>
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Kunde, Business, Service..." class="admin-input" style="width:220px;">
            </div>
            <div>
                <label class="admin-label">Von</label>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="admin-input" style="width:150px;">
            </div>
            <div>
                <label class="admin-label">Bis</label>
                <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="admin-input" style="width:150px;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filtern</button>
            <a href="/admin/bookings.php" class="btn btn-ghost btn-sm">Reset</a>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Kunde</th><th>Unternehmen</th><th>Service</th><th>Termin</th><th>Preis</th><th>Status</th><th>Erstellt</th></tr>
            </thead>
            <tbody>
            <?php foreach ($bookings as $bk):
                $statusMap = ['pending'=>['Ausstehend','#f59e0b'],'confirmed'=>['Bestätigt','#10b981'],'cancelled'=>['Storniert','#ef4444'],'completed'=>['Fertig','#6366f1'],'no_show'=>['No-Show','#f97316']];
                $s = $statusMap[$bk['status']] ?? ['?','#6b7280'];
            ?>
            <tr>
                <td style="font-size:0.8rem;color:#9080b0;">#<?= $bk['id'] ?></td>
                <td>
                    <div style="font-size:0.875rem;font-weight:500;"><?= e($bk['first_name']) ?> <?= e($bk['last_name']) ?></div>
                    <div style="font-size:0.75rem;color:#9080b0;"><?= e($bk['email']) ?></div>
                </td>
                <td>
                    <div style="font-size:0.875rem;"><?= e($bk['biz_name']) ?></div>
                    <div style="font-size:0.75rem;color:#9080b0;"><?= e($bk['city'] ?? '') ?></div>
                </td>
                <td style="font-size:0.875rem;"><?= e($bk['service_name']) ?></td>
                <td>
                    <div style="font-size:0.875rem;font-weight:500;"><?= date('d.m.Y', strtotime($bk['booking_date'])) ?></div>
                    <div style="font-size:0.75rem;color:#9080b0;"><?= substr($bk['start_time'],0,5) ?> – <?= substr($bk['end_time'],0,5) ?></div>
                </td>
                <td style="font-weight:700;">€<?= number_format($bk['total_price'],2,',','.') ?></td>
                <td><span style="background:<?= $s[1] ?>22;color:<?= $s[1] ?>;font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:20px;"><?= $s[0] ?></span></td>
                <td style="font-size:0.78rem;color:#9080b0;"><?= date('d.m.Y', strtotime($bk['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($bookings)): ?>
            <tr><td colspan="8" style="text-align:center;color:#9080b0;padding:40px;">Keine Buchungen gefunden</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
