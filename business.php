<?php
require_once 'includes/header.php';

$db   = getDB();
$slug = trim($_GET['slug'] ?? '');

if (!$slug) { header('Location: /marketplace.php'); exit; }

$stmt = $db->prepare("
    SELECT b.*, sp.name as plan_name, sp.custom_design, sp.featured_badge,
           GROUP_CONCAT(DISTINCT c.name ORDER BY c.sort_order SEPARATOR ', ') as category_names
    FROM businesses b
    LEFT JOIN subscription_plans sp ON b.subscription_plan_id = sp.id
    LEFT JOIN business_categories bc ON b.id = bc.business_id
    LEFT JOIN categories c ON bc.category_id = c.id
    WHERE b.slug = ? AND b.status = 'active'
    GROUP BY b.id
");
$stmt->execute([$slug]);
$biz = $stmt->fetch();

if (!$biz) {
    http_response_code(404);
    echo '<div class="container" style="text-align:center;padding:100px 24px;"><div style="font-size:4rem;margin-bottom:24px;">😔</div><h2>Salon nicht gefunden</h2><p style="color:var(--gray-500);margin-bottom:24px;">Dieser Salon existiert nicht oder ist nicht mehr aktiv.</p><a href="/marketplace.php" class="btn btn-primary">Zum Marktplatz</a></div>';
    require_once 'includes/footer.php';
    exit;
}

$pageTitle = $biz['name'] . ' – BeautyOS';
$accentColor = $biz['accent_color'] ?? '#e8a0bf';

// Services
$services = $db->prepare("SELECT s.*, c.name as cat_name FROM services s LEFT JOIN categories c ON s.category_id=c.id WHERE s.business_id=? AND s.is_active=1 ORDER BY s.sort_order,s.name");
$services->execute([$biz['id']]);
$services = $services->fetchAll();

// Reviews
$reviews = $db->prepare("
    SELECT r.*, u.first_name, u.last_name
    FROM reviews r JOIN users u ON r.user_id=u.id
    WHERE r.business_id=?
    ORDER BY r.created_at DESC LIMIT 20
");
$reviews->execute([$biz['id']]);
$reviews = $reviews->fetchAll();

$ratingStmt = $db->prepare("SELECT AVG(rating) as avg, COUNT(*) as cnt FROM reviews WHERE business_id=?");
$ratingStmt->execute([$biz['id']]);
$ratingInfo = $ratingStmt->fetch();

$ratingDistStmt = $db->prepare("SELECT rating, COUNT(*) as cnt FROM reviews WHERE business_id=? GROUP BY rating ORDER BY rating DESC");
$ratingDistStmt->execute([$biz['id']]);
$ratingDist = $ratingDistStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Opening hours
$hours = $db->prepare("SELECT * FROM opening_hours WHERE business_id=? ORDER BY day_of_week");
$hours->execute([$biz['id']]);
$hours = $hours->fetchAll();

// Images
$images = $db->prepare("SELECT * FROM business_images WHERE business_id=? ORDER BY sort_order LIMIT 12");
$images->execute([$biz['id']]);
$images = $images->fetchAll();

// Favorites
$isFav = false;
if (isLoggedIn()) {
    $favStmt = $db->prepare("SELECT 1 FROM favorites WHERE user_id=? AND business_id=?");
    $favStmt->execute([$_SESSION['user_id'], $biz['id']]);
    $isFav = (bool)$favStmt->fetchColumn();
}

// Booking handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book']) && verifyCsrf()) {
    requireLogin();
    $serviceId = (int)$_POST['service_id'];
    $date      = $_POST['booking_date'] ?? '';
    $time      = $_POST['start_time'] ?? '';
    $notes     = trim($_POST['notes'] ?? '');

    $svcStmt = $db->prepare("SELECT * FROM services WHERE id=? AND business_id=?");
    $svcStmt->execute([$serviceId, $biz['id']]);
    $svc = $svcStmt->fetch();

    if ($svc && $date && $time) {
        $endTime = date('H:i', strtotime($time) + $svc['duration_minutes'] * 60);
        $db->prepare("INSERT INTO bookings (user_id,business_id,service_id,booking_date,start_time,end_time,total_price,notes) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$_SESSION['user_id'], $biz['id'], $serviceId, $date, $time, $endTime, $svc['price'], $notes]);
        setFlash('success', 'Buchung erfolgreich! Du erhältst eine Bestätigung.');
        header('Location: /business.php?slug=' . urlencode($slug) . '#booking');
        exit;
    } else {
        setFlash('error', 'Bitte wähle Service, Datum und Uhrzeit.');
    }
}

// Review handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && verifyCsrf()) {
    requireLogin();
    $rating  = max(1, min(5, (int)$_POST['rating']));
    $comment = trim($_POST['comment'] ?? '');

    // Check if already reviewed
    $existStmt = $db->prepare("SELECT id FROM reviews WHERE user_id=? AND business_id=?");
    $existStmt->execute([$_SESSION['user_id'], $biz['id']]);
    if ($existStmt->fetchColumn()) {
        setFlash('error', 'Du hast diesen Salon bereits bewertet.');
    } else {
        $db->prepare("INSERT INTO reviews (user_id,business_id,rating,comment) VALUES (?,?,?,?)")
           ->execute([$_SESSION['user_id'], $biz['id'], $rating, $comment]);
        setFlash('success', 'Bewertung wurde veröffentlicht! Danke!');
    }
    header('Location: /business.php?slug=' . urlencode($slug) . '#reviews');
    exit;
}

// Track view
try {
    $db->prepare("INSERT INTO analytics_events (event_type,entity_type,entity_id,page,ip_hash) VALUES ('business_view','business',?,?,?)")
       ->execute([$biz['id'], '/business.php?slug='.$slug, hash('sha256',($_SERVER['REMOTE_ADDR']??'').date('Y-m-d'))]);
} catch(Exception $e){}

$dayNames = [1=>'Montag',2=>'Dienstag',3=>'Mittwoch',4=>'Donnerstag',5=>'Freitag',6=>'Samstag',7=>'Sonntag'];
$todayDow = date('N');
$isOpenToday = false;
foreach ($hours as $h) {
    if ($h['day_of_week'] == $todayDow && !$h['is_closed']) { $isOpenToday = true; break; }
}
?>

<style>
:root { --accent: <?= e($accentColor) ?>; }

/* ── HERO ── */
.biz-hero {
    position: relative;
    height: 420px;
    overflow: hidden;
    background: linear-gradient(135deg, <?= e($accentColor) ?>33, #f5f0ff);
}
.biz-hero-img { width:100%; height:100%; object-fit:cover; }
.biz-hero-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.2) 40%, transparent 70%);
    display: flex; align-items: flex-end;
}
.biz-hero-content { padding: 32px; width: 100%; }
.biz-hero-actions {
    position: absolute;
    top: 20px; right: 20px;
    display: flex; gap: 10px;
}
.biz-hero-btn {
    width: 42px; height: 42px;
    background: rgba(255,255,255,0.9);
    backdrop-filter: blur(8px);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 1rem;
    border: none;
    color: var(--gray-700);
    transition: all 0.2s;
    text-decoration: none;
}
.biz-hero-btn:hover { background: white; transform: scale(1.05); }
.biz-hero-btn.fav { color: <?= $isFav ? '#ef4444' : 'var(--gray-700)' ?>; }
.biz-hero-btn.fav.active { background: #fee2e2; color: #ef4444; }

/* Gallery strip */
.biz-gallery-strip {
    display: flex; gap: 6px;
    padding: 12px 20px;
    background: #1a1a2e;
    overflow-x: auto;
    scrollbar-width: none;
}
.biz-gallery-strip::-webkit-scrollbar { display: none; }
.biz-gallery-thumb {
    width: 72px; height: 54px;
    border-radius: 8px;
    object-fit: cover;
    flex-shrink: 0;
    cursor: pointer;
    opacity: 0.75;
    transition: opacity 0.15s, transform 0.15s;
    border: 2px solid transparent;
}
.biz-gallery-thumb:hover { opacity: 1; transform: scale(1.04); }
.biz-gallery-thumb.active { opacity: 1; border-color: var(--accent); }

/* Breadcrumb */
.biz-breadcrumb {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 0;
    font-size: 0.82rem;
    color: var(--gray-400);
}
.biz-breadcrumb a { color: var(--gray-400); text-decoration: none; }
.biz-breadcrumb a:hover { color: var(--primary-dark); }

/* Layout */
.biz-layout {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 32px;
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 24px 60px;
}
@media (max-width: 900px) { .biz-layout { grid-template-columns: 1fr; } }

/* ── INFO HEADER ── */
.biz-info-header {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 28px;
    padding-bottom: 24px;
    border-bottom: 1px solid #f0ebff;
}
.biz-logo {
    width: 72px; height: 72px; border-radius: 14px;
    object-fit: cover;
    border: 3px solid white;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    flex-shrink: 0;
}
.biz-logo-placeholder {
    width: 72px; height: 72px; border-radius: 14px;
    background: linear-gradient(135deg, var(--accent), #c06090);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; color: white; flex-shrink: 0;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}
.biz-name { font-size: 1.6rem; font-weight: 800; margin: 0 0 6px; color: var(--gray-900); }
.biz-meta { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.biz-meta-item { display: flex; align-items: center; gap: 5px; font-size: 0.85rem; color: var(--gray-500); }
.biz-meta-item i { color: var(--accent); font-size: 0.8rem; }
.biz-open-badge { background: #d1fae5; color: #065f46; font-size: 0.72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.biz-closed-badge { background: #fee2e2; color: #991b1b; font-size: 0.72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }

/* Section titles */
.biz-section { margin-bottom: 36px; }
.biz-section-title { font-size: 1.15rem; font-weight: 700; color: var(--gray-900); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.biz-section-title::after { content:''; flex:1; height:1px; background:#f0ebff; }

/* Services */
.service-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1.5px solid #f0ebff;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.15s;
    background: white;
}
.service-card:hover { border-color: var(--accent); background: #fdf9ff; }
.service-card.selected { border-color: var(--accent); background: #fdf5ff; box-shadow: 0 0 0 3px <?= e($accentColor) ?>33; }
.service-card .svc-icon {
    width: 40px; height: 40px; border-radius: 10px;
    background: linear-gradient(135deg, <?= e($accentColor) ?>33, #f5f0ff);
    display: flex; align-items: center; justify-content: center;
    color: var(--primary-dark); font-size: 1rem; flex-shrink: 0;
}
.service-card .svc-name { font-weight: 600; font-size: 0.9rem; }
.service-card .svc-desc { font-size: 0.78rem; color: var(--gray-500); margin-top: 2px; }
.service-card .svc-meta { margin-left: auto; text-align: right; flex-shrink: 0; }
.service-card .svc-price { font-weight: 800; font-size: 1rem; color: var(--gray-900); }
.service-card .svc-dur { font-size: 0.75rem; color: var(--gray-400); }

/* ── REVIEWS ── */
.review-card {
    background: white;
    border-radius: 14px;
    padding: 18px;
    margin-bottom: 12px;
    border: 1px solid #f0ebff;
    box-shadow: 0 2px 8px rgba(100,60,140,0.04);
}
.review-user { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.review-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), #c06090);
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
}
.review-stars { display: flex; gap: 2px; }
.review-stars i { font-size: 0.78rem; }
.review-text { font-size: 0.875rem; color: var(--gray-700); line-height: 1.65; }
.review-reply {
    margin-top: 12px;
    padding: 12px 14px;
    background: #f5f0ff;
    border-radius: 10px;
    font-size: 0.82rem;
    border-left: 3px solid var(--accent);
}

/* Write Review */
.write-review {
    background: white;
    border-radius: 16px;
    padding: 24px;
    border: 1.5px dashed #d8c8f0;
}
.star-picker { display: flex; gap: 6px; margin-bottom: 16px; }
.star-picker input[type=radio] { display: none; }
.star-picker label {
    font-size: 1.6rem;
    cursor: pointer;
    color: #e5e0f0;
    transition: color 0.15s, transform 0.1s;
}
.star-picker label:hover,
.star-picker label.selected,
.star-picker input:checked ~ label { color: #f59e0b; }
.star-picker label:hover { transform: scale(1.2); }

/* ── BOOKING WIDGET (Sidebar) ── */
.booking-widget {
    background: white;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 8px 32px rgba(100,60,140,0.12);
    border: 1px solid #f0ebff;
    position: sticky;
    top: 88px;
}
.booking-widget h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 18px; }
.bk-service-select {
    background: #faf8ff;
    border: 1.5px solid #e8e0f8;
    border-radius: 12px;
    padding: 10px 14px;
    width: 100%;
    font-size: 0.9rem;
    font-family: inherit;
    margin-bottom: 12px;
    transition: border-color 0.15s;
}
.bk-service-select:focus { outline: none; border-color: var(--accent); }
.bk-date-time {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;
}
.bk-input {
    background: #faf8ff;
    border: 1.5px solid #e8e0f8;
    border-radius: 12px;
    padding: 10px 12px;
    width: 100%;
    font-size: 0.875rem;
    font-family: inherit;
    box-sizing: border-box;
    transition: border-color 0.15s;
}
.bk-input:focus { outline: none; border-color: var(--accent); }
.bk-notes { margin-bottom: 16px; }
.bk-label { font-size: 0.78rem; font-weight: 600; color: #9080b0; margin-bottom: 5px; display: block; }
.btn-book {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, var(--accent), #c06090);
    color: white;
    border: none;
    border-radius: 14px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.15s, transform 0.15s;
}
.btn-book:hover { opacity: 0.92; transform: translateY(-1px); }

/* Price display */
.bk-price {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
    border-top: 1px solid #f0ebff;
    margin-bottom: 12px;
    font-size: 0.875rem;
    color: var(--gray-500);
}
.bk-price .amount { font-size: 1.3rem; font-weight: 800; color: var(--gray-900); }

/* Contact card */
.contact-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin-top: 16px;
    border: 1px solid #f0ebff;
}
.contact-row {
    display: flex; align-items: center; gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid #faf8ff;
    font-size: 0.875rem;
    color: var(--gray-700);
}
.contact-row:last-child { border-bottom: none; }
.contact-row .ci { width: 32px; height: 32px; border-radius: 8px; background: #f5f0ff; display: flex; align-items: center; justify-content: center; color: var(--primary-dark); font-size: 0.8rem; flex-shrink: 0; }

/* Hours */
.hours-row {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    font-size: 0.85rem;
    border-bottom: 1px solid #faf8ff;
}
.hours-row:last-child { border-bottom: none; }
.hours-row.today { font-weight: 700; color: var(--gray-900); }

/* Lightbox */
#lightbox {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.9);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
#lightbox.open { display: flex; }
#lightbox img { max-width: 90vw; max-height: 90vh; border-radius: 12px; object-fit: contain; }
#lightbox-close { position: absolute; top: 20px; right: 20px; color: white; font-size: 1.5rem; cursor: pointer; background: rgba(255,255,255,0.1); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
</style>

<!-- Hero Image -->
<div class="biz-hero" id="biz-hero">
    <?php if ($biz['cover_image']): ?>
    <img src="<?= e($biz['cover_image']) ?>" alt="<?= e($biz['name']) ?>" class="biz-hero-img" id="heroImg">
    <?php else: ?>
    <div style="width:100%;height:100%;background:linear-gradient(135deg,<?= e($accentColor) ?>55,#f5f0ff);display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-store" style="font-size:5rem;color:rgba(255,255,255,0.3);"></i>
    </div>
    <?php endif; ?>
    <div class="biz-hero-overlay">
        <div class="biz-hero-content">
            <?php if ($biz['category_names']): ?>
            <div style="margin-bottom:10px;">
                <?php foreach (array_slice(explode(', ', $biz['category_names']), 0, 3) as $cat): ?>
                <span style="background:rgba(255,255,255,0.2);color:white;font-size:0.72rem;font-weight:600;padding:3px 10px;border-radius:20px;margin-right:6px;backdrop-filter:blur(4px);"><?= e($cat) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <h1 style="color:white;font-size:2.2rem;margin:0 0 8px;text-shadow:0 2px 8px rgba(0,0,0,0.3);"><?= e($biz['name']) ?></h1>
            <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
                <?php if ($biz['city']): ?><span style="color:rgba(255,255,255,0.85);font-size:0.9rem;"><i class="fas fa-map-marker-alt"></i> <?= e($biz['city']) ?></span><?php endif; ?>
                <?php if ($ratingInfo['cnt'] > 0): ?>
                <span style="color:#fde68a;font-size:0.9rem;">★ <?= number_format($ratingInfo['avg'],1) ?> <span style="color:rgba(255,255,255,0.7);">(<?= $ratingInfo['cnt'] ?> Bewertungen)</span></span>
                <?php endif; ?>
                <?php if ($biz['is_verified']): ?><span style="background:rgba(16,185,129,0.85);color:white;font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:20px;"><i class="fas fa-check"></i> Verifiziert</span><?php endif; ?>
                <?php if ($biz['featured_badge']): ?><span style="background:rgba(245,158,11,0.85);color:white;font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:20px;"><i class="fas fa-star"></i> Featured</span><?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Action Buttons -->
    <div class="biz-hero-actions">
        <?php if (isLoggedIn()): ?>
        <button class="biz-hero-btn fav <?= $isFav ? 'active' : '' ?>" id="favBtn" onclick="toggleFav(<?= $biz['id'] ?>)" title="<?= $isFav?'Aus Favoriten entfernen':'Zu Favoriten hinzufügen' ?>">
            <i class="<?= $isFav ? 'fas' : 'far' ?> fa-heart"></i>
        </button>
        <?php endif; ?>
        <button class="biz-hero-btn" onclick="sharePage()" title="Teilen"><i class="fas fa-share-alt"></i></button>
    </div>
</div>

<!-- Gallery Strip -->
<?php if (!empty($images)): ?>
<div class="biz-gallery-strip">
    <?php if ($biz['cover_image']): ?>
    <img src="<?= e($biz['cover_image']) ?>" class="biz-gallery-thumb active" onclick="changeHero(this, '<?= e($biz['cover_image']) ?>')">
    <?php endif; ?>
    <?php foreach ($images as $img): ?>
    <img src="<?= e($img['image_path']) ?>" class="biz-gallery-thumb" onclick="openLightbox('<?= e($img['image_path']) ?>')" loading="lazy">
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Breadcrumb -->
<div class="container" style="max-width:1100px;margin:0 auto;padding:0 24px;">
    <div class="biz-breadcrumb">
        <a href="/">Startseite</a> <i class="fas fa-chevron-right" style="font-size:0.6rem;"></i>
        <a href="/marketplace.php">Marktplatz</a> <i class="fas fa-chevron-right" style="font-size:0.6rem;"></i>
        <?php if ($biz['city']): ?><a href="/marketplace.php?location=<?= urlencode($biz['city']) ?>"><?= e($biz['city']) ?></a> <i class="fas fa-chevron-right" style="font-size:0.6rem;"></i><?php endif; ?>
        <?= e($biz['name']) ?>
    </div>
</div>

<!-- Main Content -->
<div class="biz-layout">
    <!-- LEFT: Content -->
    <div>
        <!-- Info Header -->
        <div class="biz-info-header">
            <?php if ($biz['logo']): ?>
            <img src="<?= e($biz['logo']) ?>" class="biz-logo" alt="">
            <?php else: ?>
            <div class="biz-logo-placeholder"><i class="fas fa-store"></i></div>
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
                <h1 class="biz-name"><?= e($biz['name']) ?></h1>
                <div class="biz-meta">
                    <?php if ($biz['city']): ?>
                    <div class="biz-meta-item"><i class="fas fa-map-marker-alt"></i> <?= e($biz['city']) ?></div>
                    <?php endif; ?>
                    <?php if ($ratingInfo['cnt'] > 0): ?>
                    <div class="biz-meta-item"><i class="fas fa-star" style="color:#f59e0b;"></i> <?= number_format($ratingInfo['avg'],1) ?> (<?= $ratingInfo['cnt'] ?>)</div>
                    <?php endif; ?>
                    <?php if ($biz['phone']): ?>
                    <div class="biz-meta-item"><i class="fas fa-phone"></i> <a href="tel:<?= e($biz['phone']) ?>" style="color:inherit;"><?= e($biz['phone']) ?></a></div>
                    <?php endif; ?>
                    <?php if (!empty($hours)): ?>
                    <span class="<?= $isOpenToday ? 'biz-open-badge' : 'biz-closed-badge' ?>"><?= $isOpenToday ? '● Heute geöffnet' : '● Heute geschlossen' ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- About -->
        <?php if ($biz['description']): ?>
        <div class="biz-section">
            <h2 class="biz-section-title"><i class="fas fa-info-circle" style="color:var(--accent);font-size:0.9rem;"></i> Über uns</h2>
            <p style="color:var(--gray-700);line-height:1.85;font-size:0.95rem;"><?= nl2br(e($biz['description'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Services -->
        <?php if (!empty($services)): ?>
        <div class="biz-section" id="services">
            <h2 class="biz-section-title"><i class="fas fa-list" style="color:var(--accent);font-size:0.9rem;"></i> Services & Preise</h2>
            <?php
            $svcByCat = [];
            foreach ($services as $s) { $svcByCat[$s['cat_name'] ?? 'Allgemein'][] = $s; }
            foreach ($svcByCat as $catName => $svcs):
            ?>
            <?php if (count($svcByCat) > 1): ?><h4 style="font-size:0.82rem;color:#9080b0;text-transform:uppercase;letter-spacing:0.08em;margin:16px 0 8px;"><?= e($catName) ?></h4><?php endif; ?>
            <?php foreach ($svcs as $svc): ?>
            <div class="service-card" onclick="selectService(this, <?= $svc['id'] ?>, '<?= addslashes(e($svc['name'])) ?>', <?= $svc['price'] ?>)" data-id="<?= $svc['id'] ?>">
                <div class="svc-icon"><i class="fas fa-magic"></i></div>
                <div style="flex:1;min-width:0;">
                    <div class="svc-name"><?= e($svc['name']) ?></div>
                    <?php if ($svc['description']): ?><div class="svc-desc"><?= e(mb_substr($svc['description'],0,70)) ?></div><?php endif; ?>
                </div>
                <div class="svc-meta">
                    <div class="svc-price">€<?= number_format($svc['price'],0,',','.') ?></div>
                    <div class="svc-dur"><i class="far fa-clock"></i> <?= $svc['duration_minutes'] ?> Min.</div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Gallery Grid -->
        <?php if (!empty($images)): ?>
        <div class="biz-section">
            <h2 class="biz-section-title"><i class="fas fa-images" style="color:var(--accent);font-size:0.9rem;"></i> Galerie</h2>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
            <?php foreach ($images as $img): ?>
            <div style="border-radius:12px;overflow:hidden;aspect-ratio:1;cursor:pointer;" onclick="openLightbox('<?= e($img['image_path']) ?>')">
                <img src="<?= e($img['image_path']) ?>" style="width:100%;height:100%;object-fit:cover;transition:transform 0.3s;" loading="lazy"
                     onmouseenter="this.style.transform='scale(1.05)'" onmouseleave="this.style.transform=''">
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Reviews -->
        <div class="biz-section" id="reviews">
            <h2 class="biz-section-title"><i class="fas fa-star" style="color:#f59e0b;font-size:0.9rem;"></i> Bewertungen <?php if ($ratingInfo['cnt']): ?><span style="font-size:0.85rem;color:#9080b0;font-weight:400;">(<?= $ratingInfo['cnt'] ?>)</span><?php endif; ?></h2>

            <!-- Rating Overview -->
            <?php if ($ratingInfo['cnt'] > 0): ?>
            <div style="display:flex;gap:32px;align-items:center;background:white;border-radius:16px;padding:20px;margin-bottom:20px;border:1px solid #f0ebff;">
                <div style="text-align:center;flex-shrink:0;">
                    <div style="font-size:3.5rem;font-weight:900;line-height:1;color:var(--gray-900);"><?= number_format($ratingInfo['avg'],1) ?></div>
                    <div style="color:#f59e0b;font-size:1.1rem;margin:4px 0;"><?php for($i=1;$i<=5;$i++): ?><i class="fas fa-star" style="color:<?= $i<=round($ratingInfo['avg'])?'#f59e0b':'#e5e0f0' ?>"></i><?php endfor; ?></div>
                    <div style="font-size:0.78rem;color:#9080b0;"><?= $ratingInfo['cnt'] ?> Bewertungen</div>
                </div>
                <div style="flex:1;">
                    <?php for($r=5;$r>=1;$r--): $c=$ratingDist[$r]??0; $t=max(1,$ratingInfo['cnt']); ?>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:0.8rem;">
                        <span style="min-width:12px;text-align:right;color:var(--gray-600);"><?= $r ?></span>
                        <i class="fas fa-star" style="color:#f59e0b;font-size:0.65rem;"></i>
                        <div style="flex:1;height:8px;background:#f0ebff;border-radius:4px;overflow:hidden;">
                            <div style="height:100%;width:<?= round($c/$t*100) ?>%;background:linear-gradient(90deg,<?= e($accentColor) ?>,#c06090);border-radius:4px;"></div>
                        </div>
                        <span style="min-width:24px;color:#9080b0;"><?= $c ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Review List -->
            <?php foreach ($reviews as $rev): ?>
            <div class="review-card">
                <div class="review-user">
                    <div class="review-avatar"><?= strtoupper(substr($rev['first_name'],0,1).substr($rev['last_name'],0,1)) ?></div>
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:0.9rem;"><?= e($rev['first_name']) ?> <?= e(substr($rev['last_name'],0,1)) ?>.</div>
                        <div style="display:flex;align-items:center;gap:8px;margin-top:2px;">
                            <div class="review-stars">
                                <?php for($i=1;$i<=5;$i++): ?><i class="fas fa-star" style="color:<?= $i<=$rev['rating']?'#f59e0b':'#e5e0f0' ?>"></i><?php endfor; ?>
                            </div>
                            <span style="font-size:0.75rem;color:#9080b0;"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
                <?php if ($rev['comment']): ?>
                <div class="review-text"><?= nl2br(e($rev['comment'])) ?></div>
                <?php endif; ?>
                <?php if ($rev['reply']): ?>
                <div class="review-reply">
                    <strong style="color:var(--primary-dark);font-size:0.8rem;"><i class="fas fa-reply"></i> Antwort vom Salon:</strong><br>
                    <span style="color:var(--gray-700);"><?= nl2br(e($rev['reply'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if (empty($reviews)): ?>
            <div style="text-align:center;padding:40px;color:#9080b0;">
                <i class="fas fa-star" style="font-size:2.5rem;opacity:0.2;margin-bottom:12px;display:block;"></i>
                Noch keine Bewertungen
            </div>
            <?php endif; ?>

            <!-- Write Review -->
            <?php if (isLoggedIn()): ?>
            <?php
            $alreadyReviewed = false;
            foreach ($reviews as $rev) { if (($rev['user_id'] ?? 0) == $_SESSION['user_id']) { $alreadyReviewed = true; break; } }
            ?>
            <?php if (!$alreadyReviewed): ?>
            <div class="write-review" style="margin-top:20px;">
                <h4 style="margin-bottom:14px;font-size:1rem;">✍️ Deine Bewertung schreiben</h4>
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="star-picker" id="starPicker">
                        <?php for($r=5;$r>=1;$r--): ?>
                        <label for="star-<?= $r ?>" class="star-label" data-val="<?= $r ?>">★</label>
                        <input type="radio" name="rating" id="star-<?= $r ?>" value="<?= $r ?>" required>
                        <?php endfor; ?>
                    </div>
                    <textarea name="comment" rows="4" placeholder="Teile deine Erfahrung..." style="width:100%;padding:12px;border:1.5px solid #e8e0f8;border-radius:12px;font-size:0.9rem;font-family:inherit;resize:vertical;box-sizing:border-box;margin-bottom:12px;"></textarea>
                    <button type="submit" name="submit_review" class="btn-book" style="font-size:0.9rem;padding:12px;">
                        <i class="fas fa-paper-plane"></i> Bewertung veröffentlichen
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px;margin-top:16px;font-size:0.875rem;color:#065f46;text-align:center;">
                <i class="fas fa-check-circle"></i> Du hast diesen Salon bereits bewertet. Danke!
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div style="background:#f5f0ff;border:1px solid #e8d8f8;border-radius:12px;padding:16px;margin-top:16px;text-align:center;font-size:0.875rem;">
                <i class="fas fa-user"></i> <a href="/login.php" style="color:var(--primary-dark);font-weight:600;">Anmelden</a>, um eine Bewertung zu schreiben.
            </div>
            <?php endif; ?>
        </div>

        <!-- Map -->
        <?php if ($biz['latitude'] && $biz['longitude']): ?>
        <div class="biz-section">
            <h2 class="biz-section-title"><i class="fas fa-map-marker-alt" style="color:var(--accent);font-size:0.9rem;"></i> Standort</h2>
            <div style="border-radius:16px;overflow:hidden;height:280px;border:1px solid #f0ebff;">
                <div id="biz-map" style="width:100%;height:100%;"></div>
            </div>
            <?php if ($biz['street']): ?>
            <p style="font-size:0.85rem;color:#9080b0;margin-top:10px;text-align:center;">
                <i class="fas fa-map-marker-alt"></i>
                <?= e($biz['street']) ?> <?= e($biz['house_number']??'') ?>, <?= e($biz['zip_code']??'') ?> <?= e($biz['city']??'') ?>
                <a href="https://maps.google.com/?q=<?= urlencode($biz['street'].' '.$biz['city']) ?>" target="_blank" style="color:var(--primary-dark);margin-left:8px;font-weight:600;">Route anzeigen →</a>
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: Sidebar -->
    <div id="booking">
        <div class="booking-widget">
            <h3>📅 Termin buchen</h3>

            <?php if (!empty($services)): ?>
            <?php if (!isLoggedIn()): ?>
            <div style="text-align:center;padding:16px 0;">
                <div style="font-size:2rem;margin-bottom:8px;">🔒</div>
                <p style="color:#9080b0;font-size:0.875rem;margin-bottom:16px;">Bitte anmelden, um zu buchen.</p>
                <a href="/login.php" class="btn-book" style="display:block;text-decoration:none;text-align:center;padding:12px;border-radius:14px;background:linear-gradient(135deg,<?= e($accentColor) ?>,#c06090);color:white;font-weight:700;">Anmelden</a>
            </div>
            <?php else: ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="book" value="1">

                <label class="bk-label">Service</label>
                <select name="service_id" id="bk-service" class="bk-service-select" onchange="updatePrice()">
                    <?php foreach ($services as $svc): ?>
                    <option value="<?= $svc['id'] ?>" data-price="<?= $svc['price'] ?>" data-dur="<?= $svc['duration_minutes'] ?>">
                        <?= e($svc['name']) ?> · €<?= number_format($svc['price'],0,',','.') ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <div class="bk-date-time">
                    <div>
                        <label class="bk-label">Datum</label>
                        <input type="date" name="booking_date" class="bk-input" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div>
                        <label class="bk-label">Uhrzeit</label>
                        <input type="time" name="start_time" class="bk-input" required>
                    </div>
                </div>

                <div class="bk-notes">
                    <label class="bk-label">Notizen (optional)</label>
                    <textarea name="notes" class="bk-input" rows="2" placeholder="Wünsche, Allergien..." style="height:auto;resize:none;"></textarea>
                </div>

                <div class="bk-price">
                    <span>Preis</span>
                    <span class="amount" id="bk-price-display">€<?= number_format($services[0]['price'],0,',','.') ?></span>
                </div>

                <button type="submit" class="btn-book">
                    <i class="fas fa-calendar-check"></i> Jetzt buchen
                </button>

                <p style="text-align:center;font-size:0.72rem;color:#9080b0;margin-top:10px;"><i class="fas fa-shield-halved"></i> Kostenlos stornierbar</p>
            </form>
            <?php endif; ?>
            <?php else: ?>
            <p style="color:#9080b0;font-size:0.875rem;text-align:center;padding:20px 0;">Derzeit keine Services verfügbar.</p>
            <?php endif; ?>
        </div>

        <!-- Contact -->
        <div class="contact-card">
            <?php if ($biz['phone']): ?>
            <div class="contact-row">
                <div class="ci"><i class="fas fa-phone"></i></div>
                <a href="tel:<?= e($biz['phone']) ?>" style="color:inherit;"><?= e($biz['phone']) ?></a>
            </div>
            <?php endif; ?>
            <?php if ($biz['email']): ?>
            <div class="contact-row">
                <div class="ci"><i class="fas fa-envelope"></i></div>
                <a href="mailto:<?= e($biz['email']) ?>" style="color:inherit;"><?= e($biz['email']) ?></a>
            </div>
            <?php endif; ?>
            <?php if ($biz['website']): ?>
            <div class="contact-row">
                <div class="ci"><i class="fas fa-globe"></i></div>
                <a href="<?= e($biz['website']) ?>" target="_blank" style="color:var(--primary-dark);"><?= e(parse_url($biz['website'], PHP_URL_HOST)) ?></a>
            </div>
            <?php endif; ?>
            <?php if ($biz['street']): ?>
            <div class="contact-row">
                <div class="ci"><i class="fas fa-map-marker-alt"></i></div>
                <span><?= e($biz['street']) ?> <?= e($biz['house_number']??'') ?>, <?= e($biz['zip_code']??'') ?> <?= e($biz['city']??'') ?></span>
            </div>
            <?php endif; ?>

            <!-- Opening Hours -->
            <?php if (!empty($hours)): ?>
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid #f0ebff;">
                <div style="font-size:0.78rem;font-weight:700;color:#9080b0;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:8px;">Öffnungszeiten</div>
                <?php foreach ($hours as $h): ?>
                <div class="hours-row <?= $h['day_of_week'] == $todayDow ? 'today' : '' ?>">
                    <span><?= $dayNames[$h['day_of_week']] ?? '' ?></span>
                    <span><?= $h['is_closed'] ? '<span style="color:#9080b0;">Geschlossen</span>' : (date('H:i', strtotime($h['open_time'])) . ' – ' . date('H:i', strtotime($h['close_time']))) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div id="lightbox" onclick="closeLightbox()">
    <div id="lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></div>
    <img id="lightbox-img" src="" alt="">
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Map
<?php if ($biz['latitude'] && $biz['longitude']): ?>
var bMap = L.map('biz-map').setView([<?= $biz['latitude'] ?>, <?= $biz['longitude'] ?>], 15);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:19 }).addTo(bMap);
L.marker([<?= $biz['latitude'] ?>, <?= $biz['longitude'] ?>], {
    icon: L.divIcon({
        className:'',
        html:'<div style="width:32px;height:32px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:<?= e($accentColor) ?>;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.25);"></div>',
        iconSize:[32,32], iconAnchor:[16,32]
    })
}).addTo(bMap).bindPopup('<strong><?= addslashes(e($biz['name'])) ?></strong>').openPopup();
<?php endif; ?>

// Service selection
function selectService(el, id, name, price) {
    document.querySelectorAll('.service-card').forEach(function(c){ c.classList.remove('selected'); });
    el.classList.add('selected');
    var sel = document.getElementById('bk-service');
    if (sel) sel.value = id;
    updatePrice();
    document.getElementById('booking').scrollIntoView({ behavior: 'smooth' });
}

function updatePrice() {
    var sel = document.getElementById('bk-service');
    if (!sel) return;
    var opt = sel.options[sel.selectedIndex];
    var price = opt ? opt.getAttribute('data-price') : 0;
    var disp = document.getElementById('bk-price-display');
    if (disp) disp.textContent = '€' + parseFloat(price).toFixed(0);
}

// Lightbox
function openLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeLightbox(); });

// Hero gallery
function changeHero(thumb, src) {
    document.querySelectorAll('.biz-gallery-thumb').forEach(function(t){ t.classList.remove('active'); });
    thumb.classList.add('active');
    var heroImg = document.getElementById('heroImg');
    if (heroImg) { heroImg.style.opacity='0.5'; heroImg.src=src; heroImg.onload=function(){ heroImg.style.opacity='1'; }; }
}

// Share
function sharePage() {
    if (navigator.share) {
        navigator.share({ title: '<?= addslashes(e($biz['name'])) ?>', url: window.location.href });
    } else {
        navigator.clipboard.writeText(window.location.href);
        alert('Link kopiert!');
    }
}

// Favorite toggle
function toggleFav(bizId) {
    var btn = document.getElementById('favBtn');
    fetch('/api/favorite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ business_id: bizId })
    }).then(function(r){ return r.json(); }).then(function(data){
        if (data.favorited) {
            btn.innerHTML = '<i class="fas fa-heart"></i>';
            btn.classList.add('active');
        } else {
            btn.innerHTML = '<i class="far fa-heart"></i>';
            btn.classList.remove('active');
        }
    });
}

// Star picker
document.querySelectorAll('.star-label').forEach(function(label, idx, all) {
    label.addEventListener('click', function() {
        var val = parseInt(this.dataset.val);
        all.forEach(function(l) {
            l.style.color = parseInt(l.dataset.val) >= val ? '#f59e0b' : '#e5e0f0';
        });
        document.querySelector('input[name="rating"][value="'+val+'"]').checked = true;
    });
    label.addEventListener('mouseenter', function() {
        var val = parseInt(this.dataset.val);
        all.forEach(function(l) {
            l.style.color = parseInt(l.dataset.val) >= val ? '#f59e0b' : '#e5e0f0';
        });
    });
});
document.getElementById('starPicker')?.addEventListener('mouseleave', function() {
    var checked = document.querySelector('input[name="rating"]:checked');
    var val = checked ? parseInt(checked.value) : 0;
    document.querySelectorAll('.star-label').forEach(function(l) {
        l.style.color = parseInt(l.dataset.val) >= val ? '#f59e0b' : '#e5e0f0';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
