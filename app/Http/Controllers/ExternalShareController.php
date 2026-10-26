<?php

namespace App\Http\Controllers;

use App\Models\ExternalRequisition;
use App\Models\ExternalRequisitionItem;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Money;
use App\Support\NotificationService;
use App\Support\Signature;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Public, tokenized fill-and-sign flow for external requesters.
 * No account required — access is granted only through the signed share token.
 */
class ExternalShareController extends Controller
{
    /**
     * Show the requisition form to the external requester.
     */
    public function show(Request $request, string $token)
    {
        $requisition = $this->findRequisition($token);

        $requisition->load(['items', 'project:id,name', 'preparedBy.employee']);

        return Inertia::render('External/Sign', [
            'requisition' => $this->payload($requisition),
            'company' => Settings::company(),
            'signed' => $requisition->signed_at !== null,
        ]);
    }

    /**
     * The external requester fills actual costs, signs and submits.
     */
    public function submit(Request $request, string $token)
    {
        $requisition = $this->findRequisition($token);

        if ($requisition->signed_at !== null) {
            return back()->withErrors(['signature' => 'This form has already been signed and submitted.']);
        }

        $data = $request->validate([
            'requested_by_name' => ['required', 'string', 'max:255'],
            'payee_name' => ['required', 'string', 'max:255'],
            'mode_of_payment' => ['required', Rule::in(['bank_transfer', 'mobile_money', 'cash', 'cheque', 'other'])],
            'purpose' => ['required', 'string', 'max:4000'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'exists:external_requisition_items,id'],
            'items.*.actual_cost' => ['required', 'numeric', 'min:0'],
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,'],
        ], [
            'signature.required' => 'Please sign the form before submitting.',
            'signature.starts_with' => 'The signature must be drawn in the signature box.',
        ]);

        $signature = $data['signature'];
        if (strlen($signature) > 400000) {
            return back()->withErrors(['signature' => 'The signature image is too large. Please sign again.']);
        }

        DB::transaction(function () use ($requisition, $data, $signature) {
            $actualTotal = '0.00';

            foreach ($data['items'] ?? [] as $itemData) {
                $item = ExternalRequisitionItem::query()
                    ->where('external_requisition_id', $requisition->id)
                    ->whereKey($itemData['id'])
                    ->firstOrFail();

                $item->update(['actual_cost' => Money::normalize($itemData['actual_cost'])]);
                $actualTotal = Money::add($actualTotal, $itemData['actual_cost']);
            }

            // Forms without item lines: the actual total equals the estimated total.
            if (Money::compare($actualTotal, '0.00') === 0) {
                $actualTotal = Money::normalize($requisition->total_amount);
            }

            $unspent = Money::sub((string) $requisition->total_amount, $actualTotal);
            if (Money::compare($unspent, '0.00') < 0) {
                $unspent = '0.00';
            }

            $document = Signature::storeDataUrl($signature, $data['requested_by_name']);

            $requisition->update([
                'requested_by_name' => $data['requested_by_name'],
                'payee_name' => $data['payee_name'],
                'mode_of_payment' => $data['mode_of_payment'],
                'purpose' => $data['purpose'],
                'actual_total' => $actualTotal,
                'unspent_funds' => $unspent,
                'status' => 'submitted',
                'signed_by_name' => $data['requested_by_name'],
                'signed_by_email' => $requisition->external_email,
                'signature_document_id' => $document->id,
                'signed_at' => now(),
            ]);

            AuditLogger::log('external_requisition.signed', ExternalRequisition::class, $requisition->id,
                "External party signed {$requisition->requisition_number}",
                null,
                ['signed_by' => $data['requested_by_name'], 'actual_total' => $actualTotal]);
        });

        $requisition->refresh();

        // Send the completed form back to the external requester.
        $this->sendSignedCopy($requisition);

        // Notify the internal team that the form came back.
        $this->notifyInternal($requisition);

        return Inertia::render('External/Sign', [
            'requisition' => $this->payload($requisition->fresh()),
            'company' => Settings::company(),
            'signed' => true,
            'justSubmitted' => true,
        ]);
    }

    private function findRequisition(string $token): ExternalRequisition
    {
        $requisition = ExternalRequisition::query()
            ->where('share_token', $token)
            ->first();

        if (! $requisition || ! $requisition->isTokenValid()) {
            abort(404, 'This form link is invalid or has expired. Please request a new link.');
        }

        return $requisition;
    }

    private function payload(ExternalRequisition $requisition): array
    {
        return [
            'id' => $requisition->id,
            'requisition_number' => $requisition->requisition_number,
            'date' => $requisition->date?->toDateString(),
            'external_party_name' => $requisition->external_party_name,
            'external_contact' => $requisition->external_contact,
            'external_email' => $requisition->external_email,
            'requested_by_name' => $requisition->requested_by_name,
            'payee_name' => $requisition->payee_name,
            'department_text' => $requisition->department_text,
            'mode_of_payment' => $requisition->mode_of_payment,
            'purpose' => $requisition->purpose,
            'date_of_activity' => $requisition->date_of_activity?->toDateString(),
            'activity_budget' => (string) $requisition->activity_budget,
            'currency' => $requisition->currency,
            'total_amount' => (string) $requisition->total_amount,
            'actual_total' => (string) $requisition->actual_total,
            'unspent_funds' => (string) $requisition->unspent_funds,
            'terms' => $requisition->terms,
            'prepared_by' => $requisition->preparedBy?->employee?->full_name ?? $requisition->preparedBy?->name,
            'signed_by_name' => $requisition->signed_by_name,
            'signed_at' => $requisition->signed_at?->toIso8601String(),
            'items' => $requisition->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (string) $item->quantity,
                'unit' => $item->unit,
                'unit_price' => (string) $item->unit_price,
                'total_price' => (string) $item->total_price,
                'actual_cost' => $item->actual_cost !== null ? (string) $item->actual_cost : '',
            ])->values(),
        ];
    }

    private function sendSignedCopy(ExternalRequisition $requisition): void
    {
        if (! $requisition->external_email) {
            return;
        }

        try {
            $pdf = app(\App\Services\PdfService::class)->externalRequisitionPdf($requisition);

            Mail::to($requisition->external_email)->send(
                new \App\Mail\ExternalSignedMail($requisition->fresh(['items', 'preparedBy.employee']), $pdf->output())
            );

            $requisition->update(['notes' => trim(($requisition->notes ? $requisition->notes . "\n\n" : '') . 'Signed copy emailed to ' . $requisition->external_email . ' on ' . now()->format('d M Y H:i') . '.')]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifyInternal(ExternalRequisition $requisition): void
    {
        $users = User::active()
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['technical_lead', 'hr']))
            ->get();

        foreach ($users as $user) {
            NotificationService::notify(
                $user,
                'external_requisition.signed',
                'External form returned — signed',
                "{$requisition->signed_by_name} ({$requisition->external_party_name}) filled and signed {$requisition->requisition_number}. Actual total: {$requisition->actual_total} {$requisition->currency}.",
                "/external-requisitions/{$requisition->id}",
                ['external_requisition_id' => $requisition->id]
            );
        }
    }
}
