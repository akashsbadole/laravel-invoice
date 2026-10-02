<?php

use App\Http\Controllers\FirmSwitchController;
use App\Http\Controllers\Settings\ActivityLogController;
use App\Http\Controllers\Settings\BusinessController;
use App\Http\Controllers\Settings\ChargeTypeController;
use App\Http\Controllers\Settings\CustomerGroupController;
use App\Http\Controllers\Settings\InvoiceTemplateController;
use App\Http\Controllers\Settings\MetalRateController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\StaffInviteController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Middleware\EnsureIndustryAllows;
use App\Http\Middleware\EnsureSubscribed;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureUserIsActive::class, EnsureSubscribed::class])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/business', [BusinessController::class, 'edit'])->name('business.edit');
    Route::post('settings/business', [BusinessController::class, 'update'])->name('business.update');

    Route::get('settings/charge-types', [ChargeTypeController::class, 'index'])->name('charge-types.index');
    Route::post('settings/charge-types', [ChargeTypeController::class, 'store'])->name('charge-types.store');
    Route::put('settings/charge-types/{chargeType}', [ChargeTypeController::class, 'update'])->name('charge-types.update');
    Route::delete('settings/charge-types/{chargeType}', [ChargeTypeController::class, 'destroy'])->name('charge-types.destroy');

    Route::get('settings/customer-groups', [CustomerGroupController::class, 'index'])->name('customer-groups.index');
    Route::post('settings/customer-groups', [CustomerGroupController::class, 'store'])->name('customer-groups.store');
    Route::put('settings/customer-groups/{customerGroup}', [CustomerGroupController::class, 'update'])->name('customer-groups.update');
    Route::delete('settings/customer-groups/{customerGroup}', [CustomerGroupController::class, 'destroy'])->name('customer-groups.destroy');

    Route::get('settings/users', [UserController::class, 'index'])->name('users.index');
    Route::post('settings/users', [UserController::class, 'store'])->name('users.store');
    Route::put('settings/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('settings/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('settings/activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

    Route::post('settings/invites', [StaffInviteController::class, 'store'])->name('invites.store');
    Route::delete('settings/invites/{invite}', [StaffInviteController::class, 'destroy'])->name('invites.destroy');

    Route::post('settings/firms/switch', [FirmSwitchController::class, 'switch'])->name('firms.switch');

    Route::middleware(EnsureIndustryAllows::class.':metal_rates')->group(function () {
        Route::get('settings/metal-rates', [MetalRateController::class, 'index'])->name('metal-rates.index');
        Route::post('settings/metal-rates', [MetalRateController::class, 'store'])->name('metal-rates.store');
        Route::delete('settings/metal-rates/{metalRate}', [MetalRateController::class, 'destroy'])->name('metal-rates.destroy');
    });

    Route::get('settings/invoice-templates', [InvoiceTemplateController::class, 'index'])->name('invoice-templates.index');
    Route::post('settings/invoice-templates', [InvoiceTemplateController::class, 'store'])->name('invoice-templates.store');
    Route::put('settings/invoice-templates/{invoiceTemplate}', [InvoiceTemplateController::class, 'update'])->name('invoice-templates.update');
    Route::post('settings/invoice-templates/{invoiceTemplate}/default', [InvoiceTemplateController::class, 'setDefault'])->name('invoice-templates.default');
    Route::delete('settings/invoice-templates/{invoiceTemplate}', [InvoiceTemplateController::class, 'destroy'])->name('invoice-templates.destroy');
});

Route::middleware(['auth', EnsureUserIsActive::class, EnsureSubscribed::class])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

/* @chisel-passkeys */
Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
/* @end-chisel-passkeys */
