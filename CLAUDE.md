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
  **PARTIALLY REVERSED, on request** — `Auth::exists()` now answers this for
  the registration form's debounced hint. Everything listed above still
  behaves identically either way; only that one endpoint does not. See "The
  registration duplicate check" below for the mitigations and the residual
  risk before extending it anywhere else.
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

### Gmail over OAuth 2.0

- **CodeIgniter's Email class cannot do XOAUTH2** — LOGIN, PLAIN and CRAM-MD5
  only, with no extension point. So the Google transport sends via Gmail's REST
  endpoint (`gmail/v1/users/me/messages/send`) with a Bearer token and a raw
  RFC 2822 message, built by `GoogleMailService::buildMime()`.
  `MailService::deliver()` branches on `mail_protocol === 'gmail_api'`.
- `Services::mailConfig()` forces `Config\Email->protocol` back to `smtp` when
  the shop is on gmail_api, so the framework can never fall through and send
  unauthenticated.
- **Scope is `gmail.send` alone.** Not gmail.compose, not mail.google.com. A
  leaked token should not be able to read the shop's mail.
- **`access_type=offline` AND `prompt=consent` are both required.** Without the
  second, Google omits the refresh token on re-authorisation and the connection
  dies silently an hour later.
- Client secret and refresh token are ENCRYPTED at rest; the client ID is not a
  secret (it appears in the consent URL). Access tokens live in the cache only,
  expiring two minutes early, never in the database.
- **`state` is validated with `hash_equals` on the callback.** Without it,
  someone can hand an administrator a crafted callback URL and attach their own
  Google account to the shop.
- `CURLOPT_SSL_VERIFYPEER` stays on. Disabling it to "fix" a handshake hands the
  tokens to anyone on the path.
- **A method-level `static` cache is shared by every instance for the whole
  request.** `GoogleMailService::raw()` used one, so settings saved mid-request
  were invisible to a newly constructed object. It is an instance property now,
  with `refresh()` for when the controller saves and re-reads.
- `rasmein:diag-gmail` covers 29 checks. The live handshake needs real
  credentials and a browser and is NOT covered — verify it with the authorise
  button and a test send.

### Roles, permissions and the admin UI

- **`Config\Permissions` is the single catalogue.** A permission not listed there
  is not assignable from the panel, and `exists()` rejects a posted string that
  is not in it. Adding a permission means adding it there too.
- **No escalation, enforced twice.** `Roles::grantable()` limits the picker to
  permissions the current administrator holds, AND `save()` refuses a posted
  permission outside that set. Verified: a Store Manager with roles.manage was
  refused `settings.manage` and `staff.manage`, and no role was created.
- The wildcard `*` is offered and accepted only for an administrator who already
  holds it.
- **`admin_roles` has no soft delete**, so removal is permanent — which is why
  deletion is blocked for `is_system` roles and for any role still held by an
  account. Do not relax either check.
- Ungrantable permissions are rendered DISABLED rather than hidden, with a note
  saying why. Transparency about what exists is worth more than a shorter list,
  and the server refuses them regardless.
- **SweetAlert2 (MIT, vendored) with a native `confirm()` fallback.** A
  destructive action must keep its guard when a script fails to load, so
  `admin.js` falls back rather than submitting silently. Confirmations are
  declared with `data-confirm` / `data-confirm-detail` / `data-confirm-action`;
  the journey switch uses `data-confirm-phrase` for the typed word.
- Flash messages are rendered into the page AND marked with `data-flash-item` so
  they can be lifted into toasts. The server-rendered block stays visible when JS
  is unavailable — neither path loses the message.

### Charts — Chart.js 4 (MIT, vendored)

- **Data reaches the browser in a `<script type="application/json">` block**,
  never an inline script. That element is inert, so no inline JavaScript is
  needed and the page stays ready for the CSP that is still to be enabled.
- **`json_encode` uses JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS |
  JSON_HEX_QUOT.** Without those, a product named `</script>` closes the block
  and the remainder is parsed as markup. Tested: zero raw occurrences.
- Charts AUGMENT the tables and lists, never replace them. If Chart.js fails to
  load the figures are all still readable, and every canvas carries an aria-label
  spelling out its values.
- Chart.js is ~200 KB and loads only where `needsCharts => true` is passed to
  `adminPage()`. Verified absent on Orders, Staff and Products.
- Colours come from the CSS design tokens via `getComputedStyle`, not restated
  in JS, so the palette has one home. The series varies lightness as well as hue
  so it survives printing and most kinds of colour blindness.
- Bars for revenue, not a line: daily takings are discrete events and a line
  implies a continuity that is not there. Doughnut rather than pie: comparing
  arc lengths beats comparing wedge areas.

### Admin shell (top bar + icon sidebar)

- Grid layout, not fixed positioning with a matching margin — the main column is
  simply the remaining track, so nothing has to be kept in step with the sidebar
  width.
- The collapse preference lives in localStorage. It is a display preference, not
  data, so it does not warrant a round trip; a storage failure (private browsing)
  degrades to "not remembered" and nothing else.
- **Icons are `rs_icon()`, a helper — NOT a view partial.** Two bugs forced this:
  CodeIgniter's `view()` keeps its data between calls, so one icon rendered with
  an explicit class became the default for every later one (23 of 25 came out the
  wrong size); and `esc($class, 'attr')` encodes the space in "h-4 w-4" as
  `&#x20;` (trap §15.9 again). A function also avoids resolving a view 25 times
  a page.

### Customers are built from TWO sources

- `Customers::index()` unions orders (grouped by email) with the `customers`
  table. Orders alone hides anyone who registered and has not bought — reported
  from the field, and those are exactly the people worth following up. The
  `customers` table alone would hide guests, who are the majority.
- **The detail page is addressed by an opaque token, not the email.** CI4's
  `permittedURIChars` excludes `@`, so an email in the path returned 400 and the
  page had never opened. An email in a URL also lands in access logs, browser
  history and Referer headers. `Customers::encodeRef()` / `decodeRef()`.
- The search term reaches raw SQL, so it is BOUND, never interpolated.

### Shop identity — resolve by KEY, never by group

**The bug this fixes, reported from the field:** a logo uploaded on the Shop
identity screen saved successfully and never appeared anywhere.

`SettingsService::set()` files an UNKNOWN key under `group_name = 'general'`.
`Services::brand()` selected `whereIn('group_name', [...])`. So on any install
where `BrandSettingSeeder` had not run, the upload wrote a row the resolver was
not looking at. Silent, and it looked like the upload had failed.

Three changes:

1. **`Services::brand()` selects by a fixed KEY list.** Group is a UI grouping
   concern; making identity depend on it was the mistake. Do not reintroduce a
   group-based read.
2. **`SettingsService::set()` takes an optional `$group`**, so a caller that owns
   a group says so and a new key lands correctly.
3. **The screen self-heals** — it counts missing keys, warns, and offers
   "Install them now"; `BrandSettingSeeder` also MOVES any identity key found in
   the wrong group; and `rasmein:diag` fails when fewer than 15 are installed.

### Shop identity — Services::brand()

- **`Config\Rasmein` is now resolved through `Services::brand()`**, which layers
  the `store`, `brand` and `social` settings groups over the PHP defaults.
  BaseController assigns `$this->brand` from it, so every view gets the resolved
  object.
- This closed a real gap: `store_name`, `store_tagline`, `whatsapp_number` and
  `social_instagram` already existed as editable settings and were referenced by
  **zero files**. Editing them did nothing, because the storefront read the PHP
  config and that never consulted the database.
- `identity` and `social` are plain maps on the config, not typed properties:
  these have no sensible PHP default, and an unset logo is genuinely absent
  rather than "the default logo".
- The `brand`, `store` and `social` groups are locked and excluded from the
  generic Settings screen — they have their own panel that knows how to handle
  uploads.
- Logo, favicon, sharing image and a dark-background logo all go through
  ImageUploadService. Replacing one deletes the old file rather than leaving it.
- The wordmark is NOT deleted when a logo is uploaded; it is the fallback, so a
  shop with no logo still looks deliberate.

### Logo sizing — constrain BOTH dimensions

An uploaded logo is an unknown quantity: it might be square or a 10:1 banner.

- **Height-only constraints are not enough.** `h-8 w-auto` on a 3000x300 banner
  renders roughly 320px wide, and the storefront wordmark sat in a `shrink-0`
  container, so it pushed the navigation off the page rather than wrapping.
  Reported from the field.
- `.rs-logo` caps `max-width` AND `max-height` with `object-fit: contain`, so any
  aspect ratio fits its box and nothing downstream needs to know what was
  uploaded. Variants: `--header`, `--footer`, `--admin`, `--preview`.
- The header anchor is `min-w-0`, not `shrink-0` — it must be able to give way on
  a narrow screen.
- **Uploads are capped by purpose** via `Rasmein::$brandImageWidths`: 600px for
  logos, 512 for the favicon, 1200 for the sharing image. The global 2400px is
  right for a product photograph and absurd for a logo.
  `ImageUploadService::store()` takes the cap as a third argument and reports the
  stored dimensions back.
- The "Gifting studio" eyebrow is part of the wordmark lock-up and is hidden when
  a real logo is set, where it would read as a stray caption.

### Nested categories at the site root

URLs are now /teas-infusions and /teas-infusions/green-teas/sencha.

- **The catch-all route block MUST stay last in Routes.php.** CodeIgniter matches
  in declaration order, so a catch-all declared earlier swallows /cart, /admin
  and everything else. Registering it after every real route means those win
  first and only an unclaimed path reaches the category resolver.
- **`categories.path` is a materialised path**, unique-indexed. Resolving a URL
  is one equality match at any depth, instead of a query per level. Nothing may
  write `path` directly — `CategoryModel::rebuildSubtree()` owns it.
- **`rebuildSubtree($id, $oldPath)` needs the PRE-SAVE path passed in.** The
  controller has already written the new one by the time it runs, so reading the
  row compares the new path against itself, returns early, and strands every
  descendant at an address whose parent no longer exists. Found in testing: a
  rename left four subcategories 404ing.
- **Slugs are NOT globally unique.** /tea/gifts and /coffee/gifts are different
  pages that both legitimately use "gifts". The original schema had a UNIQUE
  index on `slug` which failed as a raw "Duplicate entry"; migration 000014
  drops it. Uniqueness lives on `path`.
- **`reservedSlugs()` parses Routes.php with `token_get_all()`.** Two earlier
  attempts failed: `service('routes')->getRoutes()` returns an EMPTY collection
  in both CLI and web contexts, so the check silently passed everything and a
  category called "Cart" saved at /cart. Regex over the source then broke on the
  quote characters. The tokeniser is deterministic and needs no bootstrapping.
  It is intentionally over-inclusive — reserving too much fails safe.
- Cycle prevention is in `wouldCycle()` AND the dropdown omits the category's own
  subtree. Depth is capped at `MAX_DEPTH` (5 levels).
- A category page lists products from its descendants too, via
  `descendantIds()`; otherwise a parent looks empty while its children hold
  everything. `ProductModel` accepts a list for `category`.
- Old /shop/{slug} URLs 301 to the canonical path rather than 404ing.

### Occasions

- **An occasion IS a collection with `type = 'occasion'`.** Same table, same
  pivot, same landing page. A parallel `occasions` table would have duplicated
  the model, CRUD, pivot and — the dangerous part — the root-URL collision
  check. Two copies of that check is how they drift and a shop ends up with an
  unreachable page and no error anywhere.
- **`RootUrlService` is the single authority on what may live at the site root.**
  Categories AND occasions both claim it. Every slug assignment goes through
  `whyUnavailable()`; every root URL is resolved by `resolve()`. Never add a
  second place that decides this.
- `starts_at`/`ends_at` bound an occasion. Outside its window it 404s rather
  than rendering — a Diwali page in March is worse than nothing.
- **`syncProductOccasions()` clears only OCCASION pivot rows.** Collections live
  in the same table, so a naive delete-by-product-id would silently drop a
  product's collection memberships. Tested: they survive.
- **CategoryModel returns entities; CollectionModel returns arrays.** They are
  not interchangeable — assuming otherwise produced a TypeError on the live
  occasion page.
- Occasions are flat by design: one slug segment. A path containing a slash can
  therefore only be a category, which keeps the resolver unambiguous.
- **A migration backfill cannot fix rows a seeder inserts afterwards.** On a
  --fresh rebuild, migrate runs against an empty table. The occasion `type` and
  the category `path` both had to move into the seed data itself.
  `diag-firstrun` now asserts an occasion resolves at the root and that no
  category and occasion share a URL. THIRD time this pattern has bitten — check
  it for any new column whose value a seeder must supply.

### Appearance — layout driven by CSS custom properties

- **`DesignService::cssVariables()` emits `--rs-*` into the page head**, and every
  component is written against those variables. Generating Tailwind classes from
  settings cannot work — Tailwind compiles at build time, the settings change at
  runtime. This indirection is what makes width, grid density, corners and
  palette adjustable from Admin → Appearance with no rebuild.
- **Every value is clamped and pattern-checked before it reaches the style
  block.** A colour that is not a hex colour, or a width carrying a semicolon,
  would otherwise be a CSS injection point. Tested: `red; } body{display:none`
  is refused and never reaches the stylesheet.
- **Grids declare a MINIMUM CARD WIDTH, not a column count**
  (`repeat(auto-fill, minmax(min(var(--rs-card-min), 100%), 1fr))`). The browser
  fits as many columns as the screen allows, so one setting covers a phone
  through an ultrawide. The `min(..., 100%)` guard is required — without it a
  card minimum wider than a narrow viewport forces horizontal overflow.
- `.rs-shell` is full-bleed by default: `max-width: var(--rs-container, none)`
  with a `clamp()` gutter. `full` sets the variable to `none`.
- **Product cards use a container query, not media queries.** The card sizes
  itself from the space it is given, so the same component works in a wide grid,
  a rail and a sidebar without knowing which.
- `.rs-reveal` starts VISIBLE; the JS adds `rs-reveal-ready` to the root, which
  is what arms the hidden state. The opposite order hides the whole page when a
  script fails to load. Same principle for the density toggle, which stays
  invisible until armed.
- The main menu is the `design_nav` setting, one `Label | /path` per line.
  Absolute URLs are REJECTED — an editable menu that accepted them would be a
  way to point a shop's own navigation elsewhere.

### The storefront design system

- **Layout is driven by CSS custom properties, not Tailwind classes.** Tailwind
  compiles at build time; the appearance settings change at runtime. So
  `DesignService::cssVariables()` emits a `:root{}` block into the head and every
  component is written against `var(--rs-*)`. Changing the page width or the
  card minimum in the admin re-flows the whole site with no rebuild.
- **That block lands inside `<style>`, so every value is clamped and
  pattern-checked** before it gets there. Tested: `red; } body{display:none` is
  refused at the form and would not survive the service either.
- **Grids declare a minimum card width, never a column count.**
  `repeat(auto-fill, minmax(min(var(--rs-card-min), 100%), 1fr))`. The `min()`
  guard matters: without it a card minimum wider than a narrow phone forces
  horizontal overflow. This is what makes full-bleed work from 320px to an
  ultrawide with no breakpoint per size.
- **The product card is a container query context**, so it sizes from the space
  it is given rather than the viewport.
- `.rs-shell` is full-bleed by default: `max-width: var(--rs-container, none)`,
  where the variable is literally `none` unless an administrator caps it.
- **Reveals start VISIBLE.** `.rs-reveal` is hidden only once JS adds
  `.rs-reveal-ready` to the root. The opposite order hides the whole page when a
  script fails to load.
- The menu is the `design_nav` setting, one `Label | /path` per line. Absolute
  URLs are rejected — an editable menu that accepted them is a way to point a
  shop's own navigation elsewhere.
- **"Buy now" must actually skip the basket.** A second submit on the same form
  (so the chosen quantity carries), with `Cart::add()` redirecting to checkout
  when `checkout` is posted.

### The homepage template

- Every section reads its heading from the `home` settings group. A BLANK
  setting HIDES the section — which is why the values are read raw in
  `Home::homeCopy()` rather than through `SettingsService::get()`, which treats
  blank as absent.
- A section with no content does not render at all. An empty best-sellers rail
  looks broken; no section looks deliberate. Verified both ways: with banners
  absent the feature band, clients and gallery vanish cleanly.
- The closing two words of each display headline are set in gold italic by a
  `$split()` helper, so nobody has to write HTML into a settings box. Falls back
  to a plain heading under four words.
- **`banners.position` is a database ENUM, not a varchar.** Widening only the
  model's `in_list` rule made the app accept a value MySQL then silently
  truncated — the banner saved with an empty position and appeared nowhere.
  Migration 000017 widens the column. Change BOTH or neither.
- The hero renders every slide server-side with the first marked current, so
  without JavaScript the first slide simply stays. The rail is native
  scroll-snap; its arrows are `hidden` until the script wires them, because a
  button that does nothing is worse than no button.
- The slider does not auto-advance under `prefers-reduced-motion`, and pauses on
  hover, on focus, and when the tab is hidden.
- Only the first hero image is `fetchpriority="high"`; everything else is lazy.
- `testimonials` is its own table. Squeezing quotes into `banners` (quote in the
  title, author in the subtitle) would have worked for a week.

### Locked settings must name where they ARE edited

`Config\SettingHomes` maps a locked setting to its real destination, and the
Settings screen renders a link. The previous wording — "changed through its own
guarded control" — named no destination, and for `payment_enabled` /
`payment_gateway` it promised a screen that does not exist, because the gateway
is still deferred. Those now say so plainly.

- **Resolve destinations in the CONTROLLER, not the view.** A CodeIgniter
  template's `$this` is the View renderer, so `$this->can()` there is a fatal
  error the moment a locked row renders. `Settings::resolveHomes()` does it.
- A destination the current role cannot open is described, not linked — a link
  that bounces to a refusal is worse than a sentence.
- The `design` group is excluded from the generic screen entirely; Appearance
  owns it.
- **The `home` copy settings were seeded LOCKED with no screen to edit them**,
  making 15 settings unreachable. They are plain text, so they are unlocked and
  edited on the generic Settings screen. Do not lock a group without building
  its screen first.

### Content → Homepage

- All homepage content lives on one screen, sequenced top-to-bottom as the page
  reads. Someone editing it thinks about the page, not an alphabetical list of
  setting keys — which is what the generic Settings list gave them.
- Behind its OWN permission, `homepage.manage`. "Edit the homepage" and "edit
  the mail server" are not the same job. Granted to Store Manager in the seeder;
  super-admin holds `*`.
- The `home` group is excluded from the generic Settings screen now that it has
  a home, and `Config\SettingHomes` points there for any locked row.
- **`Config\Rasmein::$bannerPositions` is the single list of banner slots.** It
  previously lived in three places — the database enum, the model's `in_list`
  rule, and the admin dropdown — and the three new homepage slots were added to
  two of them, so they could not be selected in the panel at all. Adding a slot
  means changing the enum (migration) and this list; the model and views read
  from it.
- Testimonial CRUD sits on the same screen rather than its own menu entry: it is
  homepage content, and a top-level "Testimonials" item for three quotes is
  noise.

### The listing page (shop, category, occasion)

- **Facets are COMPUTED, never declared.** `FacetService` derives every option
  and count from the same conditions as the listing, so a count is a promise:
  ticking it returns that many things. A facet with fewer than two options is
  dropped — one choice is not a choice.
- Context reshapes the sidebar: inside a category the Category facet becomes its
  SUBCATEGORIES (moving down the tree, not sideways out of it); on an occasion
  page the Occasion facet disappears entirely.
- **Card photographs are batched.** `ProductModel::imagesFor()` loads every
  image for the page in ONE query. Asking per card is an N+1, and the grid can
  show far more than a dozen cards.
- All images sit in the markup rather than being fetched on hover: fetching
  shows a blank frame for a few hundred milliseconds, which reads as broken.
  They are lazy, so only scrolled-to cards pay for it.
- Cycling respects `prefers-reduced-motion`, and on touch (no hover) it advances
  while the card is on screen. The dots are real buttons — on a phone they are
  the only way through the set.
- The filter form is a plain GET, so filtered state lives in the URL: shareable,
  bookmarkable, back-button-able, and working without JavaScript. Auto-submit is
  layered on top and hides the Apply button only once armed.
- **`esc($url, 'attr')` on a chip URL encodes `/ : ? =` into entities and the
  link 404s.** Fifth occurrence of the same trap. Controller-built URLs go out raw.
- `http_build_query` writes PHP's array keys, so a list carrying its original
  offsets becomes `band[2]=4`. Re-index before building, or offsets accumulate
  as filters are added and removed.
- Pagination must repeat EVERY filter key in `$pager->only()`, or page 2 quietly
  drops them.
- `products.material` was added because the design shows a Material facet with
  no column behind it. A filter with nothing to filter on is worse than none.

### Three layout bugs from the listing rebuild

1. **`rs_url(rs_image(...))` produced `src=""` on every product image.**
   `rs_image()` already calls `base_url()`, and `rs_url()` rejected anything
   carrying a scheme. Two helpers written for different jobs, composed.
   `rs_url()` now passes a finished http(s) URL through and still refuses
   `javascript:` and `data:`. Do not nest them — `rs_image()` alone is complete.
2. **`.rs-sidebar` is the ADMIN's dark navigation column.** The storefront filter
   column reused the name and inherited a near-black background on cream. Class
   names are one global namespace; the storefront one is `.rs-filtercol`.
3. A stray `" sizes="any">` fragment survived an edit to the favicon block in
   `layouts/storefront.php` and rendered as visible text at the top of every page.

Layout: the page title sits INSIDE the product column, not in a band above both,
so the filter list starts level with it — matching the design and saving a
screenful of scroll before the first product.

### Editing by string index cuts in the wrong place

Removing the listing's old header sliced from `<header ...>` to the first
`<div class="rs-shell py-10 lg:py-14">` — but that div is INSIDE the header. Only
the opening tag went; a second breadcrumb, the page title, the description and a
whole search form were left orphaned with a stray `</header>`, and rendered
above the real layout. Anchor on something that cannot appear inside the block
being removed, and assert on the removed text before writing.

`withPrimaryImage()` also selects `category_name` now, via a correlated
subquery. The card renders a category eyebrow and the listing query never
provided it, so it silently never appeared — a join would have to be repeated by
every caller and interacts badly with the `whereIn` subqueries the filters use.

### Replacing one wrapper with two needs the extra closing tag

Moving the listing toolbar level with the title replaced a single wrapper div
with a row div PLUS an inner controls div, and the row was never closed. The
product grid and pagination then became flex items inside a
`flex-wrap items-end justify-between` row — products collapsed into a narrow
column one word per line, and the title rendered AFTER them.

PHP lints fine either way; only the rendered output shows it. After any change to
a view's element nesting, fetch the page and compare `<div` against `</div>`
counts. Counting the SOURCE does not work: conditionals mean the source can be
unbalanced while every rendered branch is correct, and vice versa.

### esc($url, 'attr') on URLs — CORRECTED

**Earlier notes in this file claimed this breaks links. It does not.**
`esc($url, 'attr')` encodes `/` as `&#x2F;` and `:` as `&#x3A;`, and browsers
decode entities in attribute values, so the link, image and `data-` attribute all
work. Verified empirically: an og:image URL escaped this way fetches 200 once
unescaped, and `getAttribute()` returns the decoded string.

Every "the link 404s" / "the image never loads" claim came from a broken TEST —
curl was handed the literal entity text scraped out of the HTML.

The real rule is narrower: **do not `esc()` a URL you built yourself.** It is
already safe, the encoding makes the output hard to read and impossible to grep,
and it hides genuine problems. Use `rs_url()` for stored asset paths and output
`site_url()` / `current_url()` / `rs_image()` raw.

**The one genuine bug in this family** was `rs_url(rs_image(...))` returning an
empty string, because `rs_image()` already calls `base_url()` and `rs_url()
refused anything with a scheme. That produced a real `src=""`. `rs_url()` now
passes finished http(s) URLs through.


### Image handling

- **Every upload builds a size ladder** (320/480/768/1024/1440/1920) plus a WebP
  of each, written beside the original with a width suffix. The database column
  is unchanged — variants are found by convention, so images uploaded before
  this still work and fall back to the single file.
- **Never upscale.** A variant wider than the original is skipped: inventing
  pixels gives a bigger file that looks worse than letting the browser stretch.
- **A mild unsharp mask after every downscale.** Resampling averages
  neighbouring pixels, which IS blurring, so the result is always softer than
  the original looked. The kernel divisor equals the kernel sum so brightness is
  unchanged. This compensates for resize loss; it does NOT recover detail that
  was never captured.
- `rs_picture($path, $sizes, $attrs)` emits `<picture>` with a WebP source and a
  fallback `<img srcset>`. **`sizes` describes the SLOT, not the file** — get it
  wrong and the browser picks too small, which is the usual cause of "my
  high-res photo still looks soft".
- src/srcset/sizes are output RAW (built from base_url() and developer
  literals); alt/class/data-* are escaped because those carry product names.
- **`maxImageBytes` was 2 MB, which rejected the photographs a shop should be
  uploading.** Now 12 MB. `rasmein:diag` fails when PHP's own
  `upload_max_filesize`/`post_max_size` is lower, because the request is then
  discarded before the app sees it and the uploader sees nothing happen at all.
- `estimateSharpness()` (variance of a Laplacian) WARNS on soft or small
  uploads. It never rejects — a deliberately shallow-focus shot scores low too.
- Deleting an image purges its variants, or every replacement litters the disk.
- Swapping a gallery image must clear the `<source srcset>` too: a browser that
  matched a source ignores a changed `img.src` and the picture appears stuck.

### Uploaded filenames are KEPT, safely

The stored name is the uploaded one, slugified, with `-2`, `-3`… on collision.

- **The extension ALWAYS comes from the detected type**, never the client name.
  That is the whole security property: `cat.php.jpg` becomes `cat-php.jpg`
  because the type was read from the file's bytes. Do not weaken this.
- The stem is rebuilt from `[a-z0-9-]` only — `basename()` first, then
  transliterate, then filter — so traversal, null bytes, control characters, RTL
  marks and second extensions cannot survive. Verified against all of them.
- A stem ending `-<width>` matching a ladder step gets `-img` appended, or
  uploading `photo-320.jpg` would later be overwritten by `photo.jpg`'s 320px
  variant. Silent and very confusing without the guard.
- Collision checking covers the VARIANTS: reserving `photo.jpg` reserves
  `photo-320.jpg`, or a later ladder overwrites an existing file.
- Windows device names (con, prn, lpt1…) get a suffix; the loop is bounded.

### Alt text

- `product_images.alt_text` existed from the start but **the product form never
  rendered a field**, so every product image shipped with an empty alt. Fixed;
  categories, collections and testimonials gained the column.
- **Content → Image alt text** lists every image site-wide with coverage and a
  missing-only filter. Each row links to the screen that owns the image, so this
  is a place to SEE and FIX, not a second owner.
- The save is keyed `source:id` and both halves are checked against a fixed list,
  so a crafted key cannot reach another table. Verified.
- `alt=""` is correct ONLY where adjacent text already says it — a card whose
  link text is the product name, or a thumbnail whose button carries an
  aria-label. Elsewhere the stored description is used.
- In `rs_picture()`, src/srcset/sizes/class go out raw (built from base_url() and
  developer literals); **alt stays escaped** because it carries typed text.

### Cormorant Garamond

A light, high-contrast garalde with a small x-height: it reads a size or two
below its nominal point size, and its hairlines thin to nothing at weight 400 on
a standard-density screen. Compensated in and around `--font-display`:

- Display weight starts at **500**, headings at 600.
- Every display step is roughly a tenth larger than the previous face needed.
- Tracking is slightly **positive** (0.002-0.004em). A garalde opens up at
  display size where a modern face tightens — negative tracking closes the
  counters and muddies it.

Still loaded from Google Fonts, so the self-hosting + CSP item stands.

### Homepage section rhythm

`.rs-section` padding was `clamp(3.5rem, 7vw, 6.5rem)` — enough that a single
section filled a screen, which made the page feel slower to move through than it
is. Now `clamp(2.25rem, 4.2vw, 4rem)`.

`.rs-rail--bleed` runs a scroller to the screen edges while keeping inline
padding equal to the shell gutter, so the FIRST card still lines up with the
heading above it — a full-bleed scroller flush to the edge reads as broken
rather than deliberate. `scroll-padding-inline` preserves that on snap-back.

The homepage mosaic renders OCCASIONS, not categories. Note that the occasion
rail below it draws from the same source, so with few occasions the two sections
show the same things.

### The infinite slider

`.rs-loop` is a NATIVE horizontal scroller, not a transform: touch keeps its
momentum, the keyboard reaches every tile, and a browser that never runs the
script still gets a usable scroller.

- The loop is made by cloning the set and subtracting one set's width from
  `scrollLeft` once the reader passes the first copy. The jump is invisible
  because the pixels either side of it are identical.
- **Clone to at least THREE copies, whatever the widths.** The wrap starts one
  set in and jumps back at two sets in, so the track needs two sets plus a
  viewport. Cloning only "until three viewports wide" looks equivalent and is
  not: when a single set is ALREADY wider than three viewports — four tiles on a
  phone, twelve on an ultrawide — nothing cloned, the wrap point was never
  reached, and the slider ran to the end and stopped. Found by simulating the
  arithmetic across viewport sizes rather than by reading it.
- Clones are `aria-hidden` with their links given `tabindex="-1"`, or a screen
  reader announces every item three times.
- Autoplay is suppressed under `prefers-reduced-motion`, and pauses on hover,
  focus and tab-hidden.
- A drag that moved more than a few pixels swallows the click, or letting go
  over a tile follows its link.
- Re-measures on resize: card width comes from CSS that depends on the viewport,
  and a stale set width makes the jump visible.

`.rs-rail--bleed` has no inline padding at all — the first and last cards run off
screen, which is what makes a scroller read as continuing.

### Banners: artwork vs overlay

`BannerModel::isBare()` — no title, subtitle OR eyebrow — decides the treatment.

- **Bare**: the picture is shown WHOLE (height follows its own ratio, never a
  forced crop), the entire image is wrapped in its link, and there is no scrim.
  A shop that uploads finished artwork has already put the words inside it;
  overlaying a heading would duplicate them and cover the design.
- **Any text**: scrim, heading, description and button, because that text has to
  be readable over a photograph.
- A bare image MUST carry alt text — it is the only content, so without it the
  hero is silent to a screen reader. A text banner's image is decorative and
  takes `alt=""`.
- `BannerModel::safeLink()` refuses anything with a scheme. An editable banner
  link accepting absolute URLs is a way to point a shop's own hero elsewhere.
  Enforced at BOTH save and render — a row seeded before the check existed is
  still refused when drawn.
- Admin is grouped BY SLOT, each with its own guidance and ideal size, because
  "home_feature" tells a shop owner nothing. Slots where several images is the
  normal case (hero, clients, gallery) get a bulk uploader; each file becomes its
  own banner so it can still be reordered or removed individually.

### Banners: a PAGE per slot, not sections on one page

`/admin/banners` is a chooser; each slot has its own address —
`/admin/banners/hero`, `/feature`, `/clients`, `/gallery`, `/strip`,
`/category`, `/builder`. Stacking every slot on one page meant scrolling past
six others to reach the gallery, and answering "which slot?" with a dropdown
buried in the form rather than with where the person already was.

- **Slug routes must come AFTER the numeric ones**, or `banners/12/edit` is read
  as a slot named "12".
- The form has no slot dropdown — the page IS the slot. It is still posted as a
  hidden field and still validated, because a hidden field is a posted value
  like any other.

### Banners have TWO buttons

`cta_label` / `link_url` and `cta_label_2` / `link_url_2`. The second used to be
hard-coded to `/build` in the hero template, so a shop could not change its
wording or destination or remove it. A button with an empty label is not drawn,
so one button, two, or none all fall out of the same fields.

Both links go through `safeLink()` and both are refused at save if they carry a
scheme. For a bare (text-free) banner the FIRST link is what wraps the whole
image.

### Each banner slot declares the fields it uses

`Banners::SLOTS[...]['fields']` lists which groups a slot renders — image, name,
link, text, buttons, schedule — and the form shows only those. A gallery
photograph asks for three fields instead of thirteen.

A field that never appears anywhere is worse than a missing one: someone fills it
in, nothing happens, and nothing tells them why.

`save()` also CLEARS the columns a slot does not use. Without that, moving a
banner from the hero to the client row would leave a subtitle in the database
that nothing draws — invisible, and confusing next time someone looks.

**Alt text is not on the banner form.** It is managed for every image on the site
at once under Content → Image alt text, and the form links there. One place to
fill it in beats the same field scattered across eight forms.

### Never use 1fr as the MAX in grid-auto-columns

`minmax(13rem, 1fr)` on a `grid-auto-flow: column` scroller means a lone item
absorbs the entire track. With one occasion seeded, the row of small cards
rendered as a single enormous full-bleed picture — obvious on screen, invisible
to any test that counted elements or checked status codes.

Use a real maximum (`minmax(13rem, 15rem)`). A scroller holding two items SHOULD
look like two items rather than stretching to fill the width. Fixed on
`.rs-rail`, `.rs-rail--cards`, `.rs-rail--occasions` and `.rs-loop__track`.

Also: best-seller cards carry their own white surface, because on the alternate
band the picture floated on the same colour and the card edge disappeared. Add
to Cart sits ON the card rather than behind a hover — a hover-only control is
unreachable on touch and undiscoverable with a keyboard.

### Homepage section behaviour

- **Marquee** sits on the deep band with light italic text, continuing the hero
  rather than breaking to cream.
- **Three sliders share one component** (`.rs-loop`): collections, occasions and
  testimonials. Only the column width differs, via `--small` and `--quotes`
  modifiers, so all three behave identically and one fix reaches all of them.
- **Parallax uses a clipped wrapper holding a `position: fixed` child**, NOT
  `background-attachment: fixed` — iOS ignores that entirely and shows a
  stretched still. `clip-path: inset(0)` makes the wrapper a containing block for
  the fixed child. Falls back to a normal cover image below 768px and under
  `prefers-reduced-motion`, where a fixed plate costs more than it gives.
- **The gallery drifts in two rows, opposite directions**, in CSS rather than
  JavaScript: the track is duplicated and the keyframe translates by exactly
  -50%. Any other value and the loop visibly jumps. Hover pauses it; reduced
  motion turns it into a plain scroller.
- Best-seller cards are transparent — the design lets the photograph sit on the
  band, and a white panel fights the cream.

### Editing a CSS rule by replacing one property

Adding breakpoint overrides for `.rs-loop__track` by replacing its
`grid-auto-columns` line closed the rule early — `gap`, `padding-inline`,
`overflow-x` and `scrollbar-width` were left orphaned inside a media query,
applying to nothing. The slider collapsed into a strip of tiny tiles.

CSS does not fail loudly: an orphaned property is simply ignored. When adding a
breakpoint, write the override as its OWN rule after the block, never by
splitting the block open. Afterwards, check the rule still lists every property
it used to, and that no `@media` opens straight onto a property.

Also on the homepage:

- `align-items: start` on the quote slider. With `stretch`, every cloned card
  grew to the tallest in the whole cloned set — an enormous empty white box.
- Marquee text is the warm gold, not white: white on maroon is a colder, harder
  contrast than the rest of the page uses.
- The current hero dot doubles as a progress bar — a `::after` fills over
  `--rs-slide-ms`, which the SCRIPT sets from its own `DELAY`, so the timing has
  one home. Restarting it needs a forced reflow (`void el.offsetWidth`) between
  removing and re-adding the class, or the browser coalesces both into a single
  recalculation and the animation never restarts.

### A slider must not clone a row that already fits

The infinite loop clones until the track is three viewports wide. With ONE
occasion that produced thirteen identical Diwali tiles across the screen — the
component working exactly as written, and looking completely broken.

`.rs-loop` now measures first: if the original content does not overflow its
container, it adds `.is-static`, hides the arrows, skips autoplay and returns.
A row that fits should BE a row.

Measured against the CONTAINER, not a count — "enough items" depends on card
width and viewport, not on how many records exist.

This also matters for diagnosis: two rounds of CSS fixes were aimed at sections
that were never a CSS problem. Check what the DATA is before changing styling.

### Testimonial slider sizing

One card per view on a phone (86vw — the sliver of the next card is the only
thing telling a reader there IS one), two on a tablet, EXACTLY three from
1024px via `calc((100% - 2 * gap) / 3)`. Derived from the track's own width
rather than a fixed rem value, which leaves a ragged gap on some viewports and
spills to a fourth on others. Verified against eight real device widths.

The static check needs ~24px of tolerance: three cards sized to exactly a third
measure a fraction wider through sub-pixel rounding, and without slack a row
that visually fits turns itself into a slider.

**`TestimonialModel::live()` capped at 3.** Adding a fourth testimonial changed
nothing on the page and nothing said why — a slider fed a query returning
exactly what fits can never slide. Now 12. Any "show N at a time" section needs
its query to return MORE than N.

### Never use minmax(0, X) for grid-auto-columns in a scroller

`minmax(0, X)` means "may shrink to nothing, up to X". When the content is wider
than the track — ALWAYS true in a horizontal scroller — every column collapses
toward zero: five cards squeezed into a phone at 62px each. Use a bare width, so
the CARD keeps its size and the TRACK overflows, which is the point of a
scroller.

`justify-content: center` on `.is-static` compounded it by removing the free
space that partly masked the collapse. Use `start`.

**Reason about CSS from measurements, not from reading it.** Three rounds of
edits went into a stylesheet whose source order, specificity and compiled output
were all correct — the bug was in what the values MEAN under overflow, invisible
without computed widths. Playwright is installed: set
`NODE_PATH=$(npm root -g)` and
`PLAYWRIGHT_BROWSERS_PATH=/home/claude/.cache/ms-playwright`, then read
`getBoundingClientRect()` on the first child at several viewports.

The dev server MUST run on the port in `app.baseURL`, or every asset 404s and
every computed style is a browser default. That cost a diagnostic round on its
own.

### Guest baskets that survive a closed browser

`VisitorService` mints a 32-byte random token into `rs_visitor` — httpOnly,
SameSite=Lax, Secure on HTTPS, one year. It is the CREDENTIAL for a guest
basket, so it is rotated on sign-in: a token someone else may have seen cannot
be replayed against the account afterwards. Minted only when something is
actually saved, never for a passer-by.

**Set the cookie with PHP's own `setcookie()`, not CI4's.** CI4 keeps cookies on
the shared Response, but `redirect()` returns a NEW RedirectResponse and only
carries them if the caller remembers `->withCookies()`. Every wishlist action
ends in a redirect, so the row saved and the cookie was thrown away — the next
request could not find its own data. `$_COOKIE` is also updated so a save and a
read inside one request agree.

`wishlist_items.customer_id` was NOT NULL, so a guest could not save anything
and the heart did nothing until you registered — backwards, since saving is
what someone does BEFORE deciding to make an account. Now nullable with a
`visitor_token` alternative, and the wishlist moved OUT of the customerAuth
route group.

Two silent failures on the way: the model's `validationRules` still said
`customer_id => required` (insert returned false, nobody read `errors()`), and
`set_cookie()` needs `helper('cookie')` loaded or it is simply undefined.

`BasketMergeService::adopt()` runs from `establishSession()`, the single point
every login and registration passes through. Wishlist merges as a UNION; cart
quantities are REPLACED not summed — two in the account plus one from the guest
basket is one, the number the person last chose, not three.

### Pausing a slider must remember when

`setInterval` has no notion of elapsed time, so resuming with a fresh interval
restarts the whole delay however briefly the pointer rested. `play()` now
resumes with a one-shot `setTimeout` for the REMAINING time before handing back
to the steady interval, and the CSS dot fill is paused via a class rather than
restarted, so it simply continues.

Promise strip: the WORDING is homepage copy and lives on `admin/homepage`; the
on/off flag is layout and stays under Appearance, which now links across rather
than duplicating the field.

### Clean up after moving a feature between controllers

Moving the wishlist to `Storefront\Wishlist` left `AccountArea::wishlist()` and
`::toggleWishlist()` behind, unreachable. A dead controller action is worse than
a missing one: the next person reads it, assumes it runs, and edits the wrong
file. Same for `WishlistModel::forCustomer()` and `::toggle()` — customer-only
helpers sitting beside `forViewer()`/`saveFor()` invite someone to reach for the
wrong pair and silently drop every guest row.

### Guest data has to expire

A wishlist row keyed to a visitor token has no owner who can ever delete it —
the person may have cleared their cookies a year ago. `rasmein:housekeeping`
prunes guest wishlist rows and abandoned guest carts after 13 months (a month
past the cookie's own life, so a surviving token gets a grace period rather than
being cut off the day it expires). A signed-in customer's saves are never
pruned, whatever their age.

### Sign-in: one-time codes, and Google

No passwords. `Storefront\Auth` owns everything; `Storefront\Account` keeps
only logout. The password actions were DELETED, not merely unrouted — an
unreachable password login sitting beside a passwordless one invites someone to
wire it back up, and that reopens exactly what this closed.

- Codes are stored HASHED and compared with `password_verify`. Six digits is
  only safe because guesses are capped at five, and a code lives ten minutes.
- Issuing is rate-limited to five per address per hour, or the endpoint is a way
  to flood an inbox using our mail server.
- A new code retires any earlier unused one. Two live codes means the one an
  attacker glimpsed still works after the victim asks for a replacement.
- **Nothing is written to `customers` until the code is confirmed.** The signup
  details ride in `auth_codes.payload`. An unverified row would let someone
  squat on an address its real owner wants.
- "No such account" is never revealed: the screen advances identically and a
  code simply never arrives. The masked address (`de•••••@gmail.com`) is enough
  for the owner and near-useless to anyone else.
- Login accepts an email OR a phone number; the code always goes to the email,
  and the screen says which.
- Google matches on `sub`, never the email — an address can change hands, a
  subject id cannot. `email_verified` is required. `state` is random per attempt
  and compared with `hash_equals`.
- A Google sign-up lands on `account/finish` because it still owes a phone
  number; the name arrives pre-filled and editable.

Copy for all four screens is editable under the `auth` settings group.

Two traps worth remembering: a controller action typed `: string` cannot return
a redirect (500 on the "nothing pending" path), and `CustomerModel` must
allowlist every new column or the write silently drops it — `profile_completed_at`
saved as NULL until it was added.

### A new seeder must be added to DatabaseSeeder

`AuthContentSeeder` existed and worked, but was never added to the chain — so a
fresh install had no `google_auth_*` rows, `isConfigured()` was false, and the
Google button silently never rendered. Nothing failed; the feature was just
absent. Any new seeder goes into `DatabaseSeeder::run()` in the same change that
creates it.

The sign-in screen now shows an admin-only note explaining WHY the Google button
is missing, gated on `session('admin_id')`. A visitor must never be shown a route
that cannot work, but the person setting the shop up needs to know it is
unconfigured rather than broken.

### Progressive enhancement is not optional on the switch links

The sign-in / create-account links were `href="#create"` and `href="#"`, with
the panes swapped by script. If the script does not run — a blocked file, an
error earlier in the bundle — clicking one only adds a hash and the server keeps
rendering the login pane, so the person sees the wrong form and nothing explains
it. They are now real `?mode=` URLs the server honours, and the panel's CTA is
an anchor rather than a `<button>` for the same reason. The script still
intercepts them for the instant switch and keeps the CTA's href in step.

### Mail queue

`admin/mail/queue` lists what has been sent and what failed, with per-message
retry and a manual drain. **Message bodies are deliberately not shown** — a
queued message can hold a one-time sign-in code or an order total, and that does
not belong in a table anyone with panel access can scroll past. Retry refuses a
message already marked sent: re-sending something the customer received is worse
than doing nothing.

### The sliding auth panel must move the FORMS too

The panel slid from the right half to the left, but the forms stayed pinned to
column 1 — so in register mode an opaque aside sat directly on top of every
field. Markup correct, `opacity: 1`, `visibility: visible`, four inputs present:
nothing looked wrong. Only `document.elementFromPoint()` over an input showed
`ASIDE.rs-auth__panel` on top.

`.rs-auth[data-mode="register"] .rs-auth__forms { grid-column: 2 / 3; }` moves
them out from under it, and the panel is `pointer-events: none` (its links
re-enabled) so a click mid-slide still reaches the field.

**Checking visibility is not checking visibility.** For "the user says they
cannot see it", ask what is ON TOP, and click the thing with a real driver —
`elementFromPoint` alone also gives a false FAIL for anything below the fold,
which sent me chasing a second phantom bug on mobile.

### Do not lock a setting without building its screen

`google_auth_client_secret` was seeded `is_locked = 1` because it must be stored
encrypted and a plain settings field would write it in clear. But no screen
existed, so it could not be set anywhere at all — the feature was unreachable by
design. `admin/auth` now owns the sign-in wording and both Google credentials:
the secret is written encrypted, never rendered back (only whether one exists),
a blank field means "keep it" rather than "clear it", and the audit trail records
that it changed without recording what it is.

The `auth` group is excluded from the generic Settings screen and `SettingHomes`
points it at `admin/auth`, so the same keys never appear in two places.

### Email template placeholders are a MAP, not a list

`MailService::render()` iterates `array_keys($allowed)`. Declaring
`'placeholders' => ['otp_code', 'customer_name']` therefore yields tokens
`0, 1` — every real token is "declared but not supplied" and gets blanked. The
sign-in emails went out with an EMPTY code and no name, and nothing failed:
queue() returned true, the row was written, the customer just received a blank.

Always `'token' => 'what it is'`, as every other template does.

### Mail detail view

`admin/mail/queue/{id}` shows the message as the customer saw it, inside an
iframe with a bare `sandbox` attribute — no scripts, no forms, no navigation.
Email templates are editable from the panel, so a badly-edited one must not be
able to run script against an admin session.

**The one-time code IS shown**, in its own panel, with whether it is still
usable ("Still valid for 8 more minutes" / "Already used" / "Expired").

Redacting it was the wrong call. Until SMTP is configured this queue is the only
inbox there is, so a shop could not test its own sign-in; and in support, "read
me the code you were sent" is the entire question. It was also thinner
protection than it looked — anyone who can reach this screen can already change
a customer's email address and request a code to it.

What protects the customer is that looking is RECORDED: viewing a message with a
code logs `mail_code_viewed` with the recipient's address, so it can be asked
about afterwards. Ordinary messages log `mail_viewed`.

The code is read from the SENT BODY, not `auth_codes` — the stored copy is
hashed and cannot be read back, which is correct. Extraction is anchored on the
template's `<strong>` wrapper, so an order total or a year is never mistaken for
a code. Bodies stay off the list view: one at a time is a deliberate act.

### Put a nav item in the group that matches its permission

`Mail queue` and `Sign-in screen` were added to the Content group but require
`settings.manage`, so they were both in the wrong place AND invisible to anyone
holding `content.manage` alone. They live under System with the other settings
screens.

### A loop that only applies below a breakpoint

`data-loop="mobile"` with `data-loop-below="768"` — the collections row is a
boxed 3-column grid on desktop and a two-up infinite slider on a phone.

The script cannot simply set itself up once: cloning while the track is a grid
fills it with duplicate tiles. It listens to a `matchMedia` change and TEARS
DOWN — removing every `[aria-hidden="true"]` child, resetting scroll, hiding the
arrows — then rebuilds on the way back. Verified across four breakpoint
crossings with no clone accumulation (6 originals / 12 clones on mobile, 6 / 0
on desktop, every time).

`grid-auto-flow` must go back to `row` for the desktop grid, or the columns
never wrap, and the loop's negative `margin-inline` (which exists to let a
scroller bleed past the shell) has to be cancelled so a boxed grid sits inside
it.

**Counting clones with `querySelectorAll('[aria-hidden]')` is wrong.** It
descends into each tile and finds the decorative veil and arrow spans, which
reported 66 clones where there were 12. Filter `track.children` instead.

### Test with a TOUCH device profile, not a narrow window

`@media (hover: none)` was hiding the slider arrows, so they were missing on
every real phone — and invisible to every test I had run, because a resized
desktop viewport still reports `hover: hover`. Playwright's device profiles
(`devices['iPhone 14']`) set it properly; a narrow `viewport` alone does not.

The arrows now stay on touch, smaller and pulled to the very edge. Hiding them
was wrong reasoning anyway: a scroller with no visible affordance reads as a
static row, and nobody swipes something they do not know moves.

Note the `!important` on `.rs-loop--gridup .rs-loop__nav` — the touch rule and
the grid rule are both single-class selectors in the same layer, and the grid
must win regardless of source order.

### Rail scrollbars

`.rs-rail` gets a 4px bar in the shop's mulberry via `scrollbar-width: thin` +
`scrollbar-color` (Firefox/standard) AND `::-webkit-scrollbar` (Blink/WebKit) —
both are needed, neither alone covers every browser.

The LOOPING sliders restate `scrollbar-width: none` afterwards, because the
shared rule above would otherwise give them a bar, and a scrollbar position on a
row that never ends is meaningless.

### Facets are scoped to the page, not the shop

`FacetService::base()` now applies the page's own context (occasion, category)
to EVERY count. On an occasion page the Category facet lists the categories its
own gifts fall into — three, not the whole tree — and "Brass 32" can no longer
appear beside a page showing four things. A count that the page cannot honour is
worse than no count.

- Inside an occasion, Category is a CHECKBOX (`cat[]`) that narrows this
  occasion. On the shop it stays a LINK, because a category there is a place
  with its own page. Different jobs, so different controls.
- The Occasion facet is shown on occasion pages too, as links with the current
  one marked `aria-current`. Dropping it left the page with no way sideways
  except the nav.
- Price survives at a SINGLE band, unlike the other facets: it is the filter
  people look for first, and on a small page every gift may genuinely sit in one
  band. An empty space reads as missing; "Under 2,000 (4)" reads as an answer.

Facets are `<details>` — first open, rest closed, and any facet with something
ticked opens regardless so a narrowed result set is never unexplained. A closed
facet that is filtering shows a dot.

**A `static` closure has no `$this`.** CI4 calls `whereIn` subquery closures
statically, so capture what it needs into a local first — otherwise the page
500s with "Using $this when not in object context".

### The wishlist heart is AJAX over a real form

The heart sits inside a `<form>` that posts to `wishlist/toggle`. The script
intercepts submit, calls `wishlist/toggle.json`, fills the heart in place and
updates the header badge — no reload, so a listing keeps its scroll position.
With no JavaScript the form still posts. If the request fails, the handler
removes `data-wish` and submits the form for real rather than leaving the person
stuck.

- **The JSON response returns `csrf_hash()`.** CI4 rotates the token on every
  POST, so without writing the fresh one back into the form the SECOND tap is
  rejected as a forgery.
- `savedAmong()` fetches the saved ids for a whole page in ONE query. Asking per
  card is an N+1 and a listing can show fifty.
- A guest still SAVES (against the visitor token, as before) and then sees a
  modal offering an account. Refusing the save would have thrown away the guest
  basket work and the merge on sign-in; prompting after saving keeps both.

### Two escaping traps hit again

`rs_icon()` escapes its class string, and a Tailwind class containing a dot
(`h-3.5`) comes out as `h-35` — the icon then renders at its intrinsic size,
which is what made the filter chevron enormous. Size icons from their own CSS
class, not a utility with a dot in it.

Quick actions on band/rail cards are `position: absolute` at the bottom of the
IMAGE. Making them `static` put them in a row of their own between the picture
and the name, which split the card in two.

### CSRF rotation breaks the SECOND background request

CI4 rotates the token on every POST. Writing the fresh one back into only the
submitted form leaves every other form on the page holding a spent token — so
the second heart failed CSRF, the error path submitted the form for real, and
the browser landed on `/wishlist/toggle`. That was the reported bug.

**Refresh every `input[name^="csrf"]` on the page** after any background POST.
Both the wishlist and cart handlers do this, on success AND on failure.

### Icons must be pointer-transparent

Inside a button or a `<summary>`, an SVG sits on top: `e.target` is the icon,
not the control, and a click on the icon can miss a summary's native toggle.
`.rs-heart svg, .rs-qty__btn svg, .rs-facet__head svg, … { pointer-events: none }`
once, rather than `.closest()` in every handler.

### Cart quantities are ABSOLUTE, never deltas

`cart/add.json` takes the number wanted, not "+1". A delta sent twice because a
tap was slow gives two increments and the customer receives three of something
they wanted one of. `CartService::setProductQuantity()` finds the loose line for
a product (`gift_box_id IS NULL` — a configured box is a different line) and
sets it, adding or removing as needed.

### A badge that does not exist cannot be updated

The basket count was wrapped in `<?php if ($count > 0) ?>`, so after the first
add there was no node to write to and the badge stayed missing. Always render
it, `hidden` when empty, with `[hidden] { display: none }` to beat its own
display rule.

### Filters apply in place

The filter form is fetched and only the grid, count, chips and facet counts are
swapped. A full reload throws away the scroll position and closes every
accordion — most of the work the reader just did. The URL is still pushed, so
back and copied links behave normally, and an aborted controller supersedes a
slow earlier response so results cannot arrive out of order.

Price is a two-handle RANGE bounded by MIN/MAX of the page's own products, not
fixed bands: bands make a shop guess the boundaries, and on a small page every
product lands in one. Facets are single-open — expanding one closes the rest.

### Two more CartService traps

There is no `$this->db` property on `CartService`; it uses `db_connect()`
inline. And `ProductModel` is not imported in `Storefront\Cart`, so a new method
needs the fully-qualified name.

### Page templates

`pages.template` picks a layout, `pages.data` (JSON) holds whatever that layout
needs. JSON rather than a blocks table: the fields are read as one whole, always,
and never queried across pages — a join table would buy nothing and cost a query.

**`Config\PageTemplates` is the single definition.** It drives the picker, the
admin fields AND the storefront view. Split across three places, a field ends up
saveable but never shown, or shown but not editable — and nothing says why.

- Saving keeps only fields the CHOSEN template declares, so a posted key no
  template asks for is dropped rather than stored. The column cannot become a
  dumping ground.
- Empty repeated rows are discarded: three blank slots on screen must not become
  three blank cards on the page.
- A failed image upload keeps the OLD image rather than clearing it.
- Defaults fill a template chosen for the first time and only then — merging
  them into an edited page would resurrect copy the shop deliberately cleared.
- Repeatable rows render as fixed slots (existing + 1 spare) rather than a JS
  repeater. Four visible boxes beat a button that makes boxes appear.
- Contact gets a bare `/contact`; other pages keep `/page/{slug}`. It is linked
  from the header, footer and email signatures, where `/page/contact` reads like
  a filing system rather than an address.
- Channel links accept `mailto:`, `tel:`, `https://` and site paths — the scheme
  cannot simply be refused here — and anything else, `javascript:` above all, is
  dropped.

`ContactPageSeeder` is idempotent and IS in `DatabaseSeeder` (the mistake made
with AuthContentSeeder). The photograph is seeded empty on purpose: a
placeholder on a contact page looks like a fault, and the band hides itself
until a real image is uploaded.

### PIN code lookup

`Storefront\Pincode` proxies India Post (free, no key) and caches for a month.
A proxy rather than a direct browser call because: the same few hundred PINs get
typed repeatedly, the upstream can change in ONE place rather than three forms,
and no third party learns a customer's address before they have ordered.

- Only a real answer is cached. Caching a network failure for a month would
  turn a blip into a lasting outage for that PIN.
- On failure the city and state fields become writable and say so. **A lookup
  must never block a checkout.**
- `District`, not `Name` — `Name` is the individual post office ("Amer Fort")
  where the customer expects their city.
- The lookup is scoped to `closest('[data-bill-fields], fieldset, form')`, so
  the billing PIN cannot overwrite the shipping fields.
- City is now `permit_empty`: it is filled by the lookup and a PIN can
  legitimately have no district, so requiring it would block a real address.

Billing fields are `hidden`, not removed, so a value typed and then re-ticked
survives. The box is ticked by default because for most orders the two ARE the
same.

### Cart quantity, in place

The cart re-fetches its own page and swaps `[data-cart-lines]` and
`[data-cart-summary]`. Totals stay server-side — coupons, shipping bands and
gift-box pricing all live there, and recomputing in the browser is a second
implementation that eventually disagrees with the first.

Note the badge counts LINES, not units, so 3 of one product still shows 1. That
is the existing convention, not a bug.

### Wishlist page

Hearts render `aria-pressed="true"` (everything there is saved, by definition)
and un-saving REMOVES the card — an empty outline on a page of saved things
reads as a fault, and the heading count would disagree with the screen. The last
one triggers a reload so the empty state renders properly.

`cursor: pointer` on hearts and steppers: a button's default cursor is an arrow,
and one control on a card that does not show a hand reads as decoration.

### The mail preview shows the WRAPPED message

`body_html` is only the inner content; `deliver()` runs it through
`MailService::wrap()`, which adds the branded shell. Previewing the raw column
showed something no customer ever receives — the right words in none of the
right dressing. The preview now calls the same `wrap()` that sends it, so it
cannot drift from the real thing, and a second tab shows the plain-text
alternative (invisible otherwise, and a broken one is only found by someone
reading mail in a text-only client).

`sandbox="allow-same-origin"` rather than a bare `sandbox`: the height cannot be
measured through an opaque document, so the frame stayed at a fixed size and
cropped long emails. **Scripts stay off** — `allow-scripts` is the token that
matters, and an email template edited badly still cannot run anything against
the panel.

**Collapse the iframe to 0 before measuring it.** `scrollHeight` on a short
document returns at least the frame's own height, so a 640px frame holding 470px
of email reports 640 and each pass makes it taller than the last — 640 → 720 →
… Shrinking first means the number that comes back is the content's. Also
measure immediately, not only on `load`: a `srcdoc` frame often finishes before
the script runs, so `load` may never fire.

### A code that was never in the message cannot be shown

Messages queued before the placeholder bug was fixed were stored with an empty
`<strong></strong>` — the customer received a blank, and `codeFrom()` correctly
returns null. `auth_codes` holds the code hashed, deliberately, so it is
unrecoverable.

Those rows now say so LOUDLY rather than silently omitting the panel. "There is
no panel" and "the panel is empty" look identical from the outside, and one of
them means the email itself was broken — which is exactly the confusion this
caused.

The code also appears **in the queue list**, beside each row. Until SMTP is
configured this queue IS the inbox, and opening a message to read six digits is
a step for nothing. The list view audits `mail_code_viewed` when any code was on
screen.

### Corporate mode is a per-VISITOR journey switch

`journey_mode` was a global admin setting. It now reads a `rs_mode` cookie
first, so a corporate buyer can put the whole site into enquiry mode from the
header — someone ordering two hundred hampers will not use a basket, and someone
buying one gift should not fill in an enquiry form.

- The switch is a **POST**. It changes how the whole site behaves, and a GET
  that does that can be fired by any image tag on any page.
- The cookie is deliberately NOT httpOnly: it holds nothing personal, is not a
  credential, and the header needs to reflect the mode without a round trip.
- A banner runs under the header while corporate mode is on. A shop that
  suddenly has no Add to Cart looks broken otherwise.
- The switch appears twice — header and mobile drawer — because the header one
  is hidden below 900px where the icon row has no room.

**`'inherit'` must be RESOLVED, not passed through.** `rs_cta_label('inherit')`
matched neither branch and every card said "Add to cart" even in corporate mode.
It now goes through `resolveItemMode()`, which is what turns a product's
`inherit` into the site's current journey.

### settings.value_type is an ENUM without 'text'

Seeding `'text'` made MySQL store an empty string, `cast()` fell through to the
default, and every one of those settings read back EMPTY — nothing errored. Use
`'string'`. Check `SHOW COLUMNS` before inventing a type name.

### The animated search placeholder

Types a phrase, holds it, wipes it, moves to the next. Phrases come from
`search_placeholders`, one per line, so a shop advertises what it actually
sells. It stops entirely under `prefers-reduced-motion`, and on focus — leaving
a COMPLETE phrase, never half a word, because text moving under someone who is
typing is a distraction.

### Product attributes are SPECIFICATIONS, not variants

The catalogues show "COLOR VARIANT" and "SIZE 8x3.5x5" as descriptions of ONE
product with one quantity-tiered price ladder — not separate SKUs each with
their own stock and price. Full variants would need a row per combination with
stock and price on each, none of which the catalogue has and none of which the
shop tracks.

`attributes.is_selectable` is the whole difference: "this bowl is 8 inches" is a
spec (listed), "which colour would you like" is a chooser (offered as chips).

- Values are a **controlled list**, not free text on the product. "Silver",
  "silver" and "SILVER" typed on three products become three facets otherwise.
- Filtering is **OR within an attribute, AND across attributes** — silver or
  gold, AND peacock. That needs ONE SUBQUERY PER ATTRIBUTE; a single `whereIn`
  over all values returns anything matching any one of them.
- `AttributeValueModel::forProducts()` loads a whole page in one query, the same
  shape as `imagesFor()`. Per-card would be fifty queries on a listing.
- Deleting a value in use is **refused**, not cascaded. The foreign key would
  happily strip it from forty products with no way back.
- The chosen value is stored as TEXT on the cart and order line, never as an id:
  an order is a record of what was agreed, and it must still read correctly
  after a value is renamed or deleted.
- Facet counts come from `base()`, so they are scoped — Silver (2) on the Diwali
  page, Silver (6) shop-wide.

Seeded from the shop's own PDFs: Peacock and Elephant are real shapes Rasmein
sells, not placeholders.

**Three traps hit again while building this:**

- `ProductModel` returns ENTITIES — `$product['id']` throws "Cannot use object
  of type Product as array". Use `$product->id`.
- `addColumn`'s `after` names a column that must exist on THAT table. It named
  one from a different table and the ALTER was silently rejected.
- `PricingService` builds an EXPLICIT line array, so a new `cart_items` column
  does not reach the cart view for free — it has to be added there too.

### The real catalogue: ProductCatalogueSeeder

155 products, 11 categories, 224 attribute links, parsed from the German Silver
and Premium Collection PDFs. It TRUNCATES products, categories and their joins
first — destructive on purpose, because a merge would interleave the demo
catalogue with the real one. Orders are untouched: they keep name and price
snapshots precisely so the catalogue can change underneath them.

- **A PL code labels a catalogue PAGE, not a product.** `PL-7002` covers a
  platter and an urli; 67 SKUs needed a `-2`/`-3` suffix to satisfy the unique
  key. Worth renumbering properly before launch.
- **113 of 155 prices are ESTIMATED** (category median). The Premium Collection
  is two-column, so `pdftotext` ties the price to the code rather than the name.
  The 42 German Silver prices are the catalogue's own figures.
- The seeder deliberately varies the data: pieces over ₹5,000 become
  `enquire_now`, two go out of stock, slots scale 1/2/3 with price, one product
  per category is featured. **Uniform seed data leaves whole code paths
  untested** — that is how the builder's capacity maths and the sold-out path
  went unexercised.

### Diagnostics must not pin demo fixtures

Nine checks broke when the real catalogue replaced the demo one, and every
failure was the TEST, not the app: hard-coded slugs (`dark-chocolate-72`,
`blue-pottery-platter`), hard-coded prices (`320.0 * 3`), and a search for
"chocolate".

Fixed by asking for the TRAIT each assertion depends on — a sold-out test wants
`stock_qty = 0`, a two-slot test wants `giftbox_slots = 2`, a refused-category
test reads `gift_box_categories` and picks something outside it — and by
computing expectations from the row actually chosen. Assert the RULE (free
delivery above the threshold) rather than a figure (₹960).

One trap in the fixing: a blanket find-and-replace gave every lookup the same
filter, so `$bar` and `$candle` became the SAME product and the 1-slot and
2-slot tests measured one row twice.

### A seeder must seed its own dependencies

`ProductCatalogueSeeder` looks attribute values up BY LABEL, so it silently
linked nothing when run before `AttributeSeeder` — and printed
"0 attribute links" as though that were a normal result. The shop then found an
empty Attributes screen and no attribute section on the product form, with
nothing anywhere explaining why.

It now calls `AttributeSeeder` itself when `attributes` is empty (that seeder is
idempotent, so calling it twice costs nothing) and prints a WARNING if the link
count still comes out zero.

**Ordering documented in a chain is not a dependency.** Anyone running one
seeder directly — which is the normal way to re-seed one thing — bypasses the
chain entirely. A zero-result run that prints a cheerful summary is worse than
an error.

### Variants sit ON TOP of attributes

Attributes alone are a bag of unrelated facts: "silver, antique silver, round,
antique finish". They cannot answer "is antique silver available in the large
size", cannot price one colour higher, and cannot give a colour its own
photograph. Modelling them as specifications only was the wrong call.

A VARIANT is one combination that can be bought. `product_variants` +
`variant_values`.

- **Every override column is NULLABLE and falls back to the product.** A variant
  differing only in colour does not restate price, picture and description — and
  when the product's price changes, it follows.
- **`variant_key` is stored, not derived**, so a shared URL survives a value
  being renamed from "Silver" to "Sterling Silver".
- **The whole matrix goes to the browser as JSON.** Selecting a colour has to
  grey out sizes that colour does not come in; asking the server per click would
  feel like dial-up.
- **Unavailable options are disabled, not hidden.** Hiding them makes a shop
  look like it does not stock something it does. Sold-out and non-existent are
  shown differently — they are different facts.
- **The variant is part of cart line identity.** `findProductLine()` takes it,
  or adding gold silently increments the silver line already in the basket.
- **`replaceState`, not `pushState`** — trying three colours should not mean
  pressing back three times to leave.
- An unknown variant key is NOT a 404: links get shared after a variant is
  retired, and the piece still exists.
- Only ONE of the two product routes is named. Two routes cannot share a name,
  and the two-segment rule must be declared FIRST or the single-segment one
  swallows it.

### Money is formatted server-side

The variant JSON carries `rs_money()` output, not raw numbers. The browser must
not reimplement currency rules — that is how a second, subtly different format
appears on one page.

### The variant admin

`admin/products/{id}/variants` — a table, one row per combination, one form for
the lot. Someone repricing a colour range is changing four numbers, not making
four decisions, so a save button per row would be four times the clicking for
nothing.

- **A blank price means "follow the product", not zero.** That distinction is
  the whole point of the nullable columns; a cleared field must return the
  variant to the product's price, not make it free.
- A posted variant id is checked against THIS product, or a hand-edited form
  could reprice someone else's.
- `is_default` is a RADIO, so exactly one variant opens the page. Two would make
  `pick()` arbitrary; none would open on whatever sorted first.
- Deleting a variant that is **in a basket is refused** — the cascade would take
  the line with it and the customer would find their basket lighter with nothing
  explaining why. Switching it off hides it from the storefront and leaves the
  basket intact.
- The same combination twice is refused: two variants would match one set of
  choices and selection becomes ambiguous.
- A failed image upload keeps the OLD picture rather than clearing it.
- The description is behind a `<details>`, not a table column — a paragraph in a
  column squashes everything else.

The link lives on the product form beside Attributes, because variants are built
FROM those ticks and that is where someone will look for them.

### syncAttributes() landed in delete(), not save()

A regex insert matched the audit call in `delete()` instead of the one in
`save()`. Two consequences, both silent:

- Ticking attribute values on the product form **never persisted** — the form
  posted them, nothing read them.
- **Deleting a product wiped its attribute links** as a side effect, which is
  not what a soft delete is for.

Nothing errored. The tick simply reappeared unticked after a reload.

It now sits beside the occasions sync, after `$id` is known — on a new product
the id does not exist until `getInsertID()`.

**Anchor an insert on the method, not on a line that appears in several.**
`service('audit')->log(...)` appears in every controller action; matching one by
its arguments is matching a coincidence. Verify placement afterwards by grepping
for the call and reading the enclosing function name.

### Multi-axis variants: selection is BIDIRECTIONAL

A product can have any number of choosable axes — colour x size x finish — and
they need not be symmetric: silver in 8" and 12", gold only in 12". The schema
already handled this (`variant_values` is a row per value), but the browser
logic did not.

**The bug:** availability was tested against every OTHER chosen axis. With
Silver and 8" selected, Gold was measured against 8"; there is no Gold 8", so
Gold greyed out — and the buyer could never reach the Gold 12" that exists. A
dead end with no way back, on a product that was perfectly well stocked.

**First fix, also wrong:** narrowing only downward meant choosing a size could
never move the colour. But if 2 cm exists only in blue, choosing 2 cm MUST mean
blue — otherwise the size is greyed out despite being stocked, or the page sits
on a combination nobody can buy.

**The rule now:** clicking any option keeps it fixed and `bestFor()` picks the
variant carrying it that KEEPS THE MOST of the buyer's other choices, breaking
ties towards stock. Nothing moves that does not have to, and anything that must
move, does — in either direction.

Three states, and the distinction matters:

- **impossible** — no variant carries this value at all. Struck through and
  disabled.
- **fits** — works with everything currently chosen. Plain.
- **adjusts** — exists, but choosing it moves another axis. Dimmed and STILL
  CLICKABLE. Greying it would hide stock the shop actually has.

Verified with three axes: clicking Gold moves size 8"→12" AND finish
Polished→Matte in a single step, and the greying updates to match.

Colour, size, shape and finish are all axes now. Capacity stays a spec.

The catalogue seeder BROADENS the palette: the PDFs name the colour a piece was
photographed in, not the range it is offered in, and one colour per product
makes a "choose a colour" control with one option. It also gives half the range
a second size. Both are deterministic on the SKU, so the catalogue is identical
on every machine and every rerun.

`VariantSeeder` drops about a fifth of the cross product for the same reason a
real catalogue is asymmetric — and because seeding a perfect grid hides the
whole problem the selector exists to solve. The first combination of every piece
always survives, or a product could end up with nothing to sell.

### Catalogue export

A chooser at `admin/catalogue/export`, not two bare download buttons —
"everything" is rarely what someone wants, and checking one category over is a
different job from auditing 460 rows. Whole catalogue, by category, or by
occasion; CSV or SQL. All behind `catalogue.manage` and all audited with the
scope in the entry.

- Counts show against every option, and an empty one has NO button. A button
  that returns an empty file wastes someone's time.
- The filename says what is inside — `rasmein-category-dry-fruit-boxes-DATE.csv`
  — so three exports in a downloads folder are still tellable apart next week.
- The scope id is validated against the database. A bad id narrows to nothing
  rather than widening to everything.
- The occasion filter is a SUBQUERY, not a join: joining the pivot would
  multiply every variant row by its occasions.

**A scoped SQL dump emits no DELETEs.** A full dump clears each table first so a
replay is a true replacement; a filtered one must not, or replaying "just the
dry fruit boxes" would destroy the rest of the catalogue on the target machine.
It still emits ALL categories, attributes and values — the rows its products
point at — because a dump missing its own foreign keys will not replay, which is
the one thing a SQL export is for.

**One row per VARIANT, not per product.** A product-per-row export cannot answer
"which sizes does the gold one come in", which is the question it exists for.
One row per variant makes the grid sortable and filterable in a spreadsheet.

- A **UTF-8 BOM** on the CSV. Without it Excel on Windows reads the local
  codepage and turns rupees and inch marks into mojibake — a correct export that
  looks like corrupt data.
- Prices are unformatted (`3500.00`), because a spreadsheet has to be able to
  SUM the column and "Rs 3,500" is text. A "Price is" column says `inherited` or
  `set on variant`, which answers "why are these two the same".
- A column per attribute, so the sheet filters on colour or size.
- **LEFT JOIN on variants**, so a product with none still appears — otherwise an
  export used to check the catalogue silently omits exactly the products someone
  forgot to set up.
- The SQL dump emits parents before children and wraps in a transaction: a dump
  that half-loads is worse than one that fails, because nothing says which half
  arrived. Verified by replaying it into an empty database — 155 products, 460
  variants, 1002 links, all intact.

### The marquee saved but never showed: query by KEY, not by group

`Admin\Homepage::index()` fetched `where('group_name', 'home')`, but
`design_marquee_text` lives in `design` beside its on/off switch. So the field
rendered blank, saving wrote the value correctly, and the next page load showed
blank again — it looked like nothing was being stored at all, while the
storefront displayed it fine.

`SECTIONS` is the list of keys the screen owns, so it is also the right thing to
query on: `whereIn('key_name', $keys)`.

`save()` had the mirror bug waiting — it forced every key to group `home`, which
would have moved the marquee out from under its own switch. It now keeps a key
in the group it already belongs to, and only creates NEW keys in `home`.

### Multi-value settings are one item per LINE

`design_marquee_text` and `search_placeholders` are lists. A separator character
is easy to type wrong and impossible to see; a line break is neither. Both
splitters still honour the old `·` and `|` so nothing typed before the change
disappears, and the admin shows a live count so an empty line is obvious.

### SweetAlert on the storefront

Loaded on the storefront layout as well as the admin, so a confirmation looks
the same in both. Both script tags are `defer` and SweetAlert comes FIRST —
deferred scripts run in document order, so `Swal` is defined by the time the
flash block looks for it.

A TOAST, not a modal: a confirmation nobody asked for should not make them
dismiss a dialogue before carrying on. Errors last 6.5s to a success's 3.5s and
pause on hover, because an error unread is an error unfixed. The markup stays in
the page inside `<noscript>` and an `aria-live` region, so the message survives
a failed script and is still announced to a screen reader.

### A seeder that writes to `settings` MUST flush the cache

`SettingsService::all()` caches the whole array. A seeder writes straight to the
table, so the cached copy from an earlier request still holds the old values —
the row was there with 299 characters in it, and `get()` kept returning nothing.

`service('settings')->flush()` at the end of every seeder that touches
`settings`. Several still lack it (BrandSetting, DesignSetting, HomeContent,
MailSetting) and will bite the same way.

### Header & footer master screen

`admin/chrome` gathers navigation, search wording, the announcement bar, footer
columns, small print and contact details. They live in FOUR different groups
(`design`, `auth`, `store`, `social`), which is exactly why they were hard to
find; the screen queries by KEY and writes each back to the group it came from,
so Appearance, Shop identity and the sign-in screen keep working.

- Footer columns are `"Column | Label | /path"`, one per line, grouped in the
  order written — so reordering means moving a line.
- **Absolute URLs are refused** in footer links. A setting that can emit one
  makes the footer an open-redirect surface.
- Falls back to the pages marked "show in footer" when nothing is configured. A
  fresh install must not render an empty footer because nobody opened the screen.
- List fields are monospaced with a live line count: these lines have a SHAPE,
  and a proportional font hides a missing pipe.

The search placeholder animation was already working — it needs TWO OR MORE
phrases, and does nothing with one. The mobile drawer's input now animates too;
it had been left out, and on a phone that is the only search box shown.

### The About / story page template

A third page template (`about`), on the same `Config\PageTemplates` machinery:
hero, chapters with drop capitals and a pull quote, two photographs, numbered
principles, a timeline, a founder band and an enquiry form. Every string edits
in the admin; 51 fields across 7 sections.

- The hero accent takes the SECOND word from the end (`traditions built.`),
  which is what the design does — not the last two words like every other
  heading.
- The pull quote is placed after the second paragraph, and falls to the end if
  there are fewer than two. Hard-coding position 2 would silently drop it on a
  short page.
- Photographs seed EMPTY. A placeholder on a story page looks like a mistake,
  and the section hides itself until real ones are uploaded.
- `AboutPageSeeder` UPGRADES an existing plain-text about page rather than
  inserting a second one — two about pages is worse than one with the wrong
  layout — and leaves it alone once it is already on the template.

### `leads` is not `enquiries`

`enquiries.order_id` is a REQUIRED foreign key: that table tracks a quote for a
basket someone has already built. A lead from a content page has no basket and
no order, and forcing one into that shape would mean inventing an empty order
per enquiry.

Separate `leads` table, with the fields the form actually asks for as columns
rather than mashed into a note. Rate limited to five per IP per hour, because a
public unauthenticated form with no account behind it is an open invitation.
Notification failure is caught and logged — the lead is already saved, and
losing it to a mail problem would be the worst outcome.

### An AJAX swap target must EXIST in every state

`[data-grid]` was inside `<?php if ($products !== []) ?>`, so a filter matching
nothing had no element to swap into — the script found no target, the results
area was left blank, and the "Nothing matches that yet." message (which sits
BESIDE the grid, not inside it) never appeared. A reload rendered it correctly,
which is exactly what made the bug confusing to report.

`[data-results]` now wraps BOTH branches and always exists. The swap replaces
that whole region, so the empty state arrives with everything else.

Two related rules:

- A section missing from the new response is EMPTIED, not skipped. The chips row
  vanishes when the last filter is cleared, and `if (next && here)` would have
  left the old chips on screen pointing at filters that are gone.
- Swap the region that owns the STATE, not the happy-path element inside it.
  Anything conditional is the wrong target by definition.

### Scroll-reveal hides anything inserted after page load

`.rs-reveal-ready .rs-reveal { opacity: 0 }` hides every card until the
IntersectionObserver fades it in. The observer ran ONCE, over the elements
present at load — so a filtered product grid arrived at opacity 0, was never
observed, and stayed invisible for good. The products were in the DOM the whole
time; a reload "fixed" it because the observer ran again.

Two changes:

- A **MutationObserver** watches the body and observes any `.rs-reveal` inserted
  later. Every future swap is covered without each one having to remember to
  ask — the alternative is a re-scan call after every innerHTML replacement,
  and the next one written will forget.
- Anything **already inside the viewport is shown immediately** rather than
  observed. Swapped-in content usually sits exactly where the reader is looking,
  and waiting for an intersection that has already happened is how it stays
  blank.

**An entrance animation is a hiding mechanism.** Any CSS that starts at
`opacity: 0` and depends on script to undo it will strand content the day
something inserts DOM — check it against every dynamic path, not just first
paint.

### The cart drawer

Slides in from the right after an in-place add, so the shopper sees what
happened without leaving the page they were reading. `admin`-free: it is a
storefront partial fetched from `GET /cart/drawer`.

**Server-rendered, fetched whole.** Not JSON assembled in the browser — coupons,
shipping bands and gift-box pricing live in `PricingService`, and rebuilding any
of that in JavaScript is a second implementation that eventually disagrees with
the first.

- Contents are FETCHED, never rendered into the page at load. A basket printed
  with the page is out of date the moment anything changes.
- Quantity and remove post to the SAME endpoints the cart page uses, so one set
  of rules governs both. The whole drawer reloads afterwards rather than being
  patched: the totals change, and a patched line beside a stale total is exactly
  the bug this avoids.
- `e.submitter` is how a submit button's own name/value is read — the `-` and
  `+` buttons carry the quantity, and plain `FormData` does not include them.
- Shipping says "Calculated at checkout" rather than guessing. It depends on the
  delivery address, which has not been asked for yet, and a figure that changes
  later is worse than an honest blank.
- The button says "Proceed to checkout", not `rs_cta_label()`'s "Buy now", which
  reads oddly beside a total — and "Send the enquiry" in corporate mode, because
  it is a different act.

The product page's add-to-cart is now `data-cart` like the cards: no redirect,
and without JavaScript it still posts normally.

### `.rs-drawer` was already taken

The mobile menu owns `.rs-drawer` — maroon, 20rem, `translate: 100%`. Naming the
cart panel the same thing meant it opened with the menu's background and width,
so it was effectively invisible WHILE the scroll lock was applied: a frozen page
with nothing on it, which reads as a crashed site rather than a styling bug.

Renamed to `.rs-cartdrawer`. **Grep the stylesheet before naming a new
component** — a collision does not error, it silently inherits.

A guard now makes this class of failure survivable: `open()` measures the panel
and, if it has no width, closes and navigates to `/cart` instead. A working page
somewhere else beats a locked page here, whatever the cause.

The drawer opens from every add-to-cart — product detail, listing and wishlist —
because they all use the same `[data-cart]` form and the same handler. Verified
on all three: panel 448x1000, white, with the checkout button.

### Collections moved to /collection/{slug}

An occasion used to live at the ROOT — `/diwali-2026` — sharing a namespace with
every category and page, so a new occasion could silently shadow one. The old
address now returns a **301**, not a 404: those URLs may be in a history, a
printed card or a search index, and both a 404 and a silent re-render throw away
traffic that was already earned.

`rs_collection_url()` is the ONE place that builds the address. The old form was
hard-coded in four files — facets, two homepage rows and the admin preview link
— which is exactly why moving it was a hunt rather than an edit.

### /collection is a PAGE; /collection/{slug} is a LISTING

The designed landing page lives at `/collection` as a `pages` row with the
`collections` template — so it edits under Pages with the same editor as About
and Contact, rather than needing a screen of its own.

An individual occasion at `/collection/{slug}` is a plain filtered listing. It
was briefly given the landing layout, which was wrong: that page exists to browse
the products in a collection, and a hero band with an ethos paragraph is in the
way of that.

The tiles and the product rail both PICK OCCASIONS — an `occasions` field type
rendering a tick list, not free-typed rows. An occasion already has a name, a
photograph and a URL; asking someone to retype all three is three chances to get
it wrong and no way to notice when the occasion is later renamed.

**Ticking none means ALL of them.** A page called "collections" listing none is
the one failure it cannot have, and a fresh install must work before anyone
opens the editor. The product rail falls back to the tiles' own list, so ticking
"Diwali" once drives both.

Both sections were empty at first because they depended on data that did not
exist: the rail used `is_featured` and the tiles needed configured rows. A
section whose content comes from somewhere the shop has not filled in yet should
default to something real, not to nothing.

### One context key, one meaning

`Shop::listing()` took the collection scope from `lockedCollection` for the
product query and `facetOccasion` for the facets. `renderOccasion()` set both;
`collection()` set only the first — so a collection page showed six products
beside facet counts for the whole catalogue (Silver 146, Serving Trays 41).

The facets now read `lockedCollection`, the same key the query uses. **Two names
for one thing is a bug waiting for the second caller.**

### The variant chain must reach MONEY, not just the page

A variant system that only feeds the product page is worse than none: the
customer is shown one number and charged another. Four links were missing, and
all four had to be added together.

- `CartItemModel::forCart()` now LEFT JOINs `product_variants`. Without it every
  line was priced from `products.price` — 291 variants carrying a premium were
  displayed correctly and billed at base.
- `PricingService` prices from the variant when it sets one. The test is
  `!== null`, not `??` — a variant that inherits stores NULL, and `(float) null`
  is 0.00. A basket priced at zero is worse than one priced wrongly.
- `OrderService::writeLines()` writes `variant_id` AND `variant_label`. The id
  alone leaves an order unreadable once a variant is renamed; the label is a
  snapshot for the same reason `name_snapshot` is.
- Variant stock is checked in `availableStock()` and decremented in
  `reserveStock()`. Use `affectedRows()`, not `update()`'s return — that reports
  whether the STATEMENT ran, so a sold-out variant would pass silently.

**`setProductQuantity()` takes the variant.** `findProductLine()` was
variant-aware and this was not, so the AJAX path — the one the product page
actually uses — found the Silver line when Gold was chosen and incremented it.
It also had no `orderBy`, so with two lines it took whichever the database
happened to return.

### FormData does not carry the submit button

Only `e.submitter` has the button's name/value. The product page's "Buy now"
(`name="checkout"`) never reached the server, so it behaved exactly like Add to
cart. The drawer handler read `e.submitter` and this one did not — the same bug
fixed in one place and not the other.

The same handler hard-coded `quantity` to 1, which made the stepper decorative.

### Idempotency: use the POSTED key

`Checkout` generated a fresh random key when the session key was absent — and
the session key is REMOVED after a successful order. Back, resubmit, new random
key, duplicate check passes, second order for the same basket. The hidden field
existed and was inert. It is format-checked before use, since it lands in a
unique index.

### Notifications go AFTER the commit

`queueNotifications()` inserts into `notification_log` and
`admin_notifications`. Inside the transaction, any failed insert there sets
`transStatus()` false; the internal `catch` swallows the exception but cannot
reset that flag — so a mail problem rolled back a COMPLETED order. An order
without its email is a nuisance; an order that vanishes because of an email is a
lost sale.

### PricingService builds an EXPLICIT line array

`gift_recipient` was collected in the builder, stored on `cart_items` and read
by `OrderService` — but never put in the array between them, so it always
resolved to null. Third time this shape of bug has appeared: a column added at
one end does not travel unless the array in the middle names it.

### One screen owns both kinds of collection

`collections` holds `type = 'occasion'` (dated: Diwali 2026) and
`type = 'collection'` (ongoing: The Tea Drinker). `Admin\Occasions` was locked
to `'occasion'`, so the collection rows appeared on the storefront and could be
edited only in SQL.

The screen now lists and edits both, and **preserves the kind on save** — the
payload used to hard-code `'occasion'`, which would have quietly reclassified a
collection the first time someone touched its copy and dropped it out of the
collections index. `save()` takes only an id, so the current kind is looked up
rather than assumed.

The kind is chosen when creating and shown read-only afterwards: changing it
later moves the page out from under its own URL.

`CollectionPageSeeder` fills the landing copy and is CONSERVATIVE — a row that
already has `data` is left exactly as the shop has it. Overwriting edited copy
on every deploy is how people learn to stop running seeders.

### A template needing extra data declares its own route

`/collection` is served by a dedicated action that builds the occasion tiles and
their products. `/page/collections` — the generic route — rendered the SAME
layout with only `page` and `data`, so sections three and four were silently
empty. Two addresses for one page, one of them broken.

A template can now carry `'route' => 'collection'`, and `Pages::show()` sends
those slugs there with a **301**. One address, and the next template that needs
its own data cannot repeat the mistake.

**If a view needs more than the generic renderer passes, the generic renderer
must not be able to reach it.**

### An empty section is a data problem, not a layout one

The product rail was empty because no occasion had any products tagged — a
legitimate state that reads as a broken page. `CollectionsPageSeeder` now tags
eight pieces into any occasion that has NONE, offset by the collection id so the
three do not all show the same eight. A curated occasion is never touched.

### The rail's width lives on the TRACK

`rs-loop` needs `<ul class="rs-loop__track rs-loop__track--x" data-loop-track>`
inside it. The card width comes from `grid-auto-columns` on that track — put the
items straight into `[data-loop]` and they have no column size, so each one takes
the full width and arrives one at a time.

Reusing a component means copying its STRUCTURE, not just its outer class. The
class turned the scroller on; the track is what made it a row.

`--cards` is a new track variant: narrower than `--tiles` because a product card
carries a name and a price under the photograph, so more of them have to be
visible before the row reads as a row. 70vw → 38vw → 24vw → 19rem.

### Variant scoping is not done until EVERY lookup has it

`findProductLine()`, `setProductQuantity()` AND `quantityOf()` all take the
variant. The last one was missed and fed the stepper: with Silver x2 and Gold x1
in the basket it returned whichever row the database offered first, so the Gold
card showed 2 and the next "+" sent an absolute quantity computed from Silver.

**Fixing one caller of a shared shape is not fixing the shape.** Grep for every
query on `cart_items` filtered by `product_id` and check each for the variant.

### A warehouse picks by SKU

`sku_snapshot` took `product_sku`, so an order for
`TLG-11001-GOLD-6-X-6-E-5` was printed as `TLG-11001`. `variant_label` saying
"Gold" does not help someone reading a pick list by code. `pv.sku` is aliased on
the cart line and wins over the product's.

### Stock ceilings belong where the quantity is SET

`addProduct()` and `updateQuantity()` clamped against `products.stock_qty`, so a
variant with 12 accepted 25 into the basket and PricingService refused it at
checkout — the customer saw "only 12 left" AFTER the cart had taken 25. Both now
read the variant's ceiling first.

### The date window moved with the URL

`RootUrlService::isRunning()` guarded the old root path only. Once occasions
redirected to `/collection/{slug}`, an expired one rendered happily at the
address the shop links to everywhere while the dead URL correctly 404'd. The
check now lives in `Shop::collection()`, which is the one door left.

The end date is INCLUSIVE — a window ending "31 October" is still open at half
past eleven that night.

`renderOccasion()` was the only caller passing `endsAt`, and deleting it silently
took the countdown with it. `collection()` now serves both kinds and sets the
eyebrow and countdown from `type`. **Deleting a method that "nothing calls"
deletes whatever only it supplied.**

### Idempotency: verify the claim, not just the key

The key comes from a form now, and returning the matched order grants read
access to a name, address, phone and totals. 192 bits is not guessable — but
that is a property of the generator, not of the code. The duplicate branch checks
the posted email against the order's before handing it back.

### The corporate page

`/corporate`, a `pages` row with the `corporate` template: banner, marquee, work
occasions, product rows, the split panel and an enquiry form. Turning the
corporate switch ON redirects here — switching mode is a statement about WHY
someone is here, not a preference to apply to whatever page they were on. It
only redirects if the page exists; landing on a redirect-to-shop is worse than
staying put.

- `collections.audience` — `both` / `retail` / `corporate`, edited as TWO
  CHECKBOXES rather than a three-way select. "Both" is not a third kind of
  occasion; it is both boxes ticked, and the control should say so. Ticking
  NEITHER stores `both`, because an occasion visible nowhere is a row the shop
  cannot find again — an empty form must not be able to hide something by
  accident.
- Default `both`, so nothing already in the table vanishes from a page it
  currently sits on, and `forAudience()` always includes `both`: Diwali is
  gifted to a client as readily as to a cousin.
- The corporate product rows are a GRID, not a rail. On the collections page a
  rail is right — it is a taste of a collection. Here the pieces are the point,
  and a business scanning for what to order should see them all at once.
- Two new field types. `products` is a grouped `<select multiple>` — 155
  products as checkboxes is a page nobody can scan, and the browser gives search
  and keyboard selection for free. `lines` is the one-per-line list already used
  by the marquee and search placeholders.
- A picker inside a LIST row stays an array, so the "is this row empty" test
  cannot be `implode()` — that fatals on an array. Each value is tested for its
  own kind of emptiness.
- Product rows resolve in ONE query for all rows, then reorder in PHP. Six rows
  of eight would otherwise be six round trips for what a single `whereIn`
  answers.
- The hero is now `partials/hero_banners`, shared with the homepage, so a slide
  that works on one works on the other.

**A validation rule is not a column.** `banners.position` is a database ENUM;
widening the model's `in_list` let the application accept `corporate_hero` while
MySQL rejected the insert outright. Both have to agree, which needs a migration.

### A config entry copied by hand loses the keys nobody looks at

Adding `corporate_hero` to `Banners::SLOTS` without `ratio` and `multi` took the
WHOLE Banners screen down with "Undefined array key" — every slot renders on the
index, so one incomplete entry breaks all eight.

Two fixes, and the second matters more:

- The entry now carries every key.
- `slotMeta()` merges each slot over defaults, and every CONSUMING read goes
  through it. `self::SLOTS[...]` survives only where the question is structural
  — does this slot exist, what are the keys.

**An admin page that 500s because one config entry is incomplete is a bad trade
for a label nobody would have missed.** When adding to a config array, diff the
new entry's keys against an existing one rather than writing the ones that
seemed necessary.

### The media library

`media` table + `admin/media/browse|upload` + one modal in the admin layout. Any
file input marked `data-media` gets a "Choose from library" button; the input
still works alone, so a screen keeps functioning if the script fails.

- Search covers filename AND alt text: a filename says what the photographer
  called it, alt text says what it shows, and someone hunting for "the gold
  bowl" will match one or the other, rarely both.
- Upload goes through `service('images')` — the same path every other screen
  uses. A second uploader would be a second set of validation rules to keep in
  step.
- `remember()` returns the existing row when the path matches rather than
  inserting a duplicate the shop would then have to choose between.
- `countAllResults(false)` — the `false` keeps the conditions for the fetch that
  follows. Without it the page returns the whole table.
- Choosing from the library CLEARS the file input: a pending upload and a chosen
  path in one field leaves the server to guess which was meant.
- `rasmein:backfill-media` puts existing uploads in, copying alt text from
  whichever record already uses the file. Without it the picker opens empty on
  an existing shop — which is the problem it was built to solve. It skips the
  generated size variants, or the same picture appears six times and someone
  picks a 200px thumbnail for a hero band.

### Occasions: nine retail, eight corporate

`OccasionSeeder`. Same kind of row — `type = 'occasion'` — separated by
`audience`, so each page asks one question ("what belongs here") rather than
knowing about two lists.

Idempotent by slug, and it does NOT reset the audience of an occasion that
already exists: someone who deliberately moved Festivals onto the corporate page
should not find it moved back by a deploy.

No dates. These are standing occasions — Birthdays do not expire, and an
`ends_at` would 404 the page the day it passed.

**The homepage strip was excluding corporate occasions BY ACCIDENT.**
`liveOccasions(10)` filtered on nothing but type; the limit of ten happened to
cut off before the corporate ones in sort order. Reorder the list or add a tenth
retail occasion and "Employee Milestones" would have appeared beside "Baby
Shower". It now filters on audience — verified by raising the limit to 20 and
confirming the strip still shows ten.

A filter that works because of a LIMIT is not a filter.

### Shop identity owns contact details and social links

`support_email`, `support_phone`, `whatsapp_number` and the `social_*` keys live
in Shop identity, and BrandService is what every template reads.

Two duplications had crept in, and both were invisible failures:

- **Header & footer** grew `store_support_email`, `store_support_phone` and
  `store_whatsapp` beside identity's own. The footer reads the IDENTITY ones, so
  editing that pair on the other screen changed nothing on the site. Migration
  000033 carries any typed value across — only into a blank, since the working
  screen's value must win — and deletes the twins. `ChromeSeeder` no longer
  recreates them, or a fresh install would rebuild the duplication the migration
  exists to remove.
- **Three page templates** each asked for a WhatsApp number. Changing the shop's
  number meant remembering three screens, and forgetting one left a live button
  pointing at a dead line. `partials/lead_form` now reads
  `service('brand')->whatsapp`.

`$brand->whatsapp` is a first-class property, not a key inside `$identity`. The
footer, homepage and every lead form need it, and reaching into an array for
something that widely used is how one caller ends up reading a key nobody set.

**A second field for the same fact is worse than no field.** It looks editable,
saves without complaint, and does nothing.

### The corporate journey

**The page sets the mode.** Landing on `/corporate` turns the switch on; the
homepage turns it off; and an OCCASION sets it from its own `audience` — opening
"Employee Appreciation" while the switch reads "personalised" shows Add to Cart
on a page of gifts nobody buys one at a time. An occasion tagged `both` changes
nothing, because it belongs to whichever journey the visitor is already in. Arriving somewhere IS a statement about why you are
there, and leaving the switch reading "personalised" over corporate gifting is a
contradiction the visitor has to resolve by hand. `/shop` and the rest stay
neutral, so a deliberate choice survives browsing.

`setJourneyMode()` keeps a `$modeOverride` property. `IncomingRequest` reads its
cookies ONCE at construction, so writing `$_COOKIE` mid-request changes nothing
it can see — the header would render the old mode and only agree on the next
page. It also uses PHP's native `setcookie()`, not CI's helper: the helper
queues onto the Response, which a view rendered mid-request has already passed.

**`products.audience`** — same three values as a collection's, applied inside
`applyFilters()` so EVERY listing gets it. Always on, never an optional filter:
an opt-in one is the one someone forgets to pass, and a corporate-only bulk set
in the ordinary shop is exactly the bug this prevents. Verified 135 pieces
retail vs 150 corporate from the same catalogue, on the shop AND on category
pages.

**Corporate mode replaces the basket with a quote.** A business ordering two
hundred is not adding to a cart and paying, so cards and the product page show
"Bulk enquiry" instead — one modal, posting to the same `enquiry/submit` the page
forms use, with the product's NAME prefixed onto the note so nobody has to open
the catalogue to read the lead.

### The switch goes to the journey's HOME, not back

Returning to the page someone was on cannot work once pages set the mode:
switching to personalised on `/corporate` turned the cookie off and the
destination turned it straight back on, so the switch looked broken. Corporate
on goes to `/corporate`; corporate off goes to the homepage.

**A destination that undoes the action is worse than no redirect.**

The switch shows no flash either. The control names the active journey and the
destination is unmistakably one or the other — a toast saying so announces
something already visible in two places. Same reasoning removed the corporate
banner strip: it existed to explain why Add to Cart had gone, which the filled
switcher and the "Bulk enquiry" buttons now do.

### The flash partial had a container's padding

`rs-shell pt-6` on the wrapper meant every page carrying a flash gained a band of
empty space with nothing drawn in it — the visible message is a TOAST, and the
element only exists for screen readers and the no-JavaScript fallback. The
padding moved inside `<noscript>`, which is the only case where anything there is
seen.

### The switcher names both sides

A toggle labelled only "Corporate" leaves the other state unnamed, so nobody can
tell what turning it off gives them. Two options, both named, the active one
FILLED rather than merely tinted — the header carries several muted tones
already, and a colour change alone does not read as "you are here".

### A full-bleed band still needs the container

`.rs-split__panel` runs the maroon to the edge and pads to meet the container, so
the words begin exactly where every other section's do — 48px, measured. A band
whose text starts at the viewport edge agrees with nothing else on the page.

### The footer had an orphaned block

Replacing the hardcoded Shop column left its loop body and closing `</ul></nav>`
behind — five columns rendered but the markup was unbalanced, and the browser
recovered by nesting the rest inside a list. **Deleting a block means deleting
its opening AND closing tags**; check tag balance after, not just that the page
still looks right.

### The decimal-class trap, again

`rs_icon('star', 'h-3.5 w-3.5')` renders `class="h-35 w-35"` — `esc()` strips the
dot, Tailwind never generated that class, and the SVG falls back to its intrinsic
size and fills the card. This is written down twice already and I did it twice
more.

Sizing an icon now goes in a NAMED class (`.rs-star`) whose dimensions live in
the stylesheet, where nothing escapes them. Grep before shipping:
`rs_icon([^)]*[0-9]\.[0-9]` should return nothing.

### The gift box builder filters in the browser

Every piece the box allows is already rendered, so search and the category and
occasion filters HIDE rather than fetch — instant, and it does not lose the
reader's place in a half-filled box.

- A group with nothing left showing hides its heading too, or the page fills
  with empty section titles.
- Occasion matching wraps both sides in commas, so occasion 3 never matches 13
  or 30.
- The occasion list is built from what this box actually offers, so it never
  names one with nothing behind it.
- One query for the whole catalogue's occasions, not one per product: a box
  offering eighty pieces would otherwise be eighty round trips before the page
  could render.

`availableProducts()` returns `groups` / `occasions` / `byProduct` now rather
than a bare list — `DiagBuilder` counted the old shape and had to move with it.

### PNG transparency went black on upload

`imagecreatefrompng()` does NOT enable save-alpha. A PNG that needed no resizing
went straight from load to `imagepng()` with the channel discarded, and every
transparent pixel was written as opaque black. Measured: `alpha=0 rgb=0,0,0`
where the source had `alpha=127`.

It only looked right when the picture happened to be wider than the size cap,
because the resize branch set the flags on its own canvas — so the bug appeared
to depend on image size, which is what made it confusing.

Both services now set `imagealphablending(false)` + `imagesavealpha(true)` on the
LOADED image, before any branch. `ImageVariantService::open()` does it at the
single point of opening, so no future path can forget it.

Both also clear their canvas to transparent first: `imagecreatetruecolor()`
starts opaque BLACK, and a one-pixel rounding difference at an edge would leave a
black hairline on an otherwise transparent image.

**Set format flags where the image is OPENED, not where it happens to be
transformed** — the untransformed path is the one nobody tests.

### Accept any image the server can actually decode

A fixed 50-megapixel ceiling refused ordinary artwork — a logo exported at print
resolution passes it easily — while still not protecting a small server, where a
much smaller image can exhaust memory. The number was arbitrary in both
directions.

The guard measures what the image actually NEEDS: roughly `w x h x 4 x 2 x 1.2`
(GD holds a truecolor image at four bytes a pixel, a resize means two alive at
once, plus headroom). It raises `memory_limit` to suit — but NEVER past what the
machine has.

**memory_limit is permission, not reservation.** Raising it to 1.4 GB on a 1 GB
box let GD keep allocating until the kernel killed the process: the request died
with no response, no error page and nothing in the log — ERR_EMPTY_RESPONSE. The
first version of this guard caused exactly that.

`safeCeiling()` reads `MemAvailable` from /proc and allows 60% of it, capped at
1 GB. A limit of `-1` no longer means "nothing can refuse this" — that is
precisely the configuration where an oversized image kills the worker instead of
being turned away.

It refuses only when the limit genuinely cannot be raised, and says so with real
numbers: how many megapixels, how much it needs, how much the server allows.
`memory_limit` of `-1` means nothing here can refuse anything.

**A limit expressed in the wrong unit protects nothing.** The risk was memory, so
the check is in bytes — not in pixels, which only correlates with it.

### One variant rule, in the model

`MediaModel::isVariant()` is the single test, used by the backfill, by
`remember()` and by delete. Three copies would disagree the first time the size
ladder changed — and the first version already did: the backfill's regex required
`-400w.jpg` while `ImageVariantService` writes `-400.jpg`, so every variant
became a library row and one photograph appeared six times.

It also catches the full-size WebP twin: a `.webp` sitting beside a `.jpg` of the
same stem is generated, while a `.webp` that was genuinely uploaded has no such
sibling and stays.

Deleting an entry removes the whole FAMILY — `service('images')->delete()` already
purges variants, so the row and every file go together. Verified: 6 files on
disk, 1 library row, delete leaves 0.

`remember()` stamps the file's own `filemtime`, corrected in a second write
because `$useTimestamps` overwrites anything passed in. A backfill would
otherwise give every row the same instant and "newest first" would order by
nothing.

### The media library screen

`admin/media-library` — browse, search by filename or alt text, filter by folder,
write alt text inline, delete.

- Alt text saves on its own row. Describing a picture is the one thing people
  come here to do; it should not need a second screen.
- Delete removes the FILE and its variants too. A library row pointing at nothing
  is worse than no row, because the picker would still offer it.
- Delete is REFUSED while the picture is in use, naming what uses it. Removing
  one a product is showing leaves a broken image on the storefront and no way to
  tell which product broke.
- Every upload on every screen now registers in the library, wrapped in a catch:
  the file is already written and the screen that asked for it must still work.
- Multi-select with shift-click for a run, and a STICKY bar that appears only
  once something is ticked — a permanent "delete 0 selected" is a control that
  does nothing, and one that looks armed when it is not is worse.
- Bulk delete checks each picture for use independently: one still on a product
  must not stop the other nine, and the reply names what it kept.

### PNG transparency, round two — the PALETTE case

The earlier fix (alpha flags at the point of opening) was right and is still
there. It did not cover PNG-**8**: "Save for Web", and most logo exporters,
write a palette image whose transparency is one index in a tRNS chunk, not an
alpha channel. `imagesavealpha()` on a palette image is a **no-op**, so those
flags protected nothing.

Worse, `imagewebp()` does not *fail* on a palette image — it raises
`Palette image not supported by webp`, which is **FATAL**. Neither the `@` nor
`ImageUploadService`'s `try/catch (Throwable)` can hold that: the request dies
after the ladder is written, with no response, no error page and nothing useful
in the log.

- **`imagepalettetotruecolor()` at the point of opening**, in BOTH
  `ImageUploadService::reencode()` and `ImageVariantService::open()`, before the
  alpha flags are set. Everything downstream then has a real alpha channel to
  carry, whatever was uploaded.
- **`ImageVariantService::webpSafe()`** guards every `imagewebp()` call. After
  the conversion it should always pass; it stays because the cost of being
  wrong is an unrecoverable fatal, not a `false`.
- Verified against the real service code by reflection: a PNG-8 with tRNS and a
  PNG-24 with an alpha channel both come out of `open()` truecolor and round-trip
  through WebP at `alpha=127`.

**A format flag that is a no-op on some inputs is not a fix.** Check what
`imageistruecolor()` says before trusting `imagesavealpha()`.

Files uploaded BEFORE this are already flattened on disk, variants and all —
re-upload them.

### The header logo, and hand-patching the compiled CSS

`.rs-logo--header` now steps 2.65rem → 3.25rem (640px) → 4.25rem (1024px) →
4.75rem (1280px), with `max-width` rising alongside so a wide wordmark is not
cropped by the cap meant to let it breathe. `.rs-head__bar` gains matching
`min-height` at the same two breakpoints, written as **its own rules after the
block** rather than by splitting it open — a property added inside a media query
that opens mid-rule orphans everything after it, silently.

The header anchor is `min-w-0`, not `shrink-0`. It had drifted back to
`shrink-0`, which is exactly the thing that pushed the navigation off the page
last time; a taller logo makes that more likely, not less.

**`node_modules` is not installed, so Tailwind could not rebuild.** Both
`resources/css/app.css` (the source) and `public/assets/css/app.css` (the
compiled, committed output) were edited. Brace balance on the minified file was
checked after. Run `npm install && npm run build` at the next opportunity and
confirm the two still agree — a hand-patched build artefact is correct only
until someone rebuilds.

### The Candle Stand Collection

`CandleStandSeeder` — 14 pieces, generated from the shop's own
"Candle Stand Description.xlsx" rather than retyped, so the prose on the site is
verbatim what was written.

- **Additive and idempotent on SKU**, unlike `ProductCatalogueSeeder`, which
  truncates. It therefore has to run AFTER that seeder in `DatabaseSeeder`, or
  these fourteen are wiped by it. Re-running is how a revised description
  reaches the site.
- **The workbook has no prices.** Every piece seeds at one obvious
  `PLACEHOLDER_PRICE` and carries `eyebrow_label = "Price to confirm"`, so an
  unpriced product is visible in the listing rather than shippable by accident.
  A plausible-looking invented spread would have been far more dangerous than a
  figure that is visibly a placeholder.
- Sections map onto real columns: Overview + Key Features become the HTML
  `description`, Specifications become `composition`, Care Instructions become
  `care_note`, and the Finish line becomes `material` so the Material facet has
  something to filter on.
- The seeder writes through the query builder, which **bypasses
  `ProductModel::sanitiseDescription()`**. The markup is therefore built from a
  fixed tag set with every variable part escaped — nothing in it comes from a
  request. Never widen that to accept arbitrary HTML.
- `alt_text` is written on every image. The column existed from the start and
  the product form rendered no field for years, so earlier images shipped silent
  to a screen reader.
- Photographs were imported to `uploads/products/2026/09/{slug}.jpg` with the
  full ladder beside them, produced by the same arithmetic the services use. A
  missing file is **reported**, not silently linked: a `product_images` row
  pointing at nothing renders a broken image and the media picker still offers
  it.

### "For Customisation, connect us on WhatsApp"

On the product page, under the CTA block, reading
`service('brand')->whatsapp` — the same Shop identity key the footer and every
lead form use. Three page templates once each asked for their own number, and
forgetting one left a live button pointing at a dead line.

Hidden entirely when no number is set: "please connect us on WhatsApp" with
nothing to click is worse than saying nothing. The stored value is digits with
the country code; every reader strips non-digits, so `wa.me` links are built
identically wherever they appear.

### Brochures: one file, many pages, and a lead for every download

`brochures` (the file) + `brochure_targets` (which page offers it) +
`brochure_leads` (who asked). Three tables because they answer three
questions; one brochure serves several pages without being uploaded again, and
a page can be re-pointed at a new edition without touching the page.

**THE FILE LIVES UNDER `WRITEPATH`, NEVER `public/`.** A brochure sitting
behind a lead form and ALSO fetchable at its own URL is not gated at all — the
form is decoration from the moment one person shares the direct link, and
every lead after that is lost. `Storefront\Brochure::download()` is the only
way to one. Verified: both `/writable/...` and `/uploads/brochures/...` 404.

**The target is polymorphic, and unique.** A category, a collection/occasion
and a content page have nothing in common, and three nullable foreign keys on
one row would let two be set at once — a state with no correct answer.
`(target_type, target_id)` is UNIQUE, so "one brochure per page" is a property
of the schema rather than of the code that writes it. The price is that the
database cannot enforce the reference, so `BrochureService::forPage()` falls
through to the default when a target points at something deleted or switched
off, rather than erroring.

**Signed in means no form.** Somebody who has already given us their name,
email and phone should not retype them to read a catalogue; the lead is
recorded from their profile, which is also more accurate. A guest submits the
form, which grants THAT brochure for THIS session, once — and `download()`
re-checks the grant rather than trusting the redirect, so a guessed URL still
gets the form. The grant is SPENT on use, or a session would keep producing
downloads with no further lead.

**Leads outlive brochures.** `brochure_id` is `SET NULL` on delete and every
lead carries a `brochure_title` snapshot, the same reasoning as order lines
keeping a name. Deleting the DEFAULT is refused outright — every unassigned
page points at it.

**A PDF cannot be re-encoded the way an image can.** `ImageUploadService`
destroys polyglots by re-encoding; there is no equivalent here, so the upload
check is the whole of the protection: extension AND sniffed MIME AND the
`%PDF-` signature, with a generated filename so nothing from the request
reaches the path. The original name is kept in a column for the download, and
rebuilt from `[A-Za-z0-9 ._-]` before it goes in a `Content-Disposition`
header — quotes and newlines there are a response-splitting surface.

Other details worth keeping: the lead form has a honeypot (off-screen, not
`display:none` — some bots skip a field they can see is hidden) and is
throttled to 12/hour per IP, because a public form that raises staff
notifications needs a ceiling. The CSV export goes through `CsvExporter`, so
the free-text "notes" a stranger typed cannot execute in Excel — verified with
`=cmd|'/c calc'!A1`, which exports prefixed with an apostrophe.

**`partials/breadcrumbs` requires `url` on every crumb.** It reads
`$crumb['url']` directly, so passing only a label is an ErrorException, not a
missing link. Null means "this is where you are". Cost one 500 on the new
brochure form.

**Test-harness note, not an app fault.** PowerShell's `-WebSession` lost the
customer session between calls, which looked exactly like the signed-in path
being broken. The browser kept cookies properly and the same flow downloaded
straight away. When a session-dependent check fails, prove the session is
still alive before changing the code.
### The brochure form as a modal, and which fields are mandatory

**Name and phone are required; email is optional.** For a gifting business
taking bulk enquiries by phone that is the honest pair, and a required field
somebody will not answer truthfully produces worse data than an optional one
they skip. Email is still VALIDATED when given — an address that cannot
receive mail looks like a usable lead in the listing and is not.

`brochure_leads.email` is nullable (migration 000036) and stores NULL, never
`''`: "they did not give us one" is a single fact and only one spelling of it
should be representable. The admin listing shows "no email given" rather than
an empty `mailto:` — a link that opens a blank message to nobody is worse than
saying there is none.

**The modal is an enhancement; the page is the product.** The CTA is a real
link to `/brochure/{id}`, which serves the same form as a full page. The script
intercepts it only when it is running AND the server marked the link as
needing a form.

- **Whether a form is needed is decided on the SERVER**, not by the browser. A
  signed-in customer's CTA carries no `data-brochure` at all, so their click is
  never intercepted and goes straight to the download. Working it out in the
  browser would mean a round trip before the click could do anything, and
  reading it from a cookie would be a second, wronger copy of the decision.
- **ONE modal per page, filled from the CTA that was clicked** — title, id and
  source ride on `data-` attributes. A modal per button would mean duplicate
  ids on any page offering two brochures.
- The form carries no `action` in the markup; the script sets it per brochure.
  A form that could submit to the wrong brochure is worse than one that cannot
  submit without the script that owns it.

**The submit does not fetch the file.** A `fetch` cannot hand somebody a
download, so `Brochure::submitJson()` returns WHERE the file is and the script
navigates there — a response marked `attachment` downloads WITHOUT leaving the
page, so the modal closes and the reader stays exactly where they were.
Verified: after a successful submit the tab was still on `/shop`.

`RULES` and `MESSAGES` are constants shared by the page and the JSON endpoint.
Two copies would drift the first time a rule changed, and the modal would go on
accepting what the page refuses. The honeypot and the 12/hour throttle are
shared the same way, and the JSON reply answers a trapped bot exactly like a
success while recording nothing.

**Every JSON reply carries `csrf_hash()`** and the script writes it back into
every `input[name^="csrf"]` on the page. `security.regenerate` spends a token
per validated POST, so without that a second attempt in the same modal is
rejected as a forgery. Verified across six consecutive posts.

Measured, guest, all through the real endpoint:

```
name + phone only (no email)   ok, download issued
no name                        refused: Please tell us your name.
no phone                       refused: Please give us a phone number.
email given but malformed      refused: That does not look like an email address.
email given and valid          ok, download issued
honeypot filled                answered like a success, 0 leads recorded
```
### "Choose a PDF to upload" for a PDF that was chosen

Field report: a 5.4 MB PDF, a genuine one (`%PDF-`, `application/pdf`),
answered with "Problem. Choose a PDF to upload."

**An upload php.ini refuses arrives as an ERROR CODE, not as a file.** PHP
discards it before the application sees a byte, and `UploadedFile::isValid()`
is then false. `Admin\Brochures::save()` had one branch for "no valid file" and
told the person to choose one — which they had. The only code that means
nothing was picked is `UPLOAD_ERR_NO_FILE`; every other code is a real failure
with a real cause, and `getErrorString()` already names it.

The two settings are not interchangeable:

- **`upload_max_filesize` too small** — the rest of the request is intact, so
  CSRF passes and the file alone carries `UPLOAD_ERR_INI_SIZE`. This is the one
  that produced the report.
- **`post_max_size` too small** — the WHOLE body is discarded, so `$_POST` is
  empty too and the failure surfaces as a CSRF rejection, sending the diagnosis
  somewhere else entirely. `post_max_size` must be the larger of the two.

**A form must advertise the EFFECTIVE limit.** `effectiveMaxBytes()` is the
minimum of our own 25 MB and both ini values, so the upload panel says 2 MB on
a 2 MB server and prints a line naming both directives instead of promising
something the server will refuse. A form that lies reads as a bug in the
application.

`php spark rasmein:diag-brochures` checks all of it — the limits, that the
storage directory exists and is writable, that no copy of the brochures sits
under `public/` (the gate is only real while that is true), that every stored
row still has its file, that one brochure is the default, and that each upload
error code produces a message rather than "no file". Verified by running it at
`-d upload_max_filesize=2M` and at `post_max_size` below `upload_max_filesize`:
both fail loudly, with what to change.

**`getErrorString()` needs the framework booted** — it calls `lang()`, so it
fatals in a bare script with only the Composer autoloader. That is why this
check lives in a spark command rather than a scratch file.
### The mobile filter sheet was transparent, and unreachable without a script

Two faults, both invisible on a desktop screen.

**It opened fully transparent.** `.rs-filtercol` is given a surface inside
`@media (max-width: 1023px)`, and a LATER rule in the same layer said
`.rs-filtercol { background: transparent; }` for the desktop column. Both are
single-class selectors and a media query adds no specificity, so the later one
won wherever it applied: the sheet slid open over the product grid with the
page showing straight through it. Measured `rgba(0, 0, 0, 0)`.

The desktop rule is now wrapped in `@media (min-width: 1024px)` so the two
cannot reach each other. `!important` would have worked and would only have
moved the argument somewhere else. **This is the third time two same-specificity
rules in one layer have collided** — see also `.rs-loop--gridup .rs-loop__nav`.

**There was no scrim.** Nothing dimmed the page, and the only way out was a
close button most people never look for. `.rs-filterscrim` is a real element,
not a pseudo-element, because it has to be clickable; it sits before the panel
in the markup so the stacking works without a z-index fight. It is unhidden
one frame BEFORE the opacity class, or the transition has nothing to run from,
and re-hidden only after the fade so it stops intercepting taps.

**The sheet is now ARMED by the script, like `.rs-reveal`.** It is
`position: fixed` and translated off-screen, and only JavaScript can ever add
`is-open` — so with the script blocked the entire filter column sat off the
left edge of every phone, the Filter button did nothing, and the filters were
silently unreachable. That flatly contradicts "the filter form is a plain GET
so it works without JavaScript", which is true on desktop and was not on a
phone. `.rs-sheet-ready` on the root element is what turns the sheet ON;
unarmed, the column renders in the flow above the grid, which is a perfectly
good small-screen layout. The Filter button and the in-panel close button are
hidden until armed too — a dead control is worse than no control.

Also added while in there: focus moves to the close button on open and back to
the Filter button on close (the keyboard used to stay on the page underneath),
Escape only fires when the sheet is actually open, and growing the viewport
past the breakpoint closes it — otherwise the body scroll lock and the scrim
both survived into a desktop layout, leaving a page that could not be scrolled
with nothing visible holding it.
### Per-facet reset, and two alignment bugs in the price slider

**The reset control is an ANCHOR, not a button.** The sidebar is a GET form, so
"clear this one" is just another URL: it works with the script blocked, can be
middle-clicked, and needs no special case in the auto-submit handler.
`Shop::withResetLinks()` attaches a `reset_url` only where that facet's
parameters are actually in the query string, so it is never an armed-looking
control with nothing to undo.

`Shop::withoutFilter(array $keys, ?string $value)` is now the single URL
builder. The removal chips used to own a private closure doing the same job;
two copies would drift, and the re-indexing (`band[2]=4` versus `band[]=4`)
is exactly the detail one copy would lose.

**A link facet gets no reset.** Its selection is the page you are on, so
"reset" would mean navigating somewhere else — that is the breadcrumb's job,
and an icon that silently moves you to another page is worse than no icon.
A facet dropped for having fewer than two options gets none either, because
there is no heading to hang it on; the chip still clears it, which is why that
path must keep working.

**`rs_icon('rotate-ccw')` was added for this.** A cross beside a filter heading
reads as "close this section", which is what the chevron already does.

#### The price slider: the track and the thumbs were positioned by unrelated numbers

Reported as "price range UI issue". Measured, not guessed:

- **Vertical, 10.3px out.** The inputs were `top: 0.55rem` while the wrapper
  had `padding-top: 1.1rem`. Absolute positioning resolves against the PADDING
  box, so the padding moved the track and not the inputs — the line ran under
  the circles instead of through them. The track is now a rail the thumbs and
  the line both centre inside, and the wrapper uses MARGIN, because padding
  would reintroduce exactly this.
- **Horizontal.** A native range thumb travels between half a thumb from each
  end, never the full width, but the fill was a percentage of the whole track —
  so at the extremes it ran past the handles. Insetting the track by half a
  thumb makes it span the thumb's exact travel, and the existing percentages
  then land under the handles with no arithmetic in the script.
- **`height: 0` on the inputs** gave the thumb no vertical hit area: fiddly
  with a mouse, near-ungrabbable on touch. It is the rail height now.
- **The lower handle could get stuck.** The two inputs overlap and the later
  one wins every click, so dragging the lower thumb to the top end buried it
  permanently. It is lifted above once it passes 90%.
- **A full-width range now drops its parameters.** Clearing the price used to
  leave `?min_price=1400&max_price=11400` — right results, but the chip and the
  reset icon stayed and a copied link still looked filtered.

Verified in the browser: vertical offset 0 (was 10.3), fill edges within 0.7px
of the thumb centres at both extremes, the buried thumb grabbable again, and
with JavaScript off each reset URL lands on exactly the right state.

**`apply()` refreshes each facet's HEADING after a swap.** It deliberately
never replaces the sidebar — that would collapse every accordion — but leaving
the headings alone left a reset icon and an "applied" dot on a facet that was
no longer filtering. `data-facet-key` on each `<details>` is what lets it find
the matching heading in the fetched document.

**A blanket regex over the stylesheet hit an unrelated rule.** Replacing
`width: 0.9rem; height: 0.9rem` to tokenise the slider thumb also rewrote
`.rs-sortwrap__icon`, pointing the sort chevron at a variable that does not
exist in its scope. Nothing errors — the icon just sizes to `auto`. Grep for
the new token afterwards and check every hit is in the block you meant.
### The Occasion facet was the one that never went through base()

"Facets are scoped to the page" was true of every facet except this one.
`FacetService::occasions()` queried `collections` on its own, so its counts
were SHOP-WIDE: inside Candle Holders it offered occasions whose gifts were
nowhere on that page, and "Diwali 50" could sit beside a listing showing six.
It now counts from `base()` like the rest, with INNER joins so an occasion with
nothing on this page is absent rather than present with a zero.

Measured after the fix — the same facet, three pages:

```
/diwali-gift-hampers  Festivals 50, Festive Corporate Gifting 50, Diwali 50
/candle-holders       Diwali 2026 2, Client Gifting 1
/collection/diwali    Festivals 50, Festive Corporate Gifting 50, Diwali 50
```

**The facet was invisible before any of this, and not because of a bug.** It
drops itself below two options, and no product was tagged into any occasion, so
it had none. A filter that is missing because the DATA is empty looks exactly
like a filter that is missing because it was never built — check what the pivot
table holds before changing the facet code.

`DiwaliHamperSeeder` tags all fifty into `diwali`, `festivals` and
`festive-corporate-gifting` through `CollectionModel::syncProductOccasions()`,
which replaces occasion links and leaves collection links alone — so a rerun is
idempotent (150 pivot rows, before and after). It CREATES only `diwali`; the
other two belong to `OccasionSeeder` and are looked up, because a second seeder
inventing a row another one owns is how two slightly different copies of one
occasion appear.

Diwali is seeded with `audience = both` and NO dates. A dated occasion 404s
outside its window, which would kill the page the morning after Diwali while
the hampers stayed listed everywhere else.
### An image copied into uploads has no size ladder

`ImageUploadService` builds the ladder on the way in, so anything arriving any
OTHER way — copied in by hand, restored from a backup, written by an import
script or a seeder — has none. The failure is SILENT: `rs_picture()` finds no
variants, falls back to the single stored file, the page looks right, and every
phone downloads the full-size photograph.

`php spark rasmein:build-variants [dir]` fills the gaps. It defaults to
`uploads`, skips anything already complete (so it is safe after every deploy),
takes `--dry-run` and `--force`, and refuses a path containing `..`. A width
wider than the source is never counted as missing, because the service refuses
to upscale — without that carve-out every small image rebuilds on every run.

**Normalise BOTH sides before stripping a root path.** The first version did
`str_replace(['\\', $root], ['/', ''], $full)`, which replaces the separators
first and therefore leaves `$root` — still carrying backslashes on Windows —
matching nothing. Every path came out as
`uploads/C:/xampp/.../uploads/x.jpg`, nothing resolved, and all 64 files were
reported as "a file GD cannot decode". The error message sent the diagnosis at
the images rather than at the path that named them.
### The Diwali Hamper Collection — reading a catalogue with no text in it

`DiwaliHamperSeeder` — 50 hampers, DK01 to DK50, from
"Diwali catalogue 2026 New.pdf".

**The PDF has no text layer at all.** 373 MB, 51 pages, and every page is a
single flattened CMYK JPEG — `/ProcSet [/PDF /ImageC]`, one XObject, no fonts.
`pdftotext` and the Read tool's extraction both return nothing, and the file is
over the 100 MB extraction ceiling anyway. The pages were dumped by scanning
the object table for `/Subtype /Image` + `/DCTDecode` and writing each stream
out whole (a DCTDecode stream IS a JPEG, so no decoding is needed), then read
visually.

- **Adobe CMYK JPEGs are stored INVERTED, and GD cannot fix them.** GD decodes
  a CMYK JPEG without complaining and returns what is effectively a negative —
  the first read was a black page with dark-on-dark text, which looks like a
  broken catalogue rather than a colour-space problem.
  `imagefilter($im, IMG_FILTER_NEGATE)` makes it *legible* and was used to
  read the pages, but **it is not the conversion** and must never be used to
  produce artwork: inverting the RGB output is a different operation from
  inverting CMYK and then converting. It shipped 50 product photographs with
  the aubergine as pale lilac, no gold at all, and the lit diyas flattened to
  pink discs — reported from the field as "a contrast issue".
  The order that is correct is invert the CMYK channels FIRST, then convert,
  which needs a decoder that reads CMYK natively. Pillow does; GD does not:
  `ImageChops.invert(im).convert("RGB")` on an `im.mode == 'CMYK'` image.
  Check a known-coloured area — not just a light one — before trusting any
  page rendered from a print PDF.
- **The subtitle was invisible until the levels were stretched.** Near-white
  text on white: `IMG_FILTER_CONTRAST` blew it out completely (wrong
  direction), and a level stretch mapping 215-255 onto 0-255 read it cleanly.
  It turned out to be the same sentence on all fifty pages, which is why the
  short description names the contents instead.

**The prices are real**, unlike the candle stands — the catalogue prints an MRP
on every page, so there is no placeholder and no "Price to confirm" eyebrow.
1,552 to 11,344 rupees.

**Corrected misspellings, deliberately.** The artwork carries "Fragnance"
(about 12 pages), "Organsor", "Noise Grand 3les" and "3s", and
"Notty NutsCashew". Those are normalised. Brand spellings that merely look odd
— "Offikraft", "Jower Puffs", "Drippin", "chocosins", "Diwali spl box" — are
verbatim. A genuine typo repeated across forty product pages reads as
carelessness; a brand name someone chose does not.

**A hamper is not a gift-box component.** `is_giftbox_eligible = 0` — putting a
finished hamper inside a build-your-own box is a box in a box. The contents
list goes in `composition`, one line per item, because for a hamper what is
inside IS the specification.

**Photographs were cut from the catalogue pages.** The left 60.5% of each page
is the product tile — the arch plus the shot — so the left 57.8% is extracted
at 1800px with Lanczos and no chroma subsampling (gold on aubergine is exactly
what subsampling smears) and stored as
`uploads/products/2026/09/diwali-gift-hamper-dk01.jpg`. The size ladder was
then built by `service('imageVariants')->build()`, the same service every
upload goes through, rather than by reimplementing its arithmetic: 12 files per
product, 600 in all. **Seeding an image without its ladder is a silent quality
loss** — `rs_picture()` falls back to the single file and says nothing.

The seeder is additive and idempotent on SKU, so it must run AFTER
`ProductCatalogueSeeder` in `DatabaseSeeder` or that seeder's truncate takes
all fifty with it. Same rule as `CandleStandSeeder`, immediately above it.
### The product card's image fallback was the PLACEHOLDER

`partials/product_card` read:

```php
// Fall back to the single primary image when a caller has not batched them.
$shots = $images ?? [];
if ($shots === []) { $shots = [['path' => null, ...]]; }
```

The comment describes the right behaviour; the code hardcodes `null`, which is
the placeholder. So every card drawn by a caller that does not pre-load images
showed a blank frame — the homepage best-sellers rail, new arrivals and the
gift-box row — while the same product's photograph appeared correctly on its own
page.

The listing page batches through `ProductModel::imagesFor()` and so looked fine,
which is exactly what made this read as a homepage bug rather than a card one.
`primary_image` was there the whole time: `withPrimaryImage()` selects it and
every one of those queries goes through it.

**A comment that describes the intent is not the intent.** When a fallback
renders "nothing" rather than erroring, nothing anywhere says it fired.

### A Tailwind theme token can never follow a runtime setting

`.rs-btn--primary` was `background: var(--color-mulberry)`. That token is
compiled into the stylesheet at BUILD time, so Add to cart stayed `#5E1F3D`
whatever Admin → Appearance said — while `.rs-btn--gold` (Buy now), already on
`var(--rs-accent)`, followed it correctly. The two buttons sat side by side
disagreeing about which palette the shop was using.

`--rs-primary` / `--rs-deep` / `--rs-accent` are emitted into the page head by
`DesignService::cssVariables()` on every request. Anything that must follow a
setting reads those, with the Tailwind token as the FALLBACK so a page rendered
without the design block still looks right:

```css
background: var(--rs-primary, var(--color-mulberry));
```

Now dynamic: `.rs-btn--primary` (+ hover, on `--rs-deep` rather than a
colour-mix, so no `@supports` guard is needed), `.rs-btn--outline:hover`,
`.rs-badge--buy`, `.rs-prose a`, `.rs-facet__row input` accent, and input focus.
Admin chrome — the sidebar, sparklines, the avatar, the Quill toolbar — stays on
the fixed token deliberately: the panel is not the shop.

**Grep rule:** a `var(--color-*)` on anything a customer sees is a candidate
bug. Ask whether the shop can change it in Appearance; if so it must be
`var(--rs-*)`.

### Share uses the SYSTEM sheet, with the links as the fallback

`navigator.share` opens the Windows share flyout and the iOS/Android sheets,
which reach every app the person actually has — no list of links can match that.

Three things it is not safe to assume, and all three are handled:

- **That it exists.** Absent on most desktop Firefox, present but inert in some
  webviews.
- **That it works.** It needs a secure context; over plain http the property is
  there and the call rejects, so `window.isSecureContext` is required too.
- **That `canShare()` agrees.** Some builds expose `share()` and refuse a
  payload carrying a url. Where `canShare` exists it is asked first.

So the SERVER renders the explicit links (WhatsApp, Pinterest, Email, Copy
link), and the script hides them only once all three checks pass — the same rule
as the rail arrows. Rendering the button first and hiding the links up front
would strand anyone whose browser has no sheet.

**An `AbortError` is not a failure.** Dismissing the sheet rejects the promise;
that is the person saying no, and nothing should change. Only a real rejection
falls back, and it falls back by REVEALING the links and hiding the button — a
control that swallows a tap is worse than either.

### Icons beside text, and the layer-order trap

The WhatsApp customisation line and every share control now carry an SVG next to
their words rather than a bare glyph. On a page of buttons an unlabelled icon is
the one thing nobody clicks, and the whole sentence being the link makes the tap
target the words people are already reading.

The WhatsApp mark keeps its own green (`#25D366`), not the shop palette: it
identifies an app, and a recoloured one stops being recognisable at 20px.

**Icons are sized from a wrapper class in the stylesheet, and `rs_icon()` is
passed an EMPTY class.** `rs_icon()` defaults to `'h-4 w-4'`, and in Tailwind v4
the `utilities` layer is emitted AFTER `components` — so a utility beats a
component rule regardless of specificity, and `.rs-sharelink__icon svg { width:
100% }` would have lost to `h-4`. This is the decimal-class trap's cousin: same
symptom (icon renders at the wrong size), different cause (layer order, not
`esc()` eating the dot).

Two new icons in the helper: `share` (box with an arrow leaving the top — the OS
glyph, chosen over the three-linked-dots mark, which reads as "social network"
when this opens the system sheet) and `link`.

`resources/css/app.css` and the compiled `public/assets/css/app.css` were both
edited again, `node_modules` still being absent. Brace balance checked; the
appended block guards its two `color-mix()` uses behind `@supports`, matching how
the real build emits them.

### A brand mark is not an interface glyph

The first pass at the WhatsApp and Pinterest icons approximated them with two
or three strokes from the same 1.5px outline family as the interface set. At
20px that is recognisable to nobody — it reads as a speech bubble and a
squiggle.

`rs_icon()` now carries a `$filled` list. A name on it renders
`fill="currentColor" stroke="none"`; everything else keeps the stroked
treatment. The `-mark` suffix (`whatsapp-mark`, `pinterest-mark`,
`facebook-mark`, `x-mark`, `share-ios`) exists so the two families can never be
confused at a call site.

**A filled mark must not also be stroked** — the stroke rides outside the shape
and thickens every counter until the glyph closes up.

Verified by RENDERING them, not by counting paths: `icons.php` in the scratchpad
emits each one, asserts it parses as XML, and writes a page that was then served
and screenshotted. All seven read correctly at 56px. Counting `<path>` elements
would have passed the broken versions too.

### Share is ONE icon button, with a `<details>` fallback

No labels: a circle per destination, each carrying its real mark and an
`aria-label`. The visible control is a single share button in both states.

- **Native sheet** where `navigator.share` passes all three checks (exists,
  secure context, `canShare()` accepts the payload) — the plain `<button>`.
- **Otherwise a `<details>`**, whose `<summary>` IS the same button. This is why
  it is not a scripted popover: the browser opens `<details>` with no
  JavaScript at all, so the one-button design survives a blocked script instead
  of becoming a control that does nothing.
- The menu is absolutely positioned. A `<details>` that reflows the page under
  the reader's cursor when it opens is disorienting.
- The summary's marker needs hiding in BOTH spellings — `::marker` and
  `::-webkit-details-marker` — or Safari draws a triangle beside the glyph.

### The Bespoke journey drift needed a WIDER pass, not a faster one

`.rs-drift` loops by translating the track `-50%`, which is exactly one pass of
a duplicated row. That is only seamless when a pass already fills the viewport.
With a handful of gallery banners it does not: three photographs split across
two rows is two per row, about 380px of content on a 1920px screen — so the row
ran out mid-screen, sat empty, and jumped.

The row is now repeated in PHP until a pass holds at least 16 tiles (a tile is
`clamp(7rem, 16vw, 11rem)` plus gap, so ~190px at most; 16 covers ~3000px), and
THAT is what gets duplicated.

Same failure as the `.rs-loop` slider — "Clone to at least THREE copies,
whatever the widths" — reached from the other direction, because here the
repetition is in PHP and no script measures anything. **Any duplicate-and-
translate loop needs its unit of repetition to exceed the viewport, and that is
a statement about WIDTH, never about how many records exist.**

Only the first appearance of each photograph is announced; the rest are
`aria-hidden`, or a screen reader lists the gallery thirty-two times.

### The product page: sticky image, sticky CTA

**The gallery sticks on desktop only.** Two things that are easy to miss:
`align-self: start` (a grid item stretches to the row height by default, so it
is already as tall as the column beside it and has nothing to stick WITHIN —
the usual reason "sticky doesn't work" in a grid), and a `top` clear of the
sticky header. It turns itself off below 1024px, where the columns stack, and
on viewports under 640px tall, where the image would never move anyway.

**The sticky bar's buttons are the SAME form.** `form="rs-add"` associates a
button with a form it is not inside, so the chosen variant, the chosen quantity
and the CSRF token all travel, and the existing `[data-cart]` handler picks it
up through `e.submitter` exactly as it does for the buttons in the column. A
second form here would be a second copy of that state and would drift the first
time someone changed a variant.

- It renders every state the column can be in — sold out, bulk enquiry, normal.
  A bar that vanishes on some products reads as a fault; one offering Add to
  cart for something sold out is worse.
- `hidden` in the MARKUP, revealed by the observer. With no JavaScript nothing
  can say when the real buttons are on screen, and a bar permanently covering
  the foot of the page would be in the way.
- An IntersectionObserver on the CTA block, not a scroll listener — and the
  `rootMargin` excludes the bar's own height, or it covers the thing it is
  watching and oscillates.
- `transform: translateY(100%)`, not opacity: nothing of it may intercept a tap
  along the bottom edge while it is away. `hidden` is restored on
  `transitionend` so it stops occupying space without the page jumping.
- `padding-bottom: env(safe-area-inset-bottom)` clears the iOS home indicator.

**`[data-variant-price]` is now updated with `querySelectorAll`.** The price
appears in two places and updating only the first left the bar quoting the base
price for a variant that costs more — a stale number beside a live Add to cart.

### Observe the CONTROL, not the block that contains it

The sticky CTA bar watched `[data-cta-anchor]`, which sat on the `space-y-3`
wrapper. That wrapper also holds the variant chips, the specification list, the
gift-box link and the customisation line — so it stayed on screen long after
the buttons had scrolled away, and the bar hid itself exactly when it was
needed. The taller the variant section, the worse it got, which is why it
presented as "variants break the sticky bar".

The anchor is now on each branch's actual buttons: the sold-out span, the bulk
enquiry button, and the two-button grid.

Two rules that follow:

- **An IntersectionObserver anchor is the thing the reader needs to reach**,
  never a convenient parent. A parent that is mostly other content answers a
  different question than the one being asked.
- **Track visibility as shared STATE across all anchors**, not per entry. Only
  one branch renders today, but `if (!entry.isIntersecting) show()` in the
  callback gets it wrong the moment there are two — whichever entry fires last
  wins regardless of the other. A `Set` of what is on screen, then one `apply()`.

`rootMargin`'s bottom value is the BAR'S OWN HEIGHT. Without it the bar slides
up over the buttons, the buttons are then "visible" behind it, and it
oscillates.

### The sticky bar keeps every action on mobile

Buy now was hidden below 640px to save width. That was wrong: it is the whole
point of the bar for someone who has already decided, and a bar offering fewer
actions than the page it replaces is a worse bar. Both buttons now share the
width with `flex: 1 1 0` and a smaller label, and the thumbnail and product
name go instead — the reader has just scrolled past both.

`min-width: 0` plus `text-overflow: ellipsis` matters here: `ctaLabel()` comes
from the settings, and a long one would otherwise force a horizontal scrollbar
across the whole page.

### Share moved onto the photograph

Top right of the image, where catalogues and apps put it, and reachable without
scrolling — which it was not beside the description.

- **The frame is its own element** (`.rs-gallery-main`, `position: relative`,
  NO overflow clip). The picture inside it still needs `overflow: hidden` to
  crop, and a popover inside that would be clipped the instant it opened.
- The menu is anchored `inset-inline-end: 0`. Opening from the left edge of a
  button already at the right of the image runs it off the page.
- The button gets a translucent white ground over the photograph: a transparent
  circle disappears against a pale product shot and shouts against a dark one.
- **The icon is `share-nodes`** — three nodes joined by two lines, the
  Material/Android glyph. The tray-and-arrow (`share-ios`) is an iOS
  convention and means nothing on Android or Windows, and three distinct shapes
  read far better at 18px over a photograph than one outline. Both remain
  defined.

### grid-auto-rows must be FIXED in a height-capped scroller

The thumbnail rail used `grid-auto-flow: row` with no `grid-auto-rows`, so the
rows defaulted to `auto` inside a `max-height` container — and a grid divides
available height between auto rows. Every extra photograph made all of them
shorter, and past about six they collapsed into slivers. Reported as "the
thumbnails shrink when there are more of them", which is exactly what it was.

`grid-auto-rows: 4.5rem` fixes the size, so the column overflows instead, which
is what the scrollbar is for. Same family as "never use 1fr as the MAX in
grid-auto-columns" and "never use minmax(0, X) in a scroller": **a track sized
by available space is wrong wherever the content is meant to overflow.**

The rail is now exactly as tall as the photograph beside it —
`align-self: stretch` fills the grid row (whose height comes from the main
image) and **`min-height: 0` is what actually permits a grid item to be smaller
than its content**, and therefore to scroll at all. Without it the rail grows
to fit every thumbnail and `overflow-y` never engages.

`.rs-thumb` drops its `aspect-ratio` above 640px and fills the row instead: a
square derived from the 5.5rem column would overflow a 4.5rem row and overlap
its neighbours. The scrollbar is visible and in the shop's colour — a hidden
scrollbar on a column that scrolls is a scroller nobody discovers.

### The admin master switch was reading the ADMIN'S OWN cookie

Field report: the Buy / Enquire switch could not be changed at all.

`SettingsService::journeyMode()` answers "what should THIS VISITOR be shown",
and it consults the `rs_mode` cookie first — that is the whole point of the
per-visitor corporate switch. `Admin\Settings::switchJourney()` used it for the
master switch:

```php
$previous = $this->settings->journeyMode();
if ($previous === $mode) return ...'Already set to that.';
```

So it compared the requested store setting against the administrator's own
browsing preference. Three consequences, all silent:

- Switching to whichever mode the cookie happened to hold was answered with
  **"Already set to that." and nothing was written.** The switch looked
  completely stuck — which is exactly how it was reported.
- The dropdown opened on the wrong current mode, and the admin shell's journey
  indicator described the admin's browser rather than the shop.
- When it did write, the audit entry recorded the wrong `from`.

**Merely opening `/corporate` sets that cookie** (pages set the mode — see "The
corporate journey"), so almost any administrator who had looked at their own
storefront was in this state.

`storedJourneyMode()` is the fix: it reads the setting and is blind to both the
cookie and `setJourneyMode()`. The admin panel uses it in all three places; the
storefront keeps `journeyMode()`, which is correct there.

**Two questions, two methods.** "What should this visitor see" and "what has
the shop decided" are different, and one function answering both means the
wrong caller gets a plausible answer rather than an error. Any new caller has
to choose deliberately: a storefront render wants `journeyMode()`, anything
administrative wants `storedJourneyMode()`.

`switchJourney()` also calls `forgetJourneyChoice()`, clearing the acting
admin's own cookie — otherwise they set the store to Enquire, open the
storefront, still see Buy, and reasonably conclude it did not work. It affects
only the browser that performed the action.

Verified: the storefront switcher writes `rs_mode` and flips the whole site in
both directions (browser), and `set('journey_mode', …)` persists and reads back
despite `is_locked = 1` (the lock only excludes a row from the bulk form). The
admin screen itself was not exercised end to end — that needs a login.

### Order-vs-enquiry and retail-vs-corporate are DIFFERENT QUESTIONS

They had been collapsed into one value, and every consequence of that was a
bug. The distinction, now enforced:

| | what it means | who decides | read it with |
|---|---|---|---|
| `settings.journey_mode` | does the shop take money online at all | the admin, shop-wide | `storedJourneyMode()` |
| `rs_mode` cookie | is this visitor buying for a business | the visitor, per browser | `isCorporate()` |
| the resolved answer | may THIS person pay for THIS item | both, plus the product's pin | `journeyMode()` / `rs_is_enquire_mode($saleMode)` |

**The shop's setting outranks the visitor's.** A cookie may only escalate
buy → enquire; it can never pull someone out of a shop-wide enquiry mode,
because that is not the visitor's decision to make.

**Why the master switch did nothing.** `journeyMode()` consulted the cookie
first, unconditionally — and `Home::index()` stamps `rs_mode=buy_now` on every
homepage visit. So every visitor picked up a buy_now cookie on the way in, that
cookie outranked the setting, and turning the shop to Enquire changed no page
for anybody. Combined with the admin guard reading the same method (above), the
switch was unreachable from both ends at once.

Three further conflations fixed at the same time:

- **`products.audience` filtered on `journeyMode()`.** Putting the shop into
  enquiry mode silently swapped the whole catalogue to the corporate audience
  and hid every retail-only piece from every visitor. Now `isCorporate()`.
- **The header switcher tested `rs_is_enquire_mode()`**, so a shop that simply
  does not take card payments showed "Corporate Gifts" as the active journey to
  every visitor. Now `isCorporate()`.
- **The bulk-quote modal** replaced the basket on cards and product pages
  whenever the answer was "enquire". A retail visitor to an enquiry-mode shop
  wants the ordinary enquiry list, not a corporate quote form. The modal is
  `isCorporate()` only; everything else keeps the normal form and lets
  `ctaLabel()` supply the wording.

**`rs_is_enquire_mode()` was declared with no parameters** while three call
sites passed `$product->sale_mode` — and PHP accepts extra arguments to a
userland function in silence. So a product pinned to `enquire_now` was tested
against the site mode instead of its own and offered "Add to cart" on a shop
taking orders. Every piece over ₹5,000 in the seeded catalogue is pinned that
way, so this was not an edge case. It now takes an optional item mode and
resolves it.

"Buy now" skips the basket, so it is no longer rendered at all when the
resolved mode is enquire — in the column or in the sticky bar. A bar must never
offer an action the page itself does not.

Verified across all eight combinations by fetching the real pages:

```
STORE = buy_now      no cookie   -> Add to cart + Buy now
                     buy cookie  -> Add to cart + Buy now
                     corp cookie -> Request a bulk quote
                     pinned item -> Add to enquiry   (no Buy now)
STORE = enquire_now  no cookie   -> Add to enquiry   (no Buy now)
                     buy cookie  -> Add to enquiry   <- the setting now wins
                     corp cookie -> Request a bulk quote
                     pinned item -> Add to enquiry
```

`PricingService::resolveJourney()` reads `isEnquireMode()`, which goes through
`journeyMode()`, so checkout writes a genuine enquiry — the money path follows
the switch without a separate change.

**A settings change made with raw SQL is invisible for an hour.**
`SettingsService` caches the whole table (`rasmein_settings`, 3600s) and only
`set()` busts it. The first run of the test above showed the store setting
having no effect at all, and that was the harness, not the app. Clear
`writable/cache` after any direct UPDATE.

### node_modules IS installable — build, do not hand-patch

Earlier turns hand-edited `public/assets/css/app.css` because `node_modules`
was absent. `npm install` works here (44 packages, ~16s) and `npm run build`
takes under half a second. **Always build.** The compiled file went from 157 KB
of accumulated hand-patching to 108 KB from source, and everything the
hand-patches had added survived because it was all in the source too.

Hand-patching also cannot introduce a new `@theme` token, which is what this
change needed — so the first attempt at fluid type produced source edits that
were simply invisible.

### The type scale pulled apart on large screens

Measured at 1920x1080: an `h1` at **84px** beside a **9px** product eyebrow and
a **10px** section kicker. The display sizes were fluid (`clamp()` + `vw`) and
everything small was a fixed `rem`, so the gap widened with every extra pixel
of viewport. That is the whole of "some text is too small".

Four tokens in `@theme`, each solved for its endpoints (min at a 360px phone,
max at 1920) rather than eyeballed:

```
--rs-t-micro   11 -> 13px     eyebrows, flags, footer heads, arrow links
--rs-t-kicker  11 -> 14px     section kickers
--rs-t-nav     13 -> 15px     header navigation
--rs-t-action  14 -> 15px     buttons
--text-eyebrow 11 -> 13px
```

**Tailwind's own `--text-xs` and `--text-sm` are redefined on the same basis**,
because the views use them everywhere and a component-only fix leaves half the
page behind. The 94 arbitrary `text-[0.625rem]` / `text-[0.5625rem]` /
`text-[0.6875rem]` utilities across 49 views were replaced with `text-xs` —
an arbitrary value is a literal and cannot be redefined from the theme.

**11px is the floor, not 10.** These are uppercase mono at 0.14–0.22em
tracking, which reads smaller than its nominal size.

Verified at 360 / 390 / 768 / 1024 / 1280 / 1366 / 1920 / 2560: nothing under
11px on any page, and `scrollWidth - clientWidth` is 0 at every width.

### Gold on cream was 2.3:1 — everywhere

The brass accent against the cream shell fails every contrast threshold. It was
painting section kickers (14px), the `.rs-display em` headline accent, product
eyebrows, facet headings and the `text-brass` utility. At 14px and 2.3:1 that
is the other half of "hard to read".

- **`--rs-accent-ink`** is the same hue darkened to ~4.8:1 on cream, DERIVED
  from `--rs-accent` with `color-mix` so it still follows Admin → Appearance.
  It lives outside `@theme` because it depends on a variable DesignService
  writes into the page head at runtime, which a build-time token cannot see;
  the `@theme` literal is the no-`color-mix` fallback.
- **Dark bands re-point the variable** rather than restating colours:
  `.rs-hero, .rs-head, .rs-drawer, .rs-feature, .rs-foot, .rs-split__panel,
  .rs-marquee, .rs-sidebar, .rs-on-dark { --rs-accent-ink: var(--rs-accent) }`.
  A new dark section opts in with one declaration and cannot forget the em.
  `.rs-on-dark` is the opt-in for a dark panel with no component class — the
  admin sign-in page needed it, and missing it turned "ADMIN" into near-black
  on maroon.
- **`.rs-gold` replaced the `text-brass` utility on text** (27 occurrences).
  It resolves the variable in the ELEMENT's own context, so it is correct on
  both grounds with one class.
- `.rs-modeswitch__opt.is-on` was white on brass — 2.4:1, making the ACTIVE
  journey the least readable label in the header. Now the same `#2A1208` that
  `.rs-btn--gold` already uses: 7.3:1.

Verified: every gold instance now measures 4.6–5.3:1 on light grounds and
6.4:1 on dark, across home, shop, product, cart, corporate, collections and
the admin sign-in.

**Measuring contrast needs the browser to resolve the colour.** A regex over
`getComputedStyle().color` reports nonsense for `color(srgb …)` and `oklab(…)`
— it read 0.5047 as an R value of 0.5 out of 255 and called a mid gold
near-black, inventing four failures that did not exist and hiding the real
ones. Set the colour on a throwaway element and read back what the browser
computes. Text over a photograph has no computable background at all, so the
audit skips it rather than reporting fiction.

### Two traps while doing this

- **`php -S localhost:8080 public/index.php` never serves the stylesheet.** A
  router script swallows static files, so `app.css` 404s and every page renders
  as unstyled UA defaults — buttons at 13.33px in Arial. The first round of
  "measurements" was of a page with no CSS at all. Use `php spark serve`.
  Check `document.styleSheets` reports a non-zero `cssRules.length` before
  trusting any computed style.
- **PowerShell string replacement mangled a CSS rule into one line** with
  literal `0` where newlines should be, which silently dropped `.rs-gold` from
  the build. The class was in the source and absent from the output, and the
  only symptom was one label inheriting the body colour. After a scripted CSS
  edit, grep the BUILT file for the new selector.

### setJourneyMode() compares the VISITOR'S choice, not the resolved journey

Field report: `/corporate` stopped turning corporate mode on.

Separating the two questions (above) made `journeyMode()` return
`enquire_now` unconditionally once the SHOP's master switch is set to Enquire.
`setJourneyMode()` still guarded with `if ($this->journeyMode() === $mode)`, so
on a shop in enquiry mode the guard matched the moment a visitor reached
`/corporate`: it returned early, no cookie was written, the header kept reading
"Personalised", and nothing carried to the next page.

The guard now compares against the visitor's OWN state — the request cookie or
this request's override — and `null` (no preference expressed yet) is never
equal to a mode, so the first page that states one always writes.

**It only reproduces on a shop whose master switch is Enquire**, which is why a
default install looked fine. Reproduce by setting `journey_mode` to
`enquire_now`, clearing `writable/cache`, and fetching `/corporate` with no
cookie: the response must carry `Set-Cookie: rs_mode=enquire_now` AND render
the switcher with `enquire_now` marked `is-on`.

**A guard that reads a DERIVED value will fire on inputs its caller never set.**
`setJourneyMode()` writes one of the inputs to `journeyMode()`; comparing
against the output folds in the shop setting, which this method has no business
reacting to.

### partials/breadcrumbs renders "Home" itself

It always emits a Home link before the crumbs it is given. Every caller relies
on that and passes only the trail below it. `Pages::collections()` passed a Home
crumb as well, so `/collection` read **"Home / Home / Collections"**.

Two placement faults on the same page, both visible in one screenshot:

- The partial was called AFTER the hero section, so the trail sat mid-page
  between the hero and the first band instead of at the top.
- It was not wrapped in `.rs-shell`, so it ran flush to the viewport edge and
  lined up with nothing. Every other page opens with
  `<div class="rs-shell pt-8">` around it.

**`app/Views/storefront/collection_page.php` is DEAD** — `/collection` renders
`storefront/pages/collections`, and nothing references `collection_page` at
all. It is a near-copy carrying the same breadcrumb bug, so the next person to
fix this will fix the wrong file. Delete it (see "Clean up after moving a
feature between controllers").

### `npm run dev` overwrites the minified build

The `dev` script is `tailwindcss --watch` with no `--minify`, so a watcher left
running rewrites `public/assets/css/app.css` unminified — 161 KB against the
109 KB `npm run build` produces. Same CSS, and harmless in itself, but a
deployed unminified build is a sign a watcher was running when it was cut.
Run `npm run build` before deploying and check the byte count.

### view() merges the PARENT's data — pass the key even when it is empty

Field report: every card in the product page's "Goes well with" row showed the
photograph of the product being *viewed*.

`storefront/product.php` has an `$images` of its own — the gallery of the
current product — and rendered each related card with
`view('partials/product_card', ['product' => $item])`. CodeIgniter merges the
calling view's data into the partial, so `$images ?? []` inside the card
resolved to the PARENT's gallery, for every card. Twelve different pieces, one
set of pictures, all belonging to something else.

Third appearance of this shared-data trap: it also made 23 of 25 icons render
at the wrong size, which is why `rs_icon()` is a helper rather than a partial.

**Name every key a partial reads, even when the value is empty.** A partial's
`?? []` is not a default — it is "whatever the parent happens to have". The
related cards now pass `'images' => $relatedImages[$item->id] ?? []`, which
shadows the parent whatever it holds.

The controller batches those through `ProductModel::imagesFor()` — one query
for the whole row, the same shape the listing uses — so each card also gets its
full set for hover-cycling rather than the single `primary_image` fallback.

Verified by asserting each card's image filename contains that card's own slug:
12 of 12 correct, 0 foreign.

### "Goes well with" is a rail, and carries twelve

Four cards in a static grid was a smaller net than the section deserves: this
row is the page's only route to the rest of the catalogue, and the only
internal linking a crawler finds below the fold. A scroller costs the same
vertical space whatever the count, so it shows twelve.

Same component as the homepage best-sellers row — `.rs-rail rs-rail--cards`
with `data-rail` and the `[data-rail-nav]` arrows — structure copied, not just
the class name.

### The Bespoke journey gallery shows ALL of them

`liveFor('home_gallery', 12)` capped it at twelve. A shop that uploads seventy
photographs wants seventy: the section is a drifting band, so its height does
not grow with the count and the limit was protecting nothing. `findAll(0)`
means all.

**The drift duration now follows the CONTENT.** It was a fixed 48s, so two
photographs crawled and seventy raced — a 42-tile row is ~7,900px, and covering
that in 48s is 165px/s, far too fast to look at. Four seconds per tile, set
inline from PHP because CSS cannot count children:

```
--rs-drift-ms: <tiles * 4>s     →  animation-duration: var(--rs-drift-ms, 48s)
```

Measured: 13 images give rows of 42 and 36 tiles at 84s and 72s — **47 px/s
each** — and 83 images give 168s and 164s, also 47 px/s. Constant speed from a
handful to a hundred.

The `$minPerPass = 16` repetition still applies underneath and simply never
fires once a row holds more than sixteen of its own. Only the first appearance
of each photograph is announced; with 83 images that is 83 announced out of 166
rendered.

**A limit exists to protect something. Name what, or drop it.** Twelve here
protected neither height nor bytes — the tiles are lazy — so it only lost
pictures the shop had deliberately uploaded.

### The one-time code on screen — DEVELOPMENT ONLY

Until SMTP is configured there is no inbox to read, and being sent to the admin
mail queue mid-signup is a poor way to test a sign-in flow. So the code screen
shows the code — but only when `CI_ENVIRONMENT` is not `production`.

**Three independent gates, deliberately.** `OtpService::issue()` returns the
plaintext only outside production; `Auth::rememberDevCode()` checks again
before flashing it; `auth_code.php` checks a third time before drawing. Any one
would do. Three means a single careless edit cannot put a live code on a
customer's screen.

Nothing else is weakened: the code is still hashed in `auth_codes`, still
emailed, still expires in ten minutes, still capped at five guesses. The panel
is deliberately ugly — dashed amber border, "Development only" in the corner —
so that if it ever does appear on a live shop it reads as a fault rather than a
feature.

`issue()` returns `'code'` on EVERY path (null on the rate-limited one), so a
caller never has to guess the shape.

**Verified by flipping the environment**, not by reading the code: with
`CI_ENVIRONMENT = production` the registration flow renders no panel and no
code; back on development the panel appears AND the code shown actually
verifies — proving it is the real one rather than a decorative number.

### The registration duplicate check — a deliberate reversal

`Auth::exists()` answers "does an account already use this email / phone",
debounced from the registration form.

**This reverses "Never confirm whether an email has an account" above.** That
rule was set deliberately and the rest of the auth flow still honours it —
sign-in, reset and the register POST all behave identically whether or not an
account exists. This one endpoint does not, because it was asked for: filling
in three fields before discovering you already have an account is a worse
experience, and that is the shop's trade to make.

**What it costs, plainly: this is an account-enumeration oracle.** The
mitigations raise the cost of abusing it; they do not remove it.

- POST behind the CSRF filter, so it needs a session and a live token rather
  than being scrapeable from a bare URL.
- Throttled to 60/minute per IP. Signing up needs two; hundreds is not signing
  up.
- Answers only for a WELL-FORMED value, so it cannot be walked through
  malformed variants to probe how matching works.
- Yes or no for the exact value asked about — never a name, a masked address
  or anything identifying whose account it is.
- On the limit it returns `ignored`, not an error: a hint that cannot be
  fetched must never stop someone registering.

The submit path is unchanged. A taken address still gets a LOGIN code and
signs the real owner in, so this is a hint on the way in, not a new gate — and
with JavaScript off the form behaves exactly as it did.

**It must return `csrf_hash()`.** `security.regenerate` rotates the token on
every validated POST, and a debounced check spends one per keystroke-pause. The
script writes the fresh hash back into every `input[name^="csrf"]` on the page;
without that the register form is holding a spent token by the time it submits.
Verified: five consecutive checks, all accepted.

500ms debounce, not per keystroke — a check per character is a dozen requests
for one address, and far more use to someone enumerating than to someone
signing up. An in-flight check is aborted when a newer one starts, so a slow
reply cannot overwrite a newer answer.

Verified end to end with a cookie jar: a just-registered email and phone both
report `exists: true`, an unknown address `false`, and malformed values and
unknown field names are ignored.

### One presenter for an order, two screens

`OrderViewService::forCustomer()` assembles everything a customer-facing screen
needs about one order or enquiry, and `partials/order_detail` draws it. The
signed-in account page and the public tracking page both use them.

Built twice, those two WOULD drift — one would get the quoted total and the
other keep showing the basket, and nobody notices until a customer is quoting
two different numbers back at the shop.

**Access control is deliberately NOT in the service.** It presents an order it
is given; deciding who may see it belongs to the caller — the account area
scopes by `customer_id`, Track matches the reference against the contact on the
order. Putting it inside a formatter would hide the decision that matters.

### Which number the customer is shown

An enquiry's `grand_total` is what the basket came to before anyone looked at
it — carriage, quantity pricing and anything bespoke all still unknown. Once an
administrator sets `quoted_value`, THAT is what the shop has offered and it is
what appears, labelled "Your quote".

- `!== null`, never `?:` or `??`. A quote of exactly zero — a sample, a
  goodwill replacement — is a real decision and must not fall back to the
  basket total.
- When a quote exists the **line breakdown is not drawn at all**. Subtotal and
  delivery under a different grand total invites the customer to add them up
  and find they do not reconcile.
- Until then it reads "Indicative total" with a sentence saying what is still
  to be settled.

The admin enquiry screen shows both figures together — the only place the gap
between basket and quote is visible — with "The customer sees this figure."
under the quoted one.

### The pipeline vocabulary is not the customer's

`enquiryStatuses` is the sales pipeline: New, Contacted, Quoted, Won, Lost,
Spam. None of those are things to say to the person who sent the enquiry.
`Config\Rasmein::$enquiryStagesPublic` maps them to Received / In discussion /
Quote ready / Confirmed / Closed, with a sentence each in
`$enquiryStageNotes`.

**`spam` reads exactly like `lost`.** Telling someone we marked their enquiry
as spam is worse than useless, and a shop that got it wrong would have an angry
customer holding the evidence.

`orders.status` stays `pending` for an enquiry's whole life, so the account
list and order page show the STAGE instead — joined in with a LEFT JOIN on
`enquiries`, because most rows are ordinary purchases with nothing to join to.
Before this the customer saw "pending" beside a quote agreed a week ago.

### Guest tracking: reference AND contact

`/track`. Most orders here are placed as a guest, so "sign in to see your
order" is a door most customers have no key to.

- **Both factors, always.** A reference alone reads a name, an address and
  totals, and `RSM-2026-000123` is sequential enough to walk. The second factor
  is the email or phone already on the order — a guest has no account to match
  against.
- **One message** for "no such reference" and "that is not the contact on it".
  Distinguishing them confirms a reference exists, which is what someone
  walking the range wants to learn.
- Throttled to 20 attempts per IP per hour. Being rate limited IS reported,
  unlike a miss — that is not information about anybody's order.
- The result lives at the order's UUID, and the UUID has to be in
  `session('tracked_orders')` to open. The contact details never reach a URL,
  browser history or a Referer header, and the session check is what grants
  access — an unguessable UUID is a property of the generator, not of this code.
- Phone matching is the last ten digits, email is case-insensitive, both with
  `hash_equals`. A customer re-entering their own details must not fail on a
  space.

### `needed_by` cannot be in the past

`App\Validation\RasmeinRules::rs_not_past`, registered in
`Config\Validation::$ruleSets` — a rule is only usable by name once its class
is listed there.

The input carries `min`/`max` so the picker behaves, but that is a hint to a
browser; the rule is the check. Compared as `Y-m-d` strings, so it is a
calendar-day test with no hours-and-minutes edge at either end, and empty
passes because `permit_empty` is what decides optionality.

**No `errors` entry for it.** The rule sets `$error` by reference and CI4
prefers that, so a configured message would never be seen — and a message that
cannot fire is worse than none, because the next person edits it and nothing
happens. Verified: posting a date ten days ago creates no enquiry and returns
to the form with "That date is in the past."

The admin screen flags a `needed_by` that has already passed, in red. A date
that slid by unnoticed is the most useful thing on that panel.

### Checkout prefills from the profile — as DEFAULTS

A signed-in customer's name, email, phone and default shipping address fill the
checkout form. `Checkout::prefill()` reads them scoped by
`session('customer_id')`; a signed-out visitor gets an empty array and the form
behaves exactly as before.

**Order of precedence: `old()` → profile → fallback.** After a validation
failure `old()` holds what the person actually typed, and reinstating their
profile over it would undo a deliberate edit — most often a gift going to
someone else's address. Every field stays editable, and nothing is copied back
into the account: an order sent to a friend must not silently become the
customer's default.

### The account page had a form that 404'd

It carried a Password panel — current, new, confirm — posting to
`account/password`. **That route does not exist**, and `AccountArea::changePassword()`
had no route either: dead code behind a broken control, asking for a password a
passwordless account has never had.

Both are deleted, not wired up — the same reasoning that deleted the password
sign-in actions. An unreachable password path beside a passwordless one invites
someone to reconnect it, and that reopens what one-time codes closed.

In its place the page says how signing in actually works, shows saved addresses
with what they are for, and points guests at `/track`. Enquiries and purchases
are counted and labelled separately: lumping them into "orders placed" counts
things nobody bought.

### The admin enquiry screen was missing half the record

Collected and never drawn: the delivery address, the note at the foot of the
checkout form, the gift message, the GSTIN, the source, the spam score, the
estimated value and every timestamp. **A field captured and never shown is
worse than one never captured** — somebody filled it in and nobody can read it.

All of it is on the page now, plus the totals breakdown, the coupon, and a link
to the order record that holds the payment state and status history.

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
