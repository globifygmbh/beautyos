<?php
$pageTitle = 'Startseite';
require_once 'includes/header.php';

$db = getDB();

// Featured businesses
$featured = $db->query("
    SELECT b.*,
           GROUP_CONCAT(c.name) as category_names,
           COALESCE(AVG(r.rating), 0) as avg_rating,
           COUNT(DISTINCT r.id) as review_count
    FROM businesses b
    LEFT JOIN business_categories bc ON b.id = bc.business_id
    LEFT JOIN categories c ON bc.category_id = c.id
    LEFT JOIN reviews r ON b.id = r.business_id
    WHERE b.status = 'active'
    GROUP BY b.id
    ORDER BY b.is_verified DESC, b.subscription_plan_id DESC
    LIMIT 6
")->fetchAll();

// Categories
$categories = $db->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content animate-slide-up">
        <h1>Finde deinen perfekten <span class="highlight">Beauty-Termin</span></h1>
        <p>Entdecke die besten Friseure, Nagelstudios, Kosmetiker und mehr in deiner Nähe. Buche online, einfach und schnell.</p>

        <form action="/marketplace.php" method="GET" class="search-bar">
            <div class="search-bar-group">
                <i class="fas fa-search"></i>
                <input type="text" name="q" placeholder="Was suchst du? z.B. Balayage, Maniküre...">
            </div>
            <div class="search-bar-divider"></div>
            <div class="search-bar-group">
                <i class="fas fa-map-marker-alt"></i>
                <input type="text" name="location" placeholder="Stadt oder PLZ">
            </div>
            <button type="submit" class="btn btn-primary">Suchen</button>
        </form>
    </div>
</section>

<!-- Categories -->
<section class="section" style="background: var(--gray-50);">
    <div class="container">
        <div class="section-header">
            <div>
                <h2>Kategorien</h2>
                <p>Finde genau das, was du suchst</p>
            </div>
        </div>
        <div class="categories-grid stagger">
            <?php
            $icons = [
                'friseur' => 'fa-scissors',
                'nagelstudio' => 'fa-hand-sparkles',
                'kosmetik' => 'fa-spa',
                'barbershop' => 'fa-cut',
                'waxing' => 'fa-leaf',
                'massage' => 'fa-hands',
                'permanent-makeup' => 'fa-eye',
                'wimpern-brauen' => 'fa-eye-dropper',
            ];
            foreach ($categories as $cat):
                $icon = $icons[$cat['slug']] ?? 'fa-star';
            ?>
            <a href="/marketplace.php?category=<?= e($cat['slug']) ?>" class="category-card">
                <div class="category-icon">
                    <i class="fas <?= $icon ?>"></i>
                </div>
                <span><?= e($cat['name']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Businesses -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <div>
                <h2>Beliebte Salons</h2>
                <p>Von unserer Community empfohlen</p>
            </div>
            <a href="/marketplace.php" class="btn btn-outline btn-sm">Alle ansehen <i class="fas fa-arrow-right" style="margin-left: 4px;"></i></a>
        </div>
        <div class="marketplace-grid stagger">
            <?php if (empty($featured)): ?>
                <!-- Demo Cards wenn DB leer -->
                <?php
                $demoCards = [
                    ['name' => 'Salon Elegance', 'city' => 'Berlin Mitte', 'cat' => 'Friseur', 'rating' => 4.8, 'reviews' => 124, 'price' => 'Ab 45'],
                    ['name' => 'Nail Art Studio', 'city' => 'Hamburg', 'cat' => 'Nagelstudio', 'rating' => 4.9, 'reviews' => 89, 'price' => 'Ab 35'],
                    ['name' => 'Glow Kosmetik', 'city' => 'München', 'cat' => 'Kosmetik', 'rating' => 4.7, 'reviews' => 67, 'price' => 'Ab 55'],
                    ['name' => 'The Barber Club', 'city' => 'Köln', 'cat' => 'Barbershop', 'rating' => 4.9, 'reviews' => 203, 'price' => 'Ab 25'],
                    ['name' => 'Lash & Brow Bar', 'city' => 'Frankfurt', 'cat' => 'Wimpern & Brauen', 'rating' => 4.6, 'reviews' => 45, 'price' => 'Ab 40'],
                    ['name' => 'Zen Massage', 'city' => 'Düsseldorf', 'cat' => 'Massage', 'rating' => 4.8, 'reviews' => 156, 'price' => 'Ab 60'],
                ];
                $colors = ['#f5d5e5', '#d5e8f5', '#e8f5d5', '#f5ead5', '#e5d5f5', '#d5f5eb'];
                foreach ($demoCards as $i => $demo):
                ?>
                <div class="business-card">
                    <div class="card-image">
                        <div style="width:100%;height:100%;background:<?= $colors[$i] ?>;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-store" style="font-size:3rem;color:rgba(0,0,0,0.1);"></i>
                        </div>
                        <span class="card-badge"><?= $demo['cat'] ?></span>
                        <button class="card-favorite"><i class="far fa-heart"></i></button>
                    </div>
                    <div class="card-body">
                        <div class="business-name"><?= $demo['name'] ?></div>
                        <div class="business-location"><i class="fas fa-map-marker-alt"></i> <?= $demo['city'] ?></div>
                        <div class="business-rating">
                            <span class="stars">
                                <?php for ($s = 0; $s < 5; $s++): ?>
                                    <i class="fas fa-star<?= $s < floor($demo['rating']) ? '' : ($s < $demo['rating'] ? '-half-alt' : '' ) ?>"></i>
                                <?php endfor; ?>
                            </span>
                            <span class="rating-text"><?= $demo['rating'] ?> (<?= $demo['reviews'] ?> Bewertungen)</span>
                        </div>
                        <div class="business-price"><?= $demo['price'] ?> &euro;</div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($featured as $biz): ?>
                <a href="/business.php?slug=<?= e($biz['slug']) ?>" class="business-card">
                    <div class="card-image">
                        <?php if ($biz['cover_image']): ?>
                            <img src="<?= e($biz['cover_image']) ?>" alt="<?= e($biz['name']) ?>">
                        <?php else: ?>
                            <div style="width:100%;height:100%;background:var(--beige);display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-store" style="font-size:3rem;color:rgba(0,0,0,0.1);"></i>
                            </div>
                        <?php endif; ?>
                        <?php if ($biz['category_names']): ?>
                            <span class="card-badge"><?= e(explode(',', $biz['category_names'])[0]) ?></span>
                        <?php endif; ?>
                        <?php if ($biz['featured_badge'] ?? false): ?>
                            <span class="featured-badge" style="position:absolute;top:12px;right:12px;">Featured</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="business-name"><?= e($biz['name']) ?></div>
                        <div class="business-location"><i class="fas fa-map-marker-alt"></i> <?= e($biz['city']) ?></div>
                        <div class="business-rating">
                            <span class="stars">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <i class="fas fa-star" style="color: <?= $s <= round($biz['avg_rating']) ? 'var(--warning)' : 'var(--gray-300)' ?>"></i>
                                <?php endfor; ?>
                            </span>
                            <span class="rating-text"><?= number_format($biz['avg_rating'], 1) ?> (<?= $biz['review_count'] ?>)</span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- How it works -->
<section class="section" style="background: var(--gray-50);">
    <div class="container">
        <div class="section-header">
            <div>
                <h2>So funktioniert's</h2>
                <p>In 3 einfachen Schritten zum Termin</p>
            </div>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 32px;" class="stagger">
            <div style="text-align: center; padding: 32px;">
                <div style="width: 72px; height: 72px; border-radius: 50%; background: var(--primary-light); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 1.5rem; color: var(--primary-dark);">
                    <i class="fas fa-search"></i>
                </div>
                <h4 style="margin-bottom: 8px;">1. Suchen</h4>
                <p style="color: var(--gray-500); font-size: 0.95rem;">Durchsuche hunderte Beauty-Anbieter nach Kategorie, Standort oder Service.</p>
            </div>
            <div style="text-align: center; padding: 32px;">
                <div style="width: 72px; height: 72px; border-radius: 50%; background: var(--secondary-light); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 1.5rem; color: var(--secondary);">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h4 style="margin-bottom: 8px;">2. Buchen</h4>
                <p style="color: var(--gray-500); font-size: 0.95rem;">Wähle deinen Wunschtermin und buche direkt online - rund um die Uhr.</p>
            </div>
            <div style="text-align: center; padding: 32px;">
                <div style="width: 72px; height: 72px; border-radius: 50%; background: var(--accent-light); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 1.5rem; color: var(--accent);">
                    <i class="fas fa-heart"></i>
                </div>
                <h4 style="margin-bottom: 8px;">3. Geniessen</h4>
                <p style="color: var(--gray-500); font-size: 0.95rem;">Lehn dich zurück und geniesse deinen Beauty-Termin. Bewerte danach deinen Besuch.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA for businesses -->
<section class="section">
    <div class="container">
        <div style="background: linear-gradient(135deg, var(--primary-50), var(--beige)); border-radius: var(--radius-xl); padding: 60px; text-align: center;">
            <h2 style="margin-bottom: 16px;">Du hast ein Beauty-Business?</h2>
            <p style="color: var(--gray-600); max-width: 500px; margin: 0 auto 32px; font-size: 1.05rem;">Werde Teil von BeautyOS und erreiche tausende neue Kunden. Starte jetzt mit deinem kostenlosen Profil.</p>
            <a href="/register.php?type=business" class="btn btn-primary btn-lg">Jetzt Business registrieren <i class="fas fa-arrow-right" style="margin-left: 8px;"></i></a>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
