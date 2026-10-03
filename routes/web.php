<?php

use App\Http\Controllers\Admin\PlatformController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\CatalogItemImportController;
use App\Http\Controllers\CustomerAdvanceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerFollowupController;
use App\Http\Controllers\CustomerNoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\InviteAcceptController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\InvoiceShareLinkController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortalAuthController;
use App\Http\Controllers\PortalInvoiceController;
use App\Http\Controllers\PublicInvoiceController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\QuotationFollowUpController;
use App\Http\Controllers\QuotationPipelineController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Middleware\EnsurePortalCustomer;
use App\Http\Middleware\EnsureSubscribed;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Public marketing pages. Reachable by guests, and also useful to signed-in
// users who want to re-read what the product does.
//
// The industry list is read from config so the marketing page cannot claim a
// trade the product does not support, or omit one it does.
Route::inertia('/features', 'features', [
    'industries' => array_values(array_map(
        fn (array $config, string $key) => [
            'key' => $key,
            'name' => $config['label'],
            'note' => $config['description'],
        ],
        config('industries'),
        array_keys(config('industries')),
    )),
])->name('features');

Route::inertia('/docs', 'docs')->name('docs');

Route::get('/contact', fn () => inertia('contact', [
    'support' => config('billing.support'),
]))->name('contact');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->middleware('throttle:6,1');
    Route::get('forgot-password', [AuthController::class, 'forgot'])->name('password.request');
    Route::post('forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:6,1');
    Route::get('reset-password/{token}', [AuthController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [AuthController::class, 'updatePassword'])->name('password.update')->middleware('throttle:6,1');
});

Route::post('logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:6,1');
});

// Invite links work for guests (new account) and logged-in users (join firm),
// so they stay outside the guest group; the controller branches on auth state.
Route::get('invites/accept/{token}', [InviteAcceptController::class, 'create'])->name('invites.accept');
Route::post('invites/accept/{token}', [InviteAcceptController::class, 'store'])->middleware('throttle:6,1');

Route::post('billing/webhook', [BillingController::class, 'webhook'])->name('billing.webhook');

// Customer self-service portal (magic-link login, separate from staff auth).
Route::get('portal/login', [PortalAuthController::class, 'create'])->name('portal.login');
Route::post('portal/login', [PortalAuthController::class, 'store'])->middleware('throttle:6,1');
Route::get('portal/verify/{token}', [PortalAuthController::class, 'verify'])->name('portal.verify');

Route::middleware([EnsurePortalCustomer::class])->group(function () {
    Route::post('portal/logout', [PortalAuthController::class, 'destroy'])->name('portal.logout');
    Route::get('portal', [PortalInvoiceController::class, 'index'])->name('portal.dashboard');
    Route::get('portal/invoices/{invoice}', [PortalInvoiceController::class, 'show'])->name('portal.invoices.show');
    Route::get('portal/invoices/{invoice}/pdf', [PortalInvoiceController::class, 'pdf'])->name('portal.invoices.pdf');
});

// Public, unauthenticated invoice sharing — no auth/verified middleware.
// Matches the spec's exact public URL shape: /invoice/view/{token}.
Route::get('invoice/view/{token}', [PublicInvoiceController::class, 'show'])->name('invoices.public.show');
Route::post('invoice/view/{token}/verify', [PublicInvoiceController::class, 'verifyPassword'])->middleware('throttle:20,1')->name('invoices.public.verify');
Route::get('invoice/view/{token}/pdf', [InvoicePdfController::class, 'public'])->name('invoices.public.pdf');
// The customer accepting a quotation is a guest action. This sat inside the
// auth group, so a real customer was redirected to /login and the headline
// quotation feature never worked for the person it was built for.
Route::post('invoice/view/{token}/decide', [PublicInvoiceController::class, 'decide'])->middleware('throttle:20,1')->name('invoices.public.decide');
Route::post('invoice/view/{token}/changes', [PublicInvoiceController::class, 'requestChanges'])->middleware('throttle:20,1')->name('invoices.public.changes');

Route::middleware(['auth', EnsureTenantUser::class, EnsureUserIsActive::class, EnsureSubscribed::class])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::post('billing/verify', [BillingController::class, 'verify'])->name('billing.verify');
    Route::post('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');

    Route::get('customers/export', [CustomerController::class, 'exportCsv'])->name('customers.download');
    Route::resource('customers', CustomerController::class);

    Route::post('customers/{customer}/notes', [CustomerNoteController::class, 'store'])->name('customers.notes.store');

    Route::post('customers/{customer}/advances', [CustomerAdvanceController::class, 'store'])->name('customers.advances.store');
    Route::post('customers/{customer}/advances/{advance}/refund', [CustomerAdvanceController::class, 'refund'])->name('customers.advances.refund');

    Route::post('customers/{customer}/followups', [CustomerFollowupController::class, 'store'])->name('customers.followups.store');
    Route::put('customers/{customer}/followups/{followup}', [CustomerFollowupController::class, 'update'])->name('customers.followups.update');
    Route::delete('customers/{customer}/followups/{followup}', [CustomerFollowupController::class, 'destroy'])->name('customers.followups.destroy');

    // The product catalog is a day-to-day working surface (quotations are
    // built from it), so it is a top-level section rather than a settings
    // screen.
    Route::get('catalog/create', [CatalogItemController::class, 'create'])->name('catalog.create');
    Route::get('catalog', [CatalogItemController::class, 'index'])->name('catalog.index');
    Route::post('catalog', [CatalogItemController::class, 'store'])->name('catalog.store');
    Route::get('catalog/template', [CatalogItemImportController::class, 'template'])->name('catalog.template');
    Route::get('catalog/export', [CatalogItemImportController::class, 'exportCsv'])->name('catalog.export');
    Route::post('catalog/import', [CatalogItemImportController::class, 'importCsv'])->name('catalog.upload');
    Route::get('catalog/excel/template', [CatalogItemImportController::class, 'excelTemplate'])->name('catalog.excel-template');
    Route::get('catalog/excel/export', [CatalogItemImportController::class, 'exportExcel'])->name('catalog.excel-export');
    Route::get('catalog/excel/preview', [CatalogItemImportController::class, 'excelPreview'])->name('catalog.excel-preview');
    Route::post('catalog/excel/preview', [CatalogItemImportController::class, 'excelPreviewUpload'])->name('catalog.excel-preview-upload');
    Route::post('catalog/excel/import', [CatalogItemImportController::class, 'importExcel'])->name('catalog.excel-import');
    Route::post('catalog/{catalogItem}/stock', [CatalogItemController::class, 'adjustStock'])->name('catalog.stock');
    Route::post('catalog/{catalogItem}/activate', [CatalogItemController::class, 'activate'])->name('catalog.activate');
    Route::post('catalog/activate', [CatalogItemController::class, 'activateSelected'])->name('catalog.activate-selected');
    Route::put('catalog/{catalogItem}', [CatalogItemController::class, 'update'])->name('catalog.update');
    Route::delete('catalog/{catalogItem}', [CatalogItemController::class, 'destroy'])->name('catalog.destroy');

    Route::get('quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    Route::post('quotations/draft', [QuotationController::class, 'draft'])->name('quotations.draft');
    // The pipeline board: what is open, what it is worth, and what to chase.
    Route::get('quotations', [QuotationPipelineController::class, 'index'])->name('quotations.index');
    Route::post('quotations/{quotation}/nudge', [QuotationFollowUpController::class, 'store'])->name('quotations.nudge');

    Route::resource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::post('invoices/{invoice}/notes', [InvoiceController::class, 'storeNote'])->name('invoices.notes.store');
    Route::post('invoices/{invoice}/convert', [InvoiceController::class, 'convert'])->name('invoices.convert');
    Route::post('invoices/{invoice}/approve-discount', [InvoiceController::class, 'approveDiscount'])->name('invoices.approve-discount');
    Route::post('invoices/{invoice}/quotation-status', [InvoiceController::class, 'quotationStatus'])->name('invoices.quotation-status');
    Route::post('invoices/{invoice}/einvoice', [InvoiceController::class, 'generateEInvoice'])->name('invoices.einvoice');
    Route::post('invoices/{invoice}/recurring', [RecurringInvoiceController::class, 'store'])->name('invoices.recurring.store');
    Route::delete('invoices/{invoice}/recurring/{profile}', [RecurringInvoiceController::class, 'destroy'])->name('invoices.recurring.destroy');
    Route::post('invoices/{invoice}/send-email', [InvoiceController::class, 'sendEmail'])->name('invoices.send-email');
    Route::get('invoices/{invoice}/pdf', [InvoicePdfController::class, 'show'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
    Route::get('invoices/{invoice}/receipt', [ReceiptController::class, 'invoice'])->name('invoices.receipt');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}/receipt', [ReceiptController::class, 'payment'])->name('payments.receipt');
    Route::post('invoices/{invoice}/installments', [InstallmentController::class, 'store'])->name('invoices.installments.store');
    Route::post('invoices/{invoice}/installments/{installment}/collect', [InstallmentController::class, 'collect'])->name('invoices.installments.collect');
    Route::delete('invoices/{invoice}/installments/{installment}', [InstallmentController::class, 'destroy'])->name('invoices.installments.destroy');
    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
    Route::post('invoices/{invoice}/advances/apply', [CustomerAdvanceController::class, 'apply'])->name('invoices.advances.apply');
    Route::post('invoices/{invoice}/remind', [PaymentController::class, 'remind'])->name('invoices.remind');

    Route::post('invoices/{invoice}/share-links', [InvoiceShareLinkController::class, 'store'])->name('invoices.share-links.store');
    Route::post('invoices/{invoice}/share-links/{shareLink}/deactivate', [InvoiceShareLinkController::class, 'deactivate'])->name('invoices.share-links.deactivate');
    Route::post('invoices/{invoice}/share-links/{shareLink}/mark-sent', [InvoiceShareLinkController::class, 'markSent'])->name('invoices.share-links.mark-sent');
    Route::post('invoices/{invoice}/share-links/{shareLink}/sms', [InvoiceShareLinkController::class, 'sendSms'])->name('invoices.share-links.sms');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/download', [ReportController::class, 'download'])->name('reports.download');
    Route::get('reports/gstr-1', [ReportController::class, 'gstr1'])->name('reports.gstr1');
    Route::get('reports/gstr-3b', [ReportController::class, 'gstr3b'])->name('reports.gstr3b');

    Route::get('reminders', [ReminderController::class, 'index'])->name('reminders.index');

    Route::post('reminders', [ReminderController::class, 'store'])->name('reminders.store');
    Route::post('reminders/{reminder}/done', [ReminderController::class, 'done'])->name('reminders.done');
    Route::delete('reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');
    Route::post('reminders/invoices/{invoice}/sms', [ReminderController::class, 'sendPaymentSms'])->name('reminders.payment-sms');
    Route::post('reminders/customers/{customer}/occasion-sms', [ReminderController::class, 'sendOccasionSms'])->name('reminders.occasion-sms');
});

require __DIR__.'/settings.php';

/*
|--------------------------------------------------------------------------
| Platform administration
|--------------------------------------------------------------------------
|
| The super admin has no tenant of their own, so this section is mounted
| outside the tenant-scoped group and outside the subscription gate.
|
*/

Route::middleware(['auth', EnsureSuperAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [PlatformController::class, 'dashboard'])->name('dashboard');
        Route::get('tenants', [PlatformController::class, 'tenants'])->name('tenants.index');
        Route::get('tenants/{tenant}', [PlatformController::class, 'showTenant'])->name('tenants.show');
        Route::post('tenants/{tenant}/toggle-status', [PlatformController::class, 'toggleTenantStatus'])->name('tenants.toggle-status');
        Route::put('tenants/{tenant}/subscription', [PlatformController::class, 'updateSubscription'])->name('tenants.subscription.update');
        Route::post('tenants/{tenant}/impersonate', [PlatformController::class, 'impersonate'])->name('tenants.impersonate');

        Route::get('users', [PlatformController::class, 'users'])->name('users.index');
        Route::post('users/{user}/toggle-active', [PlatformController::class, 'toggleUser'])->name('users.toggle-active');

        Route::get('plans', [PlatformController::class, 'plans'])->name('plans.index');
        Route::put('plans/{plan}', [PlatformController::class, 'updatePlan'])->name('plans.update');

        Route::get('activity', [PlatformController::class, 'activity'])->name('activity.index');
    });

// Outside the super-admin gate on purpose: while impersonating, the signed-in
// user is the tenant's staff, so this is the only route that can end it.
Route::middleware('auth')
    ->post('admin/stop-impersonating', [PlatformController::class, 'stopImpersonating'])
    ->name('admin.impersonation.stop');
