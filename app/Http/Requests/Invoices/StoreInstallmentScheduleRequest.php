<?php

namespace App\Http\Requests\Invoices;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

/**
 * A payment plan must add up to the invoice balance, otherwise the schedule
 * and the money owed contradict each other.
 */
class StoreInstallmentScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canWrite();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'installments' => ['required', 'array', 'min:1', 'max:24'],
            'installments.*.due_date' => ['required', 'date'],
            'installments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'installments.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Collection<int, array<string,mixed>> $slices */
            $slices = collect($this->input('installments', []));

            if ($slices->isEmpty()) {
                return;
            }

            if ($slices->pluck('due_date')->filter()->map(fn ($date) => strtotime((string) $date))->duplicates()->isNotEmpty()) {
                $validator->errors()->add('installments', 'Each installment needs its own due date.');
            }

            $invoice = $this->route('invoice');

            if (! $invoice instanceof Invoice) {
                return;
            }

            // Amounts are rounded per slice, so compare against the sum of
            // the rounded values rather than the raw input.
            $planned = round($slices->sum(fn (array $slice) => round((float) ($slice['amount'] ?? 0), 2)), 2);
            $balance = round((float) $invoice->balance_amount, 2);

            if (abs($planned - $balance) > 0.01) {
                $validator->errors()->add(
                    'installments',
                    "The plan totals {$planned} but the invoice balance is {$balance}.",
                );
            }
        });
    }
}
