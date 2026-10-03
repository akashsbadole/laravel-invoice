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
| **Manager**         | Runs the day to day — catalog, stock, invoices, reports. No settings or staff administration.  |
| **Invoice Creator** | Quotes and invoices in, records payments. No deletes, no cost visibility.                     |
| **Viewer**          | Read-only across the board.                                                                   |

Capabilities: `view_dashboard`, `manage_customers`, `manage_catalog`,
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

### Variants

One product can be split into the forms it is actually sold in — a ring in
four sizes, a pendant in three purities, a tile in two finishes — without
duplicating the product row.

- Each variant carries its own **name, code, price override, stock and
  on-sale flag**. A blank price charges the product's own rate.
- Codes share a single tenant-wide namespace with product codes: a variant can
  never take a code a product already uses, including two rows added in the
  same submit.
- Stock lives on the variants; `catalog_items.stock_quantity` mirrors their
  sum, so low-stock checks and reports keep meaning what they say. Every
  figure still travels through the movement ledger — opening balances, hand
  corrections and the write-off when a variant is dropped.
- A split product refuses a product-level stock movement, because the next
  variant movement would overwrite it. The variant editor is where it is
  corrected.
- The invoice line picker offers the variant beside the product, sets the
  line's name to `Product (Variant)` and its code and price to the variant's,
  and stores `catalog_variant_id` on the line so the document says exactly
  which form was sold.
- The reference on invoice lines and stock movements is deliberately **not** a
  foreign key: retiring a size must never rewrite an invoice or erase the
  explanation of a write-off.

### Product status

Every product carries one of four states:

- **Draft** — staged but not ready to sell: hidden from the quotation builder,
  the invoice picker and the dashboard's active count; badged in the list;
  auto-activates the first time stock is added; `catalogs:flag-stale-drafts`
  flags drafts left untouched for 30 days.
- **Active** — live and selectable everywhere.
- **Inactive** — off sale for now (seasonal, out of favour), still editable.
- **Discontinued** — retired for good; hidden from sellers, kept for history.

Any non-active product shows a one-click **Activate** button on the catalog
list, and `POST /catalog/activate` re-activates several at once. The `status`
column carries the state through CSV/Excel imports and exports; unknown labels
fall back to Active, while `archived`/`disabled` map to Discontinued.

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
filterable via **Low stock**. A product split into variants keeps one ledger
per variant, with the product row summing them, and each row names the
variant it was for.

### CSV and Excel import and export

**CSV** imports straight into the catalog; **Excel** (`.xlsx`, `.xls` or
`.csv`) goes through a preview first.

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

The Excel flow is full CRUD on the sheet before anything is written: an upload
opens `catalog/excel/preview`, where every row can be edited inline, added to
or deleted, and only **Import** touches the catalog (previews are cached for
two hours under a one-time token). `catalog/excel/export` and
`catalog/excel/template` publish the same columns as the CSV ones, so a
workbook round-trips losslessly. Permission and validation failures surface
as toast notifications, not silent failures.

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

### Rate lock — the quote bills at the rate it promised

A quote is a promise: the customer accepts a number, and the bill has to land
on that number even when the daily rate moves before billing.

- `invoices.rate_locked_at` records the day the metal rate was struck. It
  defaults to the document's own date and staff can back-date it to the day
  they actually priced the job.
- It **survives conversion**. A quotation priced at a Monday rate is billed at
  the Monday rate, not re-dated to the billing day, so a rate move can never
  turn into a surprise on the bill.
- It prints on the quotation, the shared link, the WhatsApp message and the
  tax invoice ("Metal rate as on 12 Jan").
- `RateLockService` flags a price older than 7 days as **stale** and the
  invoice page warns the owner to re-confirm today's rate before converting.
  It warns rather than blocks: it cannot know whether the market actually moved.

### Revisions — "which quote did we agree to?"

Editing a quotation the customer has already seen changes what they agreed to,
so every such edit is a numbered revision with a reason.

- `revision_number` starts at 1 and advances only once a quotation leaves
  `draft`. Draft edits are private and cost no revision.
- The owner records a one-line reason ("Customer asked for 20g instead of
  15g") which is shown to the customer, published in the WhatsApp text and
  recorded in the activity timeline as *"Quotation revised to Rev N"*.
- Revisions show as `Rev N` on the invoice page, on the shared quotation and
  in the shared WhatsApp message, so the customer can always tell which version
  they are holding.

### Accept / Ask for changes / Decline

Rejecting a quote kills the negotiation and loses the thread. The shared page
offers three explicit paths instead:

- **Accept** — records the decision and notifies staff.
- **Ask for changes** — keeps the quotation **open** (no status change), stores
  the customer's own words as `quotation_response`, logs a
  `changes_requested` activity, notifies staff, *and* opens a prefilled WhatsApp
  message to the shop. Negotiation continues in the channel the shop actually
  uses.
- **Decline** — closes the quotation.

### Acceptance never creates a sale

A customer tapping **Accept** on a shared link records the decision **only**.
It never creates an invoice. No owner is present at that moment, nobody has
re-confirmed the metal rate and nobody has taken payment, so converting is a
human action at the counter. (An earlier `quotation_auto_convert` setting did
convert silently; the column has been dropped.)

---

## 4. Invoicing

- **Quotation, invoice and challan** document types with per-industry defaults.
- Manual pricing or calculated pricing (weight-based for jewelry, area-based
  for tiles and marble, per metre for pipes, per litre for paint and liquids —
  the line quantity is the litres being billed).
- Per-line and per-invoice charges, discounts, single or split tax, and
  rounding.
- **Customer group discount** — a customer's pricing tier is applied
  automatically to every unpriced line (see
  [Customer group pricing](#customer-group-pricing)).
- **Installments**: split an invoice into a schedule of dated amounts, with
  payment matching settling installments oldest-first.
- **Credit limits and credit days**: customers can carry a numeric limit and
  Net-N terms. Payable documents that would breach the limit are refused with
  the projected balance; quotations are never blocked by credit. An empty due
  date is filled from credit days, while an explicitly entered date always wins.
- **Old-gold exchange credit** lines for jewelry, crediting against the total.
- **Rounding** is a per-business setting — `nearest_rupee` (default) or
  `two_decimals` — applied identically by the live preview, the stored total,
  the PDF and the e-invoice breakdown.
- **TCS** is collected on top of the total; **TDS** is withheld from what the
  customer pays, so the balance due is the total minus TDS and a payment for
  that amount settles the invoice exactly.
- **Credit and debit notes**: a total raised too high is corrected with a
  credit note, a missed charge with a debit note. Both are adjustment
  documents on their own numbering series (`CRN`/`DRN`), link back to the
  parent invoice, are excluded from sales reports and GST exports, and cannot
  be edited once issued. The parent's balance moves with them and its status
  closes when nothing is outstanding.
- Per-tenant, per-industry **charge types** and **invoice templates**
  (accent colour, alignment, and toggles for HUID, stone details, HSN, bank
  details, signature, stamp and QR code).
- **Metal rates** for jewelry trades, feeding weight-based pricing.
- Cost price is visible only to roles holding `view_costs`.

### Re-quote — the repeat order in one click

A returning customer wanting the same piece again is the single most common
event at the counter, and retyping the quote is the slowest part of it.

- **Re-quote** on any document rebuilds it as a **fresh draft quotation** with
  the same lines, tax mode and template.
- **Every per-gram metal line is re-priced at today's `MetalRate`**, and all
  totals are recomputed through the calculator. Copying the stored rate would
  quote yesterday's gold — either a silent loss to the shop or an argument at
  the counter.
- A fixed-price or per-piece line (a repair, a service) keeps its price,
  because there is no daily rate to move it to.
- The new quote gets a fresh 14-day validity and `rate_locked_at = today`.
- A **discount approval does not carry over**: it approved a specific number,
  and the re-quoted total is a different number.
- Available from the invoice header and per-row on the customer page, and
  hidden for credit/debit notes, which correct another bill.

### Discount approval — one rule, not a rule engine

A shop with two staff loses margin quietly when either of them gives away 15%
without asking.

- One business setting: **"Discount approval limit (%)"**. Blank disables it.
- The discount is measured as a share of the original bill
  (`discount / (grand_total + discount)`).
- Above the limit, a quotation **cannot be converted** until an admin approves
  it (`ManageSettings` — invoice creators and managers deliberately cannot).
- The approval is **snapshotted** with the exact amount it covered, so quietly
  raising the discount later requires a fresh approval. It travels onto the
  converted invoice.

### Payments

- Record payments by cash, card, UPI, bank transfer or cheque, with references.
- Partial payments and a live balance.
- **UPI collect** links on the shared invoice, so a customer can pay from the
  link. No payment gateway is required to invoice a customer.
- **Advance receipts**: money taken before there is an invoice sits on the
  customer as an advance, on its own numbering series. It is applied to one or
  more invoices from the invoice page (settling installments oldest-first) or
  refunded back out, and the customer profile shows what is still held.
  Applying an advance is a real payment, so statuses, balances and reports all
  follow without special cases.

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

## 6. Documents — quotation and invoice PDFs

A quotation and a tax invoice are **different documents**, not one layout with
a different title. `InvoicePdfService::viewName()` picks the template from the
document type, and **all four delivery paths** — staff print, public share
link, customer portal and the emailed attachment — ask it there, so the
emailed PDF can never disagree with the page the staff previewed.

### Quotation (`pdf/quotation.blade.php`)

- Shop masthead, `QUOTE` title, quote number, date and **validity until**.
- Revision number and metal-rate date when they apply.
- Customer block, then a `Description / Qty / Rate / Amount` items table.
- Totals block: subtotal, charges, discount, tax, round off, **TOTAL**.
- **Terms and conditions** with a customer acceptance block (Name / Signature /
  Date) — a quote is an offer, so it is signed for.
- Carries no GSTIN, HSN, IRN or e-way bill, because a quote is not a tax
  invoice.

### Tax invoice (`pdf/invoice.blade.php`)

Classic Indian layout, assembled from data already stored — nothing invented:

- Shop masthead, `TAX INVOICE`, "Original for recipient".
- **PAN** and **GSTIN** row. The PAN is *derived from the seller's GSTIN*
  (characters 3–12), so no separate field has to be kept in step.
- Two-column body: **Customer Detail** (M/S, address, phone, GSTIN, place of
  supply) beside **Invoice Detail** (number, date, due date, reference,
  e-way bill, rate date).
- `Sr. No. / Name of Product or Service / HSN-SAC / Qty / Rate / Taxable Value
  / Amount` items table, plus a total row and the tax line marked
  **(E & O.E.)**.
- **Total in words** and **total tax in words**, using Indian grouping
  (`app/Support/NumberToWords.php`): lakh and crore rather than million and
  billion, with paise written as a fraction. Float noise from a stored decimal
  cannot become a spurious paise, and a sub-rupee amount reads "five paise
  only" rather than "zero rupees and five paise".
- **HSN-wise tax summary** grouped by HSN + rate with taxable value and a
  CGST/SGST or IGST split. An exchange-credit line is excluded, because
  handing back the customer's own metal is not a supply.
- Bank details with UPI ID and the UPI QR, terms, "Certified that the
  particulars given above are true and correct", the authorised signatory and
  a computer-generated-invoice note.

### PDF share passwords

The share link can carry an optional password, and the **PDF is genuinely
protected**, not just its landing page:

- The owner sets a password (min 4 characters) and an optional expiry when
  generating the link. A blank expiry means "never".
- `/invoice/view/{token}` and `/invoice/view/{token}/pdf` both return 403 until
  the password is entered and verified in-session.
- The hash is stored with `Hash::make` and stays in the model's `$hidden`.
  Only a derived `has_password` boolean reaches the browser, so the staff screen
  can warn *"send the link and the password separately"* without ever exposing
  the secret.

### Not indexed

`public/robots.txt` disallows everything, and the app shell carries
`noindex, nofollow, noarchive, nosnippet` — `robots.txt` alone only deters
crawlers that fetch it.

---

## 7. Sharing and the customer portal

- Share links per invoice with channel tracking (WhatsApp, SMS, email, link),
  optional password protection, sent/viewed/downloaded counters and expiry.
- **WhatsApp** share button on the invoice and the public quotation.
- **PDF download** on every path — staff, public link, customer portal and
  the emailed attachment — all from one shared renderer.
- A **customer self-service portal** on magic-link login, separate from staff
  auth: invoice list, invoice detail and PDF download.
- **Email** an invoice with the PDF attached; **SMS** the invoice or a payment
  reminder through a pluggable gateway (log, Twilio or HTTP).

### The shareable text

A customer decides in the chat bubble, not on a landing page, so the shared
message carries the actual numbers rather than only a link.
`resources/js/lib/share-invoice.ts` builds a breakdown — item, metal/purity,
grams, the rate used, making and wastage charges, tax, the **grand total**,
validity, rate date and revision — with WhatsApp bold markup, plus a plain
variant for email/SMS where `*bold*` would print literally. Item-level charges
are printed once: `charges_summary` already contains them multiplied by
quantity, so printing both would inflate every total.

---

## 8. Automation

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

### The chase list — replaces the owner's memory

The failure point at a small shop is not knowing who is waiting. The dashboard
carries a **"waiting on a customer"** list: quotations sent two or more days
ago, still unanswered and not converted, oldest first, badged *not opened* or
*opened · not answered*. One tap opens WhatsApp with a check-in message already
written and the quote link attached. Quotes that are recent, drafts, accepted
or already converted never appear.

---

## 9. CRM

- Customer records with assigned staff, notes, tags, search and CSV export.
- Customers carry commercial detail where it must be enforceable: GSTIN type,
  place of supply, credit limit/days, price tier, preferred contact channel,
  referral source and tags. The customer page shows the live credit position
  and tag badges; the customer list filters by exact tag.
- **Customer groups** — named pricing tiers (Wholesale, Staff, VIP) carrying a
  percentage discount, created under Settings → Customer Groups and assigned on
  the customer's record. See [Customer group pricing](#customer-group-pricing).
- **Activity timeline** per customer combining notes, invoices, payments and
  message history.
- Follow-up scheduling with reminders, and occasion tracking.
- Customer notes and follow-ups are permission-controlled.

### Customer group pricing

Half a shop's sales go to the same handful of dealers, and the same wholesale
discount used to be re-typed on every invoice. A customer group says it once.

- Each group is a **percentage**. Any invoice or quotation raised for a customer
  in that group takes the percentage off every line the shopkeeper left blank.
- **A discount you typed yourself always wins**, even a smaller one — a
  one-off negotiated rate on a single line is never quietly overruled. The
  line's discount box names the group and the amount it applied while the box
  is still empty.
- The discount lands **before GST**, so tax is charged on the lower value. An
  old-gold exchange line is never discounted, because handing back the
  customer's own metal is not a supply.
- It prints as an ordinary **"Item discounts"** row, so the bill explains
  itself and the figure is visible on the quotation, the invoice and the PDF.
- The rate is resolved **on the server from the customer's own group**, so a
  tampered request cannot claim a discount it was not given. The live preview
  receives the same figure and totals identically.
- **Switching a group off** stops it applying to the next bill; **re-pricing a
  group** never re-prices an invoice that already went out. Invoices keep the
  discount they were issued with.
- Deleting a group frees its customers rather than orphaning them — membership
  clears and their next invoice is back at face value.

---

## 10. Multi-industry support

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

## 11. Data model

32 Eloquent models. Every tenant-scoped model carries `BelongsToTenant`, so a
row can never be read across tenants even if a query forgets to scope.

### Platform and tenancy

| Model | Purpose |
| ----- | ------- |
| `Tenant` | One business. Carries `industry` and `status`. |
| `User` | Staff and platform accounts, with `role` and `tenant_id`. |
| `Subscription` / `Plan` | Tenant plan, status and period. |
| `StaffInvite` | Pending invitation into a tenant roster. |
| `PlatformActivityLog` | Super-admin action trail. |
| `ActivityLog` | Tenant-level action trail (spatie activitylog). |

### Business configuration

| Model | Purpose |
| ----- | ------- |
| `BusinessSetting` | Per-tenant singleton: identity, tax number, numbering sequences, rounding, channel toggles, discount-approval limit. |
| `InvoiceTemplate` | Layout config: accent colour, alignment and the print toggles. |
| `ChargeType` | Per-industry charge catalogue — name, code, calculation type, taxable flag. |
| `CustomerGroup` | A named percentage pricing tier. |
| `QuotationTemplate` | Saved quotation shapes. |

### Catalog

`CatalogItem`, `CatalogVariant`, `InventoryMovement`.

### Documents

| Model | Purpose |
| ----- | ------- |
| `Invoice` | Every document type — quotation, invoice, challan, credit/debit note. Carries the money columns, `rate_locked_at`, `revision_number`/`revision_note` and the discount-approval snapshot. |
| `InvoiceItem` | A document line: specs, weights, rate, discount, tax. `line_type` distinguishes a sale from an exchange credit. |
| `InvoiceItemCharge` | A charge on one line. |
| `InvoiceCharge` | A charge on the whole document. |
| `Installment` | One dated slice of an agreed payment plan. |
| `Payment` | A receipt against an invoice. |
| `CustomerAdvance` | Money held before there is an invoice. |
| `RecurringProfile` | Repeating-document schedule. |
| `MetalRate` | A dated rate for a metal + purity. |
| `InvoiceShareLink` | A secure customer link: token, expiry, `password_hash`, sent/viewed/downloaded stamps. |
| `InvoiceEvent` | Per-document activity trail — the source of the shared-page updates feed. |
| `MessageLog` | Every outbound SMS/WhatsApp/email attempt, with driver and error. |

### CRM

`Customer`, `CustomerNote`, `CustomerFollowup`, `CustomerPortalToken`.

### 21 services

`InvoiceCalculationService` (the single source of pricing truth),
`InvoiceCloner`, `InvoicePdfService`, `ReQuoteService`, `RateLockService`,
`DiscountApprovalService`, `QuotationService`, `QuotationNotifier`,
`QuotationFollowUpService`, `PaymentService`, `PaymentReminderService`,
`ReminderService`, `InventoryService`, `ChargeTypeSeeder`, `CreditLimitService`,
`EInvoiceService`, `GstExportService`, `ReportService`, `SmsService`,
`RazorpayService`, `SubscriptionService`.

### 22 enums

`AdvanceStatus`, `CatalogStatus`, `ChargeAppliesTo`, `ChargeCalculationType`,
`ContactChannel`, `DocumentType`, `FollowupStatus`, `GstinType`,
`InstallmentStatus`, `InvoiceEventType`, `InvoiceStatus`, `LineType`,
`PaymentMethod`, `Permission`, `PriceTier`, `PricingMode`, `QuotationActivity`,
`QuotationStatus`, `RateType`, `RoundingMode`, `TaxMode`, `UserRole`.

---

## 12. Free mode

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

## 13. Design

Flat **purple and navy** with no gradients. Accent `#7C3AED` with light/dark
variants, deep ink `#0F172A` / `#1E293B`, defined once in
`tailwind.config.js` and mirrored into the PDF templates so print and screen
match.

The invoice page is deliberately **compact**: reduced section spacing, icon-only
header actions, `text-xs` item and totals tables, a two-column totals grid, and
collapsible Share and Payment-plan cards so the common case (read the total)
fits on one screen.

### A Blade trap worth knowing

`@if((float) $x > 0)` silently compiles to an `if` with the expression
`(float)` and leaves the rest as literal text, because Blade's directive parser
balances parentheses and a cast opens one it did not expect. Separators placed
directly after an inline `@if` (for example `@if($ok) · value@endif`) also fail
to compile and leak `@endif` into the document. Both PDF templates therefore
resolve casts into `@php` variables and assemble joined strings with
`implode()` rather than interleaving directives with separator characters.

---

## 14. Verification

`506 tests / 3224 assertions`, with Pint, TypeScript, ESLint and a production
build all passing.

Coverage spans catalog and CSV/Excel round-tripping, catalog status lifecycle,
product variants, customer-group pricing, quotation lifecycle and expiry,
**rate-lock inheritance and staleness**, **revision numbering and the voiding of
approvals**, **the rule that acceptance never creates an invoice**,
**re-quote repricing at today's rate**, **PDF template selection and HSN
summaries**, **amount-in-words**, **share-link password gating of the PDF**,
discount-approval gating, the dashboard chase list, inventory ledger
behaviour, credit/debit notes, advance receipts, rounding and TDS/TCS,
litre-based pricing, payment reminders and preferred channels, customer credit
terms, sales-report exclusions, reports, free-mode guarantees, role permissions
and super-admin isolation.

Every failure reaches the owner as a toast: flash messages, validation errors,
expired session (419), non-Inertia server responses and thrown request errors
are all surfaced globally in `resources/js/app.tsx` rather than leaving a form
silently doing nothing.

---

## Owner-first answer: problems, why this, automation

### Problems an owner actually faces

- Quotes are built manually in WhatsApp/Excel, so prices, taxes, weights,
  wastage, discounts and terms vary by salesperson.
- Accepted quotes have to be retyped as invoices, creating errors and delays.
- Nobody knows which quotes are expiring, which invoices are overdue, or which
  customers need follow-up.
- The customer sees a number, the gold rate moves, and the bill no longer
  matches the quote.
- Stock is guessed; "available" items turn out to be missing.
- Payments are chased manually; partial payments and balances are hard to track.
- GST/e-invoice data is scattered: missing GSTIN/HSN, wrong B2B/B2C treatment,
  painful GSTR preparation.
- Generic CRMs don't understand jewelry weights/purity/HUID, tiles
  area/wastage, paint litres/shades, electronics serial/warranty, or
  contractor site work.
- Staff mistakes are invisible: no clear roles, history or approval boundaries.

### Why use this instead of another CRM

Most CRMs track contacts and reminders. This is built around the money
workflow:

1. Catalog-first selling.
2. Quotation → invoice/challan conversion without re-entry.
3. Repeat orders re-quoted at today's rate in one click.
4. A rate lock that makes the quoted number the billed number.
5. Revisions, so "which quote did we agree to?" has an answer.
6. Indian billing reality: GST modes, e-invoice IRN, GSTR exports,
   total-in-words, UPI collect, installments, balances.
7. Customer self-service: share links, portal, PDFs, WhatsApp/SMS/email.
8. Operational control: roles, staff invites, activity logs, tenant isolation,
   super-admin oversight, and a discount ceiling that needs an owner's sign-off.
9. Cost posture: free mode includes the workflow instead of gating core
   billing features.

### Automation it already gives

- **Nightly:** overdue invoice marking; quotation expiry; staff reminder digest
  plus customer payment/occasion reminders; recurring invoice generation.
- **Quotation lifecycle:** validity window, expiry overlay, revisions, rate lock,
  accepted/rejected/converted tracking, conversion reporting.
- **Chasing:** a dashboard list of quotations waiting on a customer, with a
  one-tap WhatsApp nudge; automatic follow-up for quotes never opened or about
  to expire.
- **Credit behaviour:** limits, over-limit blocking for payable documents,
  credit-day due dates.
- **Customer group pricing:** a tier discount fills in every unpriced line,
  server-side and in the live preview alike; a discount typed by hand is never
  overwritten.
- **Catalog behaviour:** industry-driven fields/rate types/charges/CSV columns;
  inventory ledger instead of silent stock edits; draft/inactive/discontinued
  products excluded from the quotation builder and invoice picker.
- **Reporting:** sales excluding non-sale documents, ageing, top items,
  quotation conversion, tax/GSTR outputs.