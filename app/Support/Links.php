<?php

namespace App\Support;

use App\Models\Document;
use App\Models\ExternalRequisition;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\PayrollLine;
use Illuminate\Support\Facades\URL;

/**
 * Tokenized (cryptographically signed, expiring) download URLs.
 *
 * Sensitive files — contracts, receipts, payslips, invoices, evidence — are
 * never reachable through plain guessable links. Every download link carries
 * an HMAC signature bound to the destination and an expiry, verified
 * server-side before the file is streamed.
 */
final class Links
{
    public const DEFAULT_TTL_MINUTES = 240; // 4 hours
    public const EMAIL_TTL_MINUTES = 10080; // 7 days (links inside emails)

    public static function document(Document $document, ?int $ttlMinutes = null): string
    {
        return URL::temporarySignedRoute(
            'documents.download',
            now()->addMinutes($ttlMinutes ?? self::DEFAULT_TTL_MINUTES),
            ['document' => $document]
        );
    }

    public static function receiptPdf(PaymentReceipt $receipt, ?int $ttlMinutes = null): string
    {
        return URL::temporarySignedRoute(
            'receipts.pdf',
            now()->addMinutes($ttlMinutes ?? self::DEFAULT_TTL_MINUTES),
            ['receipt' => $receipt->id]
        );
    }

    public static function payslipPdf(PayrollLine $line, ?int $ttlMinutes = null): string
    {
        return URL::temporarySignedRoute(
            'payroll.payslipPdf',
            now()->addMinutes($ttlMinutes ?? self::DEFAULT_TTL_MINUTES),
            ['line' => $line->id]
        );
    }

    public static function invoicePdf(Invoice $invoice, ?int $ttlMinutes = null): string
    {
        return URL::temporarySignedRoute(
            'invoices.pdf',
            now()->addMinutes($ttlMinutes ?? self::DEFAULT_TTL_MINUTES),
            ['invoice' => $invoice->id]
        );
    }

    public static function externalPdf(ExternalRequisition $requisition, ?int $ttlMinutes = null): string
    {
        return URL::temporarySignedRoute(
            'external-requisitions.pdf',
            now()->addMinutes($ttlMinutes ?? self::DEFAULT_TTL_MINUTES),
            ['external_requisition' => $requisition->id]
        );
    }
}
