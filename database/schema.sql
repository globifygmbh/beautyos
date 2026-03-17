SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8;

-- ============================================
-- USERS
-- ============================================
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `avatar` VARCHAR(500) DEFAULT NULL,
    `role` ENUM('user', 'business', 'admin') NOT NULL DEFAULT 'user',
    `email_verified_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ============================================
-- CATEGORIES
-- ============================================
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL UNIQUE,
    `icon` VARCHAR(50) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ============================================
-- SUBSCRIPTION PLANS
-- ============================================
CREATE TABLE `subscription_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL UNIQUE,
    `price_monthly` DECIMAL(10,2) NOT NULL,
    `features` TEXT DEFAULT NULL,
    `max_images` INT NOT NULL DEFAULT 5,
    `custom_design` TINYINT(1) NOT NULL DEFAULT 0,
    `priority_listing` TINYINT(1) NOT NULL DEFAULT 0,
    `featured_badge` TINYINT(1) NOT NULL DEFAULT 0,
    `booking_system` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ============================================
-- BUSINESSES
-- ============================================
CREATE TABLE `businesses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `subscription_plan_id` INT DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(280) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `short_description` VARCHAR(500) DEFAULT NULL,
    `logo` VARCHAR(500) DEFAULT NULL,
    `cover_image` VARCHAR(500) DEFAULT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `website` VARCHAR(500) DEFAULT NULL,
    `street` VARCHAR(255) DEFAULT NULL,
    `house_number` VARCHAR(20) DEFAULT NULL,
    `zip_code` VARCHAR(10) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `state` VARCHAR(100) DEFAULT NULL,
    `country` VARCHAR(100) DEFAULT 'Deutschland',
    `latitude` DECIMAL(10,7) DEFAULT NULL,
    `longitude` DECIMAL(10,7) DEFAULT NULL,
    `accent_color` VARCHAR(7) DEFAULT '#e8a0bf',
    `custom_css` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'active', 'suspended', 'closed') NOT NULL DEFAULT 'pending',
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `subscription_expires_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subscription_plan_id`) REFERENCES `subscription_plans`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_city` (`city`),
    INDEX `idx_coords` (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ============================================
-- BUSINESS CATEGORIES (M:N)
-- ============================================
CREATE TABLE `business_categories` (
    `business_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    PRIMARY KEY (`business_id`, `category_id`),
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ============================================
-- BUSINESS IMAGES
-- ============================================
CREATE TABLE `business_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `business_id` INT NOT NULL,
    `image_path` VARCHAR(500) NOT NULL,
    `caption` VARCHAR(255) DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ============================================
-- OPENING HOURS
-- ============================================
CREATE TABLE `opening_hours` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `business_id` INT NOT NULL,
    `day_of_week` TINYINT NOT NULL,
    `open_time` TIME DEFAULT NULL,
    `close_time` TIME DEFAULT NULL,
    `is_closed` TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_business_day` (`business_id`, `day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ============================================
-- SERVICES
-- ============================================
CREATE TABLE `services` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `business_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `duration_minutes` INT NOT NULL DEFAULT 60,
    `price` DECIMAL(10,2) NOT NULL,
    `category_id` INT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ============================================
-- BOOKINGS
-- ============================================
CREATE TABLE `bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `business_id` INT NOT NULL,
    `service_id` INT NOT NULL,
    `booking_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `status` ENUM('pending', 'confirmed', 'cancelled', 'completed', 'no_show') NOT NULL DEFAULT 'pending',
    `notes` TEXT DEFAULT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE,
    INDEX `idx_date` (`booking_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ============================================
-- REVIEWS
-- ============================================
CREATE TABLE `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `business_id` INT NOT NULL,
    `booking_id` INT DEFAULT NULL,
    `rating` TINYINT NOT NULL,
    `comment` TEXT DEFAULT NULL,
    `reply` TEXT DEFAULT NULL,
    `replied_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `uk_user_business` (`user_id`, `business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ============================================
-- FAVORITES
-- ============================================
CREATE TABLE `favorites` (
    `user_id` INT NOT NULL,
    `business_id` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `business_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ============================================
-- STAMMDATEN
-- ============================================

INSERT INTO `categories` (`name`, `slug`, `icon`, `sort_order`) VALUES
('Friseur', 'friseur', 'scissors', 1),
('Nagelstudio', 'nagelstudio', 'hand-sparkles', 2),
('Kosmetik', 'kosmetik', 'spa', 3),
('Barbershop', 'barbershop', 'cut', 4),
('Waxing', 'waxing', 'leaf', 5),
('Massage', 'massage', 'hands', 6),
('Permanent Make-up', 'permanent-makeup', 'eye', 7),
('Wimpern & Brauen', 'wimpern-brauen', 'eye-dropper', 8);

INSERT INTO `subscription_plans` (`name`, `slug`, `price_monthly`, `max_images`, `custom_design`, `priority_listing`, `featured_badge`, `booking_system`, `sort_order`, `features`) VALUES
('Starter', 'starter', 29.99, 5, 0, 0, 0, 1, 1, '["Basiseintrag", "Bis zu 5 Bilder", "Buchungssystem", "Bewertungen"]'),
('Professional', 'professional', 59.99, 20, 1, 1, 0, 1, 2, '["Alles aus Starter", "Bis zu 20 Bilder", "Design-Anpassungen", "Prioritaets-Listing", "Statistiken"]'),
('Premium', 'premium', 99.99, 50, 1, 1, 1, 1, 3, '["Alles aus Professional", "Bis zu 50 Bilder", "Featured Badge", "Top-Platzierung", "Premium Support", "Social Media Integration"]');

-- ============================================
-- ADMIN USER (Passwort: admin123)
-- ============================================
INSERT INTO `users` (`email`, `password_hash`, `first_name`, `last_name`, `role`, `email_verified_at`) VALUES
('admin@beautyos.at', '$2y$12$8ki9mLkQbd/g3KpXj3kXWuRytV/874Z4UiFhMup3M9p5xmiTQb7aO', 'Admin', 'BeautyOS', 'admin', NOW());

-- ============================================
-- LEGAL PAGES
-- ============================================
CREATE TABLE `legal_pages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `content` LONGTEXT DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `legal_pages` (`slug`, `title`, `content`) VALUES
('impressum', 'Impressum', '<h2>Impressum</h2><p>Angaben gemäß § 5 TMG</p><p><strong>Ihr Name / Firmenname</strong><br>Straße Nr.<br>PLZ Stadt</p><p>E-Mail: info@example.at<br>Tel: +43 000 000000</p><h3>Haftungsausschluss</h3><p>Bitte ergänzen Sie hier Ihre vollständigen Angaben.</p>'),
('datenschutz', 'Datenschutzerklärung', '<h2>Datenschutzerklärung</h2><p>Der Schutz Ihrer persönlichen Daten ist uns ein besonderes Anliegen. Wir verarbeiten Ihre Daten daher ausschließlich auf Grundlage der gesetzlichen Bestimmungen (DSGVO, TKG 2003).</p><h3>Kontakt mit uns</h3><p>Wenn Sie per Formular auf der Website oder per E-Mail Kontakt mit uns aufnehmen, werden Ihre angegebenen Daten zwecks Bearbeitung der Anfrage und für den Fall von Anschlussfragen sechs Monate bei uns gespeichert.</p><p>Bitte ergänzen Sie hier Ihre vollständige Datenschutzerklärung.</p>'),
('agb', 'Allgemeine Geschäftsbedingungen', '<h2>Allgemeine Geschäftsbedingungen</h2><h3>§ 1 Geltungsbereich</h3><p>Diese Allgemeinen Geschäftsbedingungen gelten für alle Leistungen, die über die Plattform BeautyOS erbracht werden.</p><h3>§ 2 Vertragsschluss</h3><p>Bitte ergänzen Sie hier Ihre vollständigen AGB.</p>');

-- ============================================
-- ANALYTICS EVENTS
-- ============================================
CREATE TABLE `analytics_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_type` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(50) DEFAULT NULL,
    `entity_id` INT DEFAULT NULL,
    `page` VARCHAR(255) DEFAULT NULL,
    `referrer` VARCHAR(500) DEFAULT NULL,
    `ip_hash` VARCHAR(64) DEFAULT NULL,
    `country` VARCHAR(2) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `extra` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ============================================
-- SEARCH LOGS
-- ============================================
CREATE TABLE `search_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `query` VARCHAR(500) DEFAULT NULL,
    `category` VARCHAR(120) DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `results_count` INT NOT NULL DEFAULT 0,
    `ip_hash` VARCHAR(64) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_query` (`query`(100)),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ============================================
-- STRIPE PAYMENTS
-- ============================================
CREATE TABLE `stripe_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `business_id` INT DEFAULT NULL,
    `plan_id` INT DEFAULT NULL,
    `stripe_session_id` VARCHAR(255) DEFAULT NULL,
    `stripe_payment_intent` VARCHAR(255) DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'EUR',
    `status` ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    `period_start` DATE DEFAULT NULL,
    `period_end` DATE DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
