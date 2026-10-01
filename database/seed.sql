-- =============================================================================
--  KhuntaLocal — Seed data
--  Run AFTER schema.sql:
--      mysql -u USER -p KhuntaLocal < database/seed.sql
--
--  IMPORTANT: change the default admin password immediately after first login.
--      Super admin:  admin@khuntalocal.local  /  Admin@12345
--      Reporter:     reporter@khuntalocal.local  /  Reporter@123
--  The hashes below were produced with PHP password_hash(..., PASSWORD_DEFAULT).
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Roles
-- -----------------------------------------------------------------------------
INSERT INTO roles (id, slug, name, description, is_staff) VALUES
    (1, 'visitor',            'Public Visitor',       'Unauthenticated reader.',            0),
    (2, 'member',             'Member / Reporter',    'Registered member and community reporter.', 0),
    (3, 'verification_admin', 'Verification Admin',   'Reviews and verifies submissions.', 1),
    (4, 'content_admin',      'Content Admin',        'Manages categories, homepage, breaking news.', 1),
    (5, 'moderator',          'Moderator',            'Moderates comments and user reports.', 1),
    (6, 'super_admin',        'Super Admin',          'Full system access.',               1);

-- -----------------------------------------------------------------------------
-- Permissions
-- -----------------------------------------------------------------------------
INSERT INTO permissions (slug, name, `group`) VALUES
    ('news.view',               'View news',                 'news'),
    ('news.submit',             'Submit news',               'news'),
    ('news.edit_own',           'Edit own submissions',      'news'),
    ('news.verify',             'Verify submissions',        'verification'),
    ('news.approve',            'Approve news',              'verification'),
    ('news.reject',             'Reject news',               'verification'),
    ('news.request_info',       'Request more information',  'verification'),
    ('news.manage',             'Manage published content',  'content'),
    ('category.manage',         'Manage categories',         'content'),
    ('language.manage',         'Manage languages',          'content'),
    ('banner.manage',           'Manage banners',            'content'),
    ('homepage.manage',         'Manage homepage sections',  'content'),
    ('comment.moderate',        'Moderate comments',         'moderation'),
    ('report.review',           'Review user reports',       'moderation'),
    ('user.moderate',           'Moderate users',            'moderation'),
    ('spam.review',             'Review spam',               'moderation'),
    ('user.manage',             'Manage users',              'admin'),
    ('admin.manage',            'Manage admins',             'admin'),
    ('permission.manage',       'Manage permissions',        'admin'),
    ('settings.manage',         'Manage settings',           'admin'),
    ('verification_rules.manage','Manage verification rules','admin'),
    ('autopublish.manage',      'Manage auto-publish config','admin'),
    ('audit.view',              'View audit logs',           'admin'),
    ('system.manage',           'Manage system configuration','admin');

-- -----------------------------------------------------------------------------
-- Role -> permission mapping
-- -----------------------------------------------------------------------------
-- super_admin: everything
INSERT INTO role_permissions (role_id, permission_id)
    SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug = 'super_admin';

-- member / reporter
INSERT INTO role_permissions (role_id, permission_id)
    SELECT r.id, p.id FROM roles r JOIN permissions p
    ON p.slug IN ('news.view','news.submit','news.edit_own')
    WHERE r.slug = 'member';

-- verification admin
INSERT INTO role_permissions (role_id, permission_id)
    SELECT r.id, p.id FROM roles r JOIN permissions p
    ON p.slug IN ('news.view','news.verify','news.approve','news.reject',
                  'news.request_info','audit.view')
    WHERE r.slug = 'verification_admin';

-- content admin
INSERT INTO role_permissions (role_id, permission_id)
    SELECT r.id, p.id FROM roles r JOIN permissions p
    ON p.slug IN ('news.view','news.manage','category.manage','language.manage',
                  'banner.manage','homepage.manage','news.approve')
    WHERE r.slug = 'content_admin';

-- moderator
INSERT INTO role_permissions (role_id, permission_id)
    SELECT r.id, p.id FROM roles r JOIN permissions p
    ON p.slug IN ('news.view','comment.moderate','report.review',
                  'user.moderate','spam.review')
    WHERE r.slug = 'moderator';

-- -----------------------------------------------------------------------------
-- Languages
-- -----------------------------------------------------------------------------
INSERT INTO languages (code, name, native_name, is_active, sort_order) VALUES
    ('od', 'Odia',    'ଓଡ଼ିଆ',   1, 1),
    ('en', 'English', 'English', 1, 2),
    ('hi', 'Hindi',   'हिन्दी',   1, 3);

-- -----------------------------------------------------------------------------
-- Locations (Odisha > Mayurbhanj > Khunta & nearby)
-- -----------------------------------------------------------------------------
INSERT INTO locations (id, parent_id, name, slug, type, latitude, longitude, sort_order) VALUES
    (1, NULL, 'Odisha',       'odisha',        'state',    20.9517000, 85.0985000, 1),
    (2, 1,    'Mayurbhanj',   'mayurbhanj',    'district', 21.9287000, 86.7350000, 1),
    (3, 2,    'Khunta',       'khunta',        'block',    21.6710000, 86.3400000, 1),
    (4, 2,    'Baripada',     'baripada',      'town',     21.9333000, 86.7333000, 2),
    (5, 2,    'Udala',        'udala',         'block',    21.5667000, 86.5667000, 3),
    (6, 2,    'Betnoti',      'betnoti',       'block',    21.7333000, 86.8333000, 4),
    (7, 3,    'Khunta Market','khunta-market', 'area',     21.6720000, 86.3420000, 1);

-- -----------------------------------------------------------------------------
-- Categories
-- -----------------------------------------------------------------------------
INSERT INTO categories (id, slug, name, name_od, name_hi, icon, color, sort_order) VALUES
    (1, 'local',       'Local',       'ସ୍ଥାନୀୟ',      'स्थानीय',     '📍', '#1a7f5a', 1),
    (2, 'politics',    'Politics',    'ରାଜନୀତି',       'राजनीति',    '🏛️', '#b45309', 2),
    (3, 'education',   'Education',    'ଶିକ୍ଷା',        'शिक्षा',      '🎓', '#2563eb', 3),
    (4, 'health',      'Health',      'ସ୍ୱାସ୍ଥ୍ୟ',       'स्वास्थ्य',    '🏥', '#dc2626', 4),
    (5, 'sports',      'Sports',      'କ୍ରୀଡ଼ା',        'खेल',        '🏅', '#7c3aed', 5),
    (6, 'culture',     'Culture',     'ସଂସ୍କୃତି',       'संस्कृति',     '🎭', '#db2777', 6),
    (7, 'events',      'Events',      'ଘଟଣା',          'कार्यक्रम',   '📅', '#0891b2', 7),
    (8, 'weather',     'Weather',     'ପାଗ',           'मौसम',       '⛅', '#0284c7', 8),
    (9, 'development', 'Development',  'ବିକାଶ',         'विकास',      '🏗️', '#16a34a', 9);

-- -----------------------------------------------------------------------------
-- Users  (passwords: see header)
-- -----------------------------------------------------------------------------
INSERT INTO users
    (id, name, username, email, phone, password_hash, bio, location_id, language_code,
     status, email_verified, reporter_since, show_location, created_at)
VALUES
    (1, 'KhuntaLocal Admin', 'admin', 'admin@khuntalocal.local', NULL,
     '$2y$10$QQBVlmEBKtuq1IyJFt1AZ.DiEjdkZvLSPDoZpah7HThbkIRwGNMJy',
     'Platform administrator.', 3, 'en', 'active', 1, '2026-01-01', 0, NOW()),
    (2, 'Amit Kumar', 'amit-kumar', 'reporter@khuntalocal.local', NULL,
     '$2y$10$8/JXZ6WfrHbInCjHuFnSA.tMgZMTfaeQbLvn3LtVNrcALy9Ci6P3W',
     'Community reporter covering Khunta and nearby villages.', 3, 'en',
     'active', 1, '2026-02-15', 1, NOW());

INSERT INTO user_roles (user_id, role_id) VALUES
    (1, 6),   -- admin  -> super_admin
    (1, 2),   -- admin  -> member (can also submit)
    (2, 2);   -- Amit   -> member / reporter

-- -----------------------------------------------------------------------------
-- Sample published news (no media rows — cards render themed placeholders)
-- -----------------------------------------------------------------------------
INSERT INTO news
    (id, uuid, slug, user_id, category_id, location_id, language_code,
     title, summary, body, status, priority, is_breaking, is_featured,
     breaking_expires_at, risk_level, seo_title, meta_description,
     view_count, like_count, share_count, comment_count,
     submitted_at, reviewed_at, reviewed_by, published_at, created_at)
VALUES
    (1, '11111111-1111-4111-8111-111111111111',
     'road-construction-begins-near-khunta-market', 2, 9, 3, 'en',
     'Road construction begins near Khunta market',
     'Work has started to widen and resurface the main approach road to Khunta market, easing daily congestion for traders and shoppers.',
     'Construction crews began work this week on the main approach road leading to Khunta market. The project aims to widen the carriageway and add proper drainage, which residents say has been needed for years.\n\nLocal traders welcomed the move, noting that waterlogging during the monsoon regularly disrupts business. Officials indicated the first phase is expected to continue over the coming weeks.\n\nKhuntaLocal will follow up as the work progresses.',
     'published', 'breaking', 1, 0, NOW() + INTERVAL 6 HOUR, 'low',
     'Road construction begins near Khunta market | KhuntaLocal',
     'Work has started to widen the main approach road to Khunta market, easing congestion for traders and shoppers.',
     342, 18, 7, 0,
     NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 1 HOUR, 1, NOW() - INTERVAL 50 MINUTE, NOW() - INTERVAL 3 HOUR),

    (2, '22222222-2222-4222-8222-222222222222',
     'khunta-students-win-state-science-fair', 2, 3, 3, 'en',
     'Khunta school students win state science fair',
     'A team of students from a Khunta high school earned top honours at the district-level science exhibition with a low-cost water filter project.',
     'Students from a Khunta high school have won recognition at the state science fair for a low-cost water filtration prototype built from locally available materials.\n\nTeachers said the project grew out of a classroom discussion about safe drinking water in nearby villages. The team now hopes to refine the design with support from the school.',
     'published', 'featured', 0, 1, NULL, 'low',
     'Khunta school students win state science fair | KhuntaLocal',
     'A Khunta school team won top honours at the state science fair with a low-cost water filter project.',
     521, 44, 21, 0,
     NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 22 HOUR, 1, NOW() - INTERVAL 21 HOUR, NOW() - INTERVAL 1 DAY),

    (3, '33333333-3333-4333-8333-333333333333',
     'new-health-sub-centre-opens-in-mayurbhanj-block', 2, 4, 5, 'en',
     'New health sub-centre opens in Mayurbhanj block',
     'A new health sub-centre has opened to serve several villages, reducing the distance residents must travel for basic care.',
     'A new health sub-centre has opened in the Udala area, intended to serve a cluster of nearby villages. Residents previously travelled long distances for routine check-ups and vaccinations.\n\nThe facility is expected to offer outpatient services and maternal health support. Community members said the opening is a welcome step.',
     'published', 'normal', 0, 0, NULL, 'low',
     NULL,
     'A new health sub-centre has opened in the Udala area, reducing travel for basic care.',
     198, 12, 4, 0,
     NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 47 HOUR, 1, NOW() - INTERVAL 46 HOUR, NOW() - INTERVAL 2 DAY),

    (4, '44444444-4444-4444-8444-444444444444',
     'weekly-haat-timing-changes-for-festival-season', 2, 1, 3, 'en',
     'Weekly haat timing changes for festival season',
     'The weekly haat near Khunta will shift its timing during the festival season to manage larger crowds.',
     'Organisers of the weekly haat near Khunta have announced revised timings for the festival season to better manage the expected increase in visitors.\n\nShoppers are advised to plan their visits accordingly. The change is temporary and regular timings will resume afterwards.',
     'published', 'normal', 0, 0, NULL, 'low',
     NULL,
     'The weekly haat near Khunta will shift timing during the festival season to manage crowds.',
     87, 5, 2, 0,
     NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 70 HOUR, 1, NOW() - INTERVAL 69 HOUR, NOW() - INTERVAL 3 DAY),

    (5, '55555555-5555-4555-8555-555555555555',
     'monsoon-update-heavy-rain-expected-across-mayurbhanj', 1, 8, 2, 'en',
     'Monsoon update: heavy rain expected across Mayurbhanj',
     'Local reports suggest heavy rain over the next few days across Mayurbhanj; residents are advised to take usual precautions.',
     'Heavy rainfall is expected across parts of Mayurbhanj over the coming days. Residents in low-lying areas are advised to take the usual precautions and avoid unnecessary travel during intense spells.\n\nThis is a community advisory compiled from local reports. Always follow official guidance from the authorities.',
     'published', 'urgent', 0, 0, NULL, 'low',
     NULL,
     'Heavy rain is expected across Mayurbhanj over the next few days. Residents advised to take precautions.',
     264, 9, 31, 0,
     NOW() - INTERVAL 5 HOUR, NOW() - INTERVAL 4 HOUR, 1, NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 5 HOUR);

-- Sources / references for a couple of items
INSERT INTO news_sources (news_id, label, url, note) VALUES
    (1, 'Reporter observation', NULL, 'Observed on site near Khunta market.'),
    (3, 'Community information', NULL, 'Shared by local residents.');

-- Verification summaries (low risk) + a log entry per item
INSERT INTO news_verification (news_id, risk_level, auto_decision, last_checked_at) VALUES
    (1, 'low', 'none', NOW() - INTERVAL 1 HOUR),
    (2, 'low', 'none', NOW() - INTERVAL 22 HOUR),
    (3, 'low', 'none', NOW() - INTERVAL 47 HOUR),
    (4, 'low', 'none', NOW() - INTERVAL 70 HOUR),
    (5, 'low', 'none', NOW() - INTERVAL 4 HOUR);

INSERT INTO news_verification_logs (news_id, actor_type, admin_id, action, old_status, new_status, note) VALUES
    (1, 'admin', 1, 'APPROVED', 'under_review', 'published', 'Verified by admin.'),
    (2, 'admin', 1, 'APPROVED', 'under_review', 'published', 'Verified by admin.'),
    (3, 'admin', 1, 'APPROVED', 'under_review', 'published', 'Verified by admin.'),
    (4, 'admin', 1, 'APPROVED', 'under_review', 'published', 'Verified by admin.'),
    (5, 'admin', 1, 'APPROVED', 'under_review', 'published', 'Verified by admin.');

-- -----------------------------------------------------------------------------
-- Settings  (conservative defaults)
-- -----------------------------------------------------------------------------
INSERT INTO settings (`key`, `value`, type, `group`) VALUES
    ('site_name',                  'KhuntaLocal',                         'string', 'general'),
    ('site_tagline',               'Local news from Khunta & Mayurbhanj', 'string', 'general'),
    ('site_logo',                  '',                                    'string', 'general'),
    ('default_language',           'en',                                  'string', 'general'),
    ('available_languages',        '["od","en","hi"]',                    'json',   'general'),
    ('default_location_id',        '3',                                   'int',    'general'),
    ('verification_min_minutes',   '60',                                  'int',    'verification'),
    ('verification_max_minutes',   '120',                                 'int',    'verification'),
    ('auto_publish_enabled',       '0',                                   'bool',   'verification'),
    ('auto_publish_low_risk_only', '1',                                   'bool',   'verification'),
    ('max_image_size',             '5242880',                             'int',    'uploads'),
    ('max_video_size',             '52428800',                            'int',    'uploads'),
    ('comment_moderation',         '1',                                   'bool',   'moderation'),
    ('registration_enabled',       '1',                                   'bool',   'general'),
    ('maintenance_mode',           '0',                                   'bool',   'general');

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
--  End of seed data.
-- =============================================================================
