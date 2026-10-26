<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Money;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Client invoicing for projects — the source of the receivables
 * aging report and the cash-in side of the cashbook.
 */
class InvoiceService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor, array $items): Invoice
    {
        return DB::transaction(function () use ($data, $actor, $items) {
            $invoice = Invoice::create([
                'invoice_number' => Sequence::next('invoice', 'INV', 4),
                'external_party_id' => $data['external_party_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'client_name' => $data['client_name'],
                'client_contact' => $data['client_contact'] ?? null,
                'client_email' => $data['client_email'] ?? null,
                'client_address' => $data['client_address'] ?? null,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'status' => 'draft',
                'currency' => $data['currency'],
                'tax_rate' => Money::normalize($data['tax_rate'] ?? 0),
                'terms' => $data['terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->syncItems($invoice, $items);
            $this->recalculate($invoice);

            AuditLogger::log('invoice.created', Invoice::class, $invoice->id,
                "Created invoice {$invoice->invoice_number} for {$invoice->client_name}");

            return $invoice->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Invoice $invoice, array $data, array $items, User $actor): Invoice
    {
        if ($invoice->status === 'paid' || $invoice->status === 'void') {
            throw ValidationException::withMessages([
                'status' => 'A paid or void invoice cannot be edited.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $data, $items) {
            $invoice->update([
                'external_party_id' => $data['external_party_id'] ?? $invoice->external_party_id,
                'project_id' => $data['project_id'] ?? $invoice->project_id,
                'client_name' => $data['client_name'] ?? $invoice->client_name,
                'client_contact' => $data['client_contact'] ?? $invoice->client_contact,
                'client_email' => $data['client_email'] ?? $invoice->client_email,
                'client_address' => $data['client_address'] ?? $invoice->client_address,
                'issue_date' => $data['issue_date'] ?? $invoice->issue_date,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'currency' => $data['currency'] ?? $invoice->currency,
                'tax_rate' => Money::normalize($data['tax_rate'] ?? $invoice->tax_rate),
                'terms' => $data['terms'] ?? $invoice->terms,
                'notes' => $data['notes'] ?? $invoice->notes,
            ]);

            if ($items) {
                $invoice->items()->delete();
                $this->syncItems($invoice, $items);
            }

            $this->recalculate($invoice);

            AuditLogger::log('invoice.updated', Invoice::class, $invoice->id, "Updated invoice {$invoice->invoice_number}");

            return $invoice->refresh();
        });
    }

    public function markSent(Invoice $invoice, User $actor): Invoice
    {
        if (! in_array($invoice->status, ['draft', 'sent'], true)) {
            throw ValidationException::withMessages(['status' => 'Only a draft invoice can be marked as sent.']);
        }

        $invoice->update(['status' => 'sent', 'sent_at' => now()]);

        AuditLogger::log('invoice.sent', Invoice::class, $invoice->id, "Sent invoice {$invoice->invoice_number}");

        return $invoice->refresh();
    }

    public function void(Invoice $invoice, User $actor, string $reason): Invoice
    {
        if ($invoice->isSettled() && Money::compare((string) $invoice->amount_paid, '0.00') > 0) {
            throw ValidationException::withMessages(['status' => 'An invoice with payments cannot be voided. Reverse the payments first.']);
        }

        $invoice->update(['status' => 'void', 'notes' => trim(($invoice->notes ? $invoice->notes . "\n\n" : '') . "Voided: {$reason}")]);

        AuditLogger::log('invoice.voided', Invoice::class, $invoice->id, "Voided invoice {$invoice->invoice_number}", null, ['reason' => $reason]);

        return $invoice->refresh();
    }

    /**
     * Record a client payment against an invoice (cash-in).
     */
    public function recordPayment(
        Invoice $invoice,
        User $actor,
        string $amount,
        string $paymentDate,
        string $method,
        ?string $reference,
        ?string $receivedFrom = null,
        ?string $notes = null,
    ): InvoicePayment {
        return DB::transaction(function () use ($invoice, $actor, $amount, $paymentDate, $method, $reference, $receivedFrom, $notes) {
            /** @var Invoice $invoice */
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status === 'void') {
                throw ValidationException::withMessages(['status' => 'A void invoice cannot receive payments.']);
            }

            $normalized = Money::normalize($amount);

            if (Money::compare($normalized, '0.00') <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            }

            if (Money::gt($normalized, (string) $invoice->amount_due)) {
                throw ValidationException::withMessages([
                    'amount' => "Payment exceeds the outstanding balance of {$invoice->amount_due}.",
                ]);
            }

            $payment = InvoicePayment::create([
                'payment_number' => Sequence::next('receipt_client', 'RCPT', 4),
                'invoice_id' => $invoice->id,
                'amount' => $normalized,
                'currency' => $invoice->currency,
                'payment_date' => $paymentDate,
                'payment_method' => $method,
                'transaction_reference' => $reference,
                'received_from' => $receivedFrom ?? $invoice->client_name,
                'recorded_by' => $actor->id,
                'notes' => $notes,
            ]);

            $invoice->amount_paid = Money::add((string) $invoice->amount_paid, $normalized);
            $invoice->amount_due = Money::sub((string) $invoice->amount_due, $normalized);
            $invoice->status = Money::compare((string) $invoice->amount_due, '0.00') <= 0 ? 'paid' : 'partially_paid';
            $invoice->save();

            AuditLogger::log('invoice.payment_recorded', InvoicePayment::class, $payment->id,
                "Recorded {$normalized} against {$invoice->invoice_number}",
                ['amount_paid' => $invoice->getOriginal('amount_paid')],
                ['amount_paid' => $invoice->amount_paid]);

            return $payment;
        });
    }

    /**
     * Clear (settle) an invoice that has been paid: records the remaining
     * balance as a final receipt so the invoice drops out of every pending
     * list and the aging report.
     */
    public function clear(
        Invoice $invoice,
        User $actor,
        ?string $method = 'other',
        ?string $reference = null,
        ?string $notes = null,
    ): InvoicePayment {
        return DB::transaction(function () use ($invoice, $actor, $method, $reference, $notes) {
            /** @var Invoice $invoice */
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status === 'void') {
                throw ValidationException::withMessages(['status' => 'A void invoice cannot be cleared.']);
            }

            if ($invoice->isSettled()) {
                throw ValidationException::withMessages(['status' => 'This invoice is already settled.']);
            }

            $payment = $this->recordPayment(
                $invoice,
                $actor,
                (string) $invoice->amount_due,
                now()->toDateString(),
                $method ?? 'other',
                $reference,
                $invoice->client_name,
                trim('Balance cleared' . ($notes ? ' — ' . $notes : '')),
            );

            AuditLogger::log('invoice.cleared', Invoice::class, $invoice->id,
                "Cleared invoice {$invoice->invoice_number}", ['amount_due' => $invoice->getOriginal('amount_due')], ['amount_due' => '0.00']);

            return $payment;
        });
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $index => $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? null,
                'unit_price' => Money::normalize($item['unit_price']),
                'total_price' => Money::mulQty($item['unit_price'], $item['quantity']),
                'sort_order' => $index,
            ]);
        }
    }

    private function recalculate(Invoice $invoice): void
    {
        $subtotal = Money::sum($invoice->items()->pluck('total_price')->map(fn ($v) => (string) $v)->all());
        $taxAmount = bcdiv(bcmul($subtotal, Money::normalize($invoice->tax_rate), 6), '100', 2);
        $total = Money::add($subtotal, $taxAmount);

        $invoice->subtotal = $subtotal;
        $invoice->tax_amount = $taxAmount;
        $invoice->total_amount = $total;
        $invoice->amount_due = Money::sub($total, (string) $invoice->amount_paid);
        $invoice->save();
    }
}
