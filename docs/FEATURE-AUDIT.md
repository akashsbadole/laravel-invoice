# Feature Audit — laravel-jewelry-only-invoice-app

Audit date: 2026-10-02
Auditor: codebase review (4 parallel deep passes over `app/`, `resources/js/`, `routes/`, `database/`)
Baseline at audit time: **406 tests / 2646 assertions passing**, Pint clean, TypeScript clean, ESLint clean, production build clean.

`FEATURES.md` describes what is *supposed* to exist. This document records what *actually* works,
what is quietly broken, and the order in which to fix it. Every finding below was verified against
the source — the ones I personally re-checked are marked ✅ **verified**.

---

## How to read the severity tiers

| Tier | Meaning | Handling |
| --- | --- | --- |
| **P0** | Security hole, money lost, or data destroyed | Fix before any launch |
| **P1** | The number on screen ≠ the number on the bill | Fix before any launch |
| **P2** | Data silently disappears, or a core flow is dead | Fix before selling the feature |
| **P3** | Documented feature does nothing | Fix or remove the claim |
| **P4** | Quality, dead code, inconsistency | Opportunistic |

A finding marked **FALSE CLAIM** means `FEATURES.md` actively promises something that does not
happen. Those are worse than bugs, because a shopkeeper will rely on the promise.

---

## 1. Verification summary

### Works as documented (do not re-audit these)

- All six document types and their lifecycle states — `app/Enums/DocumentType.php:7`
- Exchange credit signs, taxes and credits against the total — `InvoiceCalculationService.php:202`
- Rounding identical across preview / stored / PDF / e-invoice — `InvoiceCalculationService.php:111`
- TCS on top, TDS withheld so balance-net-of-TDS settles — `InvoiceCalculationService.php:102`, `Invoice.php:253`
- Installment settlement oldest-first — `PaymentService.php:54`
- Advance receipts are real payments — `CustomerAdvanceController.php:79`
- Customer group discount: server-derived, typed-discount-wins, exchange exempt, before GST — `InvoiceCalculationService.php:214`
- GSTR-1 / GSTR-3B with credit/debit notes excluded — `GstExportService.php:298`
- IRN/QR generation, B2B vs B2C classification, place-of-supply override — `EInvoiceService.php:140`
- Free mode is genuinely free and genuinely ungated — `SubscriptionService.php:40`
- One PDF renderer behind all four delivery paths — `InvoicePdfService.php:24`
- Low-stock filter and reorder badge agree — `CatalogItem.php:74`
- Variants: per-variant code/price/stock, product mirrors the sum, split product refuses product-level movement
- Catalog status lifecycle with one-click Activate (single item)
- No FormRequest ↔ `$fillable` mismatch anywhere in invoicing
- No calculator output column is missing from a migration

### Partially true

| Feature | What actually happens |
| --- | --- |
| "Stock is never edited directly" | True on every form path. **False** on the CSV/Excel import path (P0-6). |
| "Export uses the same columns as the template … round-trips losslessly" | False for 11 registry fields (P3-13). |
| "Previewed under a one-time token" | Token is not validated; any string works (P3-15). |
| "One-click Activate … `POST /catalog/activate` activates several at once" | Endpoint and test exist, **no UI** (P3-9). |
| Share-link "password protection, expiry, sent/downloaded counters" | Password and expiry validated server-side, **no UI**. Counters do not exist — only timestamps. |
| "Customers see every change made to a shared quotation" | Feed renders, but every row reads "Quotation updated" (P2-9). |
| "flags drafts left untouched for 30 days" | Command prints a number to stdout and does nothing (P3-7). |
| E-invoice prerequisite validation | Checks GSTIN/HSN/total. Never checks unit or tax values. |
| `accent colour, alignment` invoice templates | Stored, never read by the Blade template (P3-4). |

### False claims

| FEATURES.md says | Reality |
| --- | --- |
| `CRN`/`DRN` note prefixes | `CN`/`DN` — `BusinessSetting.php:22` |
| "Accept / Reject **buttons**" | A dropdown + submit button — `invoices/public.tsx:355` |
| "customers see every change made to a shared quotation" | Feed is wired but blank of detail — P2-9 |
| "flags drafts left untouched for 30 days" | No-op command — P3-7 |
| "Cost price is visible only to roles holding `view_costs`" | Shipped to everyone on the catalog list — P0-4 |
| ".php renamed to .csv is still refused" | `extensions:csv` checks the client extension only |

---

## 2. P0 — Security, money, data destruction

### P0-1 · Super admins browse every tenant's data ✅ verified
**`app/Concerns/TenantScope.php:17` + `app/Http/Middleware/EnsureSubscribed.php:36`**

`TenantScope::apply()` no-ops when `Tenant::currentId()` is null, and `Tenant::currentId()` returns
`auth()->user()?->tenant_id` — which is **null for a super admin**. `EnsureSubscribed` then lets
tenant-less users straight through. Every unscoped list endpoint therefore runs unscoped for a
super admin:

`/invoices` · `/customers` · `/customers/{id}` · `/payments` · `/reports` · `/reports/gstr-1` ·
`/reports/gstr-3b` · `/dashboard` · `/reminders`

That is every tenant's invoices, customers (phones, emails, timelines), payments, sales totals and
GST filings in one list. `InvoicePolicy::sameTenant` 403s on the *record* page, so it is a
half-state that is easy to miss. Side effect: `BusinessSetting::forTenant(null)`
`firstOrCreate`s orphan rows with `tenant_id = NULL` on every such request.

**Fix:** make `TenantScope` fail *closed* — a null tenant context must not silently widen a query.
Add a regression test that a super admin gets 403 on all tenant-scoped list endpoints.

### P0-2 · Any invoice can be re-typed into a credit/debit note ✅ verified
**`app/Http/Controllers/InvoiceController.php:294–311`**

`store()` blocks adjustment types at `:144`. `update()` does **not** — `:298` only blocks when the
*existing* invoice is already a note, and `:311` writes the submitted `document_type` verbatim.

A `PUT /invoices/{id}` with `document_type=credit_note` turns a live invoice into an adjustment. It
then disappears from sales reports (`ReportService.php:604`) **and** from GSTR-1/3B
(`GstExportService.php:298`), and `recalculatePaymentStatus()` returns early forever
(`Invoice.php:237`), freezing `paid_amount`, `balance_amount` and `status`. A paid ₹5L invoice
silently vanishes from revenue and from tax returns.

**Fix:** mirror the `store()` guard in `update()`, and constrain `document_type` validation to
non-adjustment values in both requests.

### P0-3 · Ten money and report surfaces have no permission check
No middleware, no policy, nothing:

| Route | Action | Should require |
| --- | --- | --- |
| `GET /reports`, `/reports/download`, `/reports/gstr-1`, `/reports/gstr-3b` | `ReportController:20,42,100,111` | `view_reports` |
| `GET /invoices` | `InvoiceController:54` | `view_invoices` |
| `GET /customers`, `/customers/{id}` | `CustomerController:24,92` | `view_customers` |
| `GET /catalog`, `/catalog/create` | `CatalogItemController:31,69` | `view_catalog` / `manage_catalog` |
| `GET /payments` | `PaymentController:20` | `record_payments` |
| `GET /reminders` | `ReminderController:22` | `send_messages` |
| `GET /quotations/create` | `QuotationController:22` | `manage_quotations` |

`invoice_creator` holds **no** `view_reports`, yet can pull every GST return for the tenant.
`InvoicePolicy::viewAny` and `CustomerPolicy::viewAny`/`view` exist but are never called.

### P0-4 · Cost price leaks to roles without `view_costs` ✅ verified
`config/permissions.php` withholds `view_costs` from manager / invoice_creator / viewer.
`CatalogItemController.php:97` ships `cost_price` in the catalog list payload to every caller, and
`GET /catalog/create` has no guard at all while `CatalogField` renders `cost_price` as a visible
input (`catalog-item-form.tsx:46`). Every role with `view_catalog` can read every product's margin.

### P0-5 · Customers cannot accept a quotation — the route requires staff login ✅ verified
**`routes/web.php:168` sits inside the group opened at `:98` (`auth`, `EnsureUserIsActive`, `EnsureSubscribed`).**

The UI posts from the public page (`invoices/public.tsx:394`) and
`PublicInvoiceController::decide()` is written for guests, but a real customer clicking
**Accept** is redirected to `/login`. The headline quotation feature has never worked for a
customer. `invoices.public.show` at `:94` is correctly outside the group.

**Fix:** move the route out of the auth group and re-test.

### P0-6 · CSV/Excel import silently zeroes stock and wipes attributes ✅ verified
**`app/Http/Controllers/CatalogItemImportController.php:250` and `:672`**

`payloadFrom()` assigns **every** registry field on every row, defaulting to `null`/`false`/`0` when
the CSV omits it. So re-importing a partial CSV matching an existing `item_code` blanks `brand`,
`hsn_code`, `warranty_months`, `image_path`, custom `attributes`, and **writes
`stock_quantity = 0` with no ledger row** because the ledger entry is gated on `stock_tracked`,
which also defaults to `false`. Stock silently disappears and tracking switches off.

This directly contradicts "Missing optional columns — a hand-written CSV with two columns imports
fine."

**Fix:** only assign fields the row actually supplied.

### P0-7 · Deleting a product erases its stock ledger
`inventory_movements.catalog_item_id` has `cascadeOnDelete`
(`2026_10_01_083900_create_inventory_movements_table.php:20`), so
`CatalogItemController.php:373` deletes the entire ledger — the exact outcome the variant FK was
dropped to prevent (`2026_10_02_140000_drop_variant_reference_foreign_keys.php:8`).

---

## 3. P1 — The screen and the bill disagree

### P1-1 · Area-priced lines preview at ₹0 and bill at full rate ✅ verified
**`InvoiceCalculationService.php:321` returns `1.0`; `invoice-calculations.ts:59` returns `0`.**

| | base_value | grand_total |
| --- | --- | --- |
| PHP | 550.00 | 550.00 |
| TS | 0 | 0 |

`per_sqft` rate 500, wastage 10%, no dimensions entered. The PHP side is deliberate and locked by
`tests/Unit/InvoiceCalculationServiceTest.php:154`. **The TS mirror is the wrong side.** A tiles or
marble shop typing the rate before the dimensions sees a ₹0 line that then saves at full rate.

### P1-2 · Invoice-level discount is rounded on the server, not in the preview
`InvoiceCalculationService.php:88` rounds to 2 dp; `invoice-calculations.ts:318` does not.
`discount = 0.005` in `two_decimals` mode → server grand_total `103.98`, preview `103.99`, and the
printed bill follows the server.

### P1-3 · Nothing guards the mirror
No JS test runner in `package.json`; no PHPUnit test invokes `invoice-calculations.ts`. The two
implementations are kept in sync by hand, which is precisely how P1-1 and P1-2 survived a green
suite. **This needs a shared test vector, not more discipline.**

### P1-4 · Overpayment is accepted and silently swallowed
`PaymentService.php:23` records any `amount ≥ 0.01` with no ceiling. `Invoice.php:253` floors
`balance_amount` at 0, so the surplus vanishes with no refund record while `paid_amount` exceeds
`grand_total` and flows into collected revenue.

### P1-5 · Dashboard counts challans and quotations as sales and outstanding
`DashboardController.php:31` defines `$withoutNotes` but applies it only to three of nine queries.
`total_sales`, `total_collected`, `total_outstanding`, `overdue_*` and the 6-month chart are
unrestricted — so every delivery challan inflates revenue, and `MarkOverdueInvoices.php:17`
happily marks challans overdue.

### P1-6 · `Customer::totalOutstanding()` counts quotations as money owed
`Customer.php:175` sums every document's `balance_amount`, while `store()` seeds
`balance_amount = grand_total` for all types. A ₹2L draft quotation shows as ₹2L outstanding on the
customer profile. `creditOutstanding()` at `:186` filters correctly — so the credit gate and the
displayed figure disagree.

### P1-7 · Installment plans are never rebased
`InvoiceController.php:336` writes a new `grand_total`; `:350` recalculates the balance but never
touches `installments`. After an edit or a credit note the plan contradicts the balance on screen.

### P1-8 · Crafted requests bypass two business rules
- `exchange_credit` line type is accepted for any industry (`StoreInvoiceRequest.php:86`), producing
  a negative base value on a paint invoice.
- `catalog_item_id` is checked for existence but not for `Active` status (`:89`), so a discontinued
  product can be sold via a crafted payload — contradicting the product-status feature.

### P1-9 · A deleted charge type silently shrinks the invoice on the next edit
`edit.tsx:108` maps `charge_type_id ?? 0`; the calculator drops unknown charge ids. The FK is
`nullOnDelete`, so retiring a charge type makes it disappear from every existing invoice on save,
with no warning.

---

## 4. P2 — Silent data loss and dead core flows

| # | Finding | Evidence |
| --- | --- | --- |
| **P2-1** | **Editing a quotation permanently deletes its validity window.** `edit.tsx:106` omits the `.slice(0, 10)` that `invoice_date`/`due_date` have at `:93–94`. The ISO datetime cannot be parsed by `<input type="date">`, renders empty, posts `''` → null. `hasLapsed()` returns false on null — so the quote can then never expire. | `edit.tsx:106` |
| **P2-2** | **Invoice-level `attributes` are wiped on every edit.** `edit.tsx` builds `initialData` without `invoice.attributes`; `update()` writes `clean($request->validated('attributes') ?? [])`. Any save destroys the site reference / job number. | `edit.tsx:90`, `InvoiceController.php:339` |
| **P2-3** | **The attribute editor cannot be used.** `commit()` drops any row whose key *or* value is empty, so typing the first character in the Name box clears the field. Deterministic, on the invoice, quotation and customer forms. | `attributes-editor.tsx:124` |
| **P2-4** | **`AttributesEditor` keys rows by array index with `defaultValue`.** Deleting row 0 of 3 leaves its typed values visible and re-submits them under the wrong key. | `attributes-editor.tsx:41` |
| **P2-5** | **A validation error destroys the whole quotation draft.** The draft is session *flash* data pulled on the next request (`InvoiceController.php:86`). Pick 8 products → forget the customer → validation error → the form comes back with **one blank line** and the prefill banner gone. Same on refresh and Back. | `QuotationController.php:109` |
| **P2-6** | **"Request changes" permanently closes the quotation.** The second option is `Decline / discuss changes`, which sets `quotation_status = rejected` — a *decided* state. A customer asking for a different chain length has killed the quote; staff can then only convert it. There is no `changes_requested` state. | `public.tsx:357` |
| **P2-7** | **The quotation lifecycle dropdown renders blank for every new quotation.** `defaultValue={status}` is `'draft'` and there is no `<SelectItem value="draft">`, so the primary lifecycle control shows an empty placeholder. | `show.tsx:963` |
| **P2-8** | **A mis-click on "Declined" is irreversible.** No `draft`/`converted` option exists, and `transition()` throws once decided. No reopen, no reset. | `show.tsx:973` |
| **P2-9** | **The customer-facing update feed is blank.** `QuotationService.php:218,227` reads `$event->properties`, but the column and cast are `meta` (`InvoiceEvent.php:15,23`). So `detail` is always null and every row reads "Quotation updated" instead of "Customer accepted the quotation". | `QuotationService.php:218` |
| **P2-10** | **Staff and customer see different statuses on the same document.** The staff page uses the raw stored `quotation_status`; the expiry overlay in `currentStatus()` is applied only on the public page. A lapsed quote reads "draft" to staff and "expired" to the customer. | `show.tsx:913` |
| **P2-11** | **Staff edits log an empty event**, so even fixing P2-9 yields "Quotation updated" with no diff. There is no revision/version table at all. | `InvoiceController.php:354` |
| **P2-12** | **Clearing a numeric field flips the input to uncontrolled.** `field()` maps a cleared number to `null`, but `quantity`/`rate`/`discount`/`tax_rate` are typed `number`. React stops syncing and strands the typed value. Clearing net weight, qty or rate is enough. | `invoice-item-editor.tsx:104` |
| **P2-13** | **The invoice form has no `<form>` element.** The save button is `type="button"`, so Enter never submits and `required` is inert — every error round-trips to the server. | `invoice-form.tsx:636` |
| **P2-14** | **The public quotation page and the whole customer portal have no global error toasts.** `app.tsx:44` returns them bare, without `AppLayout`/`FlashToaster`. A 419 or 500 on the customer's Accept button is **completely silent**. | `app.tsx:44` |
| **P2-15** | **Reminder buttons double-fire.** `reminders.tsx:288` has no in-flight guard — a double-click sends the customer two SMS greetings. `:237` and `:192` likewise. | `reminders.tsx:288` |
| **P2-16** | **Deletion without confirmation.** `settings/users.tsx:300` deletes a staff account in one click; `metal-rates.tsx:91` likewise. | — |

---

## 5. P3 — Documented features that do nothing

| # | Finding | Evidence |
| --- | --- | --- |
| **P3-1** | **QR code toggle is inert.** `InvoicePdfService.php:63` requires `simplesoftwareio/simple-qrcode`, which is **absent from `composer.json` and from `vendor/`** ✅ verified. `class_exists()` is always false, so the blade branch never renders. | — |
| **P3-2** | **Exchange-credit label never prints on any PDF.** `invoice.blade.php:121` compares `$item->line_type === 'exchange_credit'`, but `line_type` is cast to the `LineType` **enum** — always false. | — |
| **P3-3** | **`accent_color` / `header_alignment` are stored and never read.** The blade hardcodes `#7C3AED`. | — |
| **P3-4** | **Share-link password and expiry have no UI.** Both are validated server-side; the form submits only the button. | `show.tsx:539` |
| **P3-5** | **The customer portal is unreachable from the product.** No link to `/portal/login` anywhere, and no staff action sends a magic link. The customer must be told the URL out of band. | — |
| **P3-6** | **Portal magic-link lookup is not tenant-scoped.** `PortalAuthController.php:29` matches on email as a guest, so `TenantScope` no-ops and the lookup spans every tenant. | — |
| **P3-7** | **`catalogs:flag-stale-drafts` is a no-op.** It `count()`s to stdout and persists nothing, though its own docblock claims otherwise ✅ verified. Also unscoped across tenants. | — |
| **P3-8** | **`quotations:expire` writes events with `tenant_id = NULL`.** No `Tenant::runInContext()`, unlike the other two commands — so expiry events are invisible to the tenant-scoped feed. | `ExpireQuotations.php:22` |
| **P3-9** | **Bulk `POST /catalog/activate` has no UI.** `activateSelected.form()` is declared and never called. | — |
| **P3-10** | **The stock ledger is never displayed.** `InventoryService::history()` has zero callers and there is no product detail page, so "a balance can always be explained" has no interface. | `InventoryService.php:143` |
| **P3-11** | **Rejected, expired and draft quotations look identical in the list.** `invoiceStatusFor()` maps all three to `Draft`, and the list renders `invoice.status`, never `quotation_status`. The lifecycle is also unfilterable — `draft`/`sent`/`accepted`/`converted` are absent from the status filter. | `invoices/index.tsx:150,208` |
| **P3-12** | **Nothing tells staff a quotation was accepted.** `decide()` writes two columns and an event. No notification, no dashboard bucket, no follow-up. Accepted quotes are excluded from the reminders card. | `QuotationService.php:183` |
| **P3-13** | **Field-registry drift: the importer omits 11 registry fields** — `serial_number`, `fabric`, `weave`, `pattern`, `shade_code`, `volume`, `coverage_area`, `service_type`, `site_reference`, `batch_number`, `boxes` — while `payloadFrom()` still writes them. An export re-imported by a tiles/paint/textiles tenant silently nulls them. The registry does **not** drive the importer, which is the exact drift the feature claims to prevent. | `CatalogItemImportController.php:43` |
| **P3-14** | **The Excel preview "one-time token" is not enforced.** Rows are read from the raw request; the cache key is never checked, only forgotten. A test proves it by importing with a token that never existed. | `CatalogItemImportController.php:424` |
| **P3-15** | **Duplicate `item_code` inside one CSV returns 500.** The import loop is not transactional and has no per-row error handling, so one bad row aborts the rest mid-file. | — |
| **P3-16** | **Import bypasses the shared code namespace.** A CSV row can take a code already used by a variant, because the importer calls `CatalogItem::create()` directly without validation. | — |
| **P3-17** | **Import does not validate lengths or ranges**, only `rows.*.name` ≤ 255. Oversized cells throw on MySQL strict mode. | — |
| **P3-18** | **`adjustStock()` does not auto-activate a draft**, unlike `store()` and `update()` — so the documented "auto-activates the first time stock is added" is only true via the form. | `CatalogItemController.php:338` |
| **P3-19** | **`.php` renamed to `.csv` is accepted.** `extensions:csv` checks the client extension only; the file is saved from harm incidentally by `IOFactory::load()` throwing. | — |
| **P3-20** | **Share-link SMS always says "Invoice {number}"**, never "Quotation". | — |
| **P3-21** | **The `DELETE /reminders/{id}` route has no button**, so custom reminders can be completed but never deleted. | — |
| **P3-22** | **`RUNBOOK.md` lists 3 of the 5 scheduled commands.** | — |

---

## 6. P4 — Quality

- **Dead files:** `app/Http/Controllers/UserController.php` and `ActivityLogController.php` are both
  **0 bytes**. `resources/js/components/ui/sidebar.tsx` (12 exports) and `ui/separator.tsx` have zero
  importers and shadow the real `app-sidebar.tsx`.
- **Unused npm deps (7):** `@radix-ui/react-switch|tabs|toast|dropdown-menu`, `react-day-picker`,
  `date-fns`, `cmdk`.
- **Dead exports:** `JEWELRY_ONLY_FIELDS`, `GENERIC_CATALOG_FIELDS`, `AREA_ITEM_FIELDS`,
  `isAreaIndustry()`, `formatCurrency()`, `formatDate()`, `formatDateTime()`, four auth types,
  `CatalogStatus::isLive()`, `CatalogField::numericNames()/booleanNames()/groups()`,
  `Attributes::toString()/fromString()` (verbatim duplicates of private importer methods),
  `CatalogItem::creator()`, `CatalogVariant::invoiceItems()`, `InventoryMovement::item()/variant()/creator()`.
- **i18n is 0% adopted:** `lib/i18n.ts` defines 56 keys covering only `nav.*`, `auth.*`,
  `language.*`. Roughly 700+ hardcoded user-facing strings across 41 files. A user who selects
  हिन्दी gets a Hindi sidebar and then English for the entire working UI — including the public
  quotation and the customer portal, the two surfaces most likely to need a regional language.
- **Currency is hardcoded INR everywhere.** `default_currency` is a real setting, shipped to three
  pages, and read by none. Set it to USD and every figure still renders ₹.
- **16 hand-rolled `Intl.NumberFormat` instances with 4 different configs**, plus 33 bare
  `toLocaleDateString()` calls with no locale pin — a browser in a negative UTC offset renders ISO
  dates one day early.
- **20+ hardcoded URL templates** despite a `routes/*.ts` layer; a route rename breaks them silently.
- **3 `useEffect` calls app-wide.** `use-appearance.ts:20` never subscribes to
  `matchMedia('(prefers-color-scheme: dark)')`, so `appearance = 'system'` ignores OS theme changes.
- **Rate-type labels have 3 copies** with divergent wording (`"Per gram (× net weight)"` vs
  `"Per gram (× net wt)"`), which already produces the visible string bug
  `₹12,000/gram (× net wt)` in the quotation builder.
- **Catalog field labels have 2 divergent copies** (`"Metal"` vs `"Metal type"`,
  `"Serial number"` vs `"Serial no."`).
- **No chunking/pagination** on the catalog picker or the Excel preview grid — a 3k-SKU shop
  renders 3k rows with no windowing.
- **`.env.example` drift:** missing `BILLING_MODE`, `EINVOICE_DRIVER`, `EINVOICE_API_URL/KEY`,
  `TWILIO_*`, `SMS_HTTP_*`.

---

## 7. Fix order

Ordered by "how much damage does this do if a shopkeeper finds it".

### Wave 1 — Security and money (do before launch)
| # | Fix | Size |
| --- | --- | --- |
| P0-1 | `TenantScope` fails closed; super admin 403s on tenant-scoped lists | S |
| P0-2 | Block `update()` from re-typing into an adjustment | XS |
| P0-3 | Add the 10 missing permission checks | M |
| P0-4 | Strip `cost_price` without `view_costs`; guard `/catalog/create` | S |
| P0-5 | Move `invoices.public.decide` out of the auth group | XS |
| P0-6 | Import must only assign supplied fields | M |
| P0-7 | Drop the ledger FK cascade | XS |
| P1-1 | TS `computeArea` returns 1.0 to match PHP | XS |
| P1-2 | Round the invoice discount in the preview | XS |
| P1-3 | Shared PHP↔TS test vector (kills the class of bug) | M |
| P1-4 | Cap or explicitly record overpayment | M |
| P1-5/6 | Filter non-sale documents out of dashboard + customer totals | M |

### Wave 2 — Silent data loss
P2-1 → P2-2 → P2-3 → P2-5 → P2-4 → P2-12 → P2-13 → P2-14 → P2-15 → P2-16

### Wave 3 — Make the quotation flow actually work
- P2-6 add a `changes_requested` state that is not terminal
- P2-7 fix the blank dropdown; P2-8 allow reopening a mis-clicked decline
- P2-9 `meta` vs `properties`; P2-11 log a real diff
- P2-10 apply the expiry overlay on the staff page
- P3-11 dedicated quotation list with lifecycle filter and correct badges
- P3-12 notify staff on acceptance

### Wave 4 — Documentation truth and dead features
Fix the FALSE CLAIM table, then implement or delete: QR (P3-1), template colours (P3-3),
share-link password/expiry UI (P3-4), portal entry point (P3-5, P3-6), stale-draft command
(P3-7), expiry tenant context (P3-8), bulk-activate UI (P3-9), ledger view (P3-10), registry
drift (P3-13), preview token (P3-14).

### Wave 5 — Clean-up
Dead files and exports, the `default_currency` bug, shared formatters, i18n adoption, `use-appearance`
media query, RUNBOOK/.env.example.

---

## 8. Quotation workflow — what an owner cannot do today

Answering the question directly, since this is the stated priority.

| Owner wants to | Today |
| --- | --- |
| Send a quotation on WhatsApp | Works |
| Have the customer accept from the link | **Broken** — redirects to `/login` (P0-5) |
| See "sent" vs "accepted" vs "declined" in a list | **Impossible** — three states render identically (P3-11) |
| Filter quotations by lifecycle | **Impossible** — those statuses are not filterable (P3-11) |
| Have a customer ask for a change without killing the quote | **Impossible** — decline is terminal (P2-6) |
| Undo a mistaken decline | **Impossible** — irreversible (P2-8) |
| See what changed since the customer last looked | **Broken** — feed renders "Quotation updated" (P2-9) |
| Hold stock while a quote is live | **Not implemented** — no reservation logic exists at all |
| Lock today's gold rate onto the quote | **Not implemented** — no rate snapshot, no date, no source recorded |
| Be told a quote was accepted | **Nothing happens** — no notification, no task (P3-12) |
| See the age of open quotes | Sortable by date, but no ageing column and no dashboard bucket |
| Fix a typo on a sent quote | **Silently destroys the validity date** (P2-1) |

Also worth noting: **no procurement side exists at all.** Nothing for buying gold or silver,
supplier ledgers, or paying suppliers. And no job-work/repair tracking, which for a jewelry shop is
usually a larger daily pain than anything in the table above.

---

## 9. Progress

| Wave | Status |
| --- | --- |
| 1 — Security and money | not started |
| 2 — Silent data loss | not started |
| 3 — Quotation flow | not started |
| 4 — Doc truth and dead features | not started |
| 5 — Clean-up | not started |

Update this table as waves land, and keep `FEATURES.md`'s verification counts in step.
