# Invoice CRM — Feature Reference

Every feature below is included at no cost while the product is in free mode.
There are no usage quotas, no feature gates and no time limits: catalog,
quotations, e-invoicing, automation, stock and the customer portal are all
available to every tenant.

---

## 1. Accounts, roles and platform administration

### Roles

Permissions live in `config/permissions.php` and are granted per role, so a
role can be reshaped in config without touching code. Controllers ask for a
capability (`canDo(Permission::…)`) rather than testing a role name.

| Role                | Summary                                                                                       |
| ------------------- | --------------------------------------------------------------------------------------------- |
| **Super Admin**     | Platform operator with **no tenant**. Manages every tenant, plan and subscription.            |
| **Admin**           | Full control of one business, including settings and staff.                                   |
| **Manager**         | Runs the day to day — catalog, stock, invoices, reports. No settings or staff administration. |
| **Invoice Creator** | Quotes and invoices in, records payments. No deletes, no cost visibility.                     |
| **Viewer**          | Read-only across the board.                                                                   |

Capabilities include `view_dashboard`, `manage_customers`, `manage_catalog`,
`manage_inventory`, `manage_quotations`, `create_invoices`, `edit_invoices`,
`delete_invoices`, `record_payments`, `send_messages`, `view_reports`,
`view_costs`, `manage_settings`, `manage_users`, plus the platform-only
`manage_tenants`, `manage_plans`, `manage_subscriptions`,
`impersonate_tenants` and `view_platform_reports`.

A tenant can never assign the **Super Admin** role — that is the one role
excluded from `UserRole::assignable()`, enforced in the staff screen, the user
API and staff invitations.

### Platform admin panel (`/admin`)

- **Dashboard** — tenant, user, invoice and customer counts, suspended-tenant
  alerts, plan breakdown, newest tenants.
- **Tenants** — search and filter by status or industry; suspend and
  reactivate; per-tenant invoice/billed/outstanding figures.
- **Tenant detail** — business and tax details, staff roster, and subscription
  editing (plan, status, trial end, period end).
- **Users** — every account across all tenants, filtered by role and status;
  enable/disable. Super admins cannot be disabled from here.
- **Plans** — edit price, staff limit, monthly invoice ceiling (blank =
  unlimited) and availability. The free plan cannot be given a price or
  hidden, because every tenant falls back to it.
- **Activity log** — every platform action, filterable: tenant status changes,
  subscription changes, staff access changes, impersonation start/end and plan
  edits.

### Impersonation

A super admin can sign in as a tenant's staff member to reproduce a problem.
The app shows a persistent banner naming the tenant and the user, and offers a
one-click return to the platform panel. Impersonation is refused for suspended
tenants and for tenants with no active staff. Both the start and the end are
recorded in the platform activity log against the super admin, not the
impersonated user.

### Bootstrap

```bash
php artisan app:make-super-admin ops@example.com
```

Creates a tenant-less platform account, or promotes an existing user (detaching
them from their tenant roster while leaving the tenant and its data intact).

---

## 2. Catalog and inventory

The catalog is a top-level section and the working surface quotations are built
from — not a settings screen.

### Products

- Identifiers: name, item code/SKU, barcode, brand, model number, manufacturer,
  country of origin, HSN/SAC code, description.
- Pricing: priced-per basis, selling price, **cost price** (never shown to
  customers), tax-inclusive flag, minimum order quantity, pack size, unit label.
- Specification (industry-aware): metal, purity, size, finish, grade, colour,
  material, thickness, specification, warranty months, net/gross weight,
  length, width and wastage for area-priced trades.
- **Stock**: opt-in per product, with quantity, reorder level and stock unit.
  Made-to-order work simply leaves tracking off.
- **Images**: one product photo, validated and stored on the public disk.
- **Custom attributes**: repeatable key/value pairs for anything the standard
  fields do not cover. Reserved catalog/invoice keys are contractual:
  `warranty_terms` and `care_instructions` stay printable product notes, while
  `huid_number` and `certificate_number` are promoted into the invoice line's
  dedicated HUID/Cert fields instead of printing twice.

### Field registry

`app/Support/CatalogField.php` is the single registry of every product
attribute. It drives the form, the validation rules, the CSV template and the
importer together, so a field cannot exist in one and be missing from another.
Fields are industry-aware by default; a per-tenant **"Show every catalog
field"** setting reveals the full registry.

### Stock movements

`stock_quantity` holds the current on-hand figure for fast reads, and every
change is appended to an `inventory_movements` ledger (`in`, `out`,
`adjustment`) with the balance afterwards, reason and note. Stock is never
edited directly — the dialog only produces ledger entries, so a balance can
always be explained. Products at or below their reorder level are badged and
filterable via **Low stock**.

### CSV import and export

Import matches rows by `item_code`: existing codes update, new ones are added,
and only `name` is required.

Handles the cases that break naive importers:

- Missing optional columns — a hand-written CSV with two columns imports fine.
- Excel's UTF-8 byte order mark on the first header cell.
- Header names in any case, with spaces instead of underscores.
- Excel-reported MIME types (`application/vnd.ms-excel`) for a `.csv` file.
- PHP uploads rejected; `.php` renamed to `.csv` is still refused.
- Unknown `rate_type` values fall back to the industry default.
- Blank or half-filled rows skipped; NOT NULL columns get safe defaults.
- Custom attributes round-trip losslessly as `key=value;key2=value2`.

Export uses the same columns as the template, so an export can be edited and
re-imported without reformatting.

---

## 3. Quotations

- Built **from catalog products** — pick products, adjust quantity and rate,
  and the quotation form is prefilled with each product's specification, weights
  and wastage defaults.
- Lifecycle: `Draft → Sent → Accepted / Rejected / Expired → Converted`.
- **Validity window**: quotations can carry a `Valid until` date. Lapsed,
  undecided quotations read as expired, a nightly `quotations:expire` sweep
  persists that state, and quotes closing soon appear in reminders and the
  staff digest.
- **Customer decisions** are a tenant setting: Accept / Reject buttons appear
  on the shared quotation only when enabled.
- **Live updates feed** — customers see every change made to a shared quotation
  (also a tenant setting).
- Convert an accepted quotation straight into an invoice, carrying the lines
  and the catalog links across.

---

## 4. Invoicing

- **Quotation, invoice and challan** document types with per-industry defaults.
- Manual pricing or calculated pricing (weight-based for jewelry, area-based
  for tiles and marble, per metre for pipes).
- Per-line and per-invoice charges, discounts, single or split tax, and
  rounding.
- **Installments**: split an invoice into a schedule of dated amounts, with
  payment matching settling installments oldest-first.
- **Credit limits and credit days**: customers can carry a numeric limit and
  Net-N terms. Payable documents that would breach the limit are refused with
  the projected balance; quotations are never blocked by credit. An empty due
  date is filled from credit days, while an explicitly entered date always wins.
- **Old-gold exchange credit** lines for jewelry, crediting against the total.
- Per-tenant, per-industry **charge types** and **invoice templates**
  (accent colour, alignment, and toggles for HUID, stone details, HSN, bank
  details, signature, stamp and QR code).
- **Metal rates** for jewelry trades, feeding weight-based pricing.
- Cost price is visible only to roles holding `view_costs`.

### Payments

- Record payments by cash, card, UPI, bank transfer or cheque, with references.
- Partial payments and a live balance.
- **UPI collect** links on the shared invoice, so a customer can pay from the
  link. No payment gateway is required to invoice a customer.

---

## 5. GST e-invoicing

- Configurable **IRN / QR code** generation for B2B and B2C documents.
- Prerequisite validation before an e-invoice is issued (GSTIN, HSN, unit,
  tax values, seller and buyer details).
- **Buyer classification**: explicit customer GSTIN type decides B2B vs B2C,
  falling back to whether a GSTIN is present; explicit place of supply
  overrides the customer's home state for `BuyerDtls.Pos`.
- Generated IRN and acknowledgement surfaced on the invoice and in the shared
  PDF.
- Driver-based (`log` for development, `api` for a live provider), so the flow
  is fully testable offline. Live generation requires provider credentials.

---

## 6. Sharing and the customer portal

- Share links per invoice with channel tracking (WhatsApp, SMS, email, link),
  optional password protection, sent/downloaded counters and expiry.
- **WhatsApp** share button on the invoice and the public quotation.
- **PDF download** on every path — staff, public link, customer portal and
  the emailed attachment — all from one shared renderer.
- A **customer self-service portal** on magic-link login, separate from staff
  auth: invoice list, invoice detail and PDF download.
- **Email** an invoice with the PDF attached; **SMS** the invoice or a payment
  reminder through a pluggable gateway (log, Twilio or HTTP).

---

## 7. Automation

### Payment reminders

- **SMS and email** are independently toggleable, so a tenant can use either
  channel alone.
- Sends automatically for past-due invoices on a nightly schedule
  (`reminders:send`), throttled to once every three days.
- Respects each customer's preferred contact channel: email-only customers are
  not texted, WhatsApp maps onto the text send, and phone-call customers are
  left for staff rather than messaged automatically.
- A manual **Remind** button on every unpaid invoice for when a customer calls.
- Each attempt is recorded and visible on the customer timeline.

### Reminders and follow-ups

- Payment-due reminders, follow-up tasks with assignee and completion, and
  occasion greetings (birthday, anniversary) or custom dated reminders.
- Quotations closing soon appear as their own reminder card.
- Staff receive a daily digest of what is due.

---

## 8. CRM

- Customer records with assigned staff, notes, tags, search and CSV export.
- Customers carry commercial detail where it must be enforceable: GSTIN type,
  place of supply, credit limit/days, price tier, preferred contact channel,
  referral source and tags. The customer page shows the live credit position
  and tag badges; the customer list filters by exact tag.
- **Activity timeline** per customer combining notes, invoices, payments and
  message history.
- Follow-up scheduling with reminders, and occasion tracking.
- Customer notes and follow-ups are permission-controlled.

---

## 9. Multi-industry support

Industries are data, not code — `config/industries.php` plus a migration is
all a new trade needs.

| Industry                                | Notable capabilities                                                                           |
| --------------------------------------- | ---------------------------------------------------------------------------------------------- |
| **Jewelry**                             | Metal rates, weight-based pricing, hallmarking, HUID, stone details, gold exchange credit.     |
| **Hardware & Building Materials**       | Per piece/litre/kg/metre, brand and model, warranty, shade and volume fields for paint retail. |
| **Tiles, Marble & Stone**               | Area pricing (sq ft / sq m), wastage, batch and box quantities.                                |
| **Plumbing & Electrical**               | Per piece/metre, material and thickness, warranty.                                             |
| **General Trade**                       | Any product-based business, per piece/unit/fixed.                                              |
| **Furniture & Interiors**               | Per piece/area, fabric/weave/pattern, delivery and installation charges.                       |
| **Textiles, Sarees & Apparel**          | Per piece/metre, fabric/weave/pattern, alteration and stitching charges.                       |
| **Electronics & Appliances**            | Brand/model/serial, warranty months, installation and extended-warranty charges.               |
| **Paint, Coatings & Hardware Retail**   | Per litre/kg, shade codes, volume and coverage fields.                                         |
| **Contractors & Civil Work**            | Service and site references, area pricing, labour/material charges.                            |
| **Auto Parts & Accessories**            | Fitment-style specification fields, serial tracking, installation charges.                     |
| **Watches & Eyewear**                   | Brand/model/serial, warranty and service charges.                                              |
| **Mobile & Gadget Shops**               | Serial-tracked units, warranty months, installment-friendly invoicing.                         |
| **Modular Kitchens & Wardrobes**        | Area/metre pricing, material/finish fields, site installation charges.                         |
| **Photo, Optical & Creative Studios**   | Service packages, travel/studio/editing charges.                                               |
| **Repair & Servicing Shops**            | Service type, serial-tracked devices, labour/parts/diagnostic charges.                         |
| **Tuition, Coaching & Training**        | Course/service lines, registration and exam-fee charges.                                       |
| **IT Services & Agencies**              | Service lines, hosting/licence/retainer charges, no stock required.                            |
| **Interior Designers & Event Planners** | Site references, area pricing, labour/material/rental charges.                                 |

Selecting an industry drives the form fields, rate types, charge catalogue,
calculation mode, invoice template flags and the CSV columns.

---

## 10. Free mode

`config/billing.php` holds a single `mode` switch, defaulting to `free`:

```php
'mode' => env('BILLING_MODE', 'free'),
```

While `free`:

- No tenant is ever gated, with or without a subscription row.
- No staff or invoice quotas are enforced.
- Checkout and payment verification are closed (`404`), and the billing page
  shows usage with no plan to buy.
- Registration starts a tenant on the free plan with no trial clock.
- Signed-in UI knows it is free mode and stops referencing trials or upgrades.

Set `BILLING_MODE=paid` to restore plan gating, quotas and checkout. The paid
plans are already seeded, so no code change is needed — and a tenant with no
subscription still falls back to the free plan rather than losing access to
their own data.

---

## 11. Design

Flat **purple and navy** with no gradients. Accent `#7C3AED` with light/dark
variants, deep ink `#0F172A` / `#1E293B`, defined once in
`tailwind.config.js` and mirrored into the PDF template so print and screen
match.

---

## 12. Verification

`295 tests / 1939 assertions`, with Pint, TypeScript, ESLint and a production
build all passing. Coverage spans catalog and CSV round-tripping, inventory
ledger behaviour, quotation lifecycle and expiry, PDF rendering for all four
delivery paths, payment reminders and preferred channels, customer credit
terms and tag filtering, sales-report exclusions, ageing/top-items/conversion
reports, free-mode guarantees, role permissions and super-admin isolation.

---

---

## Owner-first answer: problems, why this, automation

Problems an owner actually faces

- Quotes are built manually in WhatsApp/Excel, so prices, taxes, weights, wastage, discounts, and terms vary by salesperson.
- Accepted quotes have to be retyped as invoices, creating errors and delays.
- Nobody knows which quotes are expiring, which invoices are overdue, or which customers need follow-up.
- Stock is guessed; “available” items turn out to be missing.
- Payments are chased manually; partial payments and balances are hard to track.
- GST/e-invoice data is scattered: missing GSTIN/HSN, wrong B2B/B2C treatment, painful GSTR preparation.
- Generic CRMs don’t understand jewelry weights/purity/HUID, tiles area/wastage, paint litres/shades, electronics serial/warranty, contractor site work, etc.
- Staff mistakes are invisible: no clear roles, activity history, or approval boundaries.
  Why use this instead of another CRM
  Most CRMs track contacts and reminders. This is built around the money workflow:

1. Catalog-first selling.
2. Quotation → invoice/challan conversion without re-entry.
3. Industry-specific fields and pricing:

- jewelry: metal/purity/weight/HUID/stones/gold exchange;
- tiles: area/wastage/batch/boxes;
- paint: litres/shade/coverage;
- electronics: serial/warranty;
- contractors/services: site/service references.

4. Indian billing reality:

- GST modes, e-invoice prerequisites/IRN handling, GSTR exports.
- UPI/collect links, payment recording, installments, balances.

5. Customer self-service:

- share links, portal, PDFs, WhatsApp/SMS/email.

6. Operational control:

- roles/permissions, staff invites, activity logs, tenant isolation, super-admin oversight.

7. Cost posture:

- free mode includes the workflow instead of gating core billing features.
  Automation it already gives
- Nightly:
- overdue invoice marking;
- quotation expiry;
- staff reminder digest plus customer payment/occasion reminders;
- recurring invoice generation.
- Quotation lifecycle:
- validity window, expiry overlay, accepted/rejected/converted tracking, conversion reporting.
- Credit behavior:
- limits, over-limit blocking for payable documents, credit-day due dates.
- Catalog behavior:
- industry-driven fields/rate types/charges/CSV columns;
- inventory ledger instead of silent stock edits;
- inactive products excluded from quotation builder.
- Reporting:
- sales excluding non-sale documents, ageing, top items, quotation conversion, tax/GSTR outputs.
  Draft catalog + activate: proposed behavior
  Current evidence:
- Creation defaults new catalog products to active; the Add dialog has no status toggle.
- Edit has an Active checkbox; list shows inactive badge/filter.
- Quotation builder only offers active products.
  Proposed minimal semantics:
- Catalog status becomes explicit at creation: Active or Draft/Inactive.
- Draft means:
- visible in catalog;
- badged as Draft;
- excluded from quotation builder, invoice picker, dashboard active counts, and sales-facing surfaces;
- import/export preserves the status.
- “Activate” means one explicit action moves Draft → Active, with an activity log entry.
- Keep quotation draft separate: quotation draft remains session-only prefill, not a persisted document state.
  One confirmation before implementation: should “Draft” mean unpublished/internal product, or should there be three states — Draft, Active, Discontinued?
