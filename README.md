# KhuntaLocal

A hyperlocal community news platform for **Khunta** and the surrounding areas of
**Mayurbhanj, Odisha**. Every registered member is both a news reader and a
community reporter: they submit local news that passes through a verification
workflow before public publication. The backend is designed so the same data can
later power a native Android app via REST APIs.

**Stack:** PHP 8+ · MySQL (utf8mb4) · PDO (prepared statements everywhere) ·
HTML5/CSS3/JS · Bootstrap 5 + a custom emerald theme · no framework, deployable
on ordinary PHP hosting.

---

## 🚦 Build status — Phase 1 of 5

This repository is being built in five reviewable phases. **Phases 1–2 are complete.**

| Phase | Scope | Status |
|------|-------|--------|
| **1** | Database, config, core library, auth (register/login/logout), homepage, news submission, basic article/category/search/profile pages | ✅ **Done** |
| **2** | Reporter dashboard, admin dashboard, verification review workflow (approve / reject / request info / schedule) | ✅ **Done** |
| 3 | Full media (galleries + video), advanced search/filter, comments, reports, notifications centre | ⏳ Planned |
| 4 | Automated verification engine, cron jobs, auto-publish rules, audit-log UI | ⏳ Planned |
| 5 | REST API + Android integration, production security hardening & full SEO/PWA | ⏳ Planned |

The **complete database schema for all phases** and the **reusable core library**
are already in place, so later phases build on a stable foundation.

### What works today (Phase 1)
- Register / log in / log out (secure sessions, CSRF, rate-limiting, lockout).
- Mobile-first homepage: breaking strip, featured story, latest grid, trending,
  most-viewed, category chips, photo/video sections (video shows an honest
  "coming soon" state).
- Multi-step **Submit news** form (headline, category, language, location,
  description, source/reference, optional cover image) → saved as `pending`.
- Basic article page, category listing, categories index, keyword search,
  and a profile page with editable details + a **"My submissions"** tracker.
- Role-based access control, audit logging, DB notifications — all wired.

### Added in Phase 2
- **Reporter dashboard** (`/reporter/`) — submission stat cards + full table with
  verification status, review time, reviewer notes, and per-item actions.
- **Edit & resubmit** (`/reporter/edit.php`) — edit eligible submissions; saving a
  *needs-information* item resubmits it for review (notifies verifiers).
- **Admin console** (`/admin/`) — dashboard metrics (total news, pending
  verification, published today, breaking, reports, users) + a verification queue
  with review timers and risk flags.
- **Verification review** (`/admin/verify.php`) — split layout: original
  submission on the left; a verification assistant on the right (reporter history,
  automated checks, possible duplicates/similar stories, internal notes, full
  audit history). Actions: **Approve & Publish**, **Request more information**,
  **Reject** (reason required), **Schedule** — all transactional, audited,
  idempotent (never double-publishes), and they notify the reporter.

### Honestly deferred (shown as "coming soon", never broken links)
Full photo galleries & video, comments, save/report buttons, the notifications
centre, the automated-verification *engine* (external evidence + scoring),
cron/auto-publish, the REST API, and deep SEO/PWA. Each is scheduled above.

> **On accuracy:** KhuntaLocal never claims an automated system can prove a story
> is absolutely true. The review process collects evidence, checks for duplicates
> and flags concerns for a human reviewer. Published stories show a transparent
> *"Reviewed by KhuntaLocal"* label — not a certainty score.

---

## 📁 Folder structure

```
khuntalocalweb/
├── index.php              Homepage
├── login.php  register.php  logout.php
├── submit-news.php        Multi-step submission (login required)
├── news.php               Article detail (clean URL: /news/{slug})
├── category.php           Single category (/category/{slug})
├── categories.php         All categories
├── latest.php             Latest / trending / photos / videos listing
├── search.php             Keyword + category search
├── profile.php            Own profile + "My submissions"; public /reporter/{username}
├── robots.txt  .htaccess  (clean URLs + hardening)
│
├── config/
│   ├── config.sample.php  Template (committed) — copy to config.php
│   └── config.php         Your real settings (git-ignored)
├── includes/              Reusable core library (see below)
│   └── partials/          head, header, footer, bottom-nav
├── assets/
│   ├── css/theme.css      Emerald design system
│   ├── js/app.js          Multi-step form, image preview, share, etc.
│   └── vendor/bootstrap/  Bootstrap 5 (bundled locally)
├── uploads/               User media (git-ignored contents; .htaccess blocks exec)
├── database/
│   ├── schema.sql         Full schema (all 27 tables, utf8mb4, FKs, indexes)
│   └── seed.sql           Roles, permissions, languages, locations, categories,
│                          settings, super admin, sample news
├── scripts/selftest.php   Pure-logic test suite (no DB needed)
├── admin/                 Admin console: dashboard, verification queue + review
├── reporter/              Reporter dashboard + edit/resubmit
├── api/  cron/            Placeholders for Phases 4–5 (see each README)
```

### Core library (`includes/`)
`bootstrap.php` (wiring + secure session) · `db.php` (PDO + helpers) ·
`helpers.php` · `csrf.php` · `settings.php` · `validation.php` · `ratelimit.php` ·
`auth.php` · `authz.php` (RBAC) · `audit.php` · `notifications.php` · `upload.php`
(secure images) · `news.php` (repository) · `seo.php` · `ui.php` (card/flash/pagination).

---

## ⚙️ Installation

### 1. Requirements
- PHP **8.1+** with extensions: `pdo_mysql`, `gd`, `mbstring`, `fileinfo`, `exif`, `openssl`
- MySQL **5.7+** or MariaDB **10.3+**
- Apache with `mod_rewrite` (clean URLs) — or Nginx (see note below)

### 2. Configure
```bash
cp config/config.sample.php config/config.php
```
Edit `config/config.php` (or set the equivalent environment variables — every
value falls back to an env var, which is preferred in production):

| Key | Env var | Notes |
|-----|---------|-------|
| `db.host` / `db.port` | `DB_HOST` / `DB_PORT` | or use `db.socket` / `DB_SOCKET` |
| `db.database` | `DB_NAME` | e.g. `khuntalocal` |
| `db.username` / `db.password` | `DB_USER` / `DB_PASS` | |
| `app.url` | `APP_URL` | public base URL, **no trailing slash** |
| `app.env` | `APP_ENV` | `production` (default) or `development` |

### 3. Create the database & import
```bash
mysql -u root -p -e "CREATE DATABASE khuntalocal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p khuntalocal < database/schema.sql
mysql -u root -p khuntalocal < database/seed.sql
```

### 4. Upload permissions
The web server must be able to write to `uploads/`:
```bash
chmod -R 775 uploads/
# or: chown -R www-data:www-data uploads/
```
`uploads/.htaccess` already disables script execution inside the uploads tree.
On Nginx, add an equivalent rule (deny execution of PHP under `/uploads`).

### 5. Run
- **Apache:** point a vhost at the project root; `.htaccess` handles clean URLs.
- **Local (quick test):**
  ```bash
  php -S 127.0.0.1:8000
  ```
  (The built-in server ignores `.htaccess`; use `?slug=` style URLs, or deploy
  on Apache/Nginx for pretty URLs.)

#### Nginx clean-URL rules (equivalent to `.htaccess`)
```nginx
location = / { try_files /index.php =404; }
rewrite ^/news/([A-Za-z0-9\-]+)/?$      /news.php?slug=$1     last;
rewrite ^/category/([A-Za-z0-9\-]+)/?$  /category.php?slug=$1 last;
rewrite ^/reporter/([A-Za-z0-9\-]+)/?$  /profile.php?reporter=$1 last;
location ~* ^/(config|includes|database|scripts)/ { deny all; }
location ~* ^/uploads/.*\.(php|phtml|phar)$ { deny all; }
```

---

## 🔑 Admin login setup

`seed.sql` creates two accounts. **Change these passwords immediately** after the
first login (go to *Profile*; full admin user management arrives in Phase 2).

| Role | Email | Password |
|------|-------|----------|
| Super Admin | `admin@khuntalocal.local` | `Admin@12345` |
| Reporter (sample) | `reporter@khuntalocal.local` | `Reporter@123` |

To create your own super admin instead, register normally, then grant the role:
```sql
INSERT INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u, roles r
WHERE u.email = 'you@example.com' AND r.slug = 'super_admin';
```

---

## 🔒 Security checklist (implemented in Phases 1–2)

- [x] **PDO prepared statements** for all user-controlled input (no string-built SQL)
- [x] `password_hash()` / `password_verify()` (bcrypt via `PASSWORD_DEFAULT`, auto-rehash)
- [x] **CSRF tokens** on every state-changing POST (constant-time verify, 419 on failure)
- [x] **Session regeneration** on login; secure cookies (HttpOnly, SameSite, Secure on HTTPS)
- [x] **Rate limiting** (register, submit) + **login lockout** after repeated failures
- [x] **Output escaping** via `e()` everywhere user content is printed
- [x] **Secure uploads**: content-based MIME check, extension allowlist, size limit,
      random safe filenames, no-exec `.htaccess` in `uploads/`
- [x] **RBAC** enforced on every admin/verification action (`can()`,
      `require_permission()` per action) + **audit logging** of each decision
- [x] Workflow transitions are transactional & idempotent (never double-publish)
- [x] Sensitive dirs blocked via `.htaccess`; config file git-ignored
- [x] Server-side validation (client checks are hints only)

Hardening that lands with its feature in later phases: comment/report moderation,
API token auth, and a full security review (Phase 5).

---

## ✅ How this was verified

- **`php -l`** passes on every PHP file.
- **`php scripts/selftest.php`** — 38 pure-logic assertions (slugify, validators,
  CSRF, password hashing, time/format helpers) all pass, no DB required.
- **End-to-end against real MySQL/MariaDB:** schema + seed import cleanly (27
  tables); the homepage and all pages render (HTTP 200 / correct 404 / 302 gates)
  with zero PHP errors; register → auto-login → submit-news writes a `pending`
  story with its source, verification log and notifications; seeded admin and
  reporter log in; wrong passwords and CSRF-less POSTs are rejected (419);
  the view counter increments.
- **Phase 2 workflow against MySQL/MariaDB:** admin + reporter dashboards render;
  opening a pending item auto-claims it (`pending` → `under_review`); request-info,
  reject (reason required; empty reason blocked), approve (sets `published_at`) and
  re-approve (idempotent — one audit entry) all behave correctly and notify the
  reporter; a plain reporter is refused admin pages (403); a reporter can
  edit/resubmit only their own eligible submissions (others → 404, published →
  redirected).

Run the logic suite yourself:
```bash
php scripts/selftest.php
```

---

## 🗺️ Roadmap

Phases 2–5 add the reporter & admin dashboards, the verification review screen,
full media, comments, reports, notifications, the automated-verification engine,
cron/auto-publish, the REST API for Android, and production SEO/PWA — building on
the schema and core library already shipped here.
