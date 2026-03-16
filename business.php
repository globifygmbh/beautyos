<?php
require_once 'includes/header.php';

$db = getDB();
$slug = trim($_GET['slug'] ?? '');

if (!$slug) {
    header('Location: /marketplace.php');
    exit;
}

// Fetch business
$stmt = $db->prepare("
    SELECT b.*,
           sp.name as plan_name, sp.custom_design,
           GROUP_CONCAT(DISTINCT c.name) as category_names
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
    echo '<div class="container section" style="text-align:center;"><h2>Salon nicht gefunden</h2><p><a href="/marketplace.php" class="btn btn-primary mt-2">Zum Marktplatz</a></p></div>';
    require_once 'includes/footer.php';
    exit;
}

$pageTitle = $biz['name'];

// Services
$services = $db->prepare("SELECT * FROM services WHERE business_id = ? AND is_active = 1 ORDER BY sort_order, name");
$services->execute([$biz['id']]);
$services = $services->fetchAll();

// Reviews
$reviews = $db->prepare("
    SELECT r.*, u.first_name, u.last_name
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.business_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
");
$reviews->execute([$biz['id']]);
$reviews = $reviews->fetchAll();

$avgRating = $db->prepare("SELECT AVG(rating) as avg, COUNT(*) as cnt FROM reviews WHERE business_id = ?");
$avgRating->execute([$biz['id']]);
$ratingInfo = $avgRating->fetch();

// Opening hours
$hours = $db->prepare("SELECT * FROM opening_hours WHERE business_id = ? ORDER BY day_of_week");
$hours->execute([$biz['id']]);
$hours = $hours->fetchAll();

// Images
$images = $db->prepare("SELECT * FROM business_images WHERE business_id = ? ORDER BY sort_order LIMIT 10");
$images->execute([$biz['id']]);
$images = $images->fetchAll();

$dayNames = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

// Handle booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {
    requireLogin();
    if (!verifyCsrf()) {
        setFlash('error', 'Ungültige Anfrage.');
    } else {
        $serviceId = (int)$_POST['service_id'];
        $date = $_POST['booking_date'] ?? '';
        $time = $_POST['start_time'] ?? '';

        $service = $db->prepare("SELECT * FROM services WHERE id = ? AND business_id = ?");
        $service->execute([$serviceId, $biz['id']]);
        $service = $service->fetch();

        if ($service && $date && $time) {
            $endTime = date('H:i', strtotime($time) + $service['duration_minutes'] * 60);
            $stmt = $db->prepare("INSERT INTO bookings (user_id, business_id, service_id, booking_date, start_time, end_time, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $biz['id'],
                $serviceId,
                $date,
                $time,
                $endTime,
                $service['price'],
            ]);
            setFlash('success', 'Buchung erfolgreich! Du erhältst eine Bestätigung.');
            header('Location: /business.php?slug=' . urlencode($slug));
            exit;
        } else {
            setFlash('error', 'Bitte wähle Service, Datum und Uhrzeit.');
        }
    }
}

// Accent color
$accentColor = $biz['accent_color'] ?? '#e8a0bf';
?>

<?php if ($biz['custom_css'] && ($biz['plan_name'] ?? '') !== 'Starter'): ?>
<style><?= $biz['custom_css'] ?></style>
<?php endif; ?>

<!-- Business Hero -->
<div class="business-hero">
    <?php if ($biz['cover_image']): ?>
        <img src="<?= e($biz['cover_image']) ?>" alt="<?= e($biz['name']) ?>">
    <?php else: ?>
        <div style="width:100%;height:100%;background: linear-gradient(135deg, <?= e($accentColor) ?>33, var(--beige));"></div>
    <?php endif; ?>
    <div class="business-hero-overlay">
        <div class="container">
            <div style="display: flex; align-items: center; gap: 20px;">
                <?php if ($biz['logo']): ?>
                    <img src="<?= e($biz['logo']) ?>" alt="" style="width:72px;height:72px;border-radius:var(--radius-md);object-fit:cover;border:3px solid white;">
                <?php else: ?>
                    <div style="width:72px;height:72px;border-radius:var(--radius-md);background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;border:3px solid white;">
                        <i class="fas fa-store" style="font-size:1.5rem;"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <h1 style="font-size: 2rem; color: white; margin-bottom: 4px;"><?= e($biz['name']) ?></h1>
                    <div style="display: flex; align-items: center; gap: 16px; font-size: 0.9rem; opacity: 0.9;">
                        <?php if ($biz['city']): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?= e($biz['city']) ?></span>
                        <?php endif; ?>
                        <?php if ($ratingInfo['cnt'] > 0): ?>
                            <span><i class="fas fa-star"></i> <?= number_format($ratingInfo['avg'], 1) ?> (<?= $ratingInfo['cnt'] ?> Bewertungen)</span>
                        <?php endif; ?>
                        <?php if ($biz['is_verified']): ?>
                            <span class="badge badge-success"><i class="fas fa-check"></i> Verifiziert</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<section class="section" style="padding-top: 40px;">
    <div class="container">
        <div class="business-info-grid">
            <!-- Main Content -->
            <div class="business-main">
                <!-- Description -->
                <?php if ($biz['description']): ?>
                <div style="margin-bottom: 32px;">
                    <h3 style="margin-bottom: 12px;">Über uns</h3>
                    <p style="color: var(--gray-600); line-height: 1.8;"><?= nl2br(e($biz['description'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- Services -->
                <?php if ($services): ?>
                <div style="margin-bottom: 32px;">
                    <h3 style="margin-bottom: 16px;">Unsere Services</h3>
                    <?php foreach ($services as $svc): ?>
                    <div class="service-list-item" data-service-id="<?= $svc['id'] ?>" onclick="selectService(this)">
                        <div class="service-info">
                            <h4><?= e($svc['name']) ?></h4>
                            <?php if ($svc['description']): ?>
                                <p><?= e($svc['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="service-meta">
                            <div class="service-price"><?= number_format($svc['price'], 2, ',', '.') ?> &euro;</div>
                            <div class="service-duration"><i class="far fa-clock"></i> <?= $svc['duration_minutes'] ?> Min.</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Gallery -->
                <?php if ($images): ?>
                <div style="margin-bottom: 32px;">
                    <h3 style="margin-bottom: 16px;">Galerie</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
                        <?php foreach ($images as $img): ?>
                        <div style="border-radius: var(--radius-md); overflow: hidden; aspect-ratio: 1;">
                            <img src="<?= e($img['image_path']) ?>" alt="<?= e($img['caption'] ?? '') ?>" style="width:100%;height:100%;object-fit:cover;">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Reviews -->
                <div>
                    <h3 style="margin-bottom: 16px;">Bewertungen (<?= $ratingInfo['cnt'] ?>)</h3>
                    <?php if (empty($reviews)): ?>
                        <p style="color: var(--gray-500);">Noch keine Bewertungen vorhanden.</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $rev): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div class="review-avatar"><?= strtoupper(mb_substr($rev['first_name'], 0, 1)) ?><?= strtoupper(mb_substr($rev['last_name'], 0, 1)) ?></div>
                                <div class="review-meta">
                                    <strong><?= e($rev['first_name']) ?> <?= e(mb_substr($rev['last_name'], 0, 1)) ?>.</strong>
                                    <br><span>
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <i class="fas fa-star" style="color:<?= $s <= $rev['rating'] ? 'var(--warning)' : 'var(--gray-300)' ?>;font-size:0.75rem;"></i>
                                        <?php endfor; ?>
                                        &middot; <?= date('d.m.Y', strtotime($rev['created_at'])) ?>
                                    </span>
                                </div>
                            </div>
                            <?php if ($rev['comment']): ?>
                                <div class="review-text"><?= nl2br(e($rev['comment'])) ?></div>
                            <?php endif; ?>
                            <?php if ($rev['reply']): ?>
                                <div style="margin-top: 12px; padding: 12px; background: var(--gray-50); border-radius: var(--radius-sm); font-size: 0.85rem;">
                                    <strong style="color: var(--primary-dark);">Antwort vom Salon:</strong><br>
                                    <?= nl2br(e($rev['reply'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Booking Widget -->
                <div class="booking-widget" style="margin-bottom: 24px;">
                    <h3>Termin buchen</h3>
                    <?php if ($services): ?>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="book" value="1">
                        <input type="hidden" name="service_id" id="selectedService" value="<?= $services[0]['id'] ?>">

                        <div class="form-group">
                            <label class="form-label">Service wählen</label>
                            <select name="service_id" class="form-control" id="selectedService">
                                <?php foreach ($services as $svc): ?>
                                    <option value="<?= $svc['id'] ?>"><?= e($svc['name']) ?> - <?= number_format($svc['price'], 2, ',', '.') ?> &euro;</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Datum</label>
                            <input type="date" name="booking_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Uhrzeit</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 btn-lg">
                            <i class="fas fa-calendar-check"></i> Jetzt buchen
                        </button>
                    </form>
                    <?php else: ?>
                        <p style="color: var(--gray-500);">Keine Services verfügbar.</p>
                    <?php endif; ?>
                </div>

                <!-- Contact Info -->
                <div class="business-sidebar-card">
                    <h4 style="margin-bottom: 16px;">Kontakt</h4>
                    <?php if ($biz['street']): ?>
                        <p style="font-size: 0.9rem; color: var(--gray-600); margin-bottom: 12px;">
                            <i class="fas fa-map-marker-alt" style="width: 20px; color: var(--primary);"></i>
                            <?= e($biz['street']) ?> <?= e($biz['house_number'] ?? '') ?><br>
                            <span style="padding-left: 24px;"><?= e($biz['zip_code'] ?? '') ?> <?= e($biz['city'] ?? '') ?></span>
                        </p>
                    <?php endif; ?>
                    <?php if ($biz['phone']): ?>
                        <p style="font-size: 0.9rem; color: var(--gray-600); margin-bottom: 12px;">
                            <i class="fas fa-phone" style="width: 20px; color: var(--primary);"></i>
                            <?= e($biz['phone']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($biz['email']): ?>
                        <p style="font-size: 0.9rem; color: var(--gray-600); margin-bottom: 12px;">
                            <i class="fas fa-envelope" style="width: 20px; color: var(--primary);"></i>
                            <?= e($biz['email']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($biz['website']): ?>
                        <p style="font-size: 0.9rem; color: var(--gray-600); margin-bottom: 12px;">
                            <i class="fas fa-globe" style="width: 20px; color: var(--primary);"></i>
                            <a href="<?= e($biz['website']) ?>" target="_blank" style="color: var(--primary-dark);"><?= e(parse_url($biz['website'], PHP_URL_HOST)) ?></a>
                        </p>
                    <?php endif; ?>

                    <!-- Opening Hours -->
                    <?php if ($hours): ?>
                    <h4 style="margin: 20px 0 12px;">Öffnungszeiten</h4>
                    <table style="width: 100%; font-size: 0.85rem;">
                        <?php foreach ($hours as $h): ?>
                        <tr>
                            <td style="padding: 4px 0; color: var(--gray-600);"><?= $dayNames[$h['day_of_week']] ?></td>
                            <td style="padding: 4px 0; text-align: right; font-weight: 500;">
                                <?= $h['is_closed'] ? '<span style="color:var(--danger);">Geschlossen</span>' : date('H:i', strtotime($h['open_time'])) . ' - ' . date('H:i', strtotime($h['close_time'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endif; ?>
                </div>

                <!-- Map -->
                <?php if ($biz['latitude'] && $biz['longitude']): ?>
                <div class="map-container" style="height: 250px; margin-top: 24px;">
                    <div id="businessMap" style="width:100%;height:100%;"></div>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', () => {
                    initMap('businessMap', [{
                        name: <?= json_encode($biz['name']) ?>,
                        slug: <?= json_encode($biz['slug']) ?>,
                        city: <?= json_encode($biz['city']) ?>,
                        latitude: <?= $biz['latitude'] ?>,
                        longitude: <?= $biz['longitude'] ?>,
                    }], [<?= $biz['latitude'] ?>, <?= $biz['longitude'] ?>], 15);
                });
                </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
