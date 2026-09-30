<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerFollowupController;
use App\Http\Controllers\CustomerNoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\InvoiceShareLinkController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PublicInvoiceController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->middleware('throttle:6,1');
    Route::get('forgot-password', [AuthController::class, 'forgot'])->name('password.request');
    Route::post('forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:6,1');
    Route::get('reset-password/{token}', [AuthController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [AuthController::class, 'updatePassword'])->name('password.update')->middleware('throttle:6,1');
});

Route::post('logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

// Public, unauthenticated invoice sharing — no auth/verified middleware.
// Matches the spec's exact public URL shape: /invoice/view/{token}.
Route::get('invoice/view/{token}', [PublicInvoiceController::class, 'show'])->name('invoices.public.show');
Route::post('invoice/view/{token}/verify', [PublicInvoiceController::class, 'verifyPassword'])->name('invoices.public.verify');
Route::get('invoice/view/{token}/pdf', [InvoicePdfController::class, 'public'])->name('invoices.public.pdf');

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('customers/export', [CustomerController::class, 'exportCsv'])->name('customers.download');
    Route::resource('customers', CustomerController::class);

    Route::post('customers/{customer}/notes', [CustomerNoteController::class, 'store'])->name('customers.notes.store');

    Route::post('customers/{customer}/followups', [CustomerFollowupController::class, 'store'])->name('customers.followups.store');
    Route::put('customers/{customer}/followups/{followup}', [CustomerFollowupController::class, 'update'])->name('customers.followups.update');

    Route::resource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::get('invoices/{invoice}/pdf', [InvoicePdfController::class, 'show'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/receipt', [ReceiptController::class, 'invoice'])->name('invoices.receipt');

    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}/receipt', [ReceiptController::class, 'payment'])->name('payments.receipt');
    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');

    Route::post('invoices/{invoice}/share-links', [InvoiceShareLinkController::class, 'store'])->name('invoices.share-links.store');
    Route::post('invoices/{invoice}/share-links/{shareLink}/deactivate', [InvoiceShareLinkController::class, 'deactivate'])->name('invoices.share-links.deactivate');
    Route::post('invoices/{invoice}/share-links/{shareLink}/mark-sent', [InvoiceShareLinkController::class, 'markSent'])->name('invoices.share-links.mark-sent');
    Route::post('invoices/{invoice}/share-links/{shareLink}/sms', [InvoiceShareLinkController::class, 'sendSms'])->name('invoices.share-links.sms');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/download', [ReportController::class, 'download'])->name('reports.download');

    Route::get('reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::post('reminders', [ReminderController::class, 'store'])->name('reminders.store');
    Route::post('reminders/{reminder}/done', [ReminderController::class, 'done'])->name('reminders.done');
    Route::delete('reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');
    Route::post('reminders/invoices/{invoice}/sms', [ReminderController::class, 'sendPaymentSms'])->name('reminders.payment-sms');
    Route::post('reminders/customers/{customer}/occasion-sms', [ReminderController::class, 'sendOccasionSms'])->name('reminders.occasion-sms');
});

require __DIR__.'/settings.php';
