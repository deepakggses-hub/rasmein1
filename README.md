# Rasmein — Custom Gifting & E-Commerce Platform

A dual-mode gifting platform: a **build-your-own gift box** flow alongside a
standard **e-commerce store**, with a site-wide switch between **Buy now**
(online payment) and **Enquire now** (lead capture), and a full admin panel.

Built on CodeIgniter 4.7 · PHP 8.2+ · MySQL 8 / MariaDB 10.4+ · Tailwind CSS 4

---

## Current status

| Phase | Scope | State |
|---|---|---|
| **1** | Foundation, database schema, design system, homepage | **Complete** |
| **2a** | Catalogue, filters, search, product pages, collections | **Complete** |
| 2b | Cart, checkout, order creation | Not started |
| 3 | Gift-box builder, Buy/Enquire switch | Not started |
| 4 | Admin panel | Not started |
| 5 | Customer accounts | Not started |
| 6 | Payment gateway (Razorpay) | Deferred by request |

Navigation links to unbuilt sections resolve to a branded "in build" page
rather than a 404. See `docs/PHASES.md` for the full plan.

**Payments are intentionally off.** `payment.enabled = false` in `.env`. The
`payments` table and gateway settings exist but are inert, so enabling Razorpay
later is additive rather than a refactor.

---

## Local setup

### Requirements

- PHP **8.2+** with `intl`, `mbstring`, `mysqli`, `curl`, `gd`, `fileinfo`
- MySQL 8 or MariaDB 10.4+
- Composer 2
- Node 18+ — **only** if you intend to change the CSS. The compiled
  stylesheet is committed, so the server itself never needs Node.

### Steps

```bash
# 1. Dependencies
composer install

# 2. Environment
cp .env.example .env
#    Then edit .env and set at minimum:
#      app.baseURL
#      database.default.database / username / password
php spark key:generate          # writes encryption.key

# 3. Database
mysql -u root -e "CREATE DATABASE rasmein CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php spark migrate
php spark db:seed DatabaseSeeder

# 4. Run
php spark serve
```

The seeder prints a generated admin password **once**. Copy it — it is not
written to disk anywhere, and the account is flagged to force a change on
first sign-in.

### Front-end build

```bash
npm install
npm run dev      # watch mode while working on styles
npm run build    # minified, for commit + deploy
```

Tailwind scans `app/Views/**`. Output goes to `public/assets/css/app.css`,
which **is** committed on purpose.

---

## Useful commands

```bash
php spark migrate                    # apply new migrations
php spark migrate:rollback -b 0      # drop everything (destructive)
php spark db:seed DatabaseSeeder     # idempotent — safe to re-run
php spark routes                     # every reachable route + its filters
php spark rasmein:diag               # preflight: PHP, extensions, .env, DB
php spark rasmein:diag-catalogue     # filters, sorting, pagination, related
php spark rasmein:diag-search        # hostile + awkward search input
```

`rasmein:diag` runs every storefront query and reports OK/FAIL per query. It is
CLI-only and not web-reachable. Useful straight after a deploy.

---

## Layout

```
app/
  Config/          Rasmein.php holds project constants; Routes.php is the
                   single source of what is reachable (auto-routing is OFF)
  Controllers/
    Storefront/    public pages
    Admin/         admin panel (Phase 4)
  Database/
    Migrations/    10 grouped migrations — the whole schema
    Seeds/         idempotent demo data
    Traits/        SchemaHelpers — shared column builders
  Entities/        Product, GiftBox, Category — derived logic lives here
  Filters/         security headers, admin auth, customer auth
  Helpers/         rasmein_helper.php — money, journey mode, images
  Libraries/       ApiExceptionHandler
  Models/          one per table, each with explicit allowed fields + rules
  Services/        SettingsService (journey switch), AuditService
  Views/
    layouts/       storefront shell
    partials/      header, footer, tray, product card
    storefront/    page templates
    errors/html/   branded, leak-free error pages
docs/              proposal, schema notes, phase plan, nginx reference
public/            THE DOCUMENT ROOT — nothing above it should be servable
resources/css/     Tailwind source + design tokens
```

---

## Deployment notes

1. **Document root must be `public/`.** This single setting is what keeps
   `app/`, `writable/`, `vendor/` and `.env` unreachable.
2. Set `CI_ENVIRONMENT = production` in `.env`. That disables verbose errors
   and the debug toolbar.
3. Set `app.forceGlobalSecureRequests = true` and `cookie.secure = true` once
   HTTPS is confirmed working.
4. `php spark key:generate` on the server. Do not copy a key between
   environments.
5. `composer install --no-dev --optimize-autoloader`.
6. `writable/` must be writable by the web server — `775`, not `777`.
7. Apache reads `public/.htaccess` as shipped. For nginx, use
   `docs/nginx.conf.example`.
8. Set `expose_php = Off` in `php.ini`. The app removes `X-Powered-By` at
   runtime, but turning it off at source is cleaner.
9. Work through the pre-deploy checklist in `CLAUDE.md` §14.

### Database user privileges

The application user needs only:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON rasmein.* TO 'rasmein'@'localhost';
```

Grant `CREATE, ALTER, INDEX, DROP, REFERENCES` temporarily when running
migrations, then revoke them.

---

## Conventions

- Controllers stay thin. Business rules live in Models and Services.
- Every model declares `$allowedFields` and `$validationRules` explicitly.
  Nothing is mass-assigned from a request.
- Every money figure is recalculated server-side at checkout. Cart
  `*_snapshot` columns are display values only and are never trusted.
- Views escape everything with `esc()`. The one exception is CMS page bodies,
  which staff author and which are sanitised on save.
- All schema changes go through migrations. No manual production DB edits.
- Inside a view, `$this->include($name)` shares the parent's data;
  `view($name, $data)` is required to pass *new* data. `include()`'s second
  argument is renderer options, **not** view data.

See `CLAUDE.md` for the full security ruleset and `docs/DATABASE.md` for the
schema.
