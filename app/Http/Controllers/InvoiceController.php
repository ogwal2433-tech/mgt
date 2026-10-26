<?php

namespace App\Http\Controllers;

use App\Models\ExternalParty;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\InvoiceService;
use App\Services\PdfService;
use App\Support\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly PdfService $pdf,
    ) {}

    public function index(Request $request)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::INVOICES_VIEW)) {
            abort(403);
        }

        $query = Invoice::with(['project:id,name,project_code']);

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%");
            });
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $invoices = $query->orderByDesc('id')->paginate(15)->withQueryString();

        $summary = [
            'total' => (string) Invoice::where('status', '!=', 'void')->sum('total_amount'),
            'paid' => (string) Invoice::where('status', '!=', 'void')->sum('amount_paid'),
            'due' => (string) Invoice::where('status', '!=', 'void')->sum('amount_due'),
        ];

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'summary' => $summary,
            'filters' => $request->only(['search', 'status']),
            'canManage' => $request->user()->hasPermission(\App\Support\Permissions::INVOICES_MANAGE),
        ]);
    }

    public function create()
    {
        return Inertia::render('Invoices/Create', [
            'parties' => ExternalParty::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(['id', 'name', 'project_code', 'client_name']),
        ]);
    }

    public function store(Request $request)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::INVOICES_MANAGE)) {
            abort(403);
        }

        $data = $this->validated($request);

        $invoice = $this->invoices->create($data, $request->user(), $data['items']);

        return redirect()->route('invoices.show', $invoice->id)->with('success', "Invoice {$invoice->invoice_number} created.");
    }

    public function show(Invoice $invoice)
    {
        if (! request()->user()->hasPermission(\App\Support\Permissions::INVOICES_VIEW)) {
            abort(403);
        }

        $invoice->load(['items', 'payments.recorder:id,username', 'project:id,name,project_code', 'party:id,name']);

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
            'canManage' => request()->user()->hasPermission(\App\Support\Permissions::INVOICES_MANAGE),
            'canPay' => request()->user()->hasPermission(\App\Support\Permissions::INVOICES_PAYMENTS),
        ]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::INVOICES_MANAGE)) {
            abort(403);
        }

        $data = $this->validated($request);
        $this->invoices->update($invoice, $data, $data['items'], $request->user());

        return back()->with('success', 'Invoice updated.');
    }

    public function markSent(Invoice $invoice)
    {
        if (! request()->user()->hasPermission(\App\Support\Permissions::INVOICES_MANAGE)) {
            abort(403);
        }

        $this->invoices->markSent($invoice, request()->user());

        return back()->with('success', "Invoice {$invoice->invoice_number} marked as sent.");
    }

    public function void(Request $request, Invoice $invoice)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::INVOICES_MANAGE)) {
            abort(403);
        }

        $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->invoices->void($invoice, $request->user(), $request->input('reason'));

        return back()->with('success', 'Invoice voided.');
    }

    public function recordPayment(Request $request, Invoice $invoice)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::INVOICES_PAYMENTS)) {
            abort(403);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:40'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'received_from' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->invoices->recordPayment(
            $invoice,
            $request->user(),
            Money::normalize($data['amount']),
            $data['payment_date'],
            $data['payment_method'],
            $data['transaction_reference'] ?? null,
            $data['received_from'] ?? null,
            $data['notes'] ?? null,
        );

        return back()->with('success', 'Client payment recorded.');
    }

    public function clear(Request $request, Invoice $invoice)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::INVOICES_PAYMENTS)) {
            abort(403);
        }

        $data = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:40'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->invoices->clear(
            $invoice,
            $request->user(),
            $data['payment_method'] ?? 'other',
            $data['transaction_reference'] ?? null,
            $data['notes'] ?? null,
        );

        return back()->with('success', "Invoice {$invoice->invoice_number} cleared — balance settled and removed from pending.");
    }

    public function pdf(Invoice $invoice)
    {
        if (! request()->user()->hasPermission(\App\Support\Permissions::INVOICES_VIEW)) {
            abort(403);
        }

        AuditLogger::log('invoice.pdf', Invoice::class, $invoice->id, "Generated PDF for {$invoice->invoice_number}");

        return $this->pdf->invoicePdf($invoice)->stream("invoice-{$invoice->invoice_number}.pdf");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_contact' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:190'],
            'client_address' => ['nullable', 'string', 'max:255'],
            'external_party_id' => ['nullable', 'exists:external_parties,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', 'string', 'max:10'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'terms' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
