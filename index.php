<?php
$pageTitle = 'Dein Beauty Marktplatz';
require_once 'includes/header.php';

$db = getDB();

// Featured businesses
$featured = $db->query("
    SELECT b.*,
           GROUP_CONCAT(c.name ORDER BY c.sort_order SEPARATOR ',') as category_names,
           COALESCE(AVG(r.rating), 0) as avg_rating,
           COUNT(DISTINCT r.id) as review_count
    FROM businesses b
    LEFT JOIN business_categories bc ON b.id = bc.business_id
    LEFT JOIN categories c ON bc.category_id = c.id
    LEFT JOIN reviews r ON b.id = r.business_id AND (r.hidden IS NULL OR r.hidden = 0)
    WHERE b.status = 'active'
    GROUP BY b.id
    ORDER BY b.is_verified DESC, b.subscription_plan_id DESC, avg_rating DESC
    LIMIT 6
")->fetchAll();

// Stats
$stats = [
    'businesses' => (int)$db->query("SELECT COUNT(*) FROM businesses WHERE status='active'")->fetchColumn(),
    'bookings'   => (int)$db->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'cities'     => (int)$db->query("SELECT COUNT(DISTINCT city) FROM businesses WHERE status='active'")->fetchColumn(),
];

// Categories with counts
$categories = $db->query("
    SELECT c.*, COUNT(DISTINCT bc.business_id) as biz_count
    FROM categories c
    LEFT JOIN business_categories bc ON c.id = bc.category_id
    LEFT JOIN businesses b ON bc.business_id = b.id AND b.status='active'
    GROUP BY c.id
    ORDER BY c.sort_order
")->fetchAll();
?>

<!-- ══════════════════════════════════════════
     HERO
══════════════════════════════════════════ -->
<section class="home-hero">
    <div class="home-hero-bg"></div>
    <div class="container">
        <div class="home-hero-inner">
            <div class="home-hero-content">
                <div class="home-hero-eyebrow">
                    <span class="eyebrow-dot"></span>
                    Der Beauty-Marktplatz für Österreich
                </div>
                <h1 class="home-hero-title">
                    Finde deinen<br>
                    <span class="home-hero-highlight">Wohlfühlmoment.</span>
                </h1>
                <p class="home-hero-sub">
                    Entdecke die besten Friseure, Nagelstudios und Beauty-Profis in deiner Nähe. Online buchen — in Sekunden.
                </p>
                <form action="/marketplace.php" method="GET" class="home-search">
                    <div class="home-search-field">
                        <i class="fas fa-search"></i>
                        <input type="text" name="q" placeholder="Balayage, Maniküre, Massage …">
                    </div>
                    <div class="home-search-sep"></div>
                    <div class="home-search-field">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" name="location" placeholder="Wien, Graz, Salzburg …">
                    </div>
                    <button type="submit" class="home-search-btn">
                        <i class="fas fa-search"></i>
                        Suchen
                    </button>
                </form>
                <!-- Quick links -->
                <div class="home-quicklinks">
                    <span>Beliebt:</span>
                    <?php
                    $ql = ['Friseur','Nagelstudio','Kosmetik','Massage','Barbershop'];
                    $qs = ['friseur','nagelstudio','kosmetik','massage','barbershop'];
                    foreach ($ql as $i => $name):
                    ?>
                    <a href="/marketplace.php?category=<?= $qs[$i] ?>"><?= $name ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="home-hero-visual">
                <!-- Floating stat cards -->
                <div class="hero-float-card hero-float-1">
                    <div class="hero-float-icon" style="background:linear-gradient(135deg,#d4809f,#e8a0bf);">
                        <i class="fas fa-store"></i>
                    </div>
                    <div>
                        <div class="hero-float-val"><?= $stats['businesses'] ?: '500+' ?></div>
                        <div class="hero-float-label">Anbieter</div>
                    </div>
                </div>
                <div class="hero-float-card hero-float-2">
                    <div class="hero-float-icon" style="background:linear-gradient(135deg,#d4a574,#e8c4a0);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div>
                        <div class="hero-float-val"><?= $stats['bookings'] ?: '2.400+' ?></div>
                        <div class="hero-float-label">Buchungen</div>
                    </div>
                </div>
                <div class="hero-float-card hero-float-3">
                    <div class="hero-float-icon" style="background:linear-gradient(135deg,#b8a9c9,#d4c9e4);">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div>
                        <div class="hero-float-val"><?= $stats['cities'] ?: '60+' ?></div>
                        <div class="hero-float-label">Städte</div>
                    </div>
                </div>

                <!-- Main visual card -->
                <div class="hero-main-card">
                    <div class="hero-main-card-img">
                        <div class="hero-img-placeholder">
                            <i class="fas fa-spa"></i>
                        </div>
                    </div>
                    <div class="hero-main-card-body">
                        <div class="hero-mc-name">Salon Elegance</div>
                        <div class="hero-mc-loc"><i class="fas fa-map-marker-alt"></i> Wien, 1010</div>
                        <div class="hero-mc-stars">
                            <?php for ($s = 0; $s < 5; $s++): ?>
                            <i class="fas fa-star" style="color:#ffa726;font-size:0.8rem;"></i>
                            <?php endfor; ?>
                            <span style="font-size:0.78rem;color:#9e9e9e;margin-left:4px;">4.9</span>
                        </div>
                        <div class="hero-mc-avail">
                            <span class="avail-dot"></span> Heute verfügbar
                        </div>
                        <button class="hero-mc-btn">Termin buchen</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════
     CATEGORIES
══════════════════════════════════════════ -->
<section class="home-section" style="background:#fff;">
    <div class="container">
        <div class="home-section-header">
            <div>
                <div class="section-eyebrow">Entdecken</div>
                <h2 class="section-title">Was suchst du?</h2>
            </div>
            <a href="/marketplace.php" class="btn btn-outline btn-sm">Alle anzeigen <i class="fas fa-arrow-right" style="margin-left:6px;"></i></a>
        </div>
        <div class="home-cats-grid">
            <?php
            $catIcons = [
                'friseur'          => ['fa-scissors',       'linear-gradient(135deg,#f5d5e5,#fce4ec)'],
                'nagelstudio'      => ['fa-hand-sparkles',  'linear-gradient(135deg,#e8f5d5,#dcedc8)'],
                'kosmetik'         => ['fa-spa',            'linear-gradient(135deg,#e8e0f0,#ede7f6)'],
                'barbershop'       => ['fa-cut',            'linear-gradient(135deg,#d5e8f5,#e3f2fd)'],
                'waxing'           => ['fa-leaf',           'linear-gradient(135deg,#f5ead5,#fff8e1)'],
                'massage'          => ['fa-hands',          'linear-gradient(135deg,#d5f5eb,#e0f2f1)'],
                'permanent-makeup' => ['fa-eye',            'linear-gradient(135deg,#f5d5e5,#fce4ec)'],
                'wimpern-brauen'   => ['fa-eye-dropper',    'linear-gradient(135deg,#e5d5f5,#ede7f6)'],
            ];
            foreach ($categories as $cat):
                [$icon, $bg] = $catIcons[$cat['slug']] ?? ['fa-star', 'linear-gradient(135deg,#f5f0eb,#fff)'];
            ?>
            <a href="/marketplace.php?category=<?= e($cat['slug']) ?>" class="home-cat-card">
                <div class="home-cat-icon" style="background:<?= $bg ?>;">
                    <i class="fas <?= $icon ?>"></i>
                </div>
                <div class="home-cat-name"><?= e($cat['name']) ?></div>
                <?php if ($cat['biz_count'] > 0): ?>
                <div class="home-cat-count"><?= $cat['biz_count'] ?> Anbieter</div>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════
     FEATURED BUSINESSES
══════════════════════════════════════════ -->
<section class="home-section" style="background:var(--beige);">
    <div class="container">
        <div class="home-section-header">
            <div>
                <div class="section-eyebrow">Top-Picks</div>
                <h2 class="section-title">Beliebt in deiner Stadt</h2>
            </div>
            <a href="/marketplace.php" class="btn btn-outline btn-sm">Alle ansehen <i class="fas fa-arrow-right" style="margin-left:6px;"></i></a>
        </div>
        <div class="home-biz-grid">
            <?php if (empty($featured)):
                $demoCards = [
                    ['name'=>'Salon Elegance','city'=>'Wien','cat'=>'Friseur','rating'=>4.8,'reviews'=>124,'verified'=>true],
                    ['name'=>'Nail Art Studio','city'=>'Graz','cat'=>'Nagelstudio','rating'=>4.9,'reviews'=>89,'verified'=>false],
                    ['name'=>'Glow Kosmetik','city'=>'Salzburg','cat'=>'Kosmetik','rating'=>4.7,'reviews'=>67,'verified'=>true],
                    ['name'=>'The Barber Club','city'=>'Linz','cat'=>'Barbershop','rating'=>4.9,'reviews'=>203,'verified'=>true],
                    ['name'=>'Lash & Brow Bar','city'=>'Innsbruck','cat'=>'Wimpern & Brauen','rating'=>4.6,'reviews'=>45,'verified'=>false],
                    ['name'=>'Zen Massage','city'=>'Klagenfurt','cat'=>'Massage','rating'=>4.8,'reviews'=>156,'verified'=>true],
                ];
                $demoColors = ['#f5d5e5','#e8f5d5','#e8e0f0','#d5e8f5','#f5ead5','#d5f5eb'];
                foreach ($demoCards as $i => $d):
            ?>
            <div class="home-biz-card">
                <div class="home-biz-img" style="background:<?= $demoColors[$i] ?>;">
                    <i class="fas fa-store" style="font-size:2rem;color:rgba(0,0,0,0.12);"></i>
                    <div class="home-biz-badge"><?= $d['cat'] ?></div>
                </div>
                <div class="home-biz-body">
                    <div class="home-biz-top">
                        <div class="home-biz-name"><?= $d['name'] ?> <?php if ($d['verified']): ?><i class="fas fa-circle-check" style="color:#42a5f5;font-size:0.85rem;"></i><?php endif; ?></div>
                        <div class="home-biz-loc"><i class="fas fa-map-marker-alt"></i> <?= $d['city'] ?></div>
                    </div>
                    <div class="home-biz-footer">
                        <div class="home-biz-stars">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                            <i class="fas fa-star" style="color:<?= $s <= round($d['rating']) ? '#ffa726' : '#e0e0e0' ?>;font-size:0.8rem;"></i>
                            <?php endfor; ?>
                            <span class="home-biz-rating"><?= $d['rating'] ?> <span style="color:#9e9e9e;font-weight:400;">(<?= $d['reviews'] ?>)</span></span>
                        </div>
                        <button class="home-biz-book">Buchen</button>
                    </div>
                </div>
            </div>
            <?php endforeach; else: foreach ($featured as $biz): ?>
            <a href="/business.php?slug=<?= e($biz['slug']) ?>" class="home-biz-card">
                <div class="home-biz-img" style="background:var(--beige-dark);">
                    <?php if ($biz['cover_image']): ?>
                    <img src="<?= e($biz['cover_image']) ?>" alt="<?= e($biz['name']) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                    <i class="fas fa-store" style="font-size:2rem;color:rgba(0,0,0,0.12);"></i>
                    <?php endif; ?>
                    <?php if ($biz['category_names']): ?>
                    <div class="home-biz-badge"><?= e(explode(',', $biz['category_names'])[0]) ?></div>
                    <?php endif; ?>
                </div>
                <div class="home-biz-body">
                    <div class="home-biz-top">
                        <div class="home-biz-name">
                            <?= e($biz['name']) ?>
                            <?php if ($biz['is_verified']): ?><i class="fas fa-circle-check" style="color:#42a5f5;font-size:0.85rem;"></i><?php endif; ?>
                        </div>
                        <div class="home-biz-loc"><i class="fas fa-map-marker-alt"></i> <?= e($biz['city']) ?></div>
                    </div>
                    <div class="home-biz-footer">
                        <div class="home-biz-stars">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                            <i class="fas fa-star" style="color:<?= $s <= round($biz['avg_rating']) ? '#ffa726' : '#e0e0e0' ?>;font-size:0.8rem;"></i>
                            <?php endfor; ?>
                            <span class="home-biz-rating"><?= number_format($biz['avg_rating'],1) ?> <span style="color:#9e9e9e;font-weight:400;">(<?= $biz['review_count'] ?>)</span></span>
                        </div>
                        <div class="home-biz-book">Profil ansehen</div>
                    </div>
                </div>
            </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════
     HOW IT WORKS
══════════════════════════════════════════ -->
<section class="home-section" style="background:#fff;">
    <div class="container">
        <div style="text-align:center;margin-bottom:48px;">
            <div class="section-eyebrow" style="justify-content:center;">In 3 Schritten</div>
            <h2 class="section-title" style="margin:8px auto 0;">So einfach geht's</h2>
        </div>
        <div class="home-steps">
            <div class="home-step">
                <div class="home-step-num">01</div>
                <div class="home-step-icon"><i class="fas fa-search"></i></div>
                <h3>Suchen</h3>
                <p>Durchsuche hunderte Beauty-Anbieter nach Kategorie, Standort oder Service. Nutze den Kartenradius für deine Umgebung.</p>
            </div>
            <div class="home-step-arrow"><i class="fas fa-arrow-right"></i></div>
            <div class="home-step">
                <div class="home-step-num">02</div>
                <div class="home-step-icon"><i class="fas fa-calendar-check"></i></div>
                <h3>Buchen</h3>
                <p>Wähle deinen Wunschtermin und buche direkt online — rund um die Uhr, ohne Telefonieren.</p>
            </div>
            <div class="home-step-arrow"><i class="fas fa-arrow-right"></i></div>
            <div class="home-step">
                <div class="home-step-num">03</div>
                <div class="home-step-icon"><i class="fas fa-heart"></i></div>
                <h3>Geniessen</h3>
                <p>Erscheine zu deinem Termin und genieße dein Wohlfühl-Erlebnis. Danach Bewertung hinterlassen.</p>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════
     MAP TEASER
══════════════════════════════════════════ -->
<section class="home-section home-map-teaser">
    <div class="container">
        <div class="home-map-inner">
            <div class="home-map-text">
                <div class="section-eyebrow">Kartenansicht</div>
                <h2 class="section-title">Finde Anbieter<br>in deiner Nähe.</h2>
                <p>Nutze unsere interaktive Karte mit Radius-Tool: Zeichne einfach einen Kreis und sieh alle Anbieter innerhalb deines gewünschten Bereichs.</p>
                <a href="/marketplace.php?view=map" class="btn btn-primary btn-lg" style="margin-top:8px;">
                    <i class="fas fa-map-marked-alt"></i> Karte öffnen
                </a>
            </div>
            <div class="home-map-preview">
                <div class="map-preview-frame">
                    <div class="map-preview-bg"></div>
                    <!-- Fake pins -->
                    <div class="map-pin" style="top:35%;left:40%;"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="map-pin" style="top:55%;left:55%;"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="map-pin" style="top:45%;left:65%;"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="map-pin" style="top:60%;left:32%;"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="map-pin map-pin-active" style="top:42%;left:48%;"><i class="fas fa-map-marker-alt"></i></div>
                    <!-- Radius circle -->
                    <div class="map-radius-circle"></div>
                    <div class="map-preview-pill"><i class="fas fa-draw-polygon"></i> Radius zeichnen</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════
     BUSINESS CTA
══════════════════════════════════════════ -->
<section class="home-section" style="background:var(--beige);">
    <div class="container">
        <div class="home-biz-cta">
            <div class="home-biz-cta-left">
                <div class="section-eyebrow">Für Unternehmen</div>
                <h2 class="section-title">Du hast ein<br>Beauty-Business?</h2>
                <p>Werde Teil von BeautyOS und erreiche tausende neue Kunden täglich. Kostenloses Profil, eigenes Buchungssystem und Analytics.</p>
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:28px;">
                    <a href="/register.php?type=business" class="btn btn-primary btn-lg">
                        <i class="fas fa-store"></i> Jetzt registrieren
                    </a>
                    <a href="/pricing.php" class="btn btn-outline btn-lg">
                        Preise ansehen
                    </a>
                </div>
            </div>
            <div class="home-biz-cta-features">
                <?php
                $features = [
                    ['fas fa-calendar-alt',    'Online-Buchungssystem',     'Kunden buchen 24/7 direkt auf deinem Profil'],
                    ['fas fa-chart-line',       'Statistiken & Analytics',   'Sieh wer dein Profil besucht und was gebucht wird'],
                    ['fas fa-star',             'Bewertungs-Management',     'Sammle & beantworte Kundenbewertungen'],
                    ['fas fa-crown',            'Featured-Platzierungen',    'Werde in Suchergebnissen prominent angezeigt'],
                ];
                foreach ($features as [$icon, $title, $desc]):
                ?>
                <div class="biz-feature">
                    <div class="biz-feature-icon"><i class="<?= $icon ?>"></i></div>
                    <div>
                        <div class="biz-feature-title"><?= $title ?></div>
                        <div class="biz-feature-desc"><?= $desc ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════
     PAGE-SPECIFIC STYLES
══════════════════════════════════════════ -->
<style>
/* ─── Hero ─────────────────────────────────── */
.home-hero {
    position: relative;
    background: linear-gradient(145deg, #fdf2f8 0%, #f5f0eb 50%, #ede5ff 100%);
    overflow: hidden;
    padding: 80px 0 60px;
    min-height: 640px;
    display: flex;
    align-items: center;
}
.home-hero-bg {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse 700px 500px at 80% 50%, rgba(232,160,191,0.14) 0%, transparent 70%),
        radial-gradient(ellipse 500px 400px at 10% 80%, rgba(184,169,201,0.1) 0%, transparent 70%);
    pointer-events: none;
}
.home-hero-inner {
    display: grid;
    grid-template-columns: 1fr 420px;
    gap: 60px;
    align-items: center;
}
.home-hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--primary-dark);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 20px;
}
.eyebrow-dot { width:8px; height:8px; border-radius:50%; background:var(--primary-dark); flex-shrink:0; }
.home-hero-title {
    font-family: var(--font-main);
    font-size: clamp(2.4rem, 5vw, 3.8rem);
    font-weight: 900;
    line-height: 1.05;
    color: var(--gray-900);
    margin: 0 0 20px;
    letter-spacing: -1.5px;
}
.home-hero-highlight {
    background: linear-gradient(135deg, var(--primary-dark), #c06090);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.home-hero-sub {
    font-size: 1.05rem;
    color: var(--gray-600);
    line-height: 1.7;
    margin-bottom: 36px;
    max-width: 500px;
}

/* Search bar */
.home-search {
    display: flex;
    align-items: center;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 40px rgba(180,80,120,0.15), 0 2px 12px rgba(0,0,0,0.06);
    padding: 6px 6px 6px 20px;
    gap: 0;
    max-width: 620px;
    margin-bottom: 20px;
}
.home-search-field {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
}
.home-search-field i { color: var(--primary-dark); font-size: 0.9rem; flex-shrink: 0; }
.home-search-field input {
    border: none;
    outline: none;
    background: transparent;
    font-size: 0.9rem;
    color: var(--gray-800);
    width: 100%;
    padding: 10px 0;
}
.home-search-field input::placeholder { color: var(--gray-400); }
.home-search-sep { width: 1px; height: 28px; background: var(--gray-200); flex-shrink: 0; margin: 0 12px; }
.home-search-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 22px;
    background: linear-gradient(135deg, var(--primary-dark), #c06090);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    flex-shrink: 0;
    transition: opacity 0.2s, transform 0.2s;
}
.home-search-btn:hover { opacity: 0.9; transform: scale(1.02); }

.home-quicklinks {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 0.82rem;
}
.home-quicklinks span { color: var(--gray-500); font-weight: 600; }
.home-quicklinks a {
    padding: 4px 12px;
    border-radius: 20px;
    border: 1px solid var(--gray-300);
    color: var(--gray-700);
    font-size: 0.8rem;
    transition: all 0.15s;
}
.home-quicklinks a:hover { border-color: var(--primary-dark); color: var(--primary-dark); background: var(--primary-50); }

/* Hero Visual */
.home-hero-visual {
    position: relative;
    height: 420px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.hero-float-card {
    position: absolute;
    background: #fff;
    border-radius: 16px;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    z-index: 10;
}
.hero-float-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1rem; flex-shrink: 0;
}
.hero-float-val { font-size: 1.2rem; font-weight: 800; color: var(--gray-900); line-height: 1; }
.hero-float-label { font-size: 0.72rem; color: #9e9e9e; font-weight: 600; }
.hero-float-1 { top: 10px; left: 0; animation: floatY 4s ease-in-out infinite; }
.hero-float-2 { bottom: 60px; left: 10px; animation: floatY 4s ease-in-out infinite 1.5s; }
.hero-float-3 { top: 30px; right: 0; animation: floatY 4s ease-in-out infinite 0.8s; }
@keyframes floatY {
    0%,100% { transform: translateY(0); }
    50%      { transform: translateY(-6px); }
}

.hero-main-card {
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(180,80,120,0.18), 0 4px 20px rgba(0,0,0,0.08);
    width: 240px;
    flex-shrink: 0;
    z-index: 5;
}
.hero-main-card-img {
    height: 160px;
    background: linear-gradient(135deg, var(--primary-light), var(--beige));
    display: flex; align-items: center; justify-content: center;
}
.hero-img-placeholder { font-size: 3rem; color: var(--primary-dark); opacity: 0.4; }
.hero-main-card-body { padding: 16px; }
.hero-mc-name { font-weight: 800; font-size: 0.95rem; color: var(--gray-900); margin-bottom: 4px; }
.hero-mc-loc { font-size: 0.78rem; color: #9e9e9e; margin-bottom: 8px; }
.hero-mc-stars { display: flex; align-items: center; margin-bottom: 8px; }
.hero-mc-avail { display: flex; align-items: center; gap: 6px; font-size: 0.75rem; color: var(--success); font-weight: 600; margin-bottom: 12px; }
.avail-dot { width:7px; height:7px; border-radius:50%; background:var(--success); }
.hero-mc-btn {
    width: 100%;
    padding: 10px;
    background: linear-gradient(135deg, var(--primary-dark), #c06090);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
}

/* ─── Sections ──────────────────────────────── */
.home-section { padding: 72px 0; }
.home-section-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 40px;
    gap: 16px;
}
.section-eyebrow {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--primary-dark);
    text-transform: uppercase;
    letter-spacing: 0.12em;
    margin-bottom: 6px;
}
.section-eyebrow::before {
    content: '';
    width: 20px; height: 2px;
    background: var(--primary-dark);
    border-radius: 2px;
}
.section-title {
    font-family: var(--font-main);
    font-size: clamp(1.6rem, 3vw, 2.2rem);
    font-weight: 900;
    letter-spacing: -0.5px;
    color: var(--gray-900);
    margin: 0;
}

/* ─── Categories grid ───────────────────────── */
.home-cats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 14px;
}
.home-cat-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 20px 12px;
    border-radius: 16px;
    background: #fff;
    border: 1.5px solid var(--gray-200);
    transition: all 0.2s;
    text-align: center;
    text-decoration: none;
}
.home-cat-card:hover {
    border-color: var(--primary);
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(180,80,120,0.12);
}
.home-cat-icon {
    width: 54px; height: 54px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    color: var(--primary-dark);
    transition: transform 0.2s;
}
.home-cat-card:hover .home-cat-icon { transform: scale(1.08); }
.home-cat-name { font-size: 0.85rem; font-weight: 700; color: var(--gray-800); }
.home-cat-count { font-size: 0.72rem; color: #9e9e9e; }

/* ─── Business cards ────────────────────────── */
.home-biz-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}
.home-biz-card {
    background: #fff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(100,60,140,0.07);
    transition: transform 0.2s, box-shadow 0.2s;
    text-decoration: none;
    display: block;
}
.home-biz-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 36px rgba(180,80,120,0.15);
}
.home-biz-img {
    height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}
.home-biz-badge {
    position: absolute;
    top: 12px; left: 12px;
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(4px);
    color: var(--primary-dark);
    font-size: 0.72rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
}
.home-biz-body { padding: 16px 18px 18px; }
.home-biz-top { margin-bottom: 12px; }
.home-biz-name { font-weight: 800; font-size: 0.97rem; color: var(--gray-900); display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
.home-biz-loc { font-size: 0.8rem; color: #9e9e9e; display: flex; align-items: center; gap: 5px; }
.home-biz-footer { display: flex; align-items: center; justify-content: space-between; }
.home-biz-stars { display: flex; align-items: center; gap: 3px; }
.home-biz-rating { font-size: 0.82rem; font-weight: 700; color: var(--gray-800); margin-left: 4px; }
.home-biz-book {
    padding: 7px 14px;
    background: linear-gradient(135deg, var(--primary-dark), #c06090);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
}

/* ─── Steps ─────────────────────────────────── */
.home-steps {
    display: flex;
    align-items: flex-start;
    gap: 0;
    max-width: 880px;
    margin: 0 auto;
}
.home-step {
    flex: 1;
    text-align: center;
    padding: 0 24px;
    position: relative;
}
.home-step-num {
    font-size: 3rem;
    font-weight: 900;
    color: var(--primary-light);
    line-height: 1;
    margin-bottom: 16px;
    letter-spacing: -2px;
}
.home-step-icon {
    width: 64px; height: 64px;
    border-radius: 18px;
    background: var(--primary-50);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    color: var(--primary-dark);
    margin: 0 auto 16px;
}
.home-step h3 { font-family: var(--font-main); font-weight: 800; font-size: 1.1rem; margin-bottom: 10px; }
.home-step p { font-size: 0.88rem; color: var(--gray-500); line-height: 1.65; }
.home-step-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    padding-top: 88px;
    color: var(--primary-light);
    font-size: 1.2rem;
    flex-shrink: 0;
}

/* ─── Map teaser ─────────────────────────────── */
.home-map-teaser {
    background: linear-gradient(135deg, #1a0f2e 0%, #2d1b4e 50%, #1f1030 100%);
    position: relative;
    overflow: hidden;
}
.home-map-inner {
    display: grid;
    grid-template-columns: 1fr 480px;
    gap: 60px;
    align-items: center;
}
.home-map-teaser .section-eyebrow { color: var(--primary-light); }
.home-map-teaser .section-eyebrow::before { background: var(--primary-light); }
.home-map-teaser .section-title { color: #fff; }
.home-map-teaser p { color: rgba(255,255,255,0.65); line-height: 1.7; }

.home-map-preview { position: relative; }
.map-preview-frame {
    border-radius: 20px;
    overflow: hidden;
    height: 360px;
    position: relative;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    border: 2px solid rgba(255,255,255,0.1);
}
.map-preview-bg {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, #4a6741 0%, #5a7a50 40%, #3d5c36 100%);
}
.map-pin {
    position: absolute;
    color: var(--primary-dark);
    font-size: 1.4rem;
    transform: translate(-50%, -100%);
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
    z-index: 5;
}
.map-pin-active { color: #fff; font-size: 1.7rem; z-index: 6; }
.map-radius-circle {
    position: absolute;
    width: 180px; height: 180px;
    border-radius: 50%;
    border: 2px solid rgba(232,160,191,0.7);
    background: rgba(232,160,191,0.1);
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    z-index: 4;
}
.map-preview-pill {
    position: absolute;
    bottom: 16px; left: 50%;
    transform: translateX(-50%);
    background: rgba(26,15,46,0.85);
    color: var(--primary-light);
    font-size: 0.78rem;
    font-weight: 700;
    padding: 8px 16px;
    border-radius: 20px;
    display: flex; align-items: center; gap: 6px;
    z-index: 10;
    backdrop-filter: blur(4px);
    white-space: nowrap;
}

/* ─── Business CTA ───────────────────────────── */
.home-biz-cta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}
.home-biz-cta-features {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.biz-feature {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    background: #fff;
    border-radius: 14px;
    padding: 16px 18px;
    box-shadow: 0 2px 12px rgba(100,60,140,0.06);
}
.biz-feature-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    background: var(--primary-50);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem;
    color: var(--primary-dark);
    flex-shrink: 0;
}
.biz-feature-title { font-weight: 700; font-size: 0.92rem; color: var(--gray-900); margin-bottom: 2px; }
.biz-feature-desc { font-size: 0.82rem; color: #9e9e9e; }

/* ─── Responsive ─────────────────────────────── */
@media (max-width: 1024px) {
    .home-hero-inner,
    .home-map-inner,
    .home-biz-cta { grid-template-columns: 1fr; }
    .home-hero-visual { display: none; }
    .home-map-preview { display: none; }
}
@media (max-width: 640px) {
    .home-hero { padding: 48px 0 40px; }
    .home-hero-title { letter-spacing: -0.5px; }
    .home-search { flex-direction: column; padding: 12px; gap: 8px; border-radius: 16px; }
    .home-search-sep { display: none; }
    .home-search-btn { width: 100%; justify-content: center; }
    .home-steps { flex-direction: column; }
    .home-step-arrow { transform: rotate(90deg); padding: 0; }
    .home-biz-cta { gap: 32px; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
