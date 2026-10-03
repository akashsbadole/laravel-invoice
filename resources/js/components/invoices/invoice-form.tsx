import { router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo } from 'react';
import InputError from '@/components/input-error';
import { AttributesField } from '@/components/attributes-editor';
import InvoiceItemEditor from '@/components/invoices/invoice-item-editor';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { computeInvoice } from '@/lib/invoice-calculations';
import type { RoundingMode } from '@/lib/invoice-calculations';
import type { IndustryConfig } from '@/lib/industries';
import type { Customer, Staff } from '@/types/customer';
import type {
    CatalogItem,
    ChargeType,
    InvoiceFormData,
    InvoiceItemForm,
    InvoiceTemplateOption,
    MetalRate,
    RateType,
} from '@/types/invoice';

const currency = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });

function blankItem(defaultTaxRate = 0, rateType: RateType = 'per_gram'): InvoiceItemForm {
    return {
        key: crypto.randomUUID(),
        item_name: '',
        line_type: 'sale',
        description: '',
        item_code: '',
        catalog_item_id: null,
        catalog_variant_id: null,
        hsn_code: '',
        brand: '',
        model_number: '',
        serial_number: '',
        warranty_months: null,
        size_label: '',
        finish: '',
        grade: '',
        specification: '',
        batch_number: '',
        length: null,
        width: null,
        height: null,
        wastage_percent: null,
        boxes: null,
        attributes: {},
        metal_type: '',
        purity: '',
        huid_number: '',
        stone_clarity: '',
        stone_color: '',
        stone_carat: 0,
        certificate_number: '',
        quantity: 1,
        gross_weight: 0,
        net_weight: 0,
        stone_weight: 0,
        rate_type: rateType,
        rate: 0,
        discount: 0,
        tax_rate: defaultTaxRate,
        charges: [],
    };
}

export function newInvoiceItem(
    defaultTaxRate = 0,
    rateType: RateType = 'per_gram',
): InvoiceItemForm {
    return blankItem(defaultTaxRate, rateType);
}

export default function InvoiceForm({
    mode,
    invoiceId,
    initialData,
    customers,
    staff,
    chargeTypes,
    catalogItems,
    invoiceTemplates,
    invoiceNumberPreview,
    defaultItemTaxRate = 0,
    metalRates,
    industryConfig,
    businessStateCode,
    roundingMode = 'nearest_rupee',
    /** The quotation has been shown to the customer, so the next save is a new revision. */
    isSentQuotation = false,
}: {
    mode: 'create' | 'edit';
    invoiceId?: number;
    initialData: InvoiceFormData;
    customers: Customer[];
    staff: Staff[];
    chargeTypes: ChargeType[];
    catalogItems: CatalogItem[];
    invoiceTemplates: InvoiceTemplateOption[];
    invoiceNumberPreview: string;
    defaultItemTaxRate?: number;
    metalRates: MetalRate[];
    industryConfig: IndustryConfig;
    businessStateCode?: string | null;
    /** Business-level rounding rule so the preview matches the server. */
    roundingMode?: RoundingMode;
    isSentQuotation?: boolean;
}) {
    const { data, setData, post, put, processing, errors } = useForm<InvoiceFormData>(initialData);

    const customerStateCode = customers.find((c) => c.id === data.customer_id)?.state_code ?? null;
    // What this customer's group takes off every line the shopkeeper leaves
    // unpriced. The server resolves the same figure from the customer's own
    // group when the document is stored.
    const groupDiscountPercent =
        customers.find((c) => c.id === data.customer_id)?.group_discount_percent ?? 0;
    const suggestedTaxMode = businessStateCode && customerStateCode
        ? (businessStateCode === customerStateCode ? 'cgst_sgst' : 'igst')
        : null;

    const itemChargeTypes = useMemo(
        () => chargeTypes.filter((ct) => ct.applies_to === 'item'),
        [chargeTypes],
    );
    const invoiceChargeTypes = useMemo(
        () => chargeTypes.filter((ct) => ct.applies_to === 'invoice'),
        [chargeTypes],
    );

    // A jewelry tenant can still issue a general invoice, so "jewelry invoice"
    // is offered to everyone but only highlighted for jewelry tenants.
    const documentTypes = useMemo(() => {
        const options = [
            { value: 'jewelry_invoice' as const, label: 'Jewelry invoice' },
            { value: 'general_invoice' as const, label: 'Sales invoice' },
            { value: 'quotation' as const, label: 'Quotation' },
            { value: 'delivery_challan' as const, label: 'Delivery challan' },
        ];

        return industryConfig.uses_weight_fields
            ? options
            : options.filter((option) => option.value !== 'jewelry_invoice');
    }, [industryConfig.uses_weight_fields]);

    const totals = useMemo(
        () =>
            computeInvoice(
                data.items,
                data.invoice_charges,
                data.pricing_mode,
                chargeTypes,
                data.discount || 0,
                data.tax_rate || 0,
                data.tax_mode,
                roundingMode,
                data.tcs_rate || 0,
                data.tds_rate || 0,
                groupDiscountPercent,
            ),
        [
            data.items,
            data.invoice_charges,
            data.pricing_mode,
            data.discount,
            data.tax_rate,
            data.tax_mode,
            data.tcs_rate,
            data.tds_rate,
            groupDiscountPercent,
            roundingMode,
            chargeTypes,
        ],
    );

    function itemErrors(index: number): Record<string, string> {
        const prefix = `items.${index}.`;
        const result: Record<string, string> = {};
        for (const [key, message] of Object.entries(errors)) {
            if (key.startsWith(prefix) && message) {
                result[key.slice(prefix.length)] = message as string;
            }
        }
        return result;
    }

    function updateItem(index: number, patch: Partial<InvoiceItemForm>) {
        setData(
            'items',
            data.items.map((item, i) => (i === index ? { ...item, ...patch } : item)),
        );
    }

    function addItem() {
        setData('items', [
            ...data.items,
            blankItem(defaultItemTaxRate, industryConfig.rate_types[0] ?? 'per_piece'),
        ]);
    }

    function removeItem(index: number) {
        setData('items', data.items.filter((_, i) => i !== index));
    }

    function toggleInvoiceCharge(chargeTypeId: number, enabled: boolean) {
        if (enabled) {
            const defaultRate = Number(
                chargeTypes.find((ct) => ct.id === chargeTypeId)?.default_rate ?? 0,
            );
            setData('invoice_charges', [
                ...data.invoice_charges,
                { charge_type_id: chargeTypeId, rate: defaultRate },
            ]);
        } else {
            setData(
                'invoice_charges',
                data.invoice_charges.filter((c) => c.charge_type_id !== chargeTypeId),
            );
        }
    }

    function submit() {
        if (mode === 'create') {
            post('/invoices', { preserveScroll: true });
        } else {
            put(`/invoices/${invoiceId}`, { preserveScroll: true });
        }
    }

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Invoice details</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label>Invoice number</Label>
                            <Input
                                value={mode === 'create' ? `${invoiceNumberPreview} (auto)` : invoiceNumberPreview}
                                disabled
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="customer_id">Customer</Label>
                            <Select
                                value={data.customer_id ? String(data.customer_id) : undefined}
                                onValueChange={(value) => setData('customer_id', Number(value))}
                            >
                                <SelectTrigger id="customer_id" className="w-full">
                                    <SelectValue placeholder="Select a customer" />
                                </SelectTrigger>
                                <SelectContent>
                                    {customers.map((customer) => (
                                        <SelectItem key={customer.id} value={String(customer.id)}>
                                            {customer.full_name} — {customer.mobile_number}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.customer_id} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="invoice_date">Invoice date</Label>
                            <Input
                                id="invoice_date"
                                type="date"
                                value={data.invoice_date}
                                onChange={(e) => setData('invoice_date', e.target.value)}
                            />
                            <InputError message={errors.invoice_date} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="due_date">Due date (optional)</Label>
                            <Input
                                id="due_date"
                                type="date"
                                value={data.due_date}
                                onChange={(e) => setData('due_date', e.target.value)}
                            />
                            <InputError message={errors.due_date} />
                        </div>
                        {/* Only a quotation can lapse, so the validity
                            window is meaningless on an invoice. */}
                        {data.document_type === 'quotation' && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="quotation_valid_until">
                                    Valid until (optional)
                                </Label>
                                <Input
                                    id="quotation_valid_until"
                                    type="date"
                                    value={data.quotation_valid_until ?? ''}
                                    onChange={(e) =>
                                        setData(
                                            'quotation_valid_until',
                                            e.target.value,
                                        )
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Marked expired automatically once this date
                                    passes.
                                </p>
                                <InputError
                                    message={errors.quotation_valid_until}
                                />
                            </div>
                        )}
                        {/* A quotation's value depends on the metal rate
                            struck the day it was priced, so it needs an
                            honest "as on" stamp staff can override. */}
                        {(data.document_type === 'quotation' || data.document_type === 'jewelry_invoice') && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="rate_locked_at">
                                    Metal rate as on (optional)
                                </Label>
                                <Input
                                    id="rate_locked_at"
                                    type="date"
                                    value={data.rate_locked_at ?? ''}
                                    onChange={(e) => setData('rate_locked_at', e.target.value)}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Leave blank to use the invoice date. Conversion keeps this date so the bill matches what the customer accepted.
                                </p>
                                <InputError message={errors.rate_locked_at} />
                            </div>
                        )}
                        {mode === 'edit' && isSentQuotation && data.document_type === 'quotation' && (
                            <div className="grid gap-1.5">
                                <Label htmlFor="revision_note">
                                    Revision note <span className="text-muted-foreground">(optional)</span>
                                </Label>
                                <Textarea
                                    id="revision_note"
                                    rows={2}
                                    placeholder="e.g. customer asked for 20g instead of 15g"
                                    value={data.revision_note ?? ''}
                                    onChange={(e) => setData('revision_note', e.target.value)}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Saving this quotation starts a new version — this note is what the customer will see alongside it.
                                </p>
                                <InputError message={errors.revision_note} />
                            </div>
                        )}
                        <div className="grid gap-1.5">
                            <Label htmlFor="reference_number">Reference no. (optional)</Label>
                            <Input
                                id="reference_number"
                                value={data.reference_number}
                                onChange={(e) => setData('reference_number', e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-1.5">
                            <Label>Invoice template</Label>
                            <Select
                                value={
                                    data.invoice_template_id
                                        ? String(data.invoice_template_id)
                                        : undefined
                                }
                                onValueChange={(value) => setData('invoice_template_id', Number(value))}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Default template" />
                                </SelectTrigger>
                                <SelectContent>
                                    {invoiceTemplates.map((t) => (
                                        <SelectItem key={t.id} value={String(t.id)}>
                                            {t.name}
                                            {t.is_default ? ' (default)' : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Salesperson (optional)</Label>
                            <Select
                                value={data.salesperson_id ? String(data.salesperson_id) : undefined}
                                onValueChange={(value) => setData('salesperson_id', Number(value))}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Unassigned" />
                                </SelectTrigger>
                                <SelectContent>
                                    {staff.map((member) => (
                                        <SelectItem key={member.id} value={String(member.id)}>
                                            {member.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Document type</Label>
                            <Select
                                value={data.document_type}
                                onValueChange={(value) =>
                                    setData('document_type', value as InvoiceFormData['document_type'])
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {documentTypes.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        {industryConfig.pricing_mode === 'jewelry_calculated' && (
                            <div className="grid gap-1.5">
                                <Label>Pricing mode</Label>
                                <Select
                                    value={data.pricing_mode}
                                    onValueChange={(value) =>
                                        setData(
                                            'pricing_mode',
                                            value as InvoiceFormData['pricing_mode'],
                                        )
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="jewelry_calculated">
                                            Calculated
                                        </SelectItem>
                                        <SelectItem value="manual">
                                            Manual amount
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            <div className="space-y-4">
                {data.items.map((item, index) => (
                    <InvoiceItemEditor
                        key={item.key}
                        item={item}
                        index={index}
                        pricingMode={data.pricing_mode}
                        itemChargeTypes={itemChargeTypes}
                        catalogItems={catalogItems}
                        metalRates={metalRates}
                        industry={industryConfig}
                        groupDiscountPercent={groupDiscountPercent}
                        errors={itemErrors(index)}
                        onChange={updateItem}
                        onRemove={removeItem}
                        canRemove={data.items.length > 1}
                    />
                ))}
                <Button type="button" variant="outline" onClick={addItem} className="w-full">
                    <Plus className="size-4" />
                    Add item
                </Button>
                <InputError message={errors.items} />
            </div>

            {invoiceChargeTypes.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Invoice-level charges</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2 sm:grid-cols-2">
                        {invoiceChargeTypes.map((ct) => {
                            const enabled = data.invoice_charges.some(
                                (c) => c.charge_type_id === ct.id,
                            );
                            const rate =
                                data.invoice_charges.find((c) => c.charge_type_id === ct.id)
                                    ?.rate ?? Number(ct.default_rate ?? 0);
                            return (
                                <div key={ct.id} className="flex items-center gap-2 rounded-md border p-2">
                                    <input
                                        type="checkbox"
                                        id={`inv-charge-${ct.id}`}
                                        checked={enabled}
                                        onChange={(e) => toggleInvoiceCharge(ct.id, e.target.checked)}
                                        className="size-4"
                                    />
                                    <label htmlFor={`inv-charge-${ct.id}`} className="flex-1 text-sm">
                                        {ct.name}
                                    </label>
                                    {enabled && (
                                        <Input
                                            type="number"
                                            step="0.01"
                                            className="w-24"
                                            value={rate}
                                            onChange={(e) =>
                                                setData(
                                                    'invoice_charges',
                                                    data.invoice_charges.map((c) =>
                                                        c.charge_type_id === ct.id
                                                            ? { ...c, rate: Number(e.target.value) }
                                                            : c,
                                                    ),
                                                )
                                            }
                                        />
                                    )}
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Totals</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-1.5">
                            <Label>GST mode</Label>
                            <Select
                                value={data.tax_mode}
                                onValueChange={(value) => setData('tax_mode', value as InvoiceFormData['tax_mode'])}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="single">Single tax line</SelectItem>
                                    <SelectItem value="cgst_sgst">CGST + SGST (same state)</SelectItem>
                                    <SelectItem value="igst">IGST (other state)</SelectItem>
                                </SelectContent>
                            </Select>
                            {suggestedTaxMode && suggestedTaxMode !== data.tax_mode && (
                                <button
                                    type="button"
                                    className="text-left text-xs text-primary hover:underline"
                                    onClick={() => setData('tax_mode', suggestedTaxMode)}
                                >
                                    Customer's state suggests {suggestedTaxMode === 'cgst_sgst' ? 'CGST + SGST' : 'IGST'} — use it?
                                </button>
                            )}
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Overall discount (₹, optional)</Label>
                            <Input
                                type="number"
                                step="0.01"
                                min={0}
                                value={data.discount}
                                onChange={(e) => setData('discount', Number(e.target.value))}
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Tax on invoice-level charges % (optional)</Label>
                            <Input
                                type="number"
                                step="0.01"
                                min={0}
                                max={100}
                                value={data.tax_rate}
                                onChange={(e) => setData('tax_rate', Number(e.target.value))}
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>TCS % (collected at source, optional)</Label>
                            <Input
                                type="number"
                                step="0.01"
                                min={0}
                                max={100}
                                value={data.tcs_rate}
                                onChange={(e) => setData('tcs_rate', Number(e.target.value))}
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label>TDS % (deducted at source, optional)</Label>
                            <Input
                                type="number"
                                step="0.01"
                                min={0}
                                max={100}
                                value={data.tds_rate}
                                onChange={(e) => setData('tds_rate', Number(e.target.value))}
                            />
                        </div>
                    </div>

                    <div className="space-y-1 border-t pt-4 text-sm">
                        <Row label="Subtotal" value={totals.subtotal} />
                        {totals.chargesSummary.map((c) => (
                            <Row key={c.code} label={c.label} value={c.amount} />
                        ))}
                        <Row label="Discount" value={-totals.discount} />
                        <Row label="Tax" value={totals.tax} />
                        {totals.tcsAmount > 0 && (
                            <Row
                                label={`TCS @ ${data.tcs_rate}%`}
                                value={totals.tcsAmount}
                            />
                        )}
                        <Row label="Round off" value={totals.roundOff} />
                        <Row label="Grand total" value={totals.grandTotal} emphasize />
                        {totals.tdsAmount > 0 && (
                            <>
                                <Row
                                    label={`TDS @ ${data.tds_rate}% (deducted)`}
                                    value={-totals.tdsAmount}
                                />
                                <Row label="Amount payable" value={totals.balanceDue} />
                            </>
                        )}
                    </div>
                </CardContent>
            </Card>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label>Notes (optional)</Label>
                    <Textarea
                        rows={3}
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label>Terms (optional)</Label>
                    <Textarea
                        rows={3}
                        value={data.terms}
                        onChange={(e) => setData('terms', e.target.value)}
                    />
                </div>
            </div>

            <AttributesField
                value={data.attributes ?? {}}
                onChange={(next) => setData('attributes', next)}
            />

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="secondary"
                    onClick={() => router.visit('/invoices')}
                    className="w-full sm:w-auto"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    disabled={processing}
                    onClick={submit}
                    className="w-full sm:w-auto"
                >
                    {processing ? 'Saving…' : mode === 'create' ? 'Create invoice' : 'Save changes'}
                </Button>
            </div>
        </div>
    );
}

function Row({ label, value, emphasize }: { label: string; value: number; emphasize?: boolean }) {
    return (
        <div
            className={
                emphasize
                    ? 'flex justify-between border-t pt-2 text-base font-semibold'
                    : 'flex justify-between text-muted-foreground'
            }
        >
            <span>{label}</span>
            <span>₹{currency.format(value)}</span>
        </div>
    );
}
