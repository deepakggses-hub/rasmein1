# Rasmein — Database Schema

35 tables, InnoDB, `utf8mb4_unicode_ci`. Built by 10 grouped migrations in
`app/Database/Migrations/`. Column types come from the shared
`App\Database\Traits\SchemaHelpers` trait so every table agrees on primary-key
type, timestamps and money precision.

```bash
php spark migrate                 # apply
php spark migrate:rollback -b 0   # drop everything (destructive)
```

Verified: all 10 migrations apply cleanly, roll back to zero cleanly, and
re-apply — 35 tables, 42 foreign keys.

---

## Design decisions worth knowing

### 1. One `orders` table, two journeys

A Buy order and an Enquiry are structurally identical: a set of line items plus
a contact and an address. They differ only in what happens *next* — one takes a
payment, the other enters a sales pipeline.

So there is **one `orders` table with a `journey_mode` column**
(`buy_now` | `enquire_now`), and a separate **`enquiries`** table that hangs the
lead-tracking fields (status, owner, follow-up date, quoted value) off an order
row via a unique `order_id`.

The alternative — parallel `enquiry_items` / `enquiry_item_components` tables —
would double the schema and drift apart the first time a field is added to one
side and forgotten on the other.

| Admin screen | Filter |
|---|---|
| Orders | `orders.journey_mode = 'buy_now'` |
| Enquiries | `orders.journey_mode = 'enquire_now'` joined to `enquiries` |

Reporting on "everything a customer asked for, however they asked" is one query.

### 2. The cart lives in the database, not the session

`carts` / `cart_items` / `cart_item_components`. This means a guest cart
survives a browser restart, abandoned carts are reportable, a half-built gift
box can be returned to later — and, critically, **the server owns the line
items**, so nothing about price or capacity depends on what the browser sends
back.

The `*_snapshot` columns exist so the cart page renders without a join storm.
They are display values. Checkout recomputes every figure from
`products` / `gift_boxes` / `coupons` before writing an order.

### 3. Snapshots on order rows are permanent

`order_items.name_snapshot`, `sku_snapshot`, `unit_price` are written once and
never updated. An invoice must still be correct after a product is renamed,
repriced, or deleted. That is also why product foreign keys on order rows are
`ON DELETE SET NULL` rather than `CASCADE`.

### 4. Gift-box capacity is counted in slots

A box has `capacity_slots` compartments. A product consumes
`products.giftbox_slots` per unit (a chocolate bar is 1, a platter is 3). Which
products may go in a box resolves as:

1. Start from the box's allowed categories — or every gift-box-eligible product
   if the box lists none.
2. Add products explicitly allowed for that box.
3. Remove products explicitly excluded for that box. **Exclusion wins.**

`GiftBoxModel::allowedProductIds()` is the single implementation. The builder UI
calls it to render choices; the checkout validator calls it again to verify what
was actually submitted.

### 5. Journey mode: site-wide switch, per-item override

`settings.journey_mode` is the master switch. Each product and each gift box
carries `sale_mode`: `inherit` follows the switch, or it is pinned to
`buy_now` / `enquire_now`. **One mode at a time, never both** — as the
requirement specifies. Resolution is `SettingsService::resolveItemMode()`, read
server-side at checkout so a stale browser tab cannot submit a payment request
while the store is in Enquire mode.

### 6. Payments table exists but is inert

`payments` stores only gateway references and status — never card data. Nothing
writes to it until the payment phase. `orders.idempotency_key` is unique, which
is what guards against a double-click or a retried webhook creating two orders.

---

## Tables by domain

### System
| Table | Purpose |
|---|---|
| `settings` | Runtime admin settings, including the journey switch |
| `migrations` | CI4's own ledger |

### Staff & audit
| Table | Purpose |
|---|---|
| `admin_roles` | Roles with a JSON permission list; `*` = super admin |
| `admin_users` | Staff accounts; `password_hash` only |
| `admin_audit_log` | Append-only who/what/when, with old→new diff |
| `auth_login_attempts` | Durable sign-in audit trail |
| `password_resets` | Hashed, single-use, short-lived tokens |

### Catalogue
| Table | Purpose |
|---|---|
| `categories` | Self-referencing tree |
| `products` | Price, stock, `sale_mode`, `giftbox_slots`; FULLTEXT on name/description |
| `product_images` | One `is_primary` per product |
| `collections` | Curated groupings |
| `collection_products` | Join, ordered |

### Gifting
| Table | Purpose |
|---|---|
| `gift_boxes` | Capacity, base price, theme, tier, `sale_mode` |
| `gift_box_categories` | Allowed categories |
| `gift_box_products` | Allow / exclude list with optional per-box cap |
| `gift_box_pricing_rules` | Flat price, markup, slot discounts, waive-above |

### Customers
| Table | Purpose |
|---|---|
| `customers` | `password_hash` nullable — guest checkout leaves a claimable record |
| `customer_addresses` | Address book with shipping/billing defaults |
| `wishlist_items` | Unique per customer+product |

### Cart
| Table | Purpose |
|---|---|
| `carts` | UUID-addressed, guest or customer |
| `cart_items` | A product line or a gift-box line |
| `cart_item_components` | Contents of a gift-box line |

### Orders
| Table | Purpose |
|---|---|
| `orders` | Both journeys; UUID for public reference; unique idempotency key |
| `order_items` | Snapshotted line items |
| `order_item_components` | Snapshotted gift-box contents |
| `order_status_history` | Every transition, who made it, whether notified |
| `shipments` | Manual dispatch — courier, tracking, timestamps |
| `payments` | Gateway references only. Inert until the payment phase |

### Enquiries
| Table | Purpose |
|---|---|
| `enquiries` | Lead pipeline on top of an order row |
| `enquiry_notes` | Follow-up log, typed (call / email / meeting / quote) |

### Coupons
| Table | Purpose |
|---|---|
| `coupons` | Percent / fixed / free shipping, windows, limits |
| `coupon_restrictions` | Scope to products, categories or boxes |
| `coupon_redemptions` | Ledger that enforces usage limits |

### Content
| Table | Purpose |
|---|---|
| `banners` | Scheduled, positioned |
| `pages` | CMS pages, footer-linkable |
| `notification_log` | Every email/SMS/WhatsApp attempt and its outcome |

---

## Indexing notes

- Every foreign key is indexed (MySQL requires it).
- Composite indexes match how screens actually query:
  `orders(journey_mode, status)`, `products(is_active, is_featured)`,
  `banners(position, is_active, sort_order)`.
- `products` carries a FULLTEXT index on `name`, `short_description`,
  `description` for storefront search. Added as raw SQL, guarded to MySQL only.
- `payments.gateway_payment_id` is unique — MySQL permits repeated NULLs, so
  unpaid rows coexist while a real gateway ID can only ever appear once.
