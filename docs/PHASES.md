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

## Phase 2 — Catalogue, cart & checkout

- Shop index with filtering (category, price band, availability) and sorting
- Category and collection pages, paginated
- Product detail: gallery, description, related products, stock state
- Search backed by the FULLTEXT index
- Server-side cart: add / update quantity / remove, as JSON endpoints
- Coupon application — validated and recomputed server-side
- Checkout: contact, shipping, billing, guest or signed-in
- Order creation inside a transaction, with stock reservation and the
  idempotency key doing its job
- Order confirmation page and email
- **Replaces roadmap routes:** `/shop`, `/product/*`, `/collections*`,
  `/search`, `/cart`, `/checkout`

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
