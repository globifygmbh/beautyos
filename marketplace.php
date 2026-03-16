<?php
$pageTitle = 'Marktplatz';
require_once 'includes/header.php';

$db = getDB();

// Filter parameters
$search = trim($_GET['q'] ?? '');
$location = trim($_GET['location'] ?? '');
$category = trim($_GET['category'] ?? '');
$rating = (int)($_GET['rating'] ?? 0);
$sort = $_GET['sort'] ?? 'relevance';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["b.status = 'active'"];
$params = [];

if ($search) {
    $where[] = "(b.name LIKE ? OR b.description LIKE ? OR s.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($location) {
    $where[] = "(b.city LIKE ? OR b.zip_code LIKE ?)";
    $params[] = "%$location%";
    $params[] = "%$location%";
}

if ($category) {
    $where[] = "c.slug = ?";
    $params[] = $category;
}

if ($rating > 0) {
    $where[] = "COALESCE(avg_r.avg_rating, 0) >= ?";
    $params[] = $rating;
}

$whereSQL = implode(' AND ', $where);

$orderBy = match($sort) {
    'rating' => 'avg_r.avg_rating DESC',
    'name' => 'b.name ASC',
    'newest' => 'b.created_at DESC',
    default => 'b.subscription_plan_id DESC, b.is_verified DESC, avg_r.avg_rating DESC',
};

// Count total
$countSQL = "
    SELECT COUNT(DISTINCT b.id)
    FROM businesses b
    LEFT JOIN business_categories bc ON b.id = bc.business_id
    LEFT JOIN categories c ON bc.category_id = c.id
    LEFT JOIN services s ON b.id = s.business_id
    LEFT JOIN (SELECT business_id, AVG(rating) as avg_rating FROM reviews GROUP BY business_id) avg_r ON b.id = avg_r.business_id
    WHERE $whereSQL
";
$stmt = $db->prepare($countSQL);
$stmt->execute($params);
$totalResults = $stmt->fetchColumn();
$totalPages = ceil($totalResults / $perPage);

// Fetch businesses
$sql = "
    SELECT b.*,
           GROUP_CONCAT(DISTINCT c.name) as category_names,
           COALESCE(avg_r.avg_rating, 0) as avg_rating,
           COALESCE(avg_r.review_count, 0) as review_count,
           MIN(s.price) as min_price
    FROM businesses b
    LEFT JOIN business_categories bc ON b.id = bc.business_id
    LEFT JOIN categories c ON bc.category_id = c.id
    LEFT JOIN services s ON b.id = s.business_id
    LEFT JOIN (SELECT business_id, AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews GROUP BY business_id) avg_r ON b.id = avg_r.business_id
    WHERE $whereSQL
    GROUP BY b.id
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$businesses = $stmt->fetchAll();

// Categories for filter
$categories = $db->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();
?>

<section class="section" style="padding-top: 32px;">
    <div class="container container-lg">

        <!-- Search & View Toggle -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
            <div>
                <h2 style="margin-bottom: 4px;">Marktplatz</h2>
                <p style="color: var(--gray-500);"><?= $totalResults ?> Ergebnisse<?= $search ? ' für "' . e($search) . '"' : '' ?><?= $location ? ' in ' . e($location) : '' ?></p>
            </div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="view-toggle">
                    <button class="active" onclick="toggleView('grid')"><i class="fas fa-grip"></i> Liste</button>
                    <button onclick="toggleView('map')"><i class="fas fa-map"></i> Karte</button>
                </div>
                <select class="form-control" style="width: auto; padding: 8px 36px 8px 12px;" onchange="window.location.href=updateParam('sort', this.value)">
                    <option value="relevance" <?= $sort === 'relevance' ? 'selected' : '' ?>>Relevanz</option>
                    <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Beste Bewertung</option>
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Neueste</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A-Z</option>
                </select>
            </div>
        </div>

        <div class="marketplace-layout">
            <!-- Filters Sidebar -->
            <aside class="filters-sidebar">
                <form method="GET" id="filterForm">
                    <input type="hidden" name="q" value="<?= e($search) ?>">
                    <input type="hidden" name="location" value="<?= e($location) ?>">
                    <input type="hidden" name="sort" value="<?= e($sort) ?>">

                    <!-- Search -->
                    <div class="filter-group">
                        <h4><i class="fas fa-search"></i> Suche</h4>
                        <input type="text" name="q" class="form-control" placeholder="Suchbegriff..." value="<?= e($search) ?>" style="margin-bottom: 8px;">
                        <input type="text" name="location" class="form-control" placeholder="Stadt oder PLZ..." value="<?= e($location) ?>">
                    </div>

                    <!-- Categories -->
                    <div class="filter-group">
                        <h4><i class="fas fa-tag"></i> Kategorie</h4>
                        <?php foreach ($categories as $cat): ?>
                        <label class="filter-option">
                            <input type="radio" name="category" value="<?= e($cat['slug']) ?>" <?= $category === $cat['slug'] ? 'checked' : '' ?>>
                            <?= e($cat['name']) ?>
                        </label>
                        <?php endforeach; ?>
                        <label class="filter-option">
                            <input type="radio" name="category" value="" <?= !$category ? 'checked' : '' ?>>
                            Alle Kategorien
                        </label>
                    </div>

                    <!-- Rating -->
                    <div class="filter-group">
                        <h4><i class="fas fa-star"></i> Mindestbewertung</h4>
                        <?php for ($r = 4; $r >= 1; $r--): ?>
                        <label class="filter-option">
                            <input type="radio" name="rating" value="<?= $r ?>" <?= $rating === $r ? 'checked' : '' ?>>
                            <?php for ($s = 0; $s < $r; $s++): ?><i class="fas fa-star" style="color: var(--warning); font-size: 0.8rem;"></i><?php endfor; ?> & mehr
                        </label>
                        <?php endfor; ?>
                        <label class="filter-option">
                            <input type="radio" name="rating" value="0" <?= $rating === 0 ? 'checked' : '' ?>>
                            Alle
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter anwenden</button>
                </form>
            </aside>

            <!-- Results -->
            <div>
                <!-- Grid View -->
                <div id="gridView">
                    <?php if (empty($businesses)): ?>
                        <div style="text-align: center; padding: 80px 20px;">
                            <i class="fas fa-search" style="font-size: 3rem; color: var(--gray-300); margin-bottom: 16px;"></i>
                            <h3 style="color: var(--gray-500);">Keine Ergebnisse gefunden</h3>
                            <p style="color: var(--gray-400);">Versuche andere Suchbegriffe oder Filter.</p>
                        </div>
                    <?php else: ?>
                        <div class="marketplace-grid stagger">
                            <?php foreach ($businesses as $biz): ?>
                            <a href="/business.php?slug=<?= e($biz['slug']) ?>" class="business-card">
                                <div class="card-image">
                                    <?php if ($biz['cover_image']): ?>
                                        <img src="<?= e($biz['cover_image']) ?>" alt="<?= e($biz['name']) ?>">
                                    <?php else: ?>
                                        <div style="width:100%;height:100%;background: linear-gradient(135deg, var(--primary-50), var(--beige));display:flex;align-items:center;justify-content:center;">
                                            <i class="fas fa-store" style="font-size:3rem;color:rgba(0,0,0,0.08);"></i>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($biz['category_names']): ?>
                                        <span class="card-badge"><?= e(explode(',', $biz['category_names'])[0]) ?></span>
                                    <?php endif; ?>
                                    <?php if ($biz['is_verified']): ?>
                                        <span class="featured-badge" style="position:absolute;top:12px;right:12px;"><i class="fas fa-check"></i> Verifiziert</span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <div class="business-name"><?= e($biz['name']) ?></div>
                                    <div class="business-location"><i class="fas fa-map-marker-alt"></i> <?= e($biz['city'] ?? 'Deutschland') ?></div>
                                    <div class="business-rating">
                                        <span class="stars">
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                <i class="fas fa-star" style="color: <?= $s <= round($biz['avg_rating']) ? 'var(--warning)' : 'var(--gray-300)' ?>"></i>
                                            <?php endfor; ?>
                                        </span>
                                        <span class="rating-text"><?= number_format($biz['avg_rating'], 1) ?> (<?= $biz['review_count'] ?>)</span>
                                    </div>
                                    <?php if ($biz['category_names']): ?>
                                    <div class="business-tags">
                                        <?php foreach (array_slice(explode(',', $biz['category_names']), 0, 3) as $tag): ?>
                                            <span class="tag"><?= e(trim($tag)) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($biz['min_price']): ?>
                                    <div class="business-price">Ab <strong><?= number_format($biz['min_price'], 2, ',', '.') ?> &euro;</strong></div>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"><i class="fas fa-chevron-left"></i></a>
                            <?php endif; ?>
                            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"><i class="fas fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Map View -->
                <div id="mapView" style="display: none;">
                    <div class="map-container">
                        <div id="map"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
window.businessesData = <?= json_encode(array_map(fn($b) => [
    'id' => $b['id'],
    'name' => $b['name'],
    'slug' => $b['slug'],
    'city' => $b['city'],
    'latitude' => (float)$b['latitude'],
    'longitude' => (float)$b['longitude'],
    'avg_rating' => (float)$b['avg_rating'],
], $businesses)) ?>;

function updateParam(key, value) {
    const url = new URL(window.location);
    url.searchParams.set(key, value);
    return url.toString();
}
</script>

<?php require_once 'includes/footer.php'; ?>
