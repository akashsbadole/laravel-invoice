<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reminders\StoreReminderRequest;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ReminderService;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController extends Controller
{
    public function index(ReminderService $service): Response
    {
        $data = $service->gather();

        return Inertia::render('reminders', [
            'payments' => $data['payments'],
            'followups' => $data['followups'],
            'birthdays' => $data['birthdays'],
            'anniversaries' => $data['anniversaries'],
            'custom' => $data['custom'],
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('full_name')->get(['id', 'full_name']),
            'businessName' => BusinessSetting::current()->business_name,
            'smsDriver' => config('sms.driver'),
        ]);
    }

    public function followups(ReminderService $service): Response
    {
        return $this->index($service);
    }

    public function store(StoreReminderRequest $request): RedirectResponse
    {
        Reminder::create([...$request->validated(), 'created_by' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder added.')]);

        return back();
    }

    public function done(Request $request, Reminder $reminder): RedirectResponse
    {
        abort_unless($request->user()->role->canWrite(), 403);

        $reminder->update(['is_done' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marked as done.')]);

        return back();
    }

    public function destroy(Request $request, Reminder $reminder): RedirectResponse
    {
        abort_unless($request->user()->role->canWrite(), 403);

        $reminder->delete();

        return back();
    }

    public function sendPaymentSms(Request $request, Invoice $invoice, SmsService $sms): RedirectResponse
    {
        Gate::authorize('share', $invoice);

        $invoice->loadMissing('customer');
        $business = BusinessSetting::current();
        $due = $invoice->due_date ? ' (due '.$invoice->due_date->format('d M').')' : '';

        $message = "Dear {$invoice->customer->full_name}, invoice {$invoice->invoice_number} has Rs."
            .number_format((float) $invoice->balance_amount, 2)." pending{$due}. Please pay at your earliest. - {$business->business_name}";

        $link = $invoice->shareLinks()->where('is_active', true)->latest()->first();
        if ($link && $link->isUsable() && ! $link->password_hash) {
            $message .= ' '.route('invoices.public.show', $link->token);
        }

        $log = $sms->send($invoice->customer->mobile_number, $message, [
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'created_by' => $request->user()->id,
        ]);

        if ($log->status !== 'failed') {
            $invoice->forceFill(['last_reminder_sent_at' => now()])->saveQuietly();
        }

        Inertia::flash('toast', SmsService::toast($log));

        return back();
    }

    public function sendOccasionSms(Request $request, Customer $customer, SmsService $sms): RedirectResponse
    {
        abort_unless($request->user()->role->canWrite(), 403);

        $business = BusinessSetting::current();
        $message = $request->input('occasion') === 'anniversary'
            ? "Happy Anniversary {$customer->full_name}! Wishing you many more happy years together. - {$business->business_name}"
            : "Happy Birthday {$customer->full_name}! Wishing you a wonderful year ahead. - {$business->business_name}";

        $log = $sms->send($customer->mobile_number, $message, [
            'customer_id' => $customer->id,
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', SmsService::toast($log));

        return back();
    }
}
