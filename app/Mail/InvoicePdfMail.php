<?php

namespace App\Mail;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public ?string $customMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        $business = BusinessSetting::forTenant($this->invoice->tenant_id);

        return new Envelope(
            subject: "Invoice {$this->invoice->invoice_number} from {$business->business_name}",
        );
    }

    public function content(): Content
    {
        $this->invoice->loadMissing(['customer']);

        return new Content(
            htmlString: view('mail.invoice-pdf', [
                'invoice' => $this->invoice,
                'business' => BusinessSetting::forTenant($this->invoice->tenant_id),
                'customMessage' => $this->customMessage,
            ])->render(),
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        // No share link: an emailed PDF must not double as a public token.
        $pdf = Pdf::loadView('pdf.invoice', app(InvoicePdfService::class)
            ->viewData($this->invoice, withShareLink: false))
            ->setPaper('a4')
            ->output();

        return [
            Attachment::fromData(fn () => $pdf, "{$this->invoice->invoice_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
