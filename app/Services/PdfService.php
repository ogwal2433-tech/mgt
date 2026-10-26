<?php

namespace App\Services;

use App\Models\Document;
use App\Models\ExternalRequisition;
use App\Models\PaymentReceipt;
use App\Support\DocumentService;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    public function storeReceiptPdf(PaymentReceipt $receipt): Document
    {
        $pdf = $this->receiptPdf($receipt);

        return DocumentService::storeGeneratedPdf(
            $pdf->output(),
            "receipt-{$receipt->receipt_number}.pdf",
            'payment_receipt',
            $receipt,
            false
        );
    }

    public function receiptPdf(PaymentReceipt $receipt)
    {
        $receipt->loadMissing(['payment', 'requisition.requesterEmployee', 'requester', 'generatedBy']);

        return Pdf::loadView('pdf.receipt', [
            'receipt' => $receipt,
            'company' => Settings::company(),
        ]);
    }

    public function storeExternalRequisitionPdf(ExternalRequisition $requisition): Document
    {
        $pdf = $this->externalRequisitionPdf($requisition);

        return DocumentService::storeGeneratedPdf(
            $pdf->output(),
            "external-requisition-{$requisition->requisition_number}.pdf",
            'external_requisition',
            $requisition,
            false
        );
    }

    public function payslipPdf(\App\Models\PayrollLine $line)
    {
        $line->loadMissing(['run.period', 'employee.department', 'payment']);

        return Pdf::loadView('pdf.payslip', [
            'line' => $line,
            'run' => $line->run,
            'period' => $line->run->period,
            'payment' => $line->payment,
            'currency' => \App\Support\Settings::get('finance.currency', 'UGX'),
            'company' => Settings::company(),
        ]);
    }

    public function storePayslipPdf(\App\Models\PayrollLine $line): Document
    {
        $pdf = $this->payslipPdf($line);

        return DocumentService::storeGeneratedPdf(
            $pdf->output(),
            'payslip-' . ($line->payslip_number ?? $line->employee_code) . '.pdf',
            'payslip',
            $line,
            true
        );
    }

    public function invoicePdf(\App\Models\Invoice $invoice)
    {
        $invoice->loadMissing(['items', 'project', 'party']);

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => Settings::company(),
        ]);
    }

    public function externalRequisitionPdf(ExternalRequisition $requisition)
    {
        $requisition->loadMissing(['items', 'preparedBy.employee', 'party', 'project', 'signature', 'approvals.user']);

        return Pdf::loadView('pdf.external-requisition', [
            'requisition' => $requisition,
            'company' => Settings::company(),
        ]);
    }
}
