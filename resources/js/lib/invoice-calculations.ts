import type {
    ChargeType,
    InvoiceChargeInput,
    InvoiceItemForm,
    ItemChargeComputed,
    PricingMode,
    TaxMode,
} from '@/types/invoice';

function round2(value: number): number {
    return Math.round((value + Number.EPSILON) * 100) / 100;
}

export function computeItemCharge(
    chargeInput: { charge_type_id: number; rate: number },
    chargeType: ChargeType | undefined,
    baseValuePerUnit: number,
    netWeight: number,
    stoneCarat: number,
): ItemChargeComputed | null {
    if (!chargeType || chargeType.applies_to !== 'item') return null;

    const rate = chargeInput.rate ?? Number(chargeType.default_rate ?? 0);

    const amount =
        chargeType.calculation_type === 'fixed'
            ? rate
            : chargeType.calculation_type === 'percentage'
              ? baseValuePerUnit * (rate / 100)
              : chargeType.calculation_type === 'per_gram'
                ? rate * netWeight
                : rate * stoneCarat; // per_carat

    return {
        charge_type_id: chargeType.id,
        label: chargeType.name,
        code: chargeType.code,
        calculation_type: chargeType.calculation_type,
        is_taxable: chargeType.is_taxable,
        rate: round2(rate),
        amount: round2(amount),
    };
}

export type ComputedItem = {
    base_value: number;
    charges: ItemChargeComputed[];
    charges_total: number;
    discount: number;
    tax: number;
    total: number;
};

export function computeItem(
    item: InvoiceItemForm,
    pricingMode: PricingMode,
    chargeTypesById: Map<number, ChargeType>,
): ComputedItem {
    const quantity = Math.max(item.quantity || 1, 1);
    const rate = item.rate || 0;
    const netWeight = item.net_weight || 0;
    const stoneCarat = item.stone_carat || 0;

    const baseValuePerUnit =
        pricingMode === 'manual'
            ? rate
            : item.rate_type === 'per_gram'
              ? rate * netWeight
              : item.rate_type === 'per_carat'
                ? rate * stoneCarat
                : rate; // per_piece or fixed

    let chargesPerUnit = 0;
    let taxableBasePerUnit = baseValuePerUnit;
    const charges: ItemChargeComputed[] = [];

    if (pricingMode === 'jewelry_calculated') {
        for (const chargeInput of item.charges) {
            const chargeType = chargeTypesById.get(chargeInput.charge_type_id);
            const computed = computeItemCharge(chargeInput, chargeType, baseValuePerUnit, netWeight, stoneCarat);
            if (!computed) continue;
            charges.push(computed);
            chargesPerUnit += computed.amount;
            if (computed.is_taxable) taxableBasePerUnit += computed.amount;
        }
    }

    const discount = item.discount || 0;
    const taxRate = item.tax_rate || 0;

    const baseValue = round2(baseValuePerUnit * quantity);
    const chargesTotal = round2(chargesPerUnit * quantity);
    const taxableAmount = Math.max(round2(taxableBasePerUnit * quantity - discount), 0);
    const tax = round2(taxableAmount * (taxRate / 100));
    const total = round2(baseValue + chargesTotal - discount + tax);

    return { base_value: baseValue, charges, charges_total: chargesTotal, discount, tax, total };
}

export function computeInvoiceCharge(
    chargeInput: InvoiceChargeInput,
    chargeType: ChargeType | undefined,
    subtotal: number,
): ItemChargeComputed | null {
    if (!chargeType || chargeType.applies_to !== 'invoice') return null;

    const rate = chargeInput.rate ?? Number(chargeType.default_rate ?? 0);
    const amount = chargeType.calculation_type === 'percentage' ? subtotal * (rate / 100) : rate;

    return {
        charge_type_id: chargeType.id,
        label: chargeType.name,
        code: chargeType.code,
        calculation_type: chargeType.calculation_type,
        is_taxable: chargeType.is_taxable,
        rate: round2(rate),
        amount: round2(amount),
    };
}

export type ChargeSummaryRow = { code: string; label: string; amount: number };
export type TaxBreakdownRow = { label: string; rate: number; amount: number };

export type ComputedInvoice = {
    items: ComputedItem[];
    invoiceCharges: ItemChargeComputed[];
    chargesSummary: ChargeSummaryRow[];
    taxBreakdown: TaxBreakdownRow[];
    subtotal: number;
    discount: number;
    tax: number;
    roundOff: number;
    grandTotal: number;
};

function fmtRate(rate: number): string {
    return Number(rate.toFixed(2)).toString();
}

function buildTaxBreakdown(taxBySlab: Map<string, number>, mode: TaxMode): TaxBreakdownRow[] {
    if (mode === 'single') return [];

    const rows: TaxBreakdownRow[] = [];
    const slabs = [...taxBySlab.entries()].sort((a, b) => Number(a[0]) - Number(b[0]));

    for (const [key, rawAmount] of slabs) {
        const rate = Number(key);
        const amount = round2(rawAmount);

        if (mode === 'igst') {
            rows.push({ label: `IGST @ ${fmtRate(rate)}%`, rate, amount });
            continue;
        }

        const half = round2(amount / 2);
        const halfRate = rate / 2;
        rows.push({ label: `CGST @ ${fmtRate(halfRate)}%`, rate: halfRate, amount: half });
        rows.push({ label: `SGST @ ${fmtRate(halfRate)}%`, rate: halfRate, amount: round2(amount - half) });
    }

    return rows;
}

export function computeInvoice(
    items: InvoiceItemForm[],
    invoiceCharges: InvoiceChargeInput[],
    pricingMode: PricingMode,
    chargeTypes: ChargeType[],
    invoiceDiscount: number,
    invoiceTaxRate: number,
    taxMode: TaxMode = 'single',
): ComputedInvoice {
    const chargeTypesById = new Map(chargeTypes.map((ct) => [ct.id, ct]));
    const computedItems = items.map((item) => computeItem(item, pricingMode, chargeTypesById));

    let subtotal = 0; // base + item charges - item discounts (base for % invoice charges)
    let baseSubtotal = 0; // base only (displayed "Subtotal")
    let itemDiscounts = 0;
    let itemsTax = 0;
    const taxBySlab = new Map<string, number>();

    const addToSlab = (rate: number, amount: number) => {
        if (amount === 0) return;
        const key = rate.toFixed(2);
        taxBySlab.set(key, (taxBySlab.get(key) ?? 0) + amount);
    };

    computedItems.forEach((item, i) => {
        subtotal += item.base_value + item.charges_total - item.discount;
        baseSubtotal += item.base_value;
        itemDiscounts += item.discount;
        itemsTax += item.tax;
        addToSlab(items[i].tax_rate || 0, item.tax);
    });
    subtotal = round2(subtotal);

    const computedInvoiceCharges: ItemChargeComputed[] = [];
    let invoiceChargesTotal = 0;
    let invoiceChargesTaxable = 0;

    for (const chargeInput of invoiceCharges) {
        const computed = computeInvoiceCharge(chargeInput, chargeTypesById.get(chargeInput.charge_type_id), subtotal);
        if (!computed) continue;
        computedInvoiceCharges.push(computed);
        invoiceChargesTotal += computed.amount;
        if (computed.is_taxable) invoiceChargesTaxable += computed.amount;
    }

    const invoiceLevelTax = round2(invoiceChargesTaxable * (invoiceTaxRate / 100));
    addToSlab(invoiceTaxRate, invoiceLevelTax);

    const taxBreakdown = buildTaxBreakdown(taxBySlab, taxMode);
    const tax =
        taxBreakdown.length > 0
            ? round2(taxBreakdown.reduce((sum, r) => sum + r.amount, 0))
            : round2(itemsTax + invoiceLevelTax);

    const beforeRounding = round2(subtotal + invoiceChargesTotal + tax - invoiceDiscount);
    const grandTotal = Math.round(beforeRounding);
    const roundOff = round2(grandTotal - beforeRounding);

    const summary = new Map<string, ChargeSummaryRow>();
    const addToSummary = (code: string, label: string, amount: number) => {
        const existing = summary.get(code);
        summary.set(code, { code, label, amount: round2((existing?.amount ?? 0) + amount) });
    };
    computedItems.forEach((item, i) => {
        const qty = Math.max(items[i].quantity || 1, 1);
        item.charges.forEach((c) => addToSummary(c.code, c.label, c.amount * qty));
    });
    computedInvoiceCharges.forEach((c) => addToSummary(c.code, c.label, c.amount));
    if (itemDiscounts > 0) addToSummary('item_discount', 'Item discounts', -round2(itemDiscounts));

    return {
        items: computedItems,
        invoiceCharges: computedInvoiceCharges,
        chargesSummary: Array.from(summary.values()),
        taxBreakdown,
        subtotal: round2(baseSubtotal),
        discount: invoiceDiscount,
        tax,
        roundOff,
        grandTotal,
    };
}
