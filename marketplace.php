<?php
$pageTitle = 'Marktplatz';
require_once 'includes/header.php';

$db = getDB();

$search   = trim($_GET['q'] ?? '');
$location = trim($_GET['location'] ?? '');
$category = trim($_GET['category'] ?? '');
$rating   = (int)($_GET['rating'] ?? 0);
$minPrice = (float)($_GET['min_price'] ?? 0);
$maxPrice = (float)($_GET['max_price'] ?? 0);
$sort     = $_GET['sort'] ?? 'relevance';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;
$offset   = ($page - 1) * $perPage;

$where  = ["b.status = 'active'"];
$params = [];

if ($search) {
    $where[]  = "(b.name LIKE ? OR b.description LIKE ? OR s.name LIKE ? OR b.short_description LIKE ?)";
    $params   = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
}
if ($location) {
    $where[]  = "(b.city LIKE ? OR b.zip_code LIKE ? OR b.state LIKE ?)";
    $params   = array_merge($params, ["%$location%","%$location%","%$location%"]);
}
if ($category) {
    $where[]  = "c.slug = ?";
    $params[] = $category;
}
if ($rating > 0) {
    $where[]  = "COALESCE(avg_r.avg_rating, 0) >= ?";
    $params[] = $rating;
}
if ($minPrice > 0) {
    $where[]  = "s.price >= ?";
    $params[] = $minPrice;
}
if ($maxPrice > 0) {
    $where[]  = "s.price <= ?";
    $params[] = $maxPrice;
}

$whereSQL = implode(' AND ', $where);
$orderBy  = match($sort) {
    'rating'  => 'avg_r.avg_rating DESC',
    'name'    => 'b.name ASC',
    'newest'  => 'b.created_at DESC',
    'price'   => 'min_price ASC',
    default   => 'b.subscription_plan_id DESC, b.is_verified DESC, avg_r.avg_rating DESC',
};

$stmt = $db->prepare("SELECT COUNT(DISTINCT b.id) FROM businesses b
    LEFT JOIN business_categories bc ON b.id=bc.business_id
    LEFT JOIN categories c ON bc.category_id=c.id
    LEFT JOIN services s ON b.id=s.business_id AND s.is_active=1
    LEFT JOIN (SELECT business_id, AVG(rating) as avg_rating FROM reviews GROUP BY business_id) avg_r ON b.id=avg_r.business_id
    WHERE $whereSQL");
$stmt->execute($params);
$totalResults = $stmt->fetchColumn();
$totalPages   = ceil($totalResults / $perPage);

$sql = "SELECT b.*,
    GROUP_CONCAT(DISTINCT c.name ORDER BY c.sort_order SEPARATOR ',') as category_names,
    GROUP_CONCAT(DISTINCT c.slug ORDER BY c.sort_order SEPARATOR ',') as category_slugs,
    COALESCE(avg_r.avg_rating,0) as avg_rating,
    COALESCE(avg_r.review_count,0) as review_count,
    MIN(s.price) as min_price
FROM businesses b
LEFT JOIN business_categories bc ON b.id=bc.business_id
LEFT JOIN categories c ON bc.category_id=c.id
LEFT JOIN services s ON b.id=s.business_id AND s.is_active=1
LEFT JOIN (SELECT business_id, AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews GROUP BY business_id) avg_r ON b.id=avg_r.business_id
WHERE $whereSQL
GROUP BY b.id ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$businesses = $stmt->fetchAll();

// All businesses for map (no pagination limit - only with coordinates)
$mapBiz = $db->prepare("SELECT b.id,b.name,b.slug,b.city,b.latitude,b.longitude,b.cover_image,b.short_description,
    COALESCE(avg_r.avg_rating,0) as avg_rating, COALESCE(avg_r.review_count,0) as review_count,
    MIN(s.price) as min_price,
    GROUP_CONCAT(DISTINCT c.name SEPARATOR ',') as category_names
FROM businesses b
LEFT JOIN business_categories bc ON b.id=bc.business_id
LEFT JOIN categories c ON bc.category_id=c.id
LEFT JOIN services s ON b.id=s.business_id AND s.is_active=1
LEFT JOIN (SELECT business_id,AVG(rating) avg_rating,COUNT(*) review_count FROM reviews GROUP BY business_id) avg_r ON b.id=avg_r.business_id
WHERE b.status='active' AND b.latitude IS NOT NULL AND b.longitude IS NOT NULL
GROUP BY b.id LIMIT 500");
$mapBiz->execute();
$mapBusinesses = $mapBiz->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();

// Log search
if ($search || $category) {
    try {
        $db->prepare("INSERT INTO search_logs (query, category, location, results_count, ip_hash) VALUES (?,?,?,?,?)")
           ->execute([$search ?: null, $category ?: null, $location ?: null, $totalResults, hash('sha256', ($_SERVER['REMOTE_ADDR']??'').date('Y-m-d'))]);
    } catch(Exception $e) {}
}
?>

<style>
/* ====== MARKETPLACE LAYOUT ====== */
.mp-wrap {
    display: flex;
    height: calc(100vh - 72px);
    overflow: hidden;
    position: relative;
}

/* Left panel */
.mp-panel {
    width: 420px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    background: #faf8ff;
    border-right: 1px solid #f0ebff;
    overflow: hidden;
    z-index: 10;
    transition: width 0.3s ease;
}
.mp-panel.collapsed { width: 0; }

.mp-panel-top {
    background: white;
    padding: 16px;
    border-bottom: 1px solid #f0ebff;
    flex-shrink: 0;
}

/* Search bar */
.mp-search {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
}
.mp-search input {
    flex: 1;
    padding: 10px 14px;
    border: 1.5px solid #e8e0f8;
    border-radius: 12px;
    font-size: 0.9rem;
    background: #faf8ff;
    transition: border-color 0.15s;
}
.mp-search input:focus { outline: none; border-color: var(--primary-dark); background: white; }
.mp-search button {
    padding: 10px 16px;
    background: linear-gradient(135deg, var(--primary-dark), #c06090);
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.875rem;
    transition: opacity 0.15s;
}
.mp-search button:hover { opacity: 0.9; }

/* Category chips */
.cat-chips {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: none;
}
.cat-chips::-webkit-scrollbar { display: none; }
.cat-chip {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 20px;
    border: 1.5px solid #e8e0f8;
    background: white;
    font-size: 0.78rem;
    font-weight: 500;
    color: #7060a0;
    cursor: pointer;
    white-space: nowrap;
    text-decoration: none;
    transition: all 0.15s;
}
.cat-chip:hover, .cat-chip.active {
    background: var(--primary-dark);
    border-color: var(--primary-dark);
    color: white;
}

/* Results area */
.mp-results {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
    scrollbar-width: thin;
    scrollbar-color: #e8e0f8 transparent;
}

.mp-results-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 4px 10px;
    font-size: 0.82rem;
    color: #9080b0;
}

/* Result Cards */
.mp-card {
    background: white;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 10px;
    box-shadow: 0 2px 8px rgba(100,60,140,0.06);
    transition: transform 0.18s, box-shadow 0.18s;
    text-decoration: none;
    color: inherit;
    display: flex;
    cursor: pointer;
    border: 1.5px solid transparent;
}
.mp-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(100,60,140,0.12);
    border-color: #e8d8f8;
}
.mp-card.active { border-color: var(--primary-dark); }
.mp-card-img {
    width: 100px;
    flex-shrink: 0;
    background: linear-gradient(135deg, var(--primary-light), var(--beige));
    position: relative;
    overflow: hidden;
}
.mp-card-img img { width: 100%; height: 100%; object-fit: cover; }
.mp-card-img .mp-card-cat {
    position: absolute;
    bottom: 6px; left: 6px;
    background: rgba(0,0,0,0.55);
    color: white;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 10px;
}
.mp-card-body { flex: 1; padding: 12px 14px; min-width: 0; }
.mp-card-name { font-weight: 700; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 3px; }
.mp-card-loc { font-size: 0.78rem; color: #9080b0; margin-bottom: 5px; }
.mp-card-loc i { color: var(--primary-dark); font-size: 0.7rem; }
.mp-card-rating { display: flex; align-items: center; gap: 4px; font-size: 0.78rem; }
.mp-card-rating .stars i { font-size: 0.62rem; }
.mp-card-price { margin-top: 5px; font-size: 0.78rem; }
.mp-card-price strong { color: var(--primary-dark); }

/* ====== MAP ====== */
.mp-map-wrap {
    flex: 1;
    position: relative;
    overflow: hidden;
}
#mp-map { width: 100%; height: 100%; }

/* Map toolbar */
.map-toolbar {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.map-tool-btn {
    width: 42px; height: 42px;
    background: white;
    border: 1.5px solid #e8e0f8;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 1rem;
    color: #7060a0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.15s;
    position: relative;
}
.map-tool-btn:hover { background: #f5f0ff; color: var(--primary-dark); }
.map-tool-btn.active {
    background: linear-gradient(135deg, var(--primary-dark), #c06090);
    color: white;
    border-color: transparent;
}
.map-tool-btn .tooltip {
    position: absolute;
    right: calc(100% + 8px);
    top: 50%;
    transform: translateY(-50%);
    background: #2d1b4e;
    color: white;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 8px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.15s;
}
.map-tool-btn:hover .tooltip { opacity: 1; }

/* Radius info pill */
#radius-info {
    position: absolute;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1000;
    background: linear-gradient(135deg, var(--primary-dark), #c06090);
    color: white;
    padding: 10px 20px;
    border-radius: 30px;
    font-size: 0.875rem;
    font-weight: 600;
    box-shadow: 0 4px 16px rgba(180,80,120,0.3);
    display: none;
    gap: 10px;
    align-items: center;
}
#radius-info button {
    background: rgba(255,255,255,0.25);
    border: none;
    color: white;
    width: 22px; height: 22px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 0.8rem;
    display: flex; align-items: center; justify-content: center;
}

/* Location prompt */
#locate-btn {
    position: absolute;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1000;
    background: white;
    border: 1.5px solid #e8e0f8;
    border-radius: 30px;
    padding: 8px 18px;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--primary-dark);
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex; align-items: center; gap: 8px;
    transition: all 0.15s;
}
#locate-btn:hover { background: var(--primary-dark); color: white; }

/* Panel toggle */
.panel-toggle {
    position: absolute;
    top: 50%;
    left: 420px;
    transform: translate(-50%, -50%);
    z-index: 100;
    width: 28px; height: 48px;
    background: white;
    border-radius: 0 10px 10px 0;
    box-shadow: 2px 0 8px rgba(0,0,0,0.1);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    color: #9080b0;
    font-size: 0.75rem;
    transition: left 0.3s;
}

/* Sort/Filter row */
.mp-sort-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.mp-sort-btn {
    padding: 5px 12px;
    border-radius: 20px;
    border: 1.5px solid #e8e0f8;
    background: white;
    font-size: 0.78rem;
    font-weight: 500;
    color: #7060a0;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
    white-space: nowrap;
}
.mp-sort-btn:hover, .mp-sort-btn.active { background: var(--primary-dark); border-color: var(--primary-dark); color: white; }

/* Rating filter pills */
.rating-pill {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 4px 10px;
    border-radius: 20px;
    border: 1.5px solid #e8e0f8;
    background: white;
    font-size: 0.75rem;
    font-weight: 600;
    color: #7060a0;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
}
.rating-pill:hover, .rating-pill.active { background: #fef3c7; border-color: #f59e0b; color: #92400e; }

@media (max-width: 768px) {
    .mp-wrap { flex-direction: column; height: auto; }
    .mp-panel { width: 100%; height: auto; }
    .mp-map-wrap { height: 60vh; }
}
</style>

<div class="mp-wrap">
    <!-- LEFT: Panel -->
    <div class="mp-panel" id="mp-panel">
        <div class="mp-panel-top">
            <!-- Search -->
            <form method="GET" id="searchForm" class="mp-search">
                <input type="hidden" name="category" value="<?= e($category) ?>">
                <input type="hidden" name="sort" value="<?= e($sort) ?>">
                <input type="hidden" name="rating" value="<?= $rating ?>">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Friseur, Massage, Nagels..." id="searchInput">
                <input type="text" name="location" value="<?= e($location) ?>" placeholder="Stadt..." style="width:100px;flex-shrink:0;">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>

            <!-- Category Chips -->
            <div class="cat-chips">
                <a href="?q=<?= urlencode($search) ?>&location=<?= urlencode($location) ?>&sort=<?= urlencode($sort) ?>" class="cat-chip <?= !$category?'active':'' ?>">
                    <i class="fas fa-th-large"></i> Alle
                </a>
                <?php foreach ($categories as $cat): ?>
                <a href="?q=<?= urlencode($search) ?>&location=<?= urlencode($location) ?>&category=<?= urlencode($cat['slug']) ?>&sort=<?= urlencode($sort) ?>" class="cat-chip <?= $category===$cat['slug']?'active':'' ?>">
                    <i class="fas <?= e($cat['icon']?:'fa-star') ?>"></i> <?= e($cat['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Results -->
        <div class="mp-results" id="mp-results">
            <div class="mp-results-header">
                <span id="results-count">
                    <strong><?= $totalResults ?></strong> Ergebnisse<?= $search ? ' für "'.e($search).'"' : '' ?>
                </span>
                <div style="display:flex;gap:4px;">
                    <?php
                    $sorts = ['relevance'=>'Relevanz','rating'=>'Bewertung','newest'=>'Neu','price'=>'Preis'];
                    foreach ($sorts as $sv => $sl):
                    ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>$sv,'page'=>1])) ?>" class="mp-sort-btn <?= $sort===$sv?'active':'' ?>"><?= $sl ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Rating filters -->
            <div style="display:flex;gap:5px;margin-bottom:10px;flex-wrap:wrap;">
                <a href="?<?= http_build_query(array_merge($_GET, ['rating'=>0,'page'=>1])) ?>" class="rating-pill <?= $rating===0?'active':'' ?>">Alle</a>
                <?php for($r=4;$r>=3;$r--): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['rating'=>$r,'page'=>1])) ?>" class="rating-pill <?= $rating===$r?'active':'' ?>">
                    <?php for($s=0;$s<$r;$s++): ?><i class="fas fa-star" style="color:#f59e0b;font-size:0.65rem;"></i><?php endfor; ?> +
                </a>
                <?php endfor; ?>
            </div>

            <?php if (empty($businesses)): ?>
            <div style="text-align:center;padding:60px 20px;">
                <div style="font-size:3rem;margin-bottom:16px;opacity:0.3;">🔍</div>
                <h3 style="color:#9080b0;font-size:1rem;">Keine Ergebnisse</h3>
                <p style="color:#b0a0c0;font-size:0.85rem;">Versuche andere Suchbegriffe oder Filter.</p>
                <a href="/marketplace.php" class="btn btn-ghost btn-sm" style="margin-top:12px;">Filter zurücksetzen</a>
            </div>
            <?php else: ?>

            <?php foreach ($businesses as $biz): ?>
            <a class="mp-card" href="/business.php?slug=<?= e($biz['slug']) ?>" data-id="<?= $biz['id'] ?>" data-lat="<?= $biz['latitude'] ?>" data-lng="<?= $biz['longitude'] ?>" onclick="highlightMarker(<?= $biz['id'] ?>)">
                <div class="mp-card-img">
                    <?php if ($biz['cover_image']): ?>
                    <img src="<?= e($biz['cover_image']) ?>" alt="<?= e($biz['name']) ?>" loading="lazy">
                    <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><i class="fas fa-store" style="font-size:2rem;color:rgba(180,80,120,0.2);"></i></div>
                    <?php endif; ?>
                    <?php if ($biz['category_names']): ?>
                    <div class="mp-card-cat"><?= e(explode(',', $biz['category_names'])[0]) ?></div>
                    <?php endif; ?>
                </div>
                <div class="mp-card-body">
                    <div class="mp-card-name">
                        <?= e($biz['name']) ?>
                        <?php if ($biz['is_verified']): ?><i class="fas fa-check-circle" style="color:var(--success);font-size:0.65rem;"></i><?php endif; ?>
                    </div>
                    <div class="mp-card-loc"><i class="fas fa-map-marker-alt"></i> <?= e($biz['city'] ?? 'Österreich') ?></div>
                    <div class="mp-card-rating">
                        <span class="stars">
                            <?php for($s=1;$s<=5;$s++): ?><i class="fas fa-star" style="color:<?= $s<=round($biz['avg_rating'])?'#f59e0b':'#e5e0f0' ?>;"></i><?php endfor; ?>
                        </span>
                        <span style="color:#9080b0;"><?= number_format($biz['avg_rating'],1) ?> (<?= $biz['review_count'] ?>)</span>
                    </div>
                    <?php if ($biz['min_price']): ?>
                    <div class="mp-card-price">ab <strong>€<?= number_format($biz['min_price'],0,',','.') ?></strong></div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div style="display:flex;gap:4px;justify-content:center;padding:12px 0;">
                <?php if ($page>1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="mp-sort-btn"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
                <a href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>" class="mp-sort-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($page<$totalPages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="mp-sort-btn"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- Panel Toggle -->
    <div class="panel-toggle" id="panelToggle" onclick="togglePanel()">
        <i class="fas fa-chevron-left" id="toggleIcon"></i>
    </div>

    <!-- RIGHT: Map -->
    <div class="mp-map-wrap">
        <!-- Locate Me -->
        <button id="locate-btn" onclick="locateMe()">
            <i class="fas fa-crosshairs"></i> Meinen Standort verwenden
        </button>

        <!-- Map Toolbar -->
        <div class="map-toolbar">
            <div class="map-tool-btn active" id="btn-pointer" onclick="setTool('pointer')" title="">
                <i class="fas fa-mouse-pointer"></i>
                <span class="tooltip">Auswählen</span>
            </div>
            <div class="map-tool-btn" id="btn-draw" onclick="setTool('draw')">
                <i class="fas fa-pen"></i>
                <span class="tooltip">Radius zeichnen</span>
            </div>
            <div class="map-tool-btn" id="btn-clear" onclick="clearRadius()" style="display:none;">
                <i class="fas fa-times"></i>
                <span class="tooltip">Radius löschen</span>
            </div>
            <div class="map-tool-btn" id="btn-zoom-in" onclick="mpMap.zoomIn()">
                <i class="fas fa-plus"></i>
                <span class="tooltip">Vergrößern</span>
            </div>
            <div class="map-tool-btn" id="btn-zoom-out" onclick="mpMap.zoomOut()">
                <i class="fas fa-minus"></i>
                <span class="tooltip">Verkleinern</span>
            </div>
        </div>

        <!-- Radius Info -->
        <div id="radius-info">
            <i class="fas fa-circle-dot"></i>
            <span id="radius-text">0 km Radius · 0 Unternehmen</span>
            <button onclick="clearRadius()"><i class="fas fa-times"></i></button>
        </div>

        <div id="mp-map"></div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ── Business Data ──────────────────────────────────
var allBusinesses = <?= json_encode(array_map(fn($b) => [
    'id'          => $b['id'],
    'name'        => $b['name'],
    'slug'        => $b['slug'],
    'city'        => $b['city'] ?? '',
    'lat'         => (float)($b['latitude'] ?? 0),
    'lng'         => (float)($b['longitude'] ?? 0),
    'avg_rating'  => (float)$b['avg_rating'],
    'review_count'=> (int)$b['review_count'],
    'min_price'   => $b['min_price'] ? (float)$b['min_price'] : null,
    'categories'  => $b['category_names'] ?? '',
    'cover_image' => $b['cover_image'] ?? null,
], $mapBusinesses)) ?>;

// ── Map Init ──────────────────────────────────────
var mpMap = L.map('mp-map', { zoomControl: false }).setView([47.8, 13.0], 7);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
}).addTo(mpMap);

// Custom marker icon
function makeIcon(active) {
    return L.divIcon({
        className: '',
        html: `<div style="width:32px;height:32px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:${active?'#2d1b4e':'linear-gradient(135deg,#b45078,#c06090)'};border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.25);"></div>`,
        iconSize: [32,32],
        iconAnchor: [16,32],
        popupAnchor: [0,-34],
    });
}

// Add markers
var markers = {};
allBusinesses.forEach(function(biz) {
    if (!biz.lat || !biz.lng) return;
    var m = L.marker([biz.lat, biz.lng], { icon: makeIcon(false) })
        .addTo(mpMap)
        .bindPopup(`
            <a href="/business.php?slug=${biz.slug}" style="text-decoration:none;color:inherit;display:block;min-width:180px;">
                ${biz.cover_image ? `<img src="${biz.cover_image}" style="width:100%;height:90px;object-fit:cover;border-radius:8px;margin-bottom:8px;">` : ''}
                <strong style="font-size:0.9rem;">${biz.name}</strong><br>
                <span style="font-size:0.78rem;color:#9080b0;"><i class="fas fa-map-marker-alt"></i> ${biz.city}</span><br>
                <span style="font-size:0.78rem;color:#f59e0b;">${'★'.repeat(Math.round(biz.avg_rating))} ${biz.avg_rating.toFixed(1)}</span>
                ${biz.min_price ? `<br><span style="font-size:0.78rem;">ab <strong>€${biz.min_price.toFixed(0)}</strong></span>` : ''}
                <div style="margin-top:8px;"><span style="background:linear-gradient(135deg,#b45078,#c06090);color:white;padding:4px 12px;border-radius:8px;font-size:0.75rem;font-weight:600;">Profil ansehen →</span></div>
            </a>
        `, { maxWidth: 220 })
        .on('click', function() { highlightCard(biz.id); });
    markers[biz.id] = { marker: m, data: biz };
});

// ── Panel Toggle ──────────────────────────────────
var panelOpen = true;
function togglePanel() {
    panelOpen = !panelOpen;
    document.getElementById('mp-panel').classList.toggle('collapsed', !panelOpen);
    document.getElementById('panelToggle').style.left = panelOpen ? '420px' : '0';
    document.getElementById('toggleIcon').className = panelOpen ? 'fas fa-chevron-left' : 'fas fa-chevron-right';
    setTimeout(function(){ mpMap.invalidateSize(); }, 320);
}

// ── Locate Me ─────────────────────────────────────
var userMarker = null;
function locateMe() {
    if (!navigator.geolocation) { alert('Geolokalisierung nicht verfügbar.'); return; }
    document.getElementById('locate-btn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Standort wird ermittelt...';
    navigator.geolocation.getCurrentPosition(function(pos) {
        var lat = pos.coords.latitude;
        var lng = pos.coords.longitude;
        if (userMarker) mpMap.removeLayer(userMarker);
        userMarker = L.circleMarker([lat,lng], {
            radius: 8, fillColor: '#6366f1', color: 'white', weight: 3, fillOpacity: 1
        }).addTo(mpMap).bindPopup('<strong>Du bist hier</strong>');
        mpMap.setView([lat,lng], 13);
        document.getElementById('locate-btn').innerHTML = '<i class="fas fa-crosshairs"></i> Mein Standort';
    }, function() {
        document.getElementById('locate-btn').innerHTML = '<i class="fas fa-crosshairs"></i> Standort nicht verfügbar';
    });
}

// ── Draw Tool (Pen / Radius Circle) ───────────────
var currentTool = 'pointer';
var drawState   = null; // null | 'drawing'
var radiusCircle = null;
var radiusCenter = null;

function setTool(tool) {
    currentTool = tool;
    document.querySelectorAll('.map-tool-btn').forEach(function(b) { b.classList.remove('active'); });
    document.getElementById('btn-' + tool).classList.add('active');
    mpMap.getContainer().style.cursor = tool === 'draw' ? 'crosshair' : '';

    if (tool === 'draw') {
        mpMap.dragging.disable();
    } else {
        mpMap.dragging.enable();
        if (drawState === 'drawing') {
            drawState = null;
        }
    }
}

function clearRadius() {
    if (radiusCircle) { mpMap.removeLayer(radiusCircle); radiusCircle = null; }
    radiusCenter = null;
    drawState    = null;
    document.getElementById('radius-info').style.display = 'none';
    document.getElementById('btn-clear').style.display   = 'none';
    // Show all markers again
    Object.values(markers).forEach(function(m) {
        if (!mpMap.hasLayer(m.marker)) m.marker.addTo(mpMap);
    });
    document.getElementById('results-count').innerHTML = '<strong><?= $totalResults ?></strong> Ergebnisse';
}

// Map mouse events for drawing
mpMap.on('mousedown', function(e) {
    if (currentTool !== 'draw') return;
    if (radiusCircle) { mpMap.removeLayer(radiusCircle); }
    radiusCenter = e.latlng;
    drawState    = 'drawing';
    radiusCircle = L.circle(radiusCenter, {
        radius: 1,
        color: '#b45078',
        fillColor: '#b45078',
        fillOpacity: 0.12,
        weight: 2,
        dashArray: '6,4',
    }).addTo(mpMap);
});

mpMap.on('mousemove', function(e) {
    if (drawState !== 'drawing' || !radiusCenter || !radiusCircle) return;
    var dist = radiusCenter.distanceTo(e.latlng);
    radiusCircle.setRadius(dist);
    var km = (dist / 1000).toFixed(1);
    var inRadius = countInRadius(radiusCenter, dist);
    document.getElementById('radius-info').style.display = 'flex';
    document.getElementById('radius-text').textContent = km + ' km Radius · ' + inRadius + ' Unternehmen';
});

mpMap.on('mouseup', function(e) {
    if (drawState !== 'drawing' || !radiusCenter || !radiusCircle) return;
    drawState = null;
    mpMap.dragging.enable();
    var finalRadius = radiusCircle.getRadius();
    filterByRadius(radiusCenter, finalRadius);
    document.getElementById('btn-clear').style.display = 'flex';
    setTool('pointer');
});

function countInRadius(center, radius) {
    return Object.values(markers).filter(function(m) {
        return m.data.lat && m.data.lng && center.distanceTo(L.latLng(m.data.lat, m.data.lng)) <= radius;
    }).length;
}

function filterByRadius(center, radius) {
    var count = 0;
    Object.values(markers).forEach(function(m) {
        var dist = (m.data.lat && m.data.lng) ? center.distanceTo(L.latLng(m.data.lat, m.data.lng)) : Infinity;
        if (dist <= radius) { if (!mpMap.hasLayer(m.marker)) m.marker.addTo(mpMap); count++; }
        else { if (mpMap.hasLayer(m.marker)) mpMap.removeLayer(m.marker); }
    });
    var km = (radius/1000).toFixed(1);
    document.getElementById('results-count').innerHTML = '<strong>' + count + '</strong> im Radius von ' + km + ' km';
    document.getElementById('radius-text').textContent = km + ' km Radius · ' + count + ' Unternehmen';
}

// Touch support for mobile
mpMap.on('touchstart', function(e) {
    if (currentTool !== 'draw') return;
    var touch = e.touches[0];
    var latlng = mpMap.containerPointToLatLng(L.point(touch.clientX - mpMap.getContainer().getBoundingClientRect().left, touch.clientY - mpMap.getContainer().getBoundingClientRect().top));
    if (radiusCircle) mpMap.removeLayer(radiusCircle);
    radiusCenter = latlng;
    drawState = 'drawing';
    radiusCircle = L.circle(radiusCenter, { radius:1, color:'#b45078', fillColor:'#b45078', fillOpacity:0.12, weight:2, dashArray:'6,4' }).addTo(mpMap);
});
mpMap.on('touchmove', function(e) {
    if (drawState !== 'drawing' || !radiusCenter) return;
    e.originalEvent.preventDefault();
    var touch = e.touches[0];
    var latlng = mpMap.containerPointToLatLng(L.point(touch.clientX - mpMap.getContainer().getBoundingClientRect().left, touch.clientY - mpMap.getContainer().getBoundingClientRect().top));
    var dist = radiusCenter.distanceTo(latlng);
    radiusCircle.setRadius(dist);
    var km = (dist/1000).toFixed(1);
    document.getElementById('radius-info').style.display = 'flex';
    document.getElementById('radius-text').textContent = km + ' km · ' + countInRadius(radiusCenter,dist) + ' Unternehmen';
});
mpMap.on('touchend', function(e) {
    if (drawState !== 'drawing' || !radiusCircle) return;
    drawState = null;
    filterByRadius(radiusCenter, radiusCircle.getRadius());
    document.getElementById('btn-clear').style.display = 'flex';
    setTool('pointer');
});

// ── Card Highlight ─────────────────────────────────
function highlightMarker(id) {
    if (markers[id] && markers[id].data.lat) {
        mpMap.setView([markers[id].data.lat, markers[id].data.lng], 15);
        markers[id].marker.openPopup();
    }
}
function highlightCard(id) {
    document.querySelectorAll('.mp-card').forEach(function(c){ c.classList.remove('active'); });
    var card = document.querySelector('.mp-card[data-id="'+id+'"]');
    if (card) {
        card.classList.add('active');
        card.scrollIntoView({ behavior:'smooth', block:'nearest' });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
