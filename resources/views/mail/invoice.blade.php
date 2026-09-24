<x-mail::message>
# Rocket Coding Invoice

**Invoice:** {{ $invoice->number }}
**Period:** {{ $invoice->period }}
**Invoice Date:** {{ $invoice->invoiceDate }}
**Payment Due:** {{ $invoice->dueDate }}
**Payment Method:** ACH Auto-Debit

**Bill To:** {{ $clinic->name }}
{{ $clinic->addr }}
{{ $clinic->city }}, {{ $clinic->state }} {{ $clinic->zip }}

---

## Line Items

<x-mail::table>
| Tier | Description | Qty | Rate | Amount |
|:-----|:------------|:----|-----:|-------:|
@foreach ($lines as $line)
| {{ $line->tier }} | {{ $line->description }} | {{ $line->quantity }} | ${{ number_format($line->unitPrice, 2) }} | ${{ number_format($line->amount, 2) }} |
@endforeach
</x-mail::table>

**Subtotal:** ${{ number_format($invoice->subtotal, 2) }}
**Tax:** ${{ number_format($invoice->tax, 2) }}
**TOTAL DUE:** **${{ number_format($invoice->total, 2) }}**

---

This amount will be automatically debited from your bank account on file on **{{ $invoice->dueDate }}**. No action required on your part.

Questions? Reply to this email or contact billing@rocketcoding.com.

Thanks,
Rocket Coding — Powered by Snap Ecosystem
</x-mail::message>
