<?php

namespace Database\Seeders;

use App\Enums\AdvanceStatus;
use App\Enums\CatalogStatus;
use App\Enums\DocumentType;
use App\Enums\InstallmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LineType;
use App\Enums\PaymentMethod;
use App\Enums\PricingMode;
use App\Enums\QuotationStatus;
use App\Enums\RateType;
use App\Enums\UserRole;
use App\Models\BusinessSetting;
use App\Models\CatalogItem;
use App\Models\CatalogVariant;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Models\CustomerGroup;
use App\Models\CustomerNote;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceShareLink;
use App\Models\MetalRate;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Reminder;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ChargeTypeSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Plan::ensureDefaults();

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'demo-jewelry-store'],
            [
                'name' => 'Demo Jewelry Store',
                'industry' => 'jewelry',
                'status' => 'active',
                'trial_ends_at' => now()->addDays(14),
            ],
        );

        BusinessSetting::forTenant($tenant->id);

        Subscription::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => Plan::query()->where('slug', 'starter')->firstOrFail()->id,
                'status' => 'trialing',
                'trial_ends_at' => $tenant->trial_ends_at,
            ],
        );

        $admin = User::query()->where('email', 'admin@example.com')->first();

        if (! $admin) {
            $admin = new User([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ]);
            $admin->tenant_id = $tenant->id;
            $admin->email_verified_at = now();
            $admin->save();
        }

        $existingStaff = User::query()->where('tenant_id', $tenant->id)->where('email', '!=', 'admin@example.com')->count();

        if ($existingStaff === 0) {
            $staff = User::factory()->count(3)->create(['tenant_id' => $tenant->id]);

            foreach ($staff as $member) {
                $member->firms()->attach($tenant->id, ['role' => $member->role->value]);
            }
        }

        $admin->firms()->syncWithoutDetaching([$tenant->id => ['role' => UserRole::Admin->value]]);

        // ------------------------------------------------------------------
        // Demo catalog, customers, invoices, payments and CRM history.
        // Created directly so the demo tenant is self-contained.
        // ------------------------------------------------------------------

        // Ensure the industry-specific charge types are present.
        app(ChargeTypeSeeder::class)->seedForTenant($tenant->id, $tenant->industry);

        // Catalog items (with two sellable variants on the lead ring).
        $store = $tenant;
        $adminUser = $admin;

        $goldRing = CatalogItem::firstOrNew(['name' => '22K Gold Ring']);
        $goldRing->fill([
            'name' => '22K Gold Ring',
            'brand' => 'ABC Jewellers',
            'item_code' => 'GR-22K-001',
            'hsn_code' => '7113',
            'metal_type' => 'gold',
            'purity' => '22K',
            'rate_type' => RateType::PerGram,
            'size_label' => '14',
            'default_rate' => 6500,
            'default_gross_weight' => 6.2,
            'default_net_weight' => 5.8,
            'specification' => 'Engagement ring',
            'warranty_months' => 12,
            'stock_tracked' => true,
            'stock_quantity' => 12,
            'reorder_level' => 3,
            'stock_unit' => 'pcs',
            'status' => CatalogStatus::Active,
            'description' => 'Heavy 22K gold ring.',
            'created_by' => $adminUser->id,
        ]);
        $goldRing->tenant_id = $store->id;
        $goldRing->save();

        $goldNecklace = CatalogItem::firstOrNew(['name' => '18K Gold Necklace']);
        $goldNecklace->fill([
            'name' => '18K Gold Necklace',
            'brand' => 'ABC Jewellers',
            'item_code' => 'GN-18K-002',
            'hsn_code' => '7113',
            'metal_type' => 'gold',
            'purity' => '18K',
            'rate_type' => RateType::PerGram,
            'default_rate' => 5900,
            'default_gross_weight' => 22,
            'default_net_weight' => 21,
            'specification' => 'Temple necklace',
            'warranty_months' => 12,
            'stock_tracked' => true,
            'stock_quantity' => 8,
            'reorder_level' => 2,
            'stock_unit' => 'pcs',
            'status' => CatalogStatus::Active,
            'description' => 'Antique temple necklace.',
            'created_by' => $adminUser->id,
        ]);
        $goldNecklace->tenant_id = $store->id;
        $goldNecklace->save();

        $diamondPendant = CatalogItem::firstOrNew(['name' => 'Diamond Solitaire Pendant']);
        $diamondPendant->fill([
            'name' => 'Diamond Solitaire Pendant',
            'brand' => 'ABC Jewellers',
            'item_code' => 'DP-14K-003',
            'hsn_code' => '7113',
            'metal_type' => 'gold',
            'purity' => '14K',
            'rate_type' => RateType::PerPiece,
            'default_rate' => 82000,
            'stock_tracked' => true,
            'stock_quantity' => 5,
            'reorder_level' => 1,
            'stock_unit' => 'pcs',
            'status' => CatalogStatus::Active,
            'description' => '0.45ct solitaire.',
            'created_by' => $adminUser->id,
        ]);
        $diamondPendant->tenant_id = $store->id;
        $diamondPendant->save();

        // Variants for the gold ring
        foreach ((new class
        {
            public function all(): array
            {
                return [['label' => 'Size 12', 'rate' => 6390], ['label' => 'Size 14', 'rate' => 6500], ['label' => 'Size 16', 'rate' => 6670]];
            }
        })->all() as $idx => $variant) {
            $v = CatalogVariant::firstOrNew([
                'catalog_item_id' => $goldRing->id,
                'label' => $variant['label'],
            ]);
            $v->fill([
                'label' => $variant['label'],
                'item_code' => $variant['label'] === 'Size 12' ? 'GR-22K-001-S12' : ($variant['label'] === 'Size 16' ? 'GR-22K-001-S16' : 'GR-22K-001-S14'),
                'rate' => $variant['rate'],
                'stock_quantity' => 4,
                'is_active' => true,
                'sort_order' => $idx,
            ]);
            $v->tenant_id = $store->id;
            $v->save();
        }

        // Customer groups
        $groups = [];
        foreach ([['name' => 'Wholesale', 'discount_percent' => 12, 'sort_order' => 1], ['name' => 'VIP', 'discount_percent' => 20, 'sort_order' => 2]] as $groupAttrs) {
            $cg = CustomerGroup::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $groupAttrs['name']], $groupAttrs);
            $groups[$groupAttrs['name']] = $cg;
        }

        // Customers
        $customerData = [
            ['full_name' => 'pallavi Badole', 'mobile_number' => '+919811112222', 'email' => 'pallavi@example.com', 'customer_group_id' => $groups['VIP']->id ?? null, 'state_code' => '27', 'customer_type' => 'individual'],
            ['full_name' => 'Rajesh Kumar', 'mobile_number' => '+919833334444', 'email' => 'rajesh@example.com', 'customer_group_id' => $groups['Wholesale']->id ?? null, 'state_code' => '29', 'customer_type' => 'business', 'tax_number' => '29AABCU9603R1ZX', 'credit_limit' => 200000],
            ['full_name' => 'Meera Patel', 'mobile_number' => '+919844445555', 'email' => 'meera@example.com', 'state_code' => '24', 'customer_type' => 'individual'],
        ];
        $customers = [];
        foreach ($customerData as $data) {
            $c = Customer::firstOrNew(['email' => $data['email']]);
            $c->fill($data + ['created_by' => $adminUser->id]);
            $c->tenant_id = $store->id;
            $c->save();
            $customers[$c->email] = $c;
        }

        // Metal rates
        foreach ([['metal_type' => 'gold', 'purity' => '22K', 'rate_date' => now()->subDay(), 'rate_per_gram' => 6480], ['metal_type' => 'gold', 'purity' => '18K', 'rate_date' => now()->subDay(), 'rate_per_gram' => 5900], ['metal_type' => 'silver', 'purity' => '92.5', 'rate_date' => now()->subDay(), 'rate_per_gram' => 89]] as $rateData) {
            $mr = MetalRate::firstOrNew(['tenant_id' => $tenant->id, 'metal_type' => $rateData['metal_type'], 'purity' => $rateData['purity'], 'rate_date' => $rateData['rate_date']->format('Y-m-d')]);
            $mr->fill($rateData + ['created_by' => $adminUser->id]);
            $mr->tenant_id = $tenant->id;
            $mr->save();
        }

        // Helper closure inside the seeder to make a full invoice with a spec total.
        $makeInvoice = function (string $documentType, InvoiceStatus $invoiceStatus, string $invoiceNumber, Carbon $invoiceDate, array $itemsSpecs, ?QuotationStatus $quotationStatus = null) use ($store, $customers, $adminUser) {
            $invoice = new Invoice([
                'tenant_id' => $store->id,
                'customer_id' => $customers[array_key_first($customers)]->id ?? Customer::where('tenant_id', $store->id)->first()?->id,
                'document_type' => $documentType,
                'status' => $invoiceStatus,
                'pricing_mode' => PricingMode::Manual,
                'salesperson_id' => $adminUser->id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'due_date' => $documentType === DocumentType::Quotation->value ? $invoiceDate->copy()->addDays(14) : $invoiceDate->copy()->addDays(7),
                'reference_number' => 'REF-'.now()->timestamp,
                'tax_mode' => 'single',
                'notes' => 'Sample order #'.$invoiceNumber,
                'terms' => 'Payment due within 7 days.',
                'created_by' => $adminUser->id,
            ]);

            if ($quotationStatus !== null) {
                $invoice->quotation_status = $quotationStatus;
                $invoice->quotation_valid_until = $invoiceDate->copy()->addDays(14);
                $invoice->rate_locked_at = $invoiceDate;
            }

            $subtotal = 0;
            $taxTotal = 0;
            $invoice->save();

            foreach (array_values($itemsSpecs) as $idx => $itemSpec) {
                $netW = $itemSpec['net_weight'] ?? 0;
                $rate = $itemSpec['rate'];
                $qty = $itemSpec['quantity'] ?? 1;
                $base = $rate * ($itemSpec['rate_type'] === RateType::PerGram ? ($netW ?: 1) : $qty);
                $taxLine = round($base * ($itemSpec['tax_rate'] / 100), 2);
                $total = round($base + $taxLine, 2);

                $li = new InvoiceItem([
                    'invoice_id' => $invoice->id,
                    'sort_order' => $idx,
                    'item_name' => $itemSpec['name'],
                    'line_type' => LineType::Sale,
                    'item_code' => $itemSpec['code'] ?? null,
                    'hsn_code' => '7113',
                    'metal_type' => $itemSpec['metal_type'] ?? null,
                    'purity' => $itemSpec['purity'] ?? null,
                    'quantity' => $qty,
                    'gross_weight' => $itemSpec['gross_weight'] ?? 0,
                    'net_weight' => $netW,
                    'stone_weight' => $itemSpec['stone_weight'] ?? 0,
                    'rate_type' => $itemSpec['rate_type'] ?? RateType::PerPiece,
                    'rate' => $rate,
                    'base_value' => round($base, 2),
                    'discount' => 0,
                    'tax_rate' => $itemSpec['tax_rate'] ?? 3,
                    'tax' => $taxLine,
                    'total' => $total,
                ]);
                $li->tenant_id = $store->id;
                $li->save();

                $subtotal += $base;
                $taxTotal += $taxLine;
            }

            $invoice->subtotal = round($subtotal, 2);
            $invoice->tax = round($taxTotal, 2);
            $invoice->grand_total = round($subtotal + $taxTotal, 2);
            $invoice->balance_amount = $invoice->grand_total;
            $invoice->save();

            return $invoice;
        };

        // Quotation (draft) —
        $quotation = $makeInvoice(
            DocumentType::Quotation->value,
            InvoiceStatus::Draft,
            'QT-2026-0001',
            now()->subMonths(2),
            [
                ['name' => '18K Gold Necklace', 'code' => 'GN-18K-002', 'rate' => 5900, 'rate_type' => RateType::PerGram, 'net_weight' => 21, 'gross_weight' => 22, 'tax_rate' => 3],
            ],
            QuotationStatus::Draft,
        );
        $quotation->update(['status' => InvoiceStatus::Sent]);
        $quotation->update(['quotation_status' => QuotationStatus::Sent]);

        // Quotation accepted, ready to convert
        $acceptedQuote = $makeInvoice(
            DocumentType::Quotation->value,
            InvoiceStatus::Sent,
            'QT-2026-0002',
            now()->subWeeks(2),
            [
                ['name' => 'Diamond Solitaire Pendant', 'code' => 'DP-14K-003', 'rate' => 82000, 'rate_type' => RateType::PerPiece, 'quantity' => 1, 'tax_rate' => 3],
            ],
            QuotationStatus::Accepted,
        );
        $acceptedQuote->update(['status' => InvoiceStatus::Accepted]);
        $acceptedQuote->update(['quotation_status' => QuotationStatus::Accepted]);

        // General invoice — unpaid
        $unpaidInvoice = $makeInvoice(
            DocumentType::JewelryInvoice->value,
            InvoiceStatus::Unpaid,
            'JWL-2026-00001',
            now()->subDays(10),
            [
                ['name' => '22K Gold Ring', 'code' => 'GR-22K-001', 'rate' => 6500, 'rate_type' => RateType::PerGram, 'net_weight' => 5.8, 'gross_weight' => 6.2, 'tax_rate' => 3, 'metal_type' => 'gold', 'purity' => '22K'],
                ['name' => 'Diamond Solitaire Pendant', 'code' => 'DP-14K-003', 'rate' => 82000, 'rate_type' => RateType::PerPiece, 'quantity' => 1, 'tax_rate' => 3],
            ],
        );

        // Installments on unpaid
        $firstInst = new Installment;
        $firstInst->fill(['invoice_id' => $unpaidInvoice->id, 'sequence' => 1, 'due_date' => $unpaidInvoice->due_date, 'amount' => round($unpaidInvoice->grand_total / 2, 2), 'status' => InstallmentStatus::Pending, 'created_by' => $adminUser->id]);
        $firstInst->tenant_id = $store->id;
        $firstInst->save();

        // General invoice — paid
        $paidInvoice = $makeInvoice(
            DocumentType::GeneralInvoice->value,
            InvoiceStatus::Paid,
            'GEN-2026-00001',
            now()->subWeeks(3),
            [
                ['name' => '18K Gold Necklace', 'code' => 'GN-18K-002', 'rate' => 5900, 'rate_type' => RateType::PerGram, 'net_weight' => 10.5, 'gross_weight' => 11, 'tax_rate' => 3, 'metal_type' => 'gold', 'purity' => '18K'],
            ],
        );
        $paidInvoice->update(['paid_amount' => $paidInvoice->grand_total, 'balance_amount' => 0, 'status' => InvoiceStatus::Paid]);

        // Payment forpaid invoice
        $pay = new Payment;
        $pay->fill(['invoice_id' => $paidInvoice->id, 'amount' => $paidInvoice->grand_total, 'payment_date' => $paidInvoice->invoice_date, 'payment_method' => PaymentMethod::Upi, 'reference_number' => 'UPI-20261001-001', 'notes' => 'Bank transfer', 'received_by' => $adminUser->id]);
        $pay->tenant_id = $store->id;
        $pay->save();

        // Partially paid invoice
        $partialInvoice = $makeInvoice(
            DocumentType::GeneralInvoice->value,
            InvoiceStatus::PartiallyPaid,
            'GEN-2026-00002',
            now()->subMonths(1),
            [
                ['name' => '18K Gold Necklace', 'code' => 'GN-18K-002', 'rate' => 5900, 'rate_type' => RateType::PerGram, 'net_weight' => 9, 'gross_weight' => 9.5, 'tax_rate' => 3, 'metal_type' => 'gold', 'purity' => '18K'],
            ],
        );
        $paidPart = round(fmod($partialInvoice->grand_total, 10000), 2);
        $partialInvoice->update(['paid_amount' => $paidPart, 'balance_amount' => $partialInvoice->grand_total - $paidPart, 'status' => InvoiceStatus::PartiallyPaid]);

        $partPay = new Payment;
        $partPay->fill(['invoice_id' => $partialInvoice->id, 'amount' => $paidPart, 'payment_date' => now()->subMonths(1), 'payment_method' => PaymentMethod::Card, 'reference_number' => 'POS-20260901-001', 'received_by' => $adminUser->id]);
        $partPay->tenant_id = $store->id;
        $partPay->save();

        // Share link on paid invoice
        $share = new InvoiceShareLink;
        $share->fill(['invoice_id' => $paidInvoice->id, 'expires_at' => now()->addMonths(3), 'is_active' => true, 'created_by' => $adminUser->id]);
        $share->tenant_id = $store->id;
        $share->token = Str::random(48);
        $share->save();

        // Customer notes on a customer
        $note = new CustomerNote;
        $note->fill(['customer_id' => $customers['pallavi@example.com']->id ?? 1, 'type' => 'call', 'note' => 'Prefers morning appointments.', 'created_by' => $adminUser->id]);
        $note->tenant_id = $store->id;
        $note->save();

        // A customer advance for Meera
        $adv = new CustomerAdvance;
        $adv->fill(['customer_id' => $customers['meera@example.com']->id ?? 1, 'amount' => 10000, 'applied_amount' => 0, 'advance_date' => now()->subWeeks(1), 'payment_method' => PaymentMethod::Cash, 'status' => AdvanceStatus::Available, 'reference_number' => 'ADV-20261001', 'notes' => 'Booking advance', 'created_by' => $adminUser->id]);
        $adv->tenant_id = $store->id;
        $adv->save();

        // A reminder for unpaid invoice
        $rem = new Reminder;
        $rem->fill(['customer_id' => $customers['pallavi@example.com']->id ?? 1, 'assigned_to' => $adminUser->id, 'title' => 'Follow up on invoice JWL-2026-00001', 'notes' => 'Call client for payment update', 'remind_on' => now()->addDays(3), 'is_done' => false, 'created_by' => $adminUser->id]);
        $rem->tenant_id = $store->id;
        $rem->save();
    }
}
