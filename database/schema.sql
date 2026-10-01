-- =============================================================================
--  KhuntaLocal — Database schema (MySQL 5.7+ / MariaDB 10.3+)
--  Charset: utf8mb4 (full Unicode: Odia, Hindi, English, emoji)
--  Engine:  InnoDB (foreign keys + transactions)
--
--  Import order:
--      mysql -u USER -p KhuntaLocal < database/schema.sql
--      mysql -u USER -p KhuntaLocal < database/seed.sql
--
--  The complete schema for all phases is created here so later phases
--  (media, comments, verification, API ...) build on a stable foundation.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------------------------------
-- Roles & permissions (RBAC)
-- -----------------------------------------------------------------------------
CREATE TABLE roles (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(50)  NOT NULL,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    is_staff    TINYINT(1)   NOT NULL DEFAULT 0,  -- back-office role?
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(80)  NOT NULL,
    name        VARCHAR(120) NOT NULL,
    `group`     VARCHAR(50)  NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_rp_permission (permission_id),
    CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles (id)       ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Languages
-- -----------------------------------------------------------------------------
CREATE TABLE languages (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(10)  NOT NULL,   -- 'od', 'en', 'hi'
    name        VARCHAR(50)  NOT NULL,   -- English label
    native_name VARCHAR(50)  NOT NULL,   -- native label (ଓଡ଼ିଆ, हिन्दी ...)
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_languages_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Locations (district / block / village hierarchy)
-- -----------------------------------------------------------------------------
CREATE TABLE locations (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id  INT UNSIGNED NULL,
    name       VARCHAR(120) NOT NULL,
    slug       VARCHAR(140) NOT NULL,
    type       ENUM('state','district','block','town','village','area') NOT NULL DEFAULT 'area',
    latitude   DECIMAL(10,7) NULL,
    longitude  DECIMAL(10,7) NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order INT          NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_locations_slug (slug),
    KEY idx_locations_parent (parent_id),
    CONSTRAINT fk_locations_parent FOREIGN KEY (parent_id) REFERENCES locations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Categories
-- -----------------------------------------------------------------------------
CREATE TABLE categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id   INT UNSIGNED NULL,
    slug        VARCHAR(120) NOT NULL,
    name        VARCHAR(120) NOT NULL,   -- English
    name_od     VARCHAR(120) NULL,       -- Odia
    name_hi     VARCHAR(120) NULL,       -- Hindi
    description VARCHAR(255) NULL,
    icon        VARCHAR(60)  NULL,       -- optional icon name/emoji
    color       VARCHAR(20)  NULL,       -- optional accent color
    sort_order  INT          NOT NULL DEFAULT 0,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent (parent_id),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Users  (every member is also a community reporter)
-- -----------------------------------------------------------------------------
CREATE TABLE users (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(120)  NOT NULL,
    username       VARCHAR(80)   NOT NULL,            -- used for /reporter/{username}
    email          VARCHAR(190)  NOT NULL,
    phone          VARCHAR(20)   NULL,
    password_hash  VARCHAR(255)  NOT NULL,
    avatar         VARCHAR(255)  NULL,                -- uploads/avatars/...
    bio            VARCHAR(500)  NULL,
    location_id    INT UNSIGNED  NULL,
    language_code  VARCHAR(10)   NOT NULL DEFAULT 'en',
    status         ENUM('active','suspended','banned','pending') NOT NULL DEFAULT 'active',
    email_verified TINYINT(1)    NOT NULL DEFAULT 0,
    reporter_since DATE          NULL,
    -- Privacy toggles so personal data is not exposed by default.
    show_email     TINYINT(1)    NOT NULL DEFAULT 0,
    show_phone     TINYINT(1)    NOT NULL DEFAULT 0,
    show_location  TINYINT(1)    NOT NULL DEFAULT 1,
    last_login_at  TIMESTAMP     NULL,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_location (location_id),
    KEY idx_users_status (status),
    CONSTRAINT fk_users_location FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id INT UNSIGNED    NOT NULL,
    PRIMARY KEY (user_id, role_id),
    KEY idx_ur_role (role_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- News (core content table)
-- -----------------------------------------------------------------------------
CREATE TABLE news (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid            CHAR(36)     NOT NULL,            -- stable public id (API/Android)
    slug            VARCHAR(220) NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,         -- reporter
    category_id     INT UNSIGNED NULL,
    location_id     INT UNSIGNED NULL,
    language_code   VARCHAR(10)  NOT NULL DEFAULT 'en',

    title           VARCHAR(220) NOT NULL,
    summary         VARCHAR(500) NULL,                -- short description / card excerpt
    body            MEDIUMTEXT   NULL,                -- full article

    status          ENUM('draft','pending','under_review','needs_information',
                         'approved','scheduled','published','rejected',
                         'expired','archived') NOT NULL DEFAULT 'pending',
    priority        ENUM('normal','featured','breaking','urgent') NOT NULL DEFAULT 'normal',
    is_breaking     TINYINT(1)   NOT NULL DEFAULT 0,
    is_featured     TINYINT(1)   NOT NULL DEFAULT 0,
    breaking_expires_at TIMESTAMP NULL,

    cover_media_id  BIGINT UNSIGNED NULL,             -- FK added after news_media exists

    -- Verification summary (details live in news_verification).
    risk_level      ENUM('unknown','low','medium','high') NOT NULL DEFAULT 'unknown',

    -- SEO
    seo_title        VARCHAR(220) NULL,
    meta_description VARCHAR(300) NULL,
    canonical_url    VARCHAR(255) NULL,

    -- Counters (denormalised for fast cards; kept in sync by app/cron).
    view_count      INT UNSIGNED NOT NULL DEFAULT 0,
    like_count      INT UNSIGNED NOT NULL DEFAULT 0,
    share_count     INT UNSIGNED NOT NULL DEFAULT 0,
    comment_count   INT UNSIGNED NOT NULL DEFAULT 0,

    -- Workflow timestamps
    submitted_at    TIMESTAMP    NULL,
    reviewed_at     TIMESTAMP    NULL,
    reviewed_by     BIGINT UNSIGNED NULL,
    rejection_reason VARCHAR(500) NULL,
    scheduled_at    TIMESTAMP    NULL,
    published_at    TIMESTAMP    NULL,
    expires_at      TIMESTAMP    NULL,

    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_news_slug (slug),
    UNIQUE KEY uq_news_uuid (uuid),
    KEY idx_news_status (status),
    KEY idx_news_status_published (status, published_at),
    KEY idx_news_category (category_id),
    KEY idx_news_location (location_id),
    KEY idx_news_user (user_id),
    KEY idx_news_priority (priority),
    KEY idx_news_breaking (is_breaking, breaking_expires_at),
    KEY idx_news_featured (is_featured),
    KEY idx_news_language (language_code),
    FULLTEXT KEY ft_news_search (title, summary, body),
    CONSTRAINT fk_news_user     FOREIGN KEY (user_id)     REFERENCES users (id)       ON DELETE CASCADE,
    CONSTRAINT fk_news_category FOREIGN KEY (category_id) REFERENCES categories (id)  ON DELETE SET NULL,
    CONSTRAINT fk_news_location FOREIGN KEY (location_id) REFERENCES locations (id)   ON DELETE SET NULL,
    CONSTRAINT fk_news_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- News media (images + videos)
-- -----------------------------------------------------------------------------
CREATE TABLE news_media (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    news_id       BIGINT UNSIGNED NOT NULL,
    type          ENUM('image','video') NOT NULL DEFAULT 'image',
    path          VARCHAR(255) NOT NULL,             -- stored (safe) path, relative to uploads
    thumb_path    VARCHAR(255) NULL,
    mime          VARCHAR(100) NULL,
    size_bytes    BIGINT UNSIGNED NULL,
    width         INT UNSIGNED NULL,
    height        INT UNSIGNED NULL,
    duration_secs INT UNSIGNED NULL,                 -- for video
    original_name VARCHAR(255) NULL,                 -- sanitised display name only
    caption       VARCHAR(255) NULL,
    sort_order    INT NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_media_news (news_id),
    CONSTRAINT fk_media_news FOREIGN KEY (news_id) REFERENCES news (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cover_media_id now that news_media exists.
ALTER TABLE news
    ADD CONSTRAINT fk_news_cover FOREIGN KEY (cover_media_id)
    REFERENCES news_media (id) ON DELETE SET NULL;

-- -----------------------------------------------------------------------------
-- News sources / references
-- -----------------------------------------------------------------------------
CREATE TABLE news_sources (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    news_id    BIGINT UNSIGNED NOT NULL,
    label      VARCHAR(200) NULL,
    url        VARCHAR(500) NULL,
    note       VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sources_news (news_id),
    CONSTRAINT fk_sources_news FOREIGN KEY (news_id) REFERENCES news (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Verification (automated + human) — one summary row per news item
-- -----------------------------------------------------------------------------
CREATE TABLE news_verification (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    news_id          BIGINT UNSIGNED NOT NULL,
    risk_level       ENUM('unknown','low','medium','high') NOT NULL DEFAULT 'unknown',
    checks_json      JSON NULL,                       -- structured check results
    concerns_json    JSON NULL,                       -- list of potential concerns
    evidence_json    JSON NULL,                       -- external evidence found
    duplicate_news_id BIGINT UNSIGNED NULL,           -- strongest duplicate match
    auto_decision    ENUM('none','eligible','hold') NOT NULL DEFAULT 'none',
    internal_notes   TEXT NULL,                       -- admin-only notes
    last_checked_at  TIMESTAMP NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_verification_news (news_id),
    KEY idx_verification_dup (duplicate_news_id),
    CONSTRAINT fk_verification_news FOREIGN KEY (news_id) REFERENCES news (id) ON DELETE CASCADE,
    CONSTRAINT fk_verification_dup  FOREIGN KEY (duplicate_news_id) REFERENCES news (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable audit trail of every verification / workflow transition.
CREATE TABLE news_verification_logs (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    news_id    BIGINT UNSIGNED NOT NULL,
    actor_type ENUM('system','admin','reporter') NOT NULL DEFAULT 'system',
    admin_id   BIGINT UNSIGNED NULL,
    action     VARCHAR(80) NOT NULL,                 -- e.g. AUTO_CHECK, APPROVED, REJECTED
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NULL,
    note       VARCHAR(1000) NULL,
    data_json  JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_vlogs_news (news_id),
    KEY idx_vlogs_admin (admin_id),
    CONSTRAINT fk_vlogs_news  FOREIGN KEY (news_id)  REFERENCES news (id)  ON DELETE CASCADE,
    CONSTRAINT fk_vlogs_admin FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Engagement: views, comments, likes, shares, saves
-- -----------------------------------------------------------------------------
CREATE TABLE news_views (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    news_id    BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NULL,
    ip_hash    CHAR(64) NULL,                        -- hashed IP (no raw PII)
    user_agent VARCHAR(255) NULL,
    viewed_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_views_news (news_id),
    KEY idx_views_news_time (news_id, viewed_at),
    KEY idx_views_dedupe (news_id, ip_hash, viewed_at),
    CONSTRAINT fk_views_news FOREIGN KEY (news_id) REFERENCES news (id) ON DELETE CASCADE,
    CONSTRAINT fk_views_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comments (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    news_id    BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    parent_id  BIGINT UNSIGNED NULL,
    body       TEXT NOT NULL,
    status     ENUM('pending','approved','hidden','deleted') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comments_news (news_id),
    KEY idx_comments_parent (parent_id),
    KEY idx_comments_user (user_id),
    KEY idx_comments_status (status),
    CONSTRAINT fk_comments_news   FOREIGN KEY (news_id)   REFERENCES news (id)     ON DELETE CASCADE,
    CONSTRAINT fk_comments_user   FOREIGN KEY (user_id)   REFERENCES users (id)    ON DELETE CASCADE,
    CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES comments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comment_reports (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    comment_id BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    reason     VARCHAR(50) NOT NULL,
    note       VARCHAR(500) NULL,
    status     ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_creports_comment (comment_id),
    KEY idx_creports_status (status),
    CONSTRAINT fk_creports_comment FOREIGN KEY (comment_id) REFERENCES comments (id) ON DELETE CASCADE,
    CONSTRAINT fk_creports_user    FOREIGN KEY (user_id)    REFERENCES users (id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE likes (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    news_id    BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_like (news_id, user_id),
    KEY idx_likes_user (user_id),
    CONSTRAINT fk_likes_news FOREIGN KEY (news_id) REFERENCES news (id)  ON DELETE CASCADE,
    CONSTRAINT fk_likes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shares (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    news_id    BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NULL,
    channel    VARCHAR(40) NULL,                     -- whatsapp, facebook, copy ...
    ip_hash    CHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_shares_news (news_id),
    CONSTRAINT fk_shares_news FOREIGN KEY (news_id) REFERENCES news (id)  ON DELETE CASCADE,
    CONSTRAINT fk_shares_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saved_news (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    news_id    BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_saved (news_id, user_id),
    KEY idx_saved_user (user_id),
    CONSTRAINT fk_saved_news FOREIGN KEY (news_id) REFERENCES news (id)  ON DELETE CASCADE,
    CONSTRAINT fk_saved_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Reports against published news
-- -----------------------------------------------------------------------------
CREATE TABLE news_reports (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    news_id     BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NULL,                -- null = anonymous (if allowed)
    reason      ENUM('false_information','duplicate','offensive','copyright',
                     'spam','wrong_information','other') NOT NULL,
    note        VARCHAR(1000) NULL,
    status      ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
    resolved_by BIGINT UNSIGNED NULL,
    resolved_at TIMESTAMP NULL,
    ip_hash     CHAR(64) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_nreports_news (news_id),
    KEY idx_nreports_status (status),
    CONSTRAINT fk_nreports_news     FOREIGN KEY (news_id)     REFERENCES news (id)  ON DELETE CASCADE,
    CONSTRAINT fk_nreports_user     FOREIGN KEY (user_id)     REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_nreports_resolver FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Notifications (DB-backed; API-ready for Android push later)
-- -----------------------------------------------------------------------------
CREATE TABLE notifications (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    type       VARCHAR(50) NOT NULL,                 -- news.submitted, news.approved ...
    title      VARCHAR(200) NOT NULL,
    body       VARCHAR(500) NULL,
    news_id    BIGINT UNSIGNED NULL,
    data_json  JSON NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    read_at    TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_user_read (user_id, is_read),
    KEY idx_notif_news (news_id),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_news FOREIGN KEY (news_id) REFERENCES news (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Admin / audit log
-- -----------------------------------------------------------------------------
CREATE TABLE admin_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id    BIGINT UNSIGNED NULL,
    action      VARCHAR(80) NOT NULL,                -- ADMIN_APPROVED_NEWS ...
    entity_type VARCHAR(50) NULL,                    -- news, user, setting ...
    entity_id   BIGINT UNSIGNED NULL,
    news_id     BIGINT UNSIGNED NULL,
    old_status  VARCHAR(40) NULL,
    new_status  VARCHAR(40) NULL,
    reason      VARCHAR(1000) NULL,
    ip_address  VARCHAR(45) NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_alogs_admin (admin_id),
    KEY idx_alogs_action (action),
    KEY idx_alogs_entity (entity_type, entity_id),
    CONSTRAINT fk_alogs_admin FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Settings (key/value, typed)
-- -----------------------------------------------------------------------------
CREATE TABLE settings (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`       VARCHAR(100) NOT NULL,
    `value`     TEXT NULL,
    type        ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
    `group`     VARCHAR(50) NULL,
    updated_by  BIGINT UNSIGNED NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (`key`),
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Infrastructure: login attempts + generic rate limiting
-- -----------------------------------------------------------------------------
CREATE TABLE login_attempts (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier  VARCHAR(190) NOT NULL,               -- email or username tried
    ip_address  VARCHAR(45) NULL,
    successful  TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_identifier (identifier, created_at),
    KEY idx_login_ip (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action       VARCHAR(60) NOT NULL,               -- 'register','submit_news' ...
    bucket_key   VARCHAR(190) NOT NULL,              -- ip / user id / composite
    hits         INT UNSIGNED NOT NULL DEFAULT 0,
    window_start TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rate (action, bucket_key),
    KEY idx_rate_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- API tokens (Phase 5 — Android / REST authentication)
-- -----------------------------------------------------------------------------
CREATE TABLE api_tokens (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      BIGINT UNSIGNED NOT NULL,
    token_hash   CHAR(64) NOT NULL,                  -- sha256 of the bearer token
    device_name  VARCHAR(120) NULL,
    last_used_at TIMESTAMP NULL,
    expires_at   TIMESTAMP NULL,
    revoked      TINYINT(1) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token_hash (token_hash),
    KEY idx_tokens_user (user_id),
    CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
--  End of schema.
-- =============================================================================
