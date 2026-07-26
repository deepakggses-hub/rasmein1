# Rasmein — Build Plan

## Phase 6 — Notifications & email templates · **complete**

Two things arrived together, because they are the same problem: **the system
now tells people what happened.**

### In-app notification centre

A notifications section in the admin panel, with an unread badge in the sidebar
and a tile on the dashboard. Orders, enquiries and low stock all raise one.

**Notifications are targeted by permission.** An order notification goes to
staff holding `orders.view`, not to everyone — a support account that cannot
open Settings should not be told the journey mode changed. Verified: a narrow
permission reached 1 person where `orders.view` reached 2.

One row per recipient rather than a broadcast row plus a read-state table. The
unread badge is queried on every admin page load, so that path stays a single
indexed count rather than a `NOT EXISTS` join.

### Editable email templates

Eleven templates, all editable from **Admin → Email templates**: subject, body,
and an on/off switch, with a live sandboxed preview and a "send a test to me"
button. Both audiences are covered — customer emails and team alerts.

Rendering is an **allowlist substitution, not a template engine**. Staff edit
the body, so evaluating it would turn "edit the welcome email" into "run
arbitrary PHP". Only tokens a template declares are replaced, and every
substituted value is escaped.

Sending is **queued, never inline** — `php spark rasmein:send-mail` drains it
with five capped retries and exponential backoff, so a slow mail server can
never delay a checkout or roll back an order.

### Housekeeping

`php spark rasmein:housekeeping` raises low-stock alerts (deduplicated to one
per product per day), marks week-old carts abandoned, and prunes carts, expired
tokens, sent mail and read notifications. Cron lines are in `docs/DEPLOYMENT.md`.

### Two bugs found by testing, both real

1. **The allowlist was not an allowlist.** A second substitution pass iterated
   the caller's whole data array, so any key a caller passed was substituted
   whether the template declared it or not. Now it iterates only the fixed
   brand tokens. A test asserts an undeclared token stays unsubstituted.
2. **The plain-text part decoded escaping back into markup.** The HTML body
   correctly stored a customer named `<script>` as `&lt;script&gt;`, but
   `html_entity_decode()` in `toPlainText()` turned it back into `<script>` in
   the text/plain part. Not executable in a conforming client, but the value had
   round-tripped back to markup and anything downstream putting that text in an
   HTML context would inherit it. Found by sending through a real SMTP server
   and reading what arrived — not by inspecting the code.

**Verified: 32 automated checks, plus a real SMTP delivery.** A message with a
hostile customer name arrives with no live tag in either MIME part, the HTML
part showing it escaped as text, the real name intact, and `&` readable.

---

Maps the proposal's six phases onto the actual build. Each phase ends at
something runnable and reviewable, not a half-wired layer.

---

## Phase 1 — Foundation & design system · **complete**

- Environment and security config: `.env.example`, hardened `.gitignore`,
  CSRF by HTTP method, global security-headers filter, admin/customer auth
  filters, audit service with secret redaction
- Full database schema — 35 tables, 10 migrations, verified up/down/up
- Idempotent seeders: settings, roles + first admin, 8 categories,
  26 products, 4 gift boxes with pricing rules, 3 collections, 6 CMS pages
- 14 models, each with explicit allowed fields and validation rules;
  Product / GiftBox / Category entities carrying derived logic
- `SettingsService` — the Buy/Enquire switch, cached per request
- Tailwind 4 design system, compiled and committed
- Storefront layout, header, footer, the Tray, product card
- Working homepage driven entirely by seeded data
- CMS page rendering
- Branded, leak-free error pages (400/403/404/500) + fixed JSON error envelope
- Apache `.htaccess` hardening + nginx reference config
- `php spark rasmein:diag` smoke test

**Deliberately deferred:** payment gateway, per the brief.

---

## Phase 2a — Catalogue & product pages · **complete**

- Shop listing with one template serving four contexts: everything, a category,
  a collection, and a search
- Filters: category, price range, in stock, gift-box eligible — a plain GET form
  that works with JavaScript disabled
- Whitelisted sorting (Featured / Newest / Price / Name). An unrecognised `sort`
  value falls back to Featured rather than reaching ORDER BY
- Pagination that carries active filters across pages, with a sliding page window
- Product detail: gallery with thumbnail switching, price and discount, stock
  state, box-slot cost, related products, per-product Buy/Enquire badge
- Collections index
- Search over the FULLTEXT index, OR'd with a LIKE on SKU and name
- Empty state that offers a way forward instead of apologising
- Roadmap placeholders removed for `/shop`, `/product/*`, `/collections*`, `/search`

**Verified:** 21 catalogue checks (filters, sorting, pagination, price range,
related products) and 23 search inputs including hostile ones. `php spark
rasmein:diag-catalogue` and `rasmein:diag-search`.

**Two bugs this phase caught and fixed:**

- `Pager` has no `hasPrevious()` / `getPrevious()` — those live on
  `PagerRenderer`. The real API is `getPreviousPageURI()` / `getNextPageURI()`,
  which return null rather than throwing. `/shop` 500'd whenever there was more
  than one page.
- **MySQL FULLTEXT boolean mode treats `+ - > < ( ) ~ * " @` as operators, and a
  malformed expression is a hard SQL error.** Searching `tea (loose)` — or
  anything with a bracket — returned a 500. Every token is now reduced to
  letters and digits before it reaches the query.

**Deliberately not pretending to work:** the product page's add-to-cart button is
present but disabled, with a line saying the cart arrives next. Better than a
button that leads nowhere.

---

## Phase 2b — Cart & checkout · **complete**

- Server-side cart: add / update quantity / remove, as JSON endpoints
- Coupon application — validated and recomputed server-side
- Checkout: contact, shipping, billing, guest or signed-in
- Order creation inside a transaction, with stock reservation and the
  idempotency key doing its job
- Order confirmation page and email
- Add-to-cart enabled on the product page — a real form, works without JS
- **Replaced roadmap routes:** `/cart`, `/checkout`, `/enquiry`

New: `CartModel`, `CartItemModel`, `CartItemComponentModel`, `CouponModel`,
`OrderModel`, `OrderItemModel`, `OrderItemComponentModel`,
`OrderStatusHistoryModel`, `EnquiryModel`, plus `CartService`,
`PricingService` and `OrderService`. Migration 011 adds `carts.coupon_code`.

**Verified — 39 automated checks, all passing** (`php spark
rasmein:diag-checkout`), plus a full HTTP walk-through of both journeys.
Highlights:

- Tampering with a cart's `unit_price_snapshot` in the database does not change
  the total — pricing re-reads the products table
- Coupon branches: unknown, expired, below minimum, percent cap, free shipping,
  case-insensitive codes
- Site in Buy mode + one item pinned to `enquire_now` → the whole basket becomes
  an enquiry
- Order: reference format, UUID, snapshots, stock reserved, status history,
  notifications queued, coupon redemption logged, cart emptied
- Same idempotency key submitted twice → one order, second call returns the first
- Sold-out product refused; over-quantity clamped to live stock
- Confirmation URL returns 404 to a stranger even with the correct UUID

**One bug this phase caught:** `CLIRequest` has no `getUserAgent()` — only
`IncomingRequest` does. Three files called it unguarded, so any order, audit
entry or login record written from a cron job or `spark` command would have
crashed. Now behind `rs_user_agent()`.

**Decisions worth knowing:**

- **Mixed baskets become enquiries.** If any line must be quoted, the whole
  order is an enquiry. We cannot take payment for a basket containing something
  with no price yet, and splitting one basket into two orders would be worse for
  the customer and for fulfilment.
- **An enquiry does not reserve stock.** It is a request, not a sale; holding
  inventory for it would starve real orders.
- **An enquiry is not asked for a delivery address.** That is settled when the
  quote is agreed; asking up front loses leads.
- **With no gateway live, a purchase is recorded `unpaid`** and the checkout says
  plainly that we will make contact to arrange payment — rather than implying a
  card was charged.

---

## Phase 3 — Gift-box builder & the dual journey · **complete**

- Box selection: size, theme, price tier
- The builder: the Tray becomes live — pick products, watch slots fill,
  running total, per-box caps enforced
- Step 3: gift message and special-requirement note, both optional
- Step 4: review, then Buy or Enquire
- Editing a built box from the cart
- The switch itself: cart becomes an enquiry list, checkout becomes an
  enquiry form, submissions land as leads with staff notification
- Honeypot + rate limiting on the enquiry form
- Server-side re-validation of capacity, eligibility and price at submit
- **Replaced roadmap routes:** `/gift-boxes`, `/build*`

**Where the builder keeps its state — the decision everything else follows from.**
The box under construction IS a cart line (`cart_items` with
`item_type = 'gift_box'`) and its contents ARE that line's
`cart_item_components`. No draft table, no client-side basket. That buys four
things at once: the server owns the contents so capacity and eligibility are
enforced where it matters rather than in JavaScript; a half-built box survives a
refresh, a dead phone, or a return the next day; "edit this box" from the cart is
not a feature, it is the same URL; and the box needs no conversion step when it
is ordered. The cost — an abandoned box sitting in the cart looking unfinished —
is handled honestly: `PricingService` flags any box below its minimum as
blocking, and the cart says "fill it to continue" rather than letting it reach
checkout.

**The Tray is now live.** It renders one cell per compartment from the actual
contents, so a 2-slot candle visibly occupies two cells and a 3-slot platter
three. Confirmed over HTTP: adding one candle to a 6-slot tray produced exactly
two filled cells.

**Verified — 31 automated checks, all passing** (`php spark
rasmein:diag-builder`), plus a full HTTP walk-through:

- Resuming: starting the same design twice returns the same line, never a duplicate
- Eligibility: a notebook is refused from the Classic Tray, and the offer list is
  asserted equal to the accept list — both come from
  `GiftBoxModel::allowedProductIds()`, so what is shown and what is accepted
  cannot drift
- Capacity: 1-slot and 2-slot items counted correctly; an over-capacity add is
  refused AND leaves the box unchanged; a full box refuses everything
- Personalisation: message truncated to the box's own limit, blank input stored
  as null rather than an empty string, and a field the admin disabled cannot be
  written by a posted value
- Checkout gate: empty box blocks, below-minimum blocks, at-minimum releases
- Pricing: box charge + contents, verified against a hand-computed figure
- IDOR: a line id from another cart resolves to null; `/build/box/999999` is 404

**Copy bug caught in testing:** box names already carry their article, so
"Fill the " + "The Classic Tray" read as "Fill the The Classic Tray". The heading
now lets the name stand alone.

---

## Phase 4a — Admin: auth, dashboard, fulfilment · **complete**

- Sign-in: rate limited per IP+email, one generic failure message, session
  regenerated on success, rehash-on-login, forced password change on first use
- Admin shell with permission-filtered navigation and the store's journey mode
  permanently visible in the sidebar
- Dashboard led by **what needs doing** — orders to confirm, awaiting payment,
  ready to dispatch, new enquiries, overdue follow-ups — then revenue for
  today / 7 days / 30 days, recent activity and low stock
- Orders: filterable list, full detail with snapshots, **status transitions
  through a whitelist**, payment recording, manual dispatch with courier and
  tracking, internal notes
- Enquiries: pipeline list with overdue highlighting, detail with the customer's
  brief and basket, stage/owner/quote/follow-up editing, typed follow-up log
- Settings: grouped editor, with the **Buy/Enquire master switch behind its own
  permission and a typed confirmation**
- Audit log: append-only, filterable, with before/after diffs

**Verified over HTTP:**

- Every admin route 302s to login when anonymous
- Wrong password and unknown email produce the *same* message — no user
  enumeration
- Six wrong attempts → "Too many attempts. Try again in 12 seconds"
- A Support Staff role reaches Orders and Enquiries but is refused Settings and
  Audit, and the nav hides what it cannot reach
- `pending → delivered` is refused ("An order that is pending cannot move to
  delivered"); `pending → confirmed` applies, stamps `confirmed_at`, and writes
  history
- The journey switch does nothing without the typed phrase, then works, and the
  change is audited with before and after

**Deliberate design choices:**

- **Status transitions are a whitelist, not a dropdown.** A terminal status has
  no exits. This stops a stale tab moving a delivered order back to pending and
  makes the history trustworthy.
- **The journey switch has its own permission and a typed confirmation.** It
  changes every product page, cart and checkout at once; a mis-click is
  expensive.
- **Locked settings cannot be written by the bulk form** — they have their own
  guarded endpoints.
- **Unchecked checkboxes are written to 0 explicitly**, because an unchecked box
  posts nothing and would otherwise silently keep its old value.

---

## Phase 4c — Gift-box config, pages editor, coupons · **complete**

- **Gift-box configuration** — capacity, minimum fill, box price, personalisation
  switches, journey pin, and the two things the builder actually runs on:
  *what may go in* (categories, plus per-product allow/exclude with caps) and
  *pricing rules* (flat, waived, markup, slot discounts, with slot and subtotal
  bands). Split into three independent forms so a mistake in the rules cannot
  lose the description someone just wrote.
- **Pages editor** — its blocker cleared by the 4b sanitiser. Content is passed
  raw to the model, which sanitises in `beforeInsert`/`beforeUpdate`, and the
  editor tells the author when markup was stripped.
- **Coupons** — full CRUD with guards on the combinations that silently do
  nothing.

**Verified through the real forms:**

- Restricting a box to one category and excluding one product took its reach
  from 26 giftable products to 3 — and the save reports that number back, so a
  box configured into an empty builder announces itself
- A pricing rule with `min_slots` above `max_slots` is refused ("the slot range
  is back to front") rather than stored as a rule that can never fire
- A coupon over 100%, and one whose window closes before it opens, are both
  refused — and **zero rows were created** by either attempt
- **End-to-end XSS:** a page authored through the editor containing
  `<script>`, `onerror`, `javascript:` and `<iframe>` stores as clean HTML and
  renders on the storefront with **zero occurrences** of any of them, while the
  heading, copy and internal link survive intact

---

## Phase 4d — Staff, customers, reports, banners · **complete**

- **Staff & roles** — accounts, role assignment, admin-set passwords
- **Customers** — assembled from ORDERS, not the accounts table, because most
  gifting customers check out as guests and would otherwise be invisible
- **Reports** — revenue, average order, discounts given, enquiry conversion,
  revenue-by-day, best sellers, revenue by category, pipeline, coupon use
- **CSV export** — orders, enquiries, products, customers
- **Banners** — scheduled promotional slots

**Four invariants enforced in Staff, and each one attacked:**

| Attack | Result |
|---|---|
| Store Manager reaches `/admin/staff` | 302 — lacks `staff.manage` |
| Super admin deactivates own account | Refused; still active |
| Super admin deletes own account | Refused; account present |
| Admin sets a colleague's password | Stored hashed, `must_change_password=1` |

Roles are filtered to those the current administrator wholly holds, so a Store
Manager cannot mint a Super Admin and sign in as them. The last active
super-admin cannot be deleted or disabled.

**CSV formula injection — the interesting one.** A spreadsheet treats a cell
beginning `=` `+` `-` `@` as a FORMULA, so an order placed under the name
`=cmd|'/c calc'!A1` becomes executable when the export is opened in Excel. The
data is the customer's own text and cannot be rejected at the source, so
`CsvExporter` neutralises it at the boundary with a leading apostrophe.
Verified: the database holds `=cmd|'/c calc'!A1`, the CSV holds
`'=cmd|'/c calc'!A1`, and a phone number `+919876500000` is neutralised too.

Banner links are constrained to this site — an admin-set off-site redirect on
the homepage hero is exactly what a stolen staff password would reach for.

---

## Phase 4b — Admin: catalogue & content

- Products and categories CRUD with image upload — real MIME verification, not
  extension; renamed on save; size-capped; no execution in the upload directory
- Gift-box configuration: capacity, allowed categories/products, pricing rules
- Coupons, customers, banners, pages
- Reports and CSV export
- Staff and role management
- **Blocker before the page editor ships:** `pages.content` renders unescaped.
  An allowlist HTML sanitiser must run on save first.

---

## Phase 4 — Admin panel (original outline)

- Sign-in with throttling, generic failure message, forced password change
- Dashboard: sales, orders, enquiries, low stock
- Products and categories, with image upload (validated by real MIME, renamed,
  size-capped, no execution in the upload directory)
- Gift-box configuration: capacity, allowed products, pricing rules
- Orders: status transitions, invoices, manual dispatch and tracking
- Enquiries: pipeline, assignment, follow-up dates, notes
- Coupons, customers, banners, pages
- Reports and CSV export
- Settings, including the journey switch behind its own permission
- Role and staff management; every write audited

---

## Phase 5 — Customer accounts · **complete**

Registration, sign-in, password reset, order history, address book and
wishlist. Guest checkout is untouched — an account stays optional.

**The theme is not leaking who has an account.** A shop's customer list is
commercially and personally sensitive; nobody should be able to discover that an
address shops here by trying it in a form. So:

- sign-in returns one message for every kind of failure, and `password_verify`
  runs even when the address is unknown, so timing does not answer either;
- **registering with an existing address reports success** and emails the real
  owner "someone tried to register", rather than saying "already taken" — which
  would turn the form into an existence oracle;
- password reset gives the identical reply whichever way, and is rate limited
  per IP so it cannot be used to enumerate in bulk or to mail-bomb someone.

**Verified over HTTP:**

| Test | Result |
|---|---|
| Anonymous hits `/account`, `/account/orders`, `/wishlist` | 302 to sign-in |
| Register with an address already in use | Same success message; still **1 row**; notice queued to the owner |
| Reset for a real vs. a fictional address | **Identical** reply; only 1 token issued |
| Reset token storage | SHA-256, 64 chars — the plain token is never persisted |
| Reusing a spent reset link | "That link has expired or been used" |
| Customer B opens customer A's order UUID | **404** |
| Customer B's order list | Does not contain A's order |
| Customer B loads `?edit=` with A's address id | Shows nothing |
| Customer B POSTs A's address id to save **and** delete | Address unchanged, still present |
| Guest fills a cart, then signs in | Cart survives and attaches to the account |

Scoping is the defence: every query in `AccountArea` is filtered by
`session('customer_id')`, and no owner is ever taken from the URL or the form.

---

## Phase 5 — Customer accounts (original outline)

Registration, sign-in, password reset (hashed single-use token), order history,
address book, wishlist. **Replaces:** `/account*`, `/wishlist`

---

## Phase 6 — Payments · deferred

Razorpay order creation, checkout handoff, **server-side signature
verification** before an order is ever marked paid, webhook endpoint with
replay protection, refunds. Nothing is marked paid because a browser reached a
success page.

The schema and settings for this already exist and are inert, so this phase adds
code without reshaping anything.

---

## Then: QA & launch

Cross-device testing, feature tests for checkout / journey switch / coupons /
auth lockout, performance pass, `composer audit`, and the pre-deploy checklist
in `CLAUDE.md` §14.

---

## Roadmap route policy

Any navigation destination whose feature has not shipped points at
`Storefront\Roadmap`, which renders a branded "in build" page naming the phase.
Delete the roadmap line in `app/Config/Routes.php` in the same commit that adds
the real route — that way the list of remaining placeholders is always an
accurate to-do list.
