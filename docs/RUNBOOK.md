# Operations Runbook — Jewelry Invoice SaaS

## Environments
- Local dev: `php artisan serve --port=8000` + `npm run dev` (creates `public/hot`).
- Production build: `npm run build` (used automatically when `public/hot` is absent).
  Never deploy with `public/hot` present — delete it after stopping Vite.

## If the UI shows a blank page or stale layout after a deploy
1. Hard-refresh: Ctrl+Shift+R (the PWA service worker caches the app shell).
2. If it persists: DevTools → Application → Storage → Clear site data → reload.
3. Verify the served bundle matches the manifest:
   `manifest.json` (`public/build/manifest.json`) ↔ `assets/app-*.js` referenced in page HTML.

## Mail (SMTP)
Set in `.env` (default `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log`):
```env
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="billing@yourdomain.com"
MAIL_FROM_NAME="Jewelry Invoice"
```
Test: create an invoice → Send via email → check inbox (or the log file with `log` driver).

## Scheduler (reminders + overdue flags)
Add this cron entry (runs every minute; commands self-schedule):
```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```
Covered jobs:
- `reminders:send` — staff digest + customer SMS (per tenant; honors each tenant's SMS toggles)
- `invoices:mark-overdue` — flags past-due unpaid invoices
- `recurring:run` — generates recurring invoices due today

Verify: `php artisan schedule:list`.

## Billing (Razorpay)
```env
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=
BILLING_TRIAL_DAYS=14
```
- Configure the webhook URL `https://yourdomain.com/billing/webhook` in the Razorpay dashboard
  with events `payment.captured` and `payment.failed`.
- Webhook route is CSRF-exempt by design (`bootstrap/app.php`); authenticity comes from
  the Razorpay webhook signature.
- Trials: 14 days on the Starter plan by default. Expired tenants are redirected to `/billing`
  (public share links keep working).

## Backups
Nightly database dump + `storage/app` snapshot, e.g.:
```bash
mysqldump -u root laravel-jewelry-only-invoice-app | gzip > backup-$(date +%F).sql.gz
```
Keep `APP_KEY` and `.env` somewhere safe — encrypted data (sessions, reset tokens) cannot be
recovered without the key.

## Multi-tenancy notes
- All business data is scoped by `tenant_id` (global scope). Console/queue code must wrap
  tenant work in `Tenant::runInContext($id, fn () => ...)`.
- Guest share links (`/invoice/view/{token}`) intentionally bypass scoping via unguessable tokens.
- Email addresses are globally unique so login stays unambiguous across tenants.
