# CLAUDE.md — Rasmein E-Commerce Platform (CodeIgniter 4)

This file is the project-level instruction set for Claude Code (and any dev/AI agent)
working on the Rasmein platform. It encodes the product scope, tech stack, and — most
importantly — the **non-negotiable security rules** that must be followed on every file,
every controller, and every PR. Read this before writing or editing any code.

---

## 1. Project Context

Rasmein is a dual-mode gifting + e-commerce platform:

- **Build-Your-Own Gift Box** flow (box → products → personalise → review)
- **Standard e-commerce store** (catalogue, cart, checkout, wishlist, search)
- **Dual Journey switch**: admin toggles the *entire site* between `buy_now`
  (Razorpay payment) and `enquire_now` (lead capture) — this is a global setting,
  not per-user, and must be enforced server-side on every order-creating endpoint.
- **Full admin panel**: products, categories, gift-box rules, orders, enquiries/leads,
  coupons, customers, reports, mode switch.

**Stack**
| Layer | Choice |
|---|---|
| Framework | CodeIgniter 4 (PHP 8.1+) |
| DB | MySQL 8 (InnoDB, utf8mb4) |
| Payments | Razorpay |
| Notifications | Email (SMTP) + SMS/WhatsApp API |
| Frontend | Server-rendered views (CI4) + vanilla JS/Alpine or a light framework |
| Hosting | Linux, HTTPS-only |

---

## 2. Golden Rules (apply to every task, no exceptions)

1. **Never trust client input.** Every price, quantity, box-capacity, and mode
   (buy/enquire) decision is recalculated/re-validated on the server, ignoring
   whatever the client sent, for every request that touches money or orders.
2. **Never commit secrets.** No API keys, DB passwords, Razorpay keys, or SMTP
   credentials in code — `.env` only, and `.env` is git-ignored.
3. **Never expose internals in errors.** Production must never show stack traces,
   file paths, controller/model names, or SQL errors to the browser.
4. **Least privilege everywhere.** Admin roles are scoped; DB user has only the
   grants it needs; file permissions are minimal.
5. **Every admin action is authenticated, authorized, CSRF-protected, and logged.**

---

## 3. Environment & Configuration Security

- `CI_ENVIRONMENT` must be `production` on the live server (`app/Config/Boot/production.php`
  disables `display_errors` and verbose errors automatically — verify this is actually
  in effect, don't assume).
- Set in `.env` (never in versioned config files):
  ```
  CI_ENVIRONMENT = production
  app.baseURL = 'https://rasmein.com/'
  app.forceGlobalSecureRequests = true
  database.default.hostname = ...
  database.default.password = ...
  encryption.key = <32+ byte random key, generate via `php spark key:generate`>
  RazorpayKeyID = ...
  RazorpayKeySecret = ...
  ```
- Commit a `.env.example` with **empty/placeholder values only**, never real ones.
- `.gitignore` must include: `.env`, `writable/`, `vendor/` (or lock it via composer),
  `/public/uploads/*` (except a `.gitkeep`).
- `app.CSPEnabled = true` in `Config/App.php` — configure a real Content-Security-Policy
  in `Config/ContentSecurityPolicy.php` (restrict script-src, no unsafe-inline where avoidable).
- Disable the debug toolbar in production (`Config/Filters.php` — `toolbar` filter should
  only load when `ENVIRONMENT !== 'production'`).
- Turn off directory listing on the web server (Apache: `Options -Indexes`; Nginx:
  `autoindex off;`).

---

## 4. Hiding Controllers / Preventing Information Leaks

- **Document root must point to `/public`**, never to the project root. This alone
  prevents direct access to `app/Controllers`, `app/Models`, `.env`, `writable/`, etc.
- Add a hardened `public/.htaccess` (Apache) or Nginx block that:
  - Blocks access to dotfiles (`.env`, `.git`).
  - Blocks access to `/app`, `/writable`, `/vendor`, `/tests` even if mis-deployed.
  - Removes the `X-Powered-By` and server signature headers.
- Custom error pages for 400/403/404/500 (`app/Views/errors/html/`) — generic,
  branded, **no** framework version, no PHP version, no file paths.
- Never `var_dump()`, `print_r()`, or echo raw exceptions in production code paths.
  Use CI4's logger (`log_message('error', ...)`) instead, and let a generic
  `ErrorHandler`/`Exceptions` config render a safe message.
- API/JSON error responses return a fixed shape, e.g.
  `{"status":"error","message":"Something went wrong"}` — never the raw
  `Throwable::getMessage()` from a DB or filesystem exception.
- Route only what's needed. Don't use CI4 auto-routing in production
  (`Config/Routing.php` → `$autoRoute = false;`) — declare explicit routes in
  `Config/Routes.php` so no controller is reachable unless intentionally exposed.
- Disable/remove any debug or dev-only routes, seeders, or test controllers before deploy.

---

## 5. Authentication & Password Security

- Use `password_hash($password, PASSWORD_DEFAULT)` (bcrypt, or `PASSWORD_ARGON2ID`
  if the server supports it) for every stored password — customer and admin.
  **Never** MD5/SHA1/plain text, never a custom hash.
- Verify with `password_verify()`. Never compare hashes with `==` or `===` on raw strings.
- Enforce a minimum password policy (length ≥ 8, not in a common-password blocklist)
  via CI4's `Validation` rules on register/change-password forms.
- Rehash on login if `password_needs_rehash()` returns true (lets you upgrade cost
  factor over time without forcing resets).
- Rate-limit login attempts (CI4 Throttler service) — e.g. 5 attempts / 60 seconds
  per IP+username combo, with a lockout/backoff, to block brute force.
- Session config (`Config/Session.php`):
  - `sessionCookieName` renamed from the default.
  - `sessionMatchIP = false` unless you understand the proxy implications; but do
    set `sessionExpiration` sensibly (e.g. 2 hrs for admin, longer for customers).
  - Store sessions in DB or Redis, not files, in a multi-server setup.
- Cookies: `Config/Cookie.php` → `secure = true` (HTTPS only), `httponly = true`,
  `samesite = 'Lax'` (or `Strict` for admin-only cookies).
- Regenerate session ID on login/privilege change (`session()->regenerate()`) to
  prevent session fixation.
- Separate, harder-to-guess admin login URL is optional theatre — the real control
  is: admin routes behind an `AuthFilter` + role check, rate limiting, and (ideally)
  2FA/OTP for admin accounts given they can change the buy/enquire mode and payouts.
- Never expose "user not found" vs "wrong password" distinctly — generic
  "invalid credentials" message to prevent user enumeration.
- Provide secure password-reset: signed, single-use, short-lived (≤30 min) token
  sent by email — never email the password itself, never a predictable token.

---

## 6. Authorization / Access Control

- All admin controllers extend a base `AdminController` (or use a route-group filter)
  that checks: (1) authenticated, (2) has an admin/staff role, (3) has permission for
  that specific module (e.g. a "support staff" role shouldn't be able to edit the
  buy/enquire master switch or bank/payment settings).
- Enforce authorization **in the controller**, not just by hiding UI links. Hidden
  buttons are not access control.
- Every "get resource by id" admin/customer action (order, enquiry, invoice) must
  verify the requesting user actually owns/may access that resource — don't rely on
  the ID being hard to guess (IDOR prevention). Use UUIDs for public-facing order
  references in addition to internal auto-increment IDs where feasible.
- CSRF protection enabled globally (`Config/Filters.php` → `csrf` filter on all
  POST/PUT/DELETE routes); CI4's `csrf_field()` helper in every form.

---

## 7. Input Validation & Injection Prevention

- **Always use Query Builder or parameterized queries** (`$builder->where('id', $id)`,
  or `$db->query('... WHERE id = ?', [$id])`). Never concatenate user input into raw SQL.
- Use CI4's `Validation` library on every incoming form/API payload — whitelist
  allowed fields; never mass-assign `$this->request->getPost()` directly into a
  model's `save()` without a validation + allowed-fields list (`protectFields`,
  and explicit `$allowedFields` in every Model).
- Sanitize/validate types explicitly: quantities and box-capacity as integers,
  prices as decimals compared against the DB source of truth (never the value
  posted from the cart page).
- Escape all output in views: use CI4's `esc()` helper for anything echoed into
  HTML/JS/URL/attribute context to prevent XSS (`esc($value, 'html')`,
  `esc($value, 'js')`, etc.). Never `echo` raw user-supplied content.
- File upload validation (product images, admin uploads, gift-message attachments
  if any):
  - Whitelist MIME types and extensions (image/jpeg, image/png, image/webp).
  - Enforce a max file size.
  - Re-generate the filename (`$file->getRandomName()`); never trust the original name.
  - Store uploads outside of directly-executable paths where possible, or ensure
    the upload directory has PHP execution disabled (`.htaccess`/Nginx rule:
    no PHP execution in `/public/uploads`).
  - Validate actual file content (e.g. `getimagesize()` / MIME sniffing), not just extension.
- Validate and sanitize any HTML the admin is allowed to enter (product descriptions,
  CMS content) with a strict allowlist-based sanitizer if rich text is permitted —
  don't allow raw `<script>` and `on*` attributes through.

---

## 8. Business-Logic Security (specific to this project)

- **Server-side price/total calculation only.** The gift-box total, product prices,
  discounts, and coupon values are always recomputed from the DB at checkout —
  the client-side "running total" is UI sugar only, never trusted.
- **Box capacity & allowed-products rules** are re-validated server-side when the
  order/enquiry is created, not just enforced in JS during the builder flow.
- **Buy/Enquire master switch**: read the current mode from the DB/admin setting
  at the moment of checkout, server-side — don't let a stale client page submit a
  "Buy Now" payment request while the site is actually in Enquire mode (or vice versa).
- **Coupons**: validate expiry, usage limits, min-order value, and per-user usage
  server-side; never trust a discount amount sent from the client.
- **Enquiry (lead) form**: protect against spam/bot submission (CAPTCHA/honeypot +
  rate limiting) since it triggers staff notifications.
- **Payment verification**: after Razorpay checkout, verify the payment signature
  server-side using the Razorpay webhook/signature-verification method before
  marking an order as paid. Never mark an order "paid" purely because the client
  redirected to a success page — always confirm via server-to-server
  webhook/verification call.
- Never store raw card/payment details — Razorpay (or any PCI-DSS compliant
  gateway) handles that; Rasmein's DB only stores transaction/reference IDs and status.
- Idempotency: guard order/payment creation endpoints against double-submission
  (double-click, retried webhook) using a unique order reference + DB constraint.

---

## 9. Transport & Headers

- Enforce HTTPS everywhere (`app.forceGlobalSecureRequests = true`; HSTS header
  at the web-server level once HTTPS is confirmed stable).
- Security headers to set (via a CI4 filter or web server config):
  - `Content-Security-Policy`
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY` (or `SAMEORIGIN` if you need admin panel framing)
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Permissions-Policy` limiting camera/mic/geolocation unless needed
- CORS: only allow the actual frontend origin(s); never `Access-Control-Allow-Origin: *`
  on endpoints that require authentication/cookies.

---

## 10. Logging, Monitoring & Auditing

- Log authentication events (login success/failure, password reset), admin actions
  (mode switch, price/coupon changes, order status changes), and payment events —
  to `writable/logs` or a dedicated `admin_audit_log` table (who/what/when/old→new value).
- Never log full card numbers, passwords, or full payment payloads with secrets.
- Set CI4 `Config/Logger.php` threshold appropriately (don't log at `debug` level in prod).
- Rotate/limit log file growth; restrict log directory permissions.
- Set up basic uptime/error alerting (even a simple email-on-fatal-error hook) so
  issues are caught quickly.

---

## 11. Dependency & Deployment Hygiene

- Run `composer audit` regularly and before each release; keep CI4 and all
  dependencies patched to latest stable minor versions.
- Pin dependency versions in `composer.lock` and commit it.
- `writable/` directory must be writable by the web server but not directly web-
  accessible; verify permissions are the minimum needed (not `777`).
- Database user for the app should have only `SELECT/INSERT/UPDATE/DELETE` on the
  app's schema — not `DROP`, `GRANT`, or access to other databases.
- Automate backups (DB + uploaded media) on a schedule; test restore periodically.
- Use a staging environment with the same hardening as production before every deploy.
- Remove/disable `spark serve` and any dev tooling from the production server.

---

## 12. CI4 Project Conventions (for consistency, not just security)

- Controllers stay thin — validation happens via `Validation` rules/config, business
  logic goes in Models/Services (`app/Services` or `app/Libraries`), not in controllers.
- One Model per DB table, each with explicit `$allowedFields`, `$validationRules`,
  and casts defined — never rely on defaults.
- Use CI4 Entities for anything with derived logic (e.g. `Order` entity with a
  `getFormattedTotal()`), keep raw arrays out of views where possible.
- Namespace admin vs storefront vs API code clearly (e.g. `app/Controllers/Admin/`,
  `app/Controllers/Api/`, `app/Controllers/Storefront/`) with distinct route groups
  and filters per group.
- Use CI4 migrations and seeders for all schema changes — no manual production DB edits.
- Write feature tests (CI4's `Test\FeatureTestTrait`) at minimum for: checkout flow,
  buy/enquire switch behavior, coupon logic, and auth (login/lockout).

---

## 13. Files/Context to Provide Before Starting a Build Task

When kicking off implementation work with Claude Code on this project, have these
ready in the repo/context so the agent isn't guessing:

1. **This `CLAUDE.md`** (project root).
2. **`.env.example`** — all required env keys, no real secrets.
3. **Database schema / ERD** (or a `migrations/` folder if it already exists) —
   tables for products, categories, gift boxes, box rules, orders, enquiries,
   coupons, customers, admin users/roles.
4. **The original requirements doc** (the proposal you already have) — kept as
   `/docs/proposal.pdf` or similar for reference.
5. **Wireframes/UI references**, if any, for the gift-box builder and storefront.
6. **Brand assets** (logo, color palette, fonts) if design work is in scope.
7. **Razorpay sandbox/test credentials** (test mode only) for payment integration work.
8. **A `README.md`** with local setup steps (PHP version, `composer install`,
   `.env` setup, `php spark migrate`, `php spark serve`).
9. **Coding standards reference** — if the team follows PSR-12, note it explicitly
   (CI4 ships a `.php-cs-fixer` friendly style by default).

---

## 14. Pre-Deploy Security Checklist

- [ ] `CI_ENVIRONMENT=production`, debug toolbar off, verbose errors off
- [ ] `.env` not committed, real secrets only on server
- [ ] Document root = `/public`, sensitive folders unreachable
- [ ] Custom 404/500/403 pages, no stack traces exposed
- [ ] All passwords hashed with `password_hash`/bcrypt or Argon2id
- [ ] CSRF filter enabled globally, forms include `csrf_field()`
- [ ] Login rate-limited, generic invalid-credentials message
- [ ] All admin routes behind auth + role filter
- [ ] All money-related values recalculated server-side, not trusted from client
- [ ] Payment signature verified server-side via Razorpay webhook
- [ ] File uploads validated (type, size, renamed, no PHP execution in upload dir)
- [ ] HTTPS enforced, secure/httponly/samesite cookies
- [ ] Security headers (CSP, X-Frame-Options, X-Content-Type-Options, HSTS) set
- [ ] `composer audit` clean, dependencies up to date
- [ ] DB user has least-privilege grants
- [ ] Backups configured and tested
- [ ] Admin actions and auth events logged/audited

---

*Keep this file updated as the project evolves — if a new module (e.g. loyalty
programme, multi-recipient shipping) is added per the proposal's "Larger future
phases," extend Sections 8 and 13 accordingly before implementation begins.*

---

## 15. Implementation Log & Decisions (added during Phase 1)

This section records decisions already made, so a later agent extends the
existing design instead of inventing a parallel one.

### Architecture decisions

1. **One `orders` table, two journeys.** An Enquiry is an order row with
   `journey_mode = 'enquire_now'`; the `enquiries` table hangs lead-pipeline
   fields off it via a unique `order_id`. Do **not** add `enquiry_items` —
   line items live on `order_items` for both journeys. Full rationale in
   `docs/DATABASE.md`.
2. **The cart is database-backed**, not session-backed
   (`carts` / `cart_items` / `cart_item_components`). Cart `*_snapshot`
   columns are display values only; checkout recomputes from source.
3. **Capacity is counted in slots.** `gift_boxes.capacity_slots` versus
   `products.giftbox_slots` per unit. `GiftBoxModel::allowedProductIds()` is
   the single source of truth for what may go in a box — the builder and the
   checkout validator both call it.
4. **Journey resolution** is `SettingsService::resolveItemMode($itemMode)`:
   `inherit` follows `settings.journey_mode`; otherwise the item's pin wins.
   Never read the mode from a request field.
5. **Order snapshots are immutable.** `order_items.name_snapshot` /
   `sku_snapshot` / `unit_price` are written once. Product FKs on order rows
   are `ON DELETE SET NULL`, never `CASCADE`.

### Conventions established

- `app/Config/Rasmein.php` holds code-level constants (enum vocabularies,
  upload limits, page sizes). Anything an admin changes at runtime belongs in
  the `settings` table, read via `service('settings')`.
- Migrations use `App\Database\Traits\SchemaHelpers` for column builders. Add
  new helpers there rather than hand-writing column arrays.
- Entities exist for domain objects with derived logic (`Product`, `GiftBox`,
  `Category`; add `Order` and `Enquiry` in their phases). Log-style and
  infrastructure tables return plain arrays. Keep to that split.
- View helpers live in `app/Helpers/rasmein_helper.php`, prefixed `rs_`
  (`rs_money`, `rs_cta_label`, `rs_image`, `rs_asset`, `rs_excerpt`,
  `rs_active`, `rs_setting`, `rs_journey_mode`). Presentational only.
- Every admin write calls `service('audit')->log()` or `logChange()`.
  `AuditService` redacts anything whose field name looks like a secret.
- Roadmap routes: unbuilt destinations point at `Storefront\Roadmap`. Delete
  the placeholder line in the same commit that adds the real route.
- `php spark rasmein:diag` smoke-tests every storefront query. Run it after a
  deploy. CLI-only, not web-reachable.

### Framework traps already hit — do not re-learn these

- **`select()` escapes identifiers by default.** A hand-written expression such
  as a correlated subquery gets mangled (`ASC` became `` `ASC` ``). Pass
  `escape = false`: `$this->select($sql, false)`. Same for `join()`'s fourth
  argument.
- **`$this->include($view, $data)` does not pass data.** In CI4 the second
  argument is *renderer options*. A partial that needs its own data must use
  `view($name, $data)`. `include()` is correct only when the partial reads the
  parent's inherited data (header, footer).
- **`CodeIgniter\Debug\ExceptionHandler` is `final`.** `ApiExceptionHandler`
  composes it rather than extending it.
- **CI4 does not fall back from HEAD to GET.** Public routes are registered
  with `match(['GET', 'HEAD'], ...)` so uptime monitors and link checkers do
  not receive a 404.
- **CI4 returns an empty body to non-`text/html` clients on an exception.**
  `App\Libraries\ApiExceptionHandler` supplies the fixed
  `{"status":"error","message":"…","errors":{}}` envelope required by §4, and
  delegates HTML requests to the framework handler so the branded views render.
- **`X-Powered-By` is emitted by PHP itself**, below the framework's response
  object. `Response::removeHeader()` cannot reach it; `header_remove()` in
  `SecurityHeadersFilter::after()` does. Also set `expose_php = Off` in
  `php.ini`.
- **A custom exception handler must exempt the CLI.** `Config\Exceptions::handler()`
  is called for `spark` commands too, and a `CLIRequest` has no `accept` header —
  so an "is it HTML?" test sends every command's error to the JSON branch.
  Result: `php spark migrate` printed
  `{"status":"error","message":"Something went wrong on our side."}` instead of
  the actual failure, in *both* environments. `ApiExceptionHandler::wantsJson()`
  now returns false for `is_cli()` and for anything that is not an
  `IncomingRequest`. Never let a handler swallow CLI output — it makes
  migrations, seeders and cron jobs undebuggable.
- **Error views are rendered by a plain `include`**, not through the View
  service — no `$this`, no layouts, no partials. Keep them self-contained so
  they still render when the database is down.

### Outstanding security work (tracked, not yet done)

- [ ] **CSP is written but not enabled.** `Config/ContentSecurityPolicy.php`
      needs directives finalised, then `app.CSPEnabled = true` in production.
      Google Fonts is currently loaded from a CDN — self-host the two families
      to avoid widening `font-src` / `style-src`.
- [ ] Login throttling (CI4 `Throttler`) — wire in Phase 4 alongside admin
      sign-in. `auth_login_attempts` and `LoginAttemptModel::record()` are ready.
- [ ] 2FA for admin accounts. Columns exist (`two_factor_enabled`,
      `two_factor_secret`); no implementation yet.
- [ ] HTML sanitiser for admin-authored CMS content. `pages.content` is
      currently rendered unescaped and is trusted staff input — an allowlist
      sanitiser must run on **save** before Phase 4 exposes the editor.
- [ ] Feature tests for checkout, the journey switch, coupon logic and auth
      lockout (§12).
- [ ] Session storage moves from files to DB/Redis if the app is ever
      multi-server.
