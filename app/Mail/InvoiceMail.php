<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Clinic;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Clinic $clinic,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address', 'billing@rocketcoding.com'),
            subject: "Rocket Coding Invoice — {$this->invoice->invoiceDate}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice',
            with: [
                'invoice' => $this->invoice,
                'clinic' => $this->clinic,
                'lines' => $this->invoice->lines()->get(),
            ],
        );
    }

    /**
     * PDF attachment is stubbed for now — Step 7 wires DomPDF / Barryvdh
     * to render the real thing. Until then the recipient sees the inline
     * markdown-rendered invoice with all the same numbers.
     */
    public function attachments(): array
    {
        return [];
    }
}
