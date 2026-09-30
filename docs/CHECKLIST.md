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

## 4. Invoices
- [x] Info header (number, dates, customer, salesperson, status, reference)
- [x] Jewelry items (metal, purity, weights, stone, charges, discount, tax)
- [x] Full totals incl. round-off, paid, balance
- [x] Manual + jewelry-calculated pricing modes

## 5. Payments
- [x] Record / partial / full / multiple, date, 6 methods, ref no., notes
- [x] Receipt, remaining balance, statuses (unpaid → refunded)

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
      (show page doubles as preview; reminders screen covers follow-ups)
- [x] `/payments` `/reports` `/settings/business` `/settings/invoice-templates`
      `/settings/users` + profile/security/appearance
- [x] `/register` (signup) · `/billing` (subscription)

## Multi-tenancy + SaaS
- [x] `tenants` table, `tenant_id` scoping + global scopes, per-tenant settings/numbering
- [x] Same-tenant policy enforcement, secure cross-tenant share links
- [x] Plans, trials (14d), subscriptions, seat/invoice quotas
- [x] Razorpay checkout + webhooks (Stripe switchable), billing enforcement middleware

## Design
- [x] Luxury jewelry-house theme (emerald + champagne gold, serif display)
- [x] Reskinned app screens + luxury PDF letterhead
