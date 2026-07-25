# Rasmein — Build Plan

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

## Phase 2b — Cart & checkout

- Server-side cart: add / update quantity / remove, as JSON endpoints
- Coupon application — validated and recomputed server-side
- Checkout: contact, shipping, billing, guest or signed-in
- Order creation inside a transaction, with stock reservation and the
  idempotency key doing its job
- Order confirmation page and email
- Enable the add-to-cart button on the product page
- **Replaces roadmap routes:** `/cart`, `/checkout`

New models: Cart, CartItem, Order, OrderItem, Coupon, plus a `CartService`,
`PricingService` and `OrderService`.

---

## Phase 3 — Gift-box builder & the dual journey

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
- **Replaces roadmap routes:** `/gift-boxes`, `/gift-box/*`, `/build*`,
  `/enquiry`

---

## Phase 4 — Admin panel

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

## Phase 5 — Customer accounts

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
