# Jewelry Invoice + Share + CRM — drop-in layer

Built on the official **Laravel 13 + Inertia + React** starter kit (`react-starter-kit-main.zip`).
Copy everything in this zip over your extracted starter-kit project. It overwrites these starter
files: `app.tsx`, `app.blade.php`, `app-sidebar.tsx`, `settings/layout.tsx`, `routes/web.php`,
`routes/console.php`, `routes/settings.php`, `User.php`, `types/auth.ts`, `dashboard.tsx`.

## Setup

```bash
composer require barryvdh/laravel-dompdf                  # PDF + thermal-receipt PDF (required)
composer require simplesoftwareio/simple-qrcode           # QR on invoice PDF (optional)
php artisan migrate:fresh                                  # migration files were edited in place — see note
php artisan storage:link                                   # logo/signature/stamp images
npm install && npm run dev                                 # Wayfinder generates the typed route helpers
```

Add to `.env` if you want real SMS (default `SMS_DRIVER=log` just logs messages, nothing is sent):
```
SMS_DRIVER=log            # log | twilio | http
SMS_DEFAULT_COUNTRY_CODE=91
# Twilio: TWILIO_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM
# Generic gateway: SMS_HTTP_URL (+ optional SMS_HTTP_TOKEN, SMS_HTTP_TO_FIELD, SMS_HTTP_MESSAGE_FIELD)
```

Cron, for overdue-status and the daily reminder digest:
```
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

First login: promote yourself to admin (new users default to `viewer`):
`php artisan tinker` → `User::first()->update(['role' => 'admin']);`

> **Why `migrate:fresh`:** migration files were revised in place across iterations (fixed-charge
> columns replaced by the generic charge system, GST/state fields added, `customer_notes`
> re-ordered). If you already have data, don't refresh — write new migrations from here instead.

## Everything included

| Area | Where |
|---|---|
| Roles: admin / invoice_creator / viewer, active/inactive accounts, forced logout on deactivation | Settings → Users, `EnsureUserIsActive` middleware |
| Dashboard: sales/collected/outstanding, 6-month chart, overdue banner, recent invoices/customers, today's metal rates | `/dashboard` |
| Reports: invoice, paid, unpaid, outstanding, customer summary, payments, **GST tax report**, salesperson, monthly revenue — filterable, exported as CSV / **Excel** / PDF | `/reports` |
| Activity log: every create/update/delete/login, searchable | Settings → Activity log |
| Customers: CRUD, search/filter, CSV export, notes, follow-ups, **birthday/anniversary + GST state code**, invoice history | `/customers` |
| Invoices: create/edit/cancel/delete, manual **or** jewelry-calculated pricing, **per-field validation errors on every item row** | `/invoices` |
| Generic charge engine: admins define charge types (making, wastage, stone, hallmarking, certification, polish, packing…) as fixed / % / per-gram / per-carat, per-item or whole-invoice, taxable or not | Settings → Charge types |
| **Daily metal rates** with a one-click "use today's rate" ⚡ button on each invoice item | Settings → Metal rates, invoice item editor |
| **GST**: single tax line, or CGST+SGST (same state) / IGST (other state), auto-suggested from business vs. customer state code, split by tax slab, shown on invoice/PDF/receipt/report | invoice form, `TaxMode` |
| Jewelry fields per item: metal, purity, HUID, HSN, gross/net/stone weight, stone carat/clarity/colour, certificate no., rate basis | invoice item editor |
| Payments: partial/multiple, method, reference, auto status, **payments list with filters** | `/payments` |
| PDF (Dompdf), **thermal 58mm/80mm receipt** (browser print or PDF) for both invoices and payments, public share page | `/invoices/{id}/pdf`, `/invoices/{id}/receipt`, `/payments/{id}/receipt`, `/invoice/view/{token}` |
| Sharing: secure token link, expiry, optional password, WhatsApp, email, **SMS**, copy link, disable, sent/viewed/downloaded tracking | invoice page |
| **Reminders**: payments due/overdue, follow-ups, birthdays, anniversaries (all derived live), plus custom reminders; SMS from the reminders page; daily email+in-app digest to staff | `/reminders`, `reminders:send` command |
| Invoice templates — accent colour, header alignment, toggle HUID/HSN/stone/bank/signature/stamp/QR, footer note, default template | Settings → Invoice templates |
| Item catalog + CSV import/export template (matched by `item_code`) — pre-fills invoice lines. *Not stock.* | Settings → Item catalog |
| PWA: manifest, service worker, offline page, icons | `public/` |

### Calculation rules (PHP `InvoiceCalculationService` ⇄ TS `invoice-calculations.ts`)

Per unit: `base = rate × net_wt` (per gram) / `× stone_carat` (per carat) / `rate` (piece/fixed/manual).
Each charge: fixed → rate; % → base × rate%; per-gram → rate × net_wt; per-carat → rate × carat.
Line = `(base + charges − item discount) × qty + item tax`.
Invoice: `subtotal (base only) + charges_summary (incl. a negative "item discounts" row) + tax
− discount + round_off = grand_total` — that identity is asserted in the PHP/TS parity test below.
Tax: computed per item at its own rate, grouped into slabs; `tax_mode = cgst_sgst` splits each slab
into two equal rows, `igst` keeps one row per slab, `single` collapses to one total.
The server always recalculates from charge-type definitions in the database; the browser copy
(same formulas) only drives the live preview.

**Verified, not just written:** every PHP file passes `php -l`; every TS/TSX file parses with
esbuild; the PHP and TypeScript calculators were run side-by-side on a multi-item, multi-slab
GST scenario and produced byte-identical output (checked programmatically, not by eye); the
generated `.xlsx` was opened with both `openpyxl` and real LibreOffice and read back correctly;
migration foreign-key ordering was verified across all 19 new/changed files.

## Gotchas worth knowing

* Eloquent snake-cases relation keys in JSON, so the frontend uses `share_links`, `assigned_staff`,
  `notes_log`. Relations that would collide with a column (`created_by`, `notes`) are named
  `creator`, `assignee`, `receiver`, `causer`, `notesLog`.
* Method/route names that are JS reserved words (`export`, `import`) are avoided because Wayfinder
  renames them — hence `exportCsv`/`customers.download` and `importCsv`/`catalog.upload`.
* Toasts use the starter kit's own `Inertia::flash('toast', …)` → `useFlashToast()` pipeline.
* WhatsApp/SMS sharing sends a link to the public share page — no PDF attachment, since neither
  wa.me links nor plain SMS can attach files. 10-digit numbers get the configured country code.
* `.xlsx` export is a small dependency-free writer (needs only PHP's `zip` extension) — no
  PhpSpreadsheet. One sheet, inline strings, bold header/total row, 2-decimal money format.
* The "at least one active admin" and "can't deactivate/delete yourself" rules live in
  `UserController`, not a database constraint — don't bypass it via tinker in production.
* Deactivating a user (`is_active = false`) logs them out on their next request via
  `EnsureUserIsActive`; it doesn't revoke an already-loaded page until they navigate.

## Not built yet
Estimates/quotes that convert to invoices, old-gold exchange as a first-class line type,
installment/advance payment schedules, automated tests, a settings UI for the SMS gateway
(currently `.env` only).

## Where this generalizes beyond jewelry

The part of this app that's specific to jewelry is thin: a handful of columns on `invoice_items`
(HUID, purity, stone carat/clarity/colour) and the `per_gram`/`per_carat` rate bases. Everything
else — customers, invoicing, the **generic charge-type engine** (fixed/%/per-unit, taxable,
item-or-invoice-level), GST, sharing, payments, reports, reminders — is domain-agnostic. Businesses
that quote a base price plus a stack of configurable add-on charges, and need to email/WhatsApp/SMS
a shareable, trackable invoice or estimate, fit this shape well:

- **Tailors / custom clothing** — fabric cost (per metre) + stitching + embroidery + rush charge.
- **Furniture / carpentry / modular kitchens** — material (per sq.ft) + labour + hardware + finish.
- **Interior design & contractors** — materials + labour + design fee %, GST-heavy, needs sharing.
- **Print/signage shops** — paper/material + printing + finishing + design charge, per job.
- **Loose gemstone / diamond dealers** — the per-carat rate basis is already built for exactly this.
- **Auto repair / garages** — parts + labour + GST, itemized per job card, shareable to the customer.
- **Event/catering** — per-head base + add-on services (decor, photography) as invoice-level charges.
- **Solar/appliance installers** — per-watt or per-unit base + installation + AMC as charge types.
- Generally: **any B2B/B2C shop doing itemized quotations** where the line-item math isn't a fixed
  formula (unlike, say, a pure per-unit retail POS) benefits from configurable charge types instead
  of hardcoded columns.

Making one of these concrete (e.g. a tailoring version) mostly means renaming charge types and
item fields in the UI — the schema and calculation engine underneath don't need to change.
