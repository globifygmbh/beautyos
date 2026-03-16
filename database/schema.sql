-- BeautyOS Database Schema
-- MySQL 8.0+

CREATE DATABASE IF NOT EXISTS beautyos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE beautyos;

-- ============================================
-- USERS
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(30),
    avatar VARCHAR(500),
    role ENUM('user', 'business', 'admin') NOT NULL DEFAULT 'user',
    email_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- CATEGORIES
-- ============================================
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    icon VARCHAR(50),
    description TEXT,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- SUBSCRIPTION PLANS
-- ============================================
CREATE TABLE subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    price_monthly DECIMAL(10,2) NOT NULL,
    features JSON,
    max_images INT NOT NULL DEFAULT 5,
    custom_design TINYINT(1) NOT NULL DEFAULT 0,
    priority_listing TINYINT(1) NOT NULL DEFAULT 0,
    featured_badge TINYINT(1) NOT NULL DEFAULT 0,
    booking_system TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- BUSINESSES
-- ============================================
CREATE TABLE businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_plan_id INT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(280) NOT NULL UNIQUE,
    description TEXT,
    short_description VARCHAR(500),
    logo VARCHAR(500),
    cover_image VARCHAR(500),
    phone VARCHAR(30),
    email VARCHAR(255),
    website VARCHAR(500),

    -- Address
    street VARCHAR(255),
    house_number VARCHAR(20),
    zip_code VARCHAR(10),
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Deutschland',
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),

    -- Design customization (for premium plans)
    accent_color VARCHAR(7) DEFAULT '#e8a0bf',
    custom_css TEXT,

    -- Status
    status ENUM('pending', 'active', 'suspended', 'closed') NOT NULL DEFAULT 'pending',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    subscription_expires_at DATETIME NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_city (city),
    INDEX idx_coords (latitude, longitude)
) ENGINE=InnoDB;

-- ============================================
-- BUSINESS CATEGORIES (M:N)
-- ============================================
CREATE TABLE business_categories (
    business_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (business_id, category_id),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- BUSINESS IMAGES
-- ============================================
CREATE TABLE business_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    caption VARCHAR(255),
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- OPENING HOURS
-- ============================================
CREATE TABLE opening_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    day_of_week TINYINT NOT NULL, -- 0=Mon, 6=Sun
    open_time TIME,
    close_time TIME,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    UNIQUE KEY uk_business_day (business_id, day_of_week)
) ENGINE=InnoDB;

-- ============================================
-- SERVICES
-- ============================================
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INT NOT NULL DEFAULT 60,
    price DECIMAL(10,2) NOT NULL,
    category_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================
-- BOOKINGS
-- ============================================
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_id INT NOT NULL,
    service_id INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed', 'no_show') NOT NULL DEFAULT 'pending',
    notes TEXT,
    total_price DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_date (booking_date),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================
-- REVIEWS
-- ============================================
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_id INT NOT NULL,
    booking_id INT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    reply TEXT,
    replied_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    UNIQUE KEY uk_user_business (user_id, business_id)
) ENGINE=InnoDB;

-- ============================================
-- FAVORITES
-- ============================================
CREATE TABLE favorites (
    user_id INT NOT NULL,
    business_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, business_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- SEED DATA
-- ============================================

-- Categories
INSERT INTO categories (name, slug, icon, sort_order) VALUES
('Friseur', 'friseur', 'scissors', 1),
('Nagelstudio', 'nagelstudio', 'hand-sparkles', 2),
('Kosmetik', 'kosmetik', 'spa', 3),
('Barbershop', 'barbershop', 'cut', 4),
('Waxing', 'waxing', 'leaf', 5),
('Massage', 'massage', 'hands', 6),
('Permanent Make-up', 'permanent-makeup', 'eye', 7),
('Wimpern & Brauen', 'wimpern-brauen', 'eye-dropper', 8);

-- Subscription Plans
INSERT INTO subscription_plans (name, slug, price_monthly, max_images, custom_design, priority_listing, featured_badge, booking_system, sort_order, features) VALUES
('Starter', 'starter', 29.99, 5, 0, 0, 0, 1, 1, '["Basiseintrag", "Bis zu 5 Bilder", "Buchungssystem", "Bewertungen"]'),
('Professional', 'professional', 59.99, 20, 1, 1, 0, 1, 2, '["Alles aus Starter", "Bis zu 20 Bilder", "Design-Anpassungen", "Prioritäts-Listing", "Statistiken"]'),
('Premium', 'premium', 99.99, 50, 1, 1, 1, 1, 3, '["Alles aus Professional", "Bis zu 50 Bilder", "Featured Badge", "Top-Platzierung", "Premium Support", "Social Media Integration"]');
