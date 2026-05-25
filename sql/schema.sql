-- =====================================================
--  CityLive · Database Schema
-- =====================================================

CREATE DATABASE IF NOT EXISTS citylive CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE citylive;

-- ─── USERS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  UNIQUE NOT NULL,
    email         VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100),
    bio           TEXT,
    avatar        VARCHAR(255) DEFAULT NULL,
    reputation    DECIMAL(3,2) DEFAULT 0.00,
    rep_count     INT          DEFAULT 0,
    plan          ENUM('free','pro','platinum') DEFAULT 'free',
    tokens_balance INT         DEFAULT 0,
    verified      TINYINT(1)   DEFAULT 0,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    last_active   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ─── PUBLICATIONS ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS publications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT         NOT NULL,
    type        ENUM('incident','event','activity') NOT NULL,
    title       VARCHAR(200) NOT NULL,
    description TEXT,
    latitude    DECIMAL(10,8) NOT NULL,
    longitude   DECIMAL(11,8) NOT NULL,
    address     VARCHAR(255),
    category    VARCHAR(50),
    image_url   VARCHAR(255),
    token_cost     INT          DEFAULT 0,
    status         ENUM('active','expired','removed') DEFAULT 'active',
    views          INT          DEFAULT 0,
    attendees      INT          DEFAULT 0,
    min_attendees  INT          DEFAULT NULL,
    max_attendees  INT          DEFAULT NULL,
    starts_at      DATETIME,
    expires_at     DATETIME,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── REVIEWS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reviews (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    publication_id INT       NOT NULL,
    user_id        INT       NOT NULL,
    rating         TINYINT   NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment        TEXT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    UNIQUE KEY unique_review (publication_id, user_id)
);

-- ─── TOKEN TRANSACTIONS ───────────────────────────────
CREATE TABLE IF NOT EXISTS token_transactions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    amount      INT          NOT NULL,
    type        ENUM('subscription','purchase','publication','reward','refund','spin') NOT NULL,
    description VARCHAR(255),
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── SUBSCRIPTIONS ────────────────────────────────────
CREATE TABLE IF NOT EXISTS subscriptions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNIQUE   NOT NULL,
    plan       ENUM('free','pro','platinum') DEFAULT 'free',
    started_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    renews_at  TIMESTAMP,
    active     TINYINT(1)   DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── FOLLOWERS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS followers (
    follower_id  INT NOT NULL,
    following_id INT NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, following_id),
    FOREIGN KEY (follower_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ─── SPIN HISTORY ────────────────────────────────────
-- Tabla de auditoría de tiradas de la ruleta
-- EV por tirada: 58,75 tokens · Margen pagada: 41,25%
CREATE TABLE IF NOT EXISTS spin_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    spin_type   ENUM('daily','paid')  NOT NULL,
    cost        INT          NOT NULL DEFAULT 0,
    reward      INT          NOT NULL DEFAULT 0,
    prize_label VARCHAR(50),
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sh_user    (user_id),
    INDEX idx_sh_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── SAVED PUBLICATIONS ───────────────────────────────
CREATE TABLE IF NOT EXISTS saves (
    user_id        INT NOT NULL,
    publication_id INT NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, publication_id),
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE
);
