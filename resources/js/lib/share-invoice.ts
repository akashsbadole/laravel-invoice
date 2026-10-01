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

export function buildShareMessage(params: {
    businessName: string;
    invoiceNumber: string;
    grandTotal: string;
    publicUrl: string;
}): string {
    return `Hi! Here's your invoice ${params.invoiceNumber} from ${params.businessName} for ₹${params.grandTotal}. View, download, or check payment status here: ${params.publicUrl}`;
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
