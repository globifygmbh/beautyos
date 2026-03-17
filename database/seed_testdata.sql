-- ============================================================
-- BeautyOS — Test Data Seed
-- 5 Unternehmen mit Services, Öffnungszeiten & Bewertungen
-- Import via phpMyAdmin → SQL Tab
-- ============================================================

SET NAMES utf8;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Business Users (Passwort für alle: test1234) ──────────────
-- Hash für 'test1234' mit PASSWORD_BCRYPT
INSERT INTO `users` (`email`, `password_hash`, `first_name`, `last_name`, `role`, `email_verified_at`) VALUES
('salon.elegance@test.at',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc8P2C3a', 'Sophie',  'Kastner',  'business', NOW()),
('nagelstudio.wien@test.at', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc8P2C3a', 'Lena',    'Müller',   'business', NOW()),
('glow.kosmetik@test.at',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc8P2C3a', 'Anna',    'Bauer',    'business', NOW()),
('barber.club@test.at',      '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc8P2C3a', 'Markus',  'Wagner',   'business', NOW()),
('zen.massage@test.at',      '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc8P2C3a', 'Julia',   'Hofer',    'business', NOW());

-- Review Users
INSERT INTO `users` (`email`, `password_hash`, `first_name`, `last_name`, `role`, `email_verified_at`) VALUES
('kunde1@test.at', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc8P2C3a', 'Maria',   'Fischer',  'user', NOW()),
('kunde2@test.at', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc8P2C3a', 'Thomas',  'Schneider','user', NOW()),
('kunde3@test.at', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc8P2C3a', 'Laura',   'Huber',    'user', NOW()),
('kunde4@test.at', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc8P2C3a', 'David',   'Mayr',     'user', NOW()),
('kunde5@test.at', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSc8P2C3a', 'Sarah',   'Gruber',   'user', NOW());

-- ── 5 Businesses ─────────────────────────────────────────────
INSERT INTO `businesses` (
    `user_id`, `subscription_plan_id`, `name`, `slug`, `description`, `short_description`,
    `cover_image`, `logo`,
    `phone`, `email`, `website`,
    `street`, `house_number`, `zip_code`, `city`, `country`,
    `latitude`, `longitude`,
    `accent_color`, `status`, `is_verified`, `subscription_expires_at`
) VALUES
(
    -- Salon Elegance — Friseur, Wien
    (SELECT id FROM users WHERE email='salon.elegance@test.at'),
    (SELECT id FROM subscription_plans WHERE slug='premium'),
    'Salon Elegance',
    'salon-elegance-wien',
    '<p>Willkommen im Salon Elegance – Ihrem Premium-Friseursalon im Herzen Wiens. Seit über 15 Jahren verwöhnen wir unsere Kunden mit modernsten Haarschnitt-Techniken, hochwertigen Colorationen und individueller Beratung. Unser erfahrenes Team aus zertifizierten Stylisten sorgt dafür, dass Sie sich rundum wohlfühlen.</p><p>Wir arbeiten ausschließlich mit professionellen Produkten von Oribe und Davines. Entspannen Sie sich bei einer Tasse Kaffee oder Tee, während wir uns um Ihr Haar kümmern.</p>',
    'Premium-Friseursalon in Wien mit 15 Jahren Erfahrung. Haarschnitt, Coloration & Balayage.',
    'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=1200&q=80',
    'https://images.unsplash.com/photo-1598369327882-8b5b3c87a4b2?w=200&q=80',
    '+43 1 234 5678', 'info@salon-elegance.at', 'https://salon-elegance.at',
    'Kärntner Straße', '12', '1010', 'Wien', 'Österreich',
    48.2048, 16.3699,
    '#c06090', 'active', 1,
    DATE_ADD(NOW(), INTERVAL 12 MONTH)
),
(
    -- Nail Art Studio — Nagelstudio, Wien
    (SELECT id FROM users WHERE email='nagelstudio.wien@test.at'),
    (SELECT id FROM subscription_plans WHERE slug='professional'),
    'Nail Art Studio Lena',
    'nail-art-studio-lena',
    '<p>Das Nail Art Studio Lena ist deine Anlaufstelle für kreative Nageldesigns in Wien. Von klassischer French Manicure über Gel-Nägel bis hin zu handgemalten Nail Art Designs – wir machen deine Nägel zum Kunstwerk.</p><p>Alle unsere Produkte sind vegan und cruelty-free. Wir verwenden ausschließlich hochwertige Gele und Lacke der Marken OPI und CND.</p>',
    'Kreatives Nagelstudio in Wien – Gel, Acryl, Nail Art & French Manicure.',
    'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=1200&q=80',
    'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=200&q=80',
    '+43 1 345 6789', 'lena@nailart-wien.at', NULL,
    'Mariahilfer Straße', '88', '1060', 'Wien', 'Österreich',
    48.1967, 16.3511,
    '#e8a0bf', 'active', 1,
    DATE_ADD(NOW(), INTERVAL 8 MONTH)
),
(
    -- Glow Kosmetik — Kosmetik, Graz
    (SELECT id FROM users WHERE email='glow.kosmetik@test.at'),
    (SELECT id FROM subscription_plans WHERE slug='professional'),
    'Glow Kosmetikstudio',
    'glow-kosmetikstudio-graz',
    '<p>Im Glow Kosmetikstudio in Graz erwartet Sie pure Entspannung und professionelle Hautpflege. Wir bieten individuelle Gesichtsbehandlungen, Microneedling, chemische Peelings und ganzheitliche Körperbehandlungen an.</p><p>Unsere Kosmetikerinnen sind ausgebildete Fachkräfte mit jahrelanger Erfahrung. Alle Produkte von Environ und Biodroga.</p>',
    'Professionelles Kosmetikstudio in Graz – Gesichtsbehandlungen, Microneedling & Körperpflege.',
    'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=1200&q=80',
    'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=200&q=80',
    '+43 316 123 456', 'info@glow-graz.at', 'https://glow-graz.at',
    'Herrengasse', '7', '8010', 'Graz', 'Österreich',
    47.0707, 15.4395,
    '#b8a9c9', 'active', 1,
    DATE_ADD(NOW(), INTERVAL 6 MONTH)
),
(
    -- The Barber Club — Barbershop, Wien
    (SELECT id FROM users WHERE email='barber.club@test.at'),
    (SELECT id FROM subscription_plans WHERE slug='premium'),
    'The Barber Club',
    'the-barber-club-wien',
    '<p>The Barber Club ist Wiens exklusivster Barbershop – ein Ort, an dem traditionelles Barbier-Handwerk auf modernes Design trifft. Rasuren mit dem Rasiermesser, klassische Herrenhaarschnitte und Bartpflege auf höchstem Niveau.</p><p>Entspannen Sie sich in unserem stylischen Ambiente und lassen Sie sich von unseren erfahrenen Barbieren verwöhnen. Whiskey an der Bar inklusive.</p>',
    'Exklusiver Barbershop in Wien – Rasur, Haarschnitt & Bartpflege auf Premiumlevel.',
    'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=1200&q=80',
    'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=200&q=80',
    '+43 1 456 7890', 'info@thebarberclub.at', 'https://thebarberclub.at',
    'Neubaugasse', '34', '1070', 'Wien', 'Österreich',
    48.2001, 16.3503,
    '#8b6f4e', 'active', 1,
    DATE_ADD(NOW(), INTERVAL 11 MONTH)
),
(
    -- Zen Massage — Massage, Salzburg
    (SELECT id FROM users WHERE email='zen.massage@test.at'),
    (SELECT id FROM subscription_plans WHERE slug='starter'),
    'Zen Massage & Wellness',
    'zen-massage-wellness-salzburg',
    '<p>Im Zen Massage & Wellness in Salzburg finden Sie Ihre innere Ruhe. Wir bieten klassische Massagen, Thai-Massage, Hot Stone, Lymphdrainage und Aromatherapie an. Jede Behandlung wird individuell auf Ihre Bedürfnisse abgestimmt.</p><p>Unsere ausgebildeten Therapeuten haben jahrelange Erfahrung in der ganzheitlichen Körpertherapie. Tauchen Sie ein in eine Welt der Entspannung.</p>',
    'Ganzheitliches Massage & Wellness Studio in Salzburg – Thai, Hot Stone, Aromatherapie.',
    'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=1200&q=80',
    'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=200&q=80',
    '+43 662 789 012', 'info@zen-massage.at', NULL,
    'Getreidegasse', '23', '5020', 'Salzburg', 'Österreich',
    47.8003, 13.0400,
    '#7ab8a0', 'active', 1,
    DATE_ADD(NOW(), INTERVAL 4 MONTH)
);

-- ── Business Categories ───────────────────────────────────────
INSERT INTO `business_categories` (`business_id`, `category_id`) VALUES
((SELECT id FROM businesses WHERE slug='salon-elegance-wien'),     (SELECT id FROM categories WHERE slug='friseur')),
((SELECT id FROM businesses WHERE slug='nail-art-studio-lena'),    (SELECT id FROM categories WHERE slug='nagelstudio')),
((SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'),(SELECT id FROM categories WHERE slug='kosmetik')),
((SELECT id FROM businesses WHERE slug='the-barber-club-wien'),    (SELECT id FROM categories WHERE slug='barbershop')),
((SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), (SELECT id FROM categories WHERE slug='massage'));

-- ── Services ─────────────────────────────────────────────────
-- Salon Elegance
INSERT INTO `services` (`business_id`, `name`, `description`, `duration_minutes`, `price`, `sort_order`) VALUES
((SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 'Damen-Haarschnitt', 'Waschen, Schneiden & Föhnen', 60, 49.00, 1),
((SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 'Balayage / Highlights', 'Professionelle Aufhelltechnik inkl. Pflege', 150, 149.00, 2),
((SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 'Vollcoloration', 'Komplette Haarfarbe nach Wunsch', 120, 89.00, 3),
((SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 'Herren-Haarschnitt', 'Waschen, Schneiden & Stylen', 45, 35.00, 4),
((SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 'Hochzeitsstyling', 'Braut-Hochsteckfrisur inkl. Probe', 120, 179.00, 5);

-- Nail Art Studio Lena
INSERT INTO `services` (`business_id`, `name`, `description`, `duration_minutes`, `price`, `sort_order`) VALUES
((SELECT id FROM businesses WHERE slug='nail-art-studio-lena'), 'Gel-Maniküre', 'Gel-Aufbau oder Verlängerung mit Design', 90, 59.00, 1),
((SELECT id FROM businesses WHERE slug='nail-art-studio-lena'), 'French Manicure', 'Klassische French mit Shellac', 75, 45.00, 2),
((SELECT id FROM businesses WHERE slug='nail-art-studio-lena'), 'Nail Art Design', 'Handgemalte Designs nach Wunsch (pro Nagel)', 30, 5.00, 3),
((SELECT id FROM businesses WHERE slug='nail-art-studio-lena'), 'Nagelpflege Basis', 'Feilen, Kutikula & Farblack', 45, 29.00, 4),
((SELECT id FROM businesses WHERE slug='nail-art-studio-lena'), 'Pediküre Wellness', 'Fußbad, Hornhaut, Massage & Farblack', 60, 49.00, 5);

-- Glow Kosmetik
INSERT INTO `services` (`business_id`, `name`, `description`, `duration_minutes`, `price`, `sort_order`) VALUES
((SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'), 'Intensiv-Gesichtsbehandlung', 'Reinigung, Peeling, Maske & Serum', 75, 79.00, 1),
((SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'), 'Microneedling', 'Kollagenbooster für straffere Haut', 60, 149.00, 2),
((SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'), 'Chemisches Peeling', 'Fruchtsäure-Peeling für ebenmäßige Haut', 45, 89.00, 3),
((SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'), 'Augenbrauen Styling', 'Zupfen, Färben & Wachsen', 30, 35.00, 4),
((SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'), 'Ganzkörper-Peeling', 'Körperpeeling & Feuchtigkeitspflege', 60, 69.00, 5);

-- The Barber Club
INSERT INTO `services` (`business_id`, `name`, `description`, `duration_minutes`, `price`, `sort_order`) VALUES
((SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 'Klassischer Herrenschnitt', 'Waschen, Schneiden & Stylen', 45, 35.00, 1),
((SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 'Rasur mit Rasiermesser', 'Traditionelle Nassrasur mit heißem Handtuch', 30, 39.00, 2),
((SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 'Schnitt & Rasur Kombi', 'Haarschnitt + Rasiermesser-Rasur', 75, 65.00, 3),
((SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 'Bartpflege & Trimmen', 'Bartschnitt, Konturieren & Pflegeöl', 30, 29.00, 4),
((SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 'Black Mask Behandlung', 'Porenreinigung & Gesichtspflege für Männer', 45, 49.00, 5);

-- Zen Massage
INSERT INTO `services` (`business_id`, `name`, `description`, `duration_minutes`, `price`, `sort_order`) VALUES
((SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), 'Klassische Massage (60 min)', 'Ganzkörper-Entspannungsmassage', 60, 75.00, 1),
((SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), 'Hot Stone Massage', 'Vulkansteine & tiefenwirksame Massage', 90, 110.00, 2),
((SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), 'Thai-Massage', 'Traditionelle Ganzkörperbehandlung', 90, 95.00, 3),
((SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), 'Aromatherapie-Massage', 'Ätherische Öle & sanfte Massage', 60, 85.00, 4),
((SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), 'Lymphdrainage', 'Entstauende manuelle Lymphdrainage', 60, 90.00, 5);

-- ── Öffnungszeiten (0=Mo, 1=Di, ... 6=So) ────────────────────
-- Salon Elegance: Mo-Sa 9-19, So geschlossen
INSERT INTO `opening_hours` (`business_id`, `day_of_week`, `open_time`, `close_time`, `is_closed`) VALUES
((SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 0, '09:00', '19:00', 0),
((SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 1, '09:00', '19:00', 0),
((SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 2, '09:00', '19:00', 0),
((SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 3, '09:00', '20:00', 0),
((SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 4, '09:00', '20:00', 0),
((SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 5, '09:00', '17:00', 0),
((SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 6, NULL, NULL, 1);

-- Nail Art Studio: Di-Sa 10-18, Mo+So geschlossen
INSERT INTO `opening_hours` (`business_id`, `day_of_week`, `open_time`, `close_time`, `is_closed`) VALUES
((SELECT id FROM businesses WHERE slug='nail-art-studio-lena'), 0, NULL, NULL, 1),
((SELECT id FROM businesses WHERE slug='nail-art-studio-lena'), 1, '10:00', '18:30', 0),
((SELECT id FROM businesses WHERE slug='nail-art-studio-lena'), 2, '10:00', '18:30', 0),
((SELECT id FROM businesses WHERE slug='nail-art-studio-lena'), 3, '10:00', '18:30', 0),
((SELECT id FROM businesses WHERE slug='nail-art-studio-lena'), 4, '10:00', '20:00', 0),
((SELECT id FROM businesses WHERE slug='nail-art-studio-lena'), 5, '09:00', '16:00', 0),
((SELECT id FROM businesses WHERE slug='nail-art-studio-lena'), 6, NULL, NULL, 1);

-- Glow Kosmetik: Mo-Fr 9-18, Sa 10-15, So geschlossen
INSERT INTO `opening_hours` (`business_id`, `day_of_week`, `open_time`, `close_time`, `is_closed`) VALUES
((SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'), 0, '09:00', '18:00', 0),
((SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'), 1, '09:00', '18:00', 0),
((SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'), 2, '09:00', '18:00', 0),
((SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'), 3, '09:00', '18:00', 0),
((SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'), 4, '09:00', '18:00', 0),
((SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'), 5, '10:00', '15:00', 0),
((SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'), 6, NULL, NULL, 1);

-- Barber Club: Mo-Sa 10-20, So geschlossen
INSERT INTO `opening_hours` (`business_id`, `day_of_week`, `open_time`, `close_time`, `is_closed`) VALUES
((SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 0, '10:00', '20:00', 0),
((SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 1, '10:00', '20:00', 0),
((SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 2, '10:00', '20:00', 0),
((SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 3, '10:00', '20:00', 0),
((SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 4, '10:00', '21:00', 0),
((SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 5, '09:00', '18:00', 0),
((SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 6, NULL, NULL, 1);

-- Zen Massage: Mo-So 10-20
INSERT INTO `opening_hours` (`business_id`, `day_of_week`, `open_time`, `close_time`, `is_closed`) VALUES
((SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), 0, '10:00', '20:00', 0),
((SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), 1, '10:00', '20:00', 0),
((SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), 2, '10:00', '20:00', 0),
((SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), 3, '10:00', '20:00', 0),
((SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), 4, '10:00', '20:00', 0),
((SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), 5, '10:00', '20:00', 0),
((SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), 6, '11:00', '18:00', 0);

-- ── Bewertungen ───────────────────────────────────────────────
-- Salon Elegance
INSERT INTO `reviews` (`user_id`, `business_id`, `rating`, `comment`, `reply`, `replied_at`, `created_at`) VALUES
((SELECT id FROM users WHERE email='kunde1@test.at'), (SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 5, 'Absolut traumhafter Salon! Sophie hat meine Haare perfekt coloriert, genau das gewünschte Ergebnis. Die Atmosphäre ist super angenehm und das Team sehr professionell. Komme auf jeden Fall wieder!', 'Vielen herzlichen Dank, Maria! Es war uns eine Freude 🌸', NOW()),
((SELECT id FROM users WHERE email='kunde2@test.at'), (SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 5, 'Bester Friseur in Wien! Habe den Balayage bekommen und bin begeistert. Sehr gute Beratung, top Ergebnis. Preislich fair für die Qualität.', NULL, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
((SELECT id FROM users WHERE email='kunde3@test.at'), (SELECT id FROM businesses WHERE slug='salon-elegance-wien'), 4, 'Sehr schöner Salon, freundliches Personal. Haarschnitt top, nur die Wartezeit war etwas länger. Trotzdem sehr empfehlenswert!', 'Danke für dein Feedback! Wir arbeiten daran ⏰', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Nail Art Studio
INSERT INTO `reviews` (`user_id`, `business_id`, `rating`, `comment`, `reply`, `replied_at`, `created_at`) VALUES
((SELECT id FROM users WHERE email='kunde4@test.at'), (SELECT id FROM businesses WHERE slug='nail-art-studio-lena'), 5, 'Lena ist eine absolute Künstlerin! Die Nail Art Designs sind unglaublich detailliert. Noch nie so schöne Nägel gehabt. Alle fragen mich wo ich war 😍', 'Das freut mich so sehr, danke!! ❤️', NOW()),
((SELECT id FROM users WHERE email='kunde5@test.at'), (SELECT id FROM businesses WHERE slug='nail-art-studio-lena'), 5, 'Endlich ein Nagelstudio das vegane Produkte verwendet! Super Qualität, faire Preise und eine super Atmosphäre. Bin Stammkundin!', NULL, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Glow Kosmetik
INSERT INTO `reviews` (`user_id`, `business_id`, `rating`, `comment`, `reply`, `replied_at`, `created_at`) VALUES
((SELECT id FROM users WHERE email='kunde1@test.at'), (SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'), 5, 'Das Microneedling hat meine Haut wirklich transformiert! Anna ist super professionell und erklärt alles genau. Meine Haut war noch nie so straff. Absolute Empfehlung!', 'Danke, das motiviert uns! Wir freuen uns auf deinen nächsten Besuch 🌟', DATE_SUB(NOW(), INTERVAL 2 DAY)),
((SELECT id FROM users WHERE email='kunde3@test.at'), (SELECT id FROM businesses WHERE slug='glow-kosmetikstudio-graz'), 4, 'Sehr angenehmes Studio, kompetente Beratung. Die Gesichtsbehandlung war entspannend und die Haut danach super. Gerne wieder!', NULL, NULL, DATE_SUB(NOW(), INTERVAL 7 DAY));

-- Barber Club
INSERT INTO `reviews` (`user_id`, `business_id`, `rating`, `comment`, `reply`, `replied_at`, `created_at`) VALUES
((SELECT id FROM users WHERE email='kunde2@test.at'), (SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 5, 'Bester Barbershop der Stadt, keine Diskussion. Die Nassrasur mit Rasiermesser ist ein absolutes Erlebnis. Fühle mich danach wie neu. Das Ambiente ist klasse!', 'Danke Markus! Das ist genau das Gefühl das wir vermitteln wollen 💈', NOW()),
((SELECT id FROM users WHERE email='kunde4@test.at'), (SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 5, 'Perfekter Haarschnitt, super Service und sogar ein Whiskey dabei! So macht Friseurbesuch Spaß. Klare Empfehlung für jeden Mann in Wien.', NULL, NULL, DATE_SUB(NOW(), INTERVAL 4 DAY)),
((SELECT id FROM users WHERE email='kunde5@test.at'), (SELECT id FROM businesses WHERE slug='the-barber-club-wien'), 4, 'Toller Laden, sehr stylisches Ambiente. Haarschnitt war top. Einziger Kritikpunkt: Online-Terminbuchung könnte etwas einfacher sein.', 'Danke für den Hinweis! Arbeiten dran 💪', DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Zen Massage
INSERT INTO `reviews` (`user_id`, `business_id`, `rating`, `comment`, `reply`, `replied_at`, `created_at`) VALUES
((SELECT id FROM users WHERE email='kunde1@test.at'), (SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), 5, 'Die Hot Stone Massage war das Entspannendste was ich je erlebt habe! Julia hat einen sensationellen Druck und sehr einfühlsame Hände. Danach komplett tiefenentspannt. Vielen Dank!', 'Das hören wir sehr gerne! Bis zum nächsten Mal 🪨✨', DATE_SUB(NOW(), INTERVAL 1 DAY)),
((SELECT id FROM users WHERE email='kunde3@test.at'), (SELECT id FROM businesses WHERE slug='zen-massage-wellness-salzburg'), 5, 'Wunderschönes ruhiges Studio, absolute Wohlfühlatmosphäre. Thai-Massage war perfekt intensiv. Komme regelmäßig her, beste Investition in mich selbst!', NULL, NULL, DATE_SUB(NOW(), INTERVAL 6 DAY));

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FERTIG! Alle Test-Logins (Passwort: test1234):
-- Business:  salon.elegance@test.at / nagelstudio.wien@test.at
--            glow.kosmetik@test.at  / barber.club@test.at
--            zen.massage@test.at
-- Kunden:    kunde1@test.at ... kunde5@test.at
-- ============================================================
