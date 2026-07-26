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
- **`Pager` has no `hasPrevious()` / `hasNext()` / `getPrevious()`.** Those are
  `PagerRenderer` methods. On the `Pager` instance the API is
  `getPreviousPageURI()` and `getNextPageURI()`, both nullable. Calling the
  wrong ones 500s the listing page the moment there is a second page — and a
  single-page result set hides it, so it passes a casual test.
- **MySQL FULLTEXT boolean mode is a parser, not a string match.** `+ - > < ( )
  ~ * " @` are operators, and a malformed expression raises a SQL error rather
  than returning nothing. A customer searching `tea (loose)` crashed the page.
  `ProductModel::applySearch()` now reduces every token to `\p{L}\p{N}` before
  building the expression, caps the term length, and OR's the match with a LIKE
  on SKU and name — FULLTEXT indexes name/description only, so SKU lookups
  ("RSM-CH-001") would otherwise silently return nothing.
- **`esc($url, 'attr')` entity-encodes `:` `/` `=` `&`.** Browsers decode it, so
  it works, but view-source becomes unreadable. Internal URLs built by
  `site_url()` from slugs the models validate as `^[a-z0-9-]+$` are output raw.
  Anything a user can influence — pagination query strings, the clear-filters
  link — stays escaped.
- **`CLIRequest` has no `getUserAgent()`.** Only `IncomingRequest` does. Any
  shared code that records a request fingerprint — order creation, the audit
  log, login attempts — crashes when reached from `spark` or cron. Use
  `rs_user_agent()`, which returns null when the method is absent.
- **`getIPAddress()` IS on the base `Request`**, so that one is safe from CLI.
  Do not "fix" it by guarding it too.
- **Error views are rendered by a plain `include`**, not through the View
  service — no `$this`, no layouts, no partials. Keep them self-contained so
  they still render when the database is down.

### Money and order rules now implemented (do not re-litigate)

- `PricingService` is the ONLY place a total is decided. It reads products,
  gift-box rules, settings and coupons from the database. Cart `*_snapshot`
  columns are display values; a test asserts that tampering with them changes
  nothing.
- `PricingService::resolveJourney()` decides Buy vs Enquire for a whole basket:
  site setting first, then any line pinned to `enquire_now` forces the basket to
  Enquire. One order, one journey.
- `OrderService::placeFromCart()` re-prices from source, then does everything in
  ONE transaction: order row, line snapshots, stock reservation, coupon
  redemption, status history, enquiry row, cart conversion, queued
  notifications. Any failure rolls the lot back.
- Idempotency is a per-visit key rendered on the checkout form and stored in the
  session, backed by a unique index on `orders.idempotency_key`. A repeat submit
  returns the existing order.
- Stock is taken by conditional UPDATE (`ProductModel::reserveStock()`), so
  concurrent checkouts cannot oversell. Enquiries deliberately reserve nothing.
- Order confirmation pages check `session('viewable_orders')` or customer
  ownership. An unguessable UUID is not treated as access control on its own.

### Gift-box builder rules now implemented (do not re-litigate)

- **The box under construction is a cart line.** `cart_items.item_type =
  'gift_box'` plus `cart_item_components`. There is no draft table. Do not add
  one — see docs/PHASES.md Phase 3 for why.
- `GiftBoxBuilderService::state()` and every mutator scope the line to the
  current visitor's cart. A guessed line id resolves to null rather than being
  editable. That scoping IS the access control.
- `GiftBoxModel::allowedProductIds()` is the single source of truth for what may
  go in a box. The builder calls it to render choices and the validator calls it
  again to check what was submitted. A test asserts the two agree.
- Capacity is counted in slots: `gift_boxes.capacity_slots` versus
  `products.giftbox_slots` per unit. Re-checked server-side on every add.
- `gift_boxes.min_slots` is enforced in `PricingService`, not just the builder —
  a box below its minimum is a BLOCKING cart issue, so a half-built box cannot
  reach checkout by any route.
- Personalisation honours `allow_gift_message` / `allow_special_note` per box and
  truncates to `gift_message_max_chars`. A posted value cannot bypass a field the
  admin turned off.

### Admin panel rules now implemented (do not re-litigate)

- Authorisation is checked in the CONTROLLER (`AdminController::deny()`), and the
  route group also names the permission. Nav filtering is cosmetic on top of
  both — never the only check.
- Order status changes go through `Orders::TRANSITIONS`, a whitelist keyed by
  current status. Terminal statuses have no exits. Do not replace this with a
  free dropdown.
- The Buy/Enquire switch lives behind `settings.journey_mode`, separate from
  `settings.manage`, and requires the typed phrase SWITCH. It is audited with
  before and after, and also written to the framework log.
- `settings.is_locked = 1` rows are skipped by the bulk settings form entirely.
  They change only through their own endpoints.
- Boolean settings absent from a POST are written to 0 — an unchecked checkbox
  posts nothing, so relying on presence would make flags impossible to turn off.
- Login: generic failure message for every cause, `password_verify` runs even
  when the account does not exist (so timing does not leak), throttled on
  IP+email, `session()->regenerate(true)` on success, rehash when the cost
  factor moves.

### Two field-reported bugs and their lessons

- **Every admin view must render through `AdminController::adminPage()`.**
  `admin/layouts/admin.php` needs `pageTitle`, `admin`, `nav` and
  `journeyMode`, and only `adminPage()` assembles them. `Auth::showPassword()`
  used a bare `view()` call, so the forced password-change screen — the very
  first page a new admin sees — died with "Undefined variable $pageTitle". The
  layout now has fallbacks so the failure mode is an empty nav rather than a
  white screen, but the rule stands: go through `adminPage()`.
- **A password blocklist must compare by equality, not substring.**
  `stripos($new, 'password')` rejected "ANewLongerPassword2026", which is a good
  password that merely contains the word. The check now reduces the candidate to
  lowercase letters and compares for equality, which still catches
  "Password123" and "rasmein2026" while allowing real passphrases.

### Phase 4b traps — all three found by testing the real form, not the service

- **CI4 4.7 requires a validation rule for any field used as a `{placeholder}`.**
  `is_unique[products.sku,id,{id}]` throws
  `LogicException: No validation rules for the placeholder: "id"` unless the
  model also declares `'id' => 'permit_empty|is_natural_no_zero'`. Nine models
  needed it. Without it EVERY admin edit form 500s. Any new model using `{id}`
  must carry that rule.
- **`Model::update($id, $data)` does not inject the primary key into `$data`.**
  So `{id}` resolved to nothing and a product's SKU was compared against itself
  — "That SKU is already in use". The controller must put `id` in the payload
  for updates; it is not in `$allowedFields`, so it never reaches the UPDATE.
- **`UploadedFile::isValid()` calls `is_uploaded_file()`**, which is only true
  during a real HTTP POST. Upload code cannot be tested from a `spark` command;
  it has to be exercised through the form with a multipart request.

### Uploads and sanitising (do not weaken)

- `ImageUploadService` decides the type by `getimagesize()`, then RE-ENCODES
  through GD. Re-encoding is the security control, not an optimisation: it
  destroys polyglot payloads and strips EXIF (which carries GPS). Proven — a
  JPEG with `<?php` appended lands on disk with zero PHP tags.
- Filenames are generated (`bin2hex(random_bytes(16))`). Nothing from the client
  reaches the path. Destination is chosen by KEY from `Rasmein::$uploadPaths`.
- `HtmlSanitiser` is an allowlist and runs on SAVE, so stored HTML is clean and
  every read path is safe by construction. 24 XSS payloads are covered by
  `rasmein:diag-sanitiser`; add to that list rather than loosening the allowlist.

### Phase 4c rules

- Gift-box config is THREE independent forms (basics / contents / pricing).
  Keep it that way — one giant form means a rejected pricing rule discards the
  prose someone just typed.
- `GiftBoxes::saveContents()` and `saveRules()` REPLACE wholesale: the form is
  the complete picture for that box. Category ids are intersected against the
  real table before insert, so a posted id cannot create a phantom link.
- After saving contents the controller recomputes
  `allowedProductIds()` and reports the count. A box where nothing qualifies is
  flagged as an error, because the failure is otherwise invisible until a
  customer opens an empty builder.
- Pricing rules with `min_slots > max_slots`, or a minimum above the box's
  capacity, are rejected rather than stored — a rule that can never fire is
  worse than no rule, because it looks configured.
- `Pages` passes content RAW to the model. `PageModel::sanitiseContent()` is the
  single sanitising point. Do not sanitise in the controller as well: two places
  to keep in step is how one of them drifts.

### Phase 4d rules (do not weaken)

- **Staff::assignableRoles()** filters roles to those the current admin wholly
  holds. Never offer a role granting a permission the actor lacks — that is
  escalation by proxy.
- Self-lockout guards: cannot deactivate, change the role of, or delete your own
  account; cannot remove the last active holder of `*`.
- An admin-set password is always single-use: `must_change_password = 1`, so the
  person who set it never holds a working credential.
- **CsvExporter::neutralise() is mandatory on every exported cell.** Cells
  starting `=` `+` `-` `@` (or tab/CR) execute as formulas in Excel and Sheets.
  Any new export must go through the service, not fputcsv directly.
- Banner `link_url` must be relative or on this site. An admin-controlled
  off-site redirect in the hero is a phishing primitive.
- Customers are derived from `orders` grouped by email, not from the `customers`
  table — guests are the majority and must not be invisible. The screen is
  read-only: editing details after the fact would desynchronise order snapshots.

### Phase 5 rules (do not weaken)

- **Never confirm whether an email has an account.** Sign-in, registration and
  password reset all return the same response regardless. Registering with a
  taken address reports success and notifies the real owner — do not "improve"
  this into "that email is already registered".
- `password_verify()` runs even when the account does not exist, so response
  time does not leak existence either.
- Reset tokens: only `hash('sha256', $token)` is stored, TTL 60 minutes, burned
  by `consume()` BEFORE the caller acts, and issuing a new one voids the old.
- Everything in `AccountArea` is scoped by `session('customer_id')`. An id from a
  URL or a form is only honoured after `findForCustomer()` confirms ownership.
  Do not add a method that trusts a posted owner id.
- `CartService::attachToCustomer()` runs on every sign-in so a basket filled as
  a guest is not lost.
- Email is not editable from the account page: changing it needs verification of
  the new address, which is its own flow.

### Notifications & email (Phase 6) — do not weaken

- **MailService::render() is an allowlist.** Only tokens a template DECLARES are
  substituted, and the second pass covers `$global` (brand tokens) — never the
  caller's `$data`. Iterating `$values` there once made the allowlist
  decorative; a test now guards it.
- Substituted values are escaped into the HTML body. Never render a template
  through anything that evaluates code — staff can edit the body.
- **`toPlainText()` must strip tags AFTER decoding entities.** Decoding turns
  stored `&lt;script&gt;` back into `<script>`; the second `preg_replace` pass
  removes it. Do not simplify that back to one strip_tags call.
- Sending is queued, always. `rasmein:send-mail` retries five times with
  exponential backoff; nothing sends inline from a request.
- Notifications are targeted by permission via `NotificationService::toStaff()`.
  Do not broadcast to all staff.
- The template test-send goes ONLY to the signed-in admin's own address. A
  free-text recipient box is an open relay with extra steps.
- `template_key` is not editable from the UI — the code sends by key.

### Email templates — the full set, and why restore exists

- 19 templates, seeded by `EmailTemplateSeeder`. Every key
  `MailService::queue()` is ever called with MUST have one; a missing key is
  logged and the email is silently skipped.
- **`Admin → Email templates → Install missing`** re-runs the seeder for absent
  keys only. It exists because a migrated-but-unseeded database showed a blank
  page with no way forward — reported from the field. `rasmein:diag` also fails
  loudly when the table is empty.
- Adding a template: put it in the seeder AND make the restore path reachable.
  Never write to `email_templates` from a migration; seeders are re-runnable,
  migrations are not.
- A bug worth remembering: during Phase 6 the password-reset confirmation was
  wired to `customer_welcome`, so resetting a password sent "Welcome to
  Rasmein". Found by auditing sent keys against seeded keys — worth repeating
  that audit whenever an event is added.

### The rich text editor — Quill 2, and why not CKEditor

- **Licensing decided this, not features.** CKEditor 5 and TinyMCE are both
  GPL-2.0-or-later on their free tiers. Shipping GPL code inside Rasmein would
  oblige Rasmein itself to be GPL. CKEditor's GPL tier also requires a licence
  key and renders a "Powered by CKEditor" mark. Quill 2 is BSD-3-Clause: no key,
  no branding, no cap, ~200 KB against CKEditor's ~500 KB.
- **Vendored, not CDN**: `public/assets/vendor/quill/`, with its LICENSE file.
- **Quill is configured with STYLE attributors, not class ones.** By default it
  writes alignment/colour/size/font as `ql-*` classes, which the sanitiser
  strips. `registerStyleAttributors()` in editor.js swaps them for inline
  styles, and a custom Parchment attributor maps indent to `padding-left`. If a
  format ever stops surviving a save, check that registration first.
- **The sanitiser now allows `style`, but only via SAFE_CSS.** Each declaration
  is parsed, its property checked against an allowlist, and its VALUE matched
  against a per-property pattern. No property accepts a `url()`. CSS_POISON
  voids a declaration containing `url(`, `expression(`, `behavior:`, `@import`,
  comments or backslashes, checked against a whitespace-stripped copy so
  `url ( x )` is caught too. 26 CSS-injection vectors are covered by
  `rasmein:diag-sanitiser` — add to that list rather than loosening the patterns.
- Adding a toolbar button means adding its property to SAFE_CSS or its tag to
  ALLOWED, plus an attack case and a keep case in the diag suite. A button whose
  output is silently stripped is worse than no button.
- **The editor is not a security control.** It runs in the browser. Server-side
  sanitising on save is the actual protection; the editor only makes writing
  pleasant.
- **Progressive**: the real field is a `<textarea>` that already works. Quill is
  layered on top and syncs back on every change and on submit. A blocked script
  leaves a usable HTML textarea.
- `products.description` and `gift_boxes.description` are now HTML and rendered
  unescaped on the storefront. That is only safe because both models sanitise in
  `beforeInsert`/`beforeUpdate`. Never write to those columns bypassing the model.
- Quill loads only where `needsEditor => true` is passed to `adminPage()`, so the
  rest of the panel does not pay for it.

### baseURL and fetch() — a bug that reported itself as a permissions error

Field report: the editor's image upload returned
`{"status":"error","message":"You do not have access to that.","errors":[]}`.
That string is ApiExceptionHandler's 403 mapping, not a permissions check — a
403 was thrown before the controller ran, i.e. CSRF.

Root cause: `site_url()` builds an ABSOLUTE url from `app.baseURL`. When baseURL
does not exactly match the host the browser is on (different port, http vs
https, localhost vs a hostname), `fetch()` treats the request as cross-origin,
`credentials: 'same-origin'` withholds cookies, and it arrives with no session
and no CSRF token.

Rules that follow:

- **Any in-app `fetch()` target must be a ROOT-RELATIVE path**, never
  `site_url()`. Same for URLs returned to the client for insertion — the upload
  response returns `/uploads/...`, not `base_url(...)`, or a wrong baseURL leaves
  broken `<img src>` on the live storefront.
- **Never `esc($path, 'attr')` a constant internal path.** It encodes `/` as
  `&#x2F;`. Browsers decode it, but it is fragile — and this is trap §15.9 again.
- **`security.regenerate = true`, so the token rotates after every validated
  POST.** Any JS that POSTs must read the token live from the DOM at request
  time and write the rotated one back into every CSRF input on the page.
  Otherwise uploading an image then saving the form fails with a 403 — verified:
  stale token 403, fresh token 303.
- A 403 on an AJAX endpoint should say "your token expired, reload" rather than
  "you do not have access". The generic message sends people hunting through
  roles for a problem that is a stale page.
- `rasmein:diag` now checks baseURL is set, and fails when a production baseURL
  still points at localhost.

### Mail configuration in the admin panel

- Settings live in the `settings` table, group `mail`, all `is_locked = 1` so the
  generic Settings screen skips them — that screen would render the encrypted
  password into a text input and re-save it as plain text.
- **The SMTP password is encrypted** with `service('encrypter')` before storage,
  base64-wrapped, and NEVER rendered back to the browser — not even masked,
  because a masked value in a `value` attribute is still in the HTML. Blank on
  save keeps the stored one. Absent from the audit payload.
  Without `encryption.key` the panel REFUSES to store it rather than falling
  back to plain text.
- `Services::email()` is overridden so every existing caller picks up the stored
  settings without knowing about them. `.env` remains the fallback.
- **`Services::mailConfig()` reads the settings group with a RAW query, not
  `SettingsService::get()`.** That method treats a blank stored value as absent
  and returns the default, which meant choosing "None" for encryption silently
  stayed on TLS, and clearing the SMTP username brought the .env one back. Only a
  MISSING key defers to the fallback now.
- Crypto is stored as `none|tls|ssl`, never `''`, for the same reason: an empty
  setting cannot be told apart from an unset one.
- **A failed send must report the LAST error line, not the first 300 characters.**
  `printDebugger()` opens with the server's "220 ready" greeting, so truncating
  from the front showed a success message for a failure — which is exactly what
  it did on the first test. `explainFailure()` finds the 4xx/5xx replies instead.
- The test send goes only to the signed-in administrator's own address.

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
