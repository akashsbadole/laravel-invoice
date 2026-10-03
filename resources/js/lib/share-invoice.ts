/**
 * Best-effort normalization to a WhatsApp-friendly international number.
 * wa.me needs digits only, no leading +/00. If the number doesn't already
 * look like it has a country code, we assume India (91) since that's this
 * app's primary market — wrong guesses still just open WhatsApp's picker
 * instead of a specific chat, so this never blocks sharing.
 */
export function formatWhatsAppNumber(mobileNumber: string): string {
    const digits = mobileNumber.replace(/\D/g, '');
    if (digits.length === 10) return `91${digits}`;
    return digits;
}

export function buildPublicInvoiceUrl(token: string): string {
    return `${window.location.origin}/invoice/view/${token}`;
}

export function buildWhatsAppShareUrl(mobileNumber: string, message: string): string {
    const number = formatWhatsAppNumber(mobileNumber);
    const text = encodeURIComponent(message);
    return number ? `https://wa.me/${number}?text=${text}` : `https://wa.me/?text=${text}`;
}

export function buildMailtoUrl(email: string, subject: string, message: string): string {
    return `mailto:${email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(message)}`;
}

export function buildUpiCollectUrl(params: {
    upiId: string;
    payeeName: string;
    amount: number | string;
    note: string;
}): string | null {
    const upiId = params.upiId.trim();
    const amount = Number(params.amount);
    if (!upiId || !Number.isFinite(amount) || amount <= 0) return null;

    const query = new URLSearchParams({
        pa: upiId,
        pn: params.payeeName.slice(0, 60),
        am: amount.toFixed(2),
        cu: 'INR',
        tn: params.note.slice(0, 80),
    });

    return `upi://pay?${query.toString()}`;
}

/* ------------------------------------------------------------------ *
 * Rich share text
 *
 * A jewellery customer decides in the chat bubble, not on a landing page.
 * The old one-line "click here" message made them tap through and scroll
 * a PDF-shaped page before finding the number. So the text carries the
 * actual breakdown, and the link is only there for the decision.
 * ------------------------------------------------------------------ */

const rupee = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 2,
});

const divider = '━━━━━━━━━━━━━━━━';

/** Money, minus-prefixed when negative — the sign carries meaning here. */
function money(value: number | string | null | undefined): string {
    const amount = typeof value === 'string' ? Number(value) : (value ?? 0);
    if (!Number.isFinite(amount)) return rupee.format(0);
    return amount < 0 ? `-${rupee.format(Math.abs(amount))}` : rupee.format(amount);
}

/** Grams to three decimals the way a gold counter writes it: 15.200g. */
function grams(value: number | string | null | undefined): string | null {
    const amount = typeof value === 'string' ? Number(value) : (value ?? 0);
    return amount > 0 ? `${amount.toFixed(3)}g` : null;
}

function firstName(fullName: string | null | undefined): string | null {
    const trimmed = (fullName ?? '').trim();
    return trimmed ? trimmed.split(/\s+/)[0] : null;
}

function formatDay(value: string | null | undefined): string | null {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

export type ShareMessageItem = {
    item_name: string;
    line_type?: 'sale' | 'exchange_credit';
    metal_type?: string | null;
    purity?: string | null;
    net_weight?: string | null;
    rate?: string | null;
    quantity: number;
    /** Item-level charges. Amounts are per unit — quantity is applied here. */
    charges?: { label: string; amount: number }[];
    /** Value before charges and tax. Use this rather than deriving from total. */
    base_value?: string | number | null;
};

export type ShareMessageParams = {
    businessName: string;
    documentNumber: string;
    /** 'Quotation' | 'Invoice' | 'Delivery Challan' — shown as the heading. */
    documentLabel: string;
    documentDate?: string | null;
    customerName?: string | null;
    items: ShareMessageItem[];
    chargesSummary?: { label: string; amount: number }[] | null;
    taxBreakdown?: { label: string; amount: number }[] | null;
    tax?: string | null;
    discount?: string | null;
    tcsRate?: string | null;
    tcsAmount?: string | null;
    roundOff?: string | null;
    grandTotal: string | number;
    balanceAmount?: string | null;
    validUntil?: string | null;
    /**
     * Date the metal rate behind these prices was struck. Rendered as
     * "Gold rate as on 12 Jan" so a later rate move can't become a
     * disagreement about what was quoted.
     */
    rateLockedOn?: string | null;
    /**
     * Which revision of the quotation this is. Shown as "(Rev 2)" so
     * the customer can tell at a glance which version they are holding
     * after a negotiation round.
     */
    revisionNumber?: number | null;
    publicUrl: string;
    /** Overrides the closing call to action. */
    callToAction?: string;
};

/**
 * The full share text for a quotation, invoice or challan: header, an
 * itemised breakdown, the money lines, then the link for the decision.
 *
 * WhatsApp renders `*bold*`, so the business name, line items and the
 * grand total carry the weight — a customer scanning the bubble should
 * land on the item and the total without reading anything else.
 */
export function buildDocumentMessage(params: ShareMessageParams): string {
    const lines: string[] = [];

    lines.push(`*${params.businessName}*`);
    const rev = params.revisionNumber && params.revisionNumber > 1 ? ` (Rev ${params.revisionNumber})` : '';
    lines.push(`${params.documentLabel} ${params.documentNumber}${rev}`);

    const date = formatDay(params.documentDate);
    if (date) lines.push(date);

    lines.push(divider);

    const name = firstName(params.customerName);
    if (name) lines.push(`Hi ${name},`);

    for (const item of params.items) {
        lines.push(...itemLines(item));
    }

    // Charges summary already folds in item-level charges, so printing it
    // alongside the per-item ones would double-count. Show only the charges
    // that belong to the document itself.
    const itemChargeLabels = new Set(params.items.flatMap((item) => (item.charges ?? []).map((c) => c.label)));
    const documentCharges = (params.chargesSummary ?? []).filter((charge) => !itemChargeLabels.has(charge.label));

    for (const charge of documentCharges) {
        lines.push(`${charge.label}  ${money(charge.amount)}`);
    }

    const discount = Number(params.discount ?? 0);
    if (discount > 0) {
        lines.push(`Discount  -${money(discount)}`);
    }

    const taxLines = params.taxBreakdown?.length
        ? params.taxBreakdown
        : Number(params.tax ?? 0) > 0
          ? [{ label: 'GST', amount: Number(params.tax) }]
          : [];
    for (const tax of taxLines) {
        lines.push(`${tax.label}  ${money(tax.amount)}`);
    }

    const tcs = Number(params.tcsAmount ?? 0);
    if (tcs > 0) {
        lines.push(`TCS @${params.tcsRate ?? 0}%  ${money(tcs)}`);
    }

    const roundOff = Number(params.roundOff ?? 0);
    if (roundOff !== 0) {
        lines.push(`Round off  ${money(roundOff)}`);
    }

    lines.push('─────────────────');
    lines.push(`*Total ${money(params.grandTotal)}*`);

    const balance = Number(params.balanceAmount ?? 0);
    if (balance > 0 && balance !== Number(params.grandTotal)) {
        lines.push(`Balance due  ${money(balance)}`);
    }

    const validUntil = formatDay(params.validUntil);
    if (validUntil) lines.push(`Valid till ${validUntil}`);

    const lockedOn = formatDay(params.rateLockedOn);
    if (lockedOn) lines.push(`Metal rate as on ${lockedOn}`);

    lines.push('');
    lines.push(`${params.callToAction ?? 'View, accept or ask for changes:'} ${params.publicUrl}`);

    return lines.join('\n');
}

/** One item as two or three lines: the name, then how the price was reached. */
function itemLines(item: ShareMessageItem): string[] {
    const lines: string[] = [];
    const isCredit = item.line_type === 'exchange_credit';

    lines.push(`${isCredit ? '↩' : '•'} *${item.item_name}*`);

    const weight = grams(item.net_weight);
    const rate = Number(item.rate ?? 0);
    const spec = [item.metal_type, item.purity].filter(Boolean).join(' ');
    const measure = [weight, spec].filter(Boolean).join(' · ');

    if (measure) lines.push(`  ${measure}`);

    if (item.quantity > 1) {
        lines.push(`  Qty ${item.quantity}`);
    }

    // The rate line is the whole point: it is what the customer is agreeing
    // to, and the reason the quote still holds when the daily rate moves.
    if (weight && rate > 0) {
        lines.push(`  ${money(rate)}/g = ${money(item.base_value ?? 0)}`);
    }

    for (const charge of item.charges ?? []) {
        lines.push(`  ${charge.label}  ${money(charge.amount * item.quantity)}`);
    }

    return lines;
}

/**
 * A plain-text summary for SMS and email, where `*bold*` would show up
 * literally. Same numbers, no WhatsApp markup.
 */
export function buildPlainShareMessage(params: ShareMessageParams): string {
    return buildDocumentMessage(params)
        .replace(/\*/g, '')
        .replace(/━/g, '-')
        .replace(/─/g, '-');
}
