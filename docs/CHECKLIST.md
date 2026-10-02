# Jewelry Invoice + Sharing App — Feature Checklist

Workflow: **Create customer → Create invoice → Generate PDF → Share PDF/link → Track payment**
Status as of 2026-09-30. Legend: ✅ done · 🔶 partial · ⬜ todo

## 1. Login and user management
- [x] Admin login
- [x] Staff login (role-based)
- [x] Forgot password + reset
- [x] Roles: admin / invoice_creator / viewer
- [x] Permissions via policies
- [x] User activity history
- [x] Public signup (business owner registers → tenant + admin account)
- [x] No POS / barcode / cash-register screens anywhere

## 2. Business profile (per tenant)
- [x] Business name, logo, address, phone, email, website
- [x] Tax/GST number, bank details
- [x] Invoice prefix + number start (`JWL-2026-00001` verified)
- [x] Default tax, currency, terms, footer text
- [x] Signature + stamp images

## 3. Customers
- [x] All fields (name, mobile, email, address, tax no., notes, type, staff)
- [x] Profile: invoices, totals, paid/outstanding, last invoice, payments, follow-ups, share history
- [x] Create / edit / search / filter / export CSV / invoice history
- [x] Customer groups: named percentage tiers under settings, assigned per
      customer, badge on the profile, deleting a group frees its customers

## 3a. Catalog and inventory
- [x] Products with a field registry that drives form, validation and CSV/Excel
- [x] Statuses: draft / active / inactive / discontinued + one-click Activate
- [x] Stock opt-in per product, movement ledger, reorder-level low-stock filter
- [x] Product variants (sizes, colours, purities): per-variant code, price and
      stock, product balance is the sum, variant recorded on the invoice line
- [x] CSV + Excel template, import with preview and validation, export

## 4. Invoices
- [x] Info header (number, dates, customer, salesperson, status, reference)
- [x] Jewelry items (metal, purity, weights, stone, charges, discount, tax)
- [x] Full totals incl. round-off, paid, balance
- [x] Manual + jewelry-calculated pricing modes
- [x] Rounding setting (nearest rupee / two decimals) shared by preview, PDF
- [x] TCS added to the total, TDS withheld from the balance due
- [x] Credit and debit notes against an invoice (own series, linked, report-safe)
- [x] Customer group discount fills every unpriced line, server-side, before GST;
      a hand-typed discount always wins and exchange credit is never discounted

## 5. Payments
- [x] Record / partial / full / multiple, date, 6 methods, ref no., notes
- [x] Receipt, remaining balance, statuses (unpaid → refunded)
- [x] Advance receipts: hold money per customer, apply to invoices or refund

## 6. PDF
- [x] Preview / download / print / email / share link / copy / WhatsApp / regenerate / template select
- [x] Logo, weights, charges, tax, payments, balance, terms, signature/stamp, QR-or-link

## 7. Sharing (`/invoice/view/{secure-token}`)
- [x] Guest view / download / print / payment status
- [x] Email / WhatsApp / copy / SMS, expiry, disable, password, sent/viewed/download tracking
- [x] Random token (never sequential IDs)

## 8. CRM
- [x] Follow-ups (5 statuses, assignable) · notes · 5 reminder types

## 9. Dashboard (all 12 widgets)
- [x] Totals, this month, paid/unpaid/partial, sales, collected, outstanding
- [x] Recent invoices/customers, upcoming follow-ups, overdue

## 10. Reports
- [x] All 9 types · date/customer/staff/status filters · PDF/Excel/CSV

## Screens
- [x] `/login` `/dashboard` `/customers` `/customers/create` `/customers/{id}`
- [x] `/invoices` `/invoices/create` `/invoices/{id}` `/invoices/{id}/edit`
      `/invoices/{id}/preview` (dedicated route) · `/follow-ups` (dedicated route)
- [x] `/payments` `/reports` `/settings/business` `/settings/invoice-templates`
      `/settings/users` `/settings/customer-groups` + profile/security/appearance
- [x] `/register` (signup) · `/billing` (subscription) · `/portal/*` (customer portal)

## Boot fix (usePage provider)
- [x] Layouts render inside the Inertia provider via `Component.layout` in `resolve`
- [x] Favicon, SW cache v3, error boundary, toasts, dev-safe SW registration

## Multi-tenancy + SaaS
- [x] `tenants` table, `tenant_id` scoping + global scopes, per-tenant settings/numbering
- [x] Same-tenant policy enforcement, secure cross-tenant share links
- [x] Plans, trials (14d), subscriptions, seat/invoice quotas
- [x] Razorpay checkout + webhooks (Stripe switchable), billing enforcement middleware

## Design
- [x] Flat purple + navy theme (accent `#7C3AED`, mirrored into the PDF template)
- [x] Reskinned app screens + PDF letterhead

## Stability (Phase A)
- [x] PWA service worker dev-safe + cache versioned (no more stale-bundle blank pages)
- [x] Toast notifications wired (backend flashes → sonner)
- [x] Global failure toasts (expired session, server errors, dropped requests) in `app.tsx`
- [x] React error boundary with branded fallback + cache-clear reload
- [x] Dashboard stat overflow fixed
- [x] Ops runbook (`docs/RUNBOOK.md`): SMTP, cron, backups, webhooks, tenancy notes

## Growth (Phase B)
- [x] Quotations + general invoices (`document_type`, QT numbering, draft→convert flow)
- [x] Staff email invites (token links, accept page, revoke, quota-aware)
- [x] Recurring invoices (weekly/monthly/quarterly profiles + `recurring:run`)
- [x] Subscription receipt emails (checkout verify + webhook activation)
- [x] Customer portal (magic-link login, invoice list/detail/PDF, per-customer isolation)
- [x] Per-tenant SMS gateway settings (log/Twilio/custom HTTP, write-only secrets)
- [x] Customer groups with automatic wholesale discount on unpriced invoice lines
