<?php

namespace App\Http\Controllers;

use App\Enums\RequisitionStatus;
use App\Models\AccountabilityItem;
use App\Models\AccountabilitySubmission;
use App\Models\Refund;
use App\Models\Requisition;
use App\Support\AuditLogger;
use App\Support\DocumentService;
use App\Support\Money;
use App\Support\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AccountabilityController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = AccountabilitySubmission::with(['requisition:id,requisition_number,title,currency,amount_paid,accountability_required', 'submitter:id,username', 'reviewer:id,username']);

        if (! $user->hasPermission(\App\Support\Permissions::FINANCE_ACCOUNTABILITY_REVIEW)) {
            $query->where('submitted_by_user_id', $user->id);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $submissions = $query->orderByDesc('id')->paginate(15)->withQueryString();

        $outstanding = Requisition::where('accountability_required', true)
            ->where('requester_user_id', $user->hasPermission(\App\Support\Permissions::FINANCE_ACCOUNTABILITY_REVIEW) ? '!=' : '=', $user->id)
            ->whereIn('status', [RequisitionStatus::FullyDisbursed, RequisitionStatus::PartiallyDisbursed, RequisitionStatus::PendingAccountability])
            ->whereDoesntHave('accountabilitySubmissions', fn ($q) => $q->whereIn('status', ['pending', 'under_review', 'accepted']))
            ->with(['requesterEmployee:id,first_name,last_name,employee_code'])
            ->orderBy('id')
            ->limit(20)
            ->get();

        return Inertia::render('Finance/Accountability', [
            'submissions' => $submissions,
            'outstanding' => $outstanding->map(fn ($r) => [
                'id' => $r->id,
                'requisition_number' => $r->requisition_number,
                'title' => $r->title,
                'requester' => $r->requesterEmployee?->full_name,
                'amount_paid' => (string) $r->amount_paid,
                'currency' => $r->currency,
                'can_submit' => $r->requester_user_id === $user->id,
            ]),
            'filters' => $request->only(['status']),
        ]);
    }

    public function store(Request $request, Requisition $requisition)
    {
        if ($request->user()->id !== $requisition->requester_user_id
            && ! $request->user()->hasPermission(\App\Support\Permissions::FINANCE_ACCOUNTABILITY_REVIEW)) {
            abort(403, 'Only the requester can submit accountability for this requisition.');
        }

        $data = $request->validate([
            'actual_amount' => ['required', 'numeric', 'min:0'],
            'explanation' => ['required', 'string', 'max:4000'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
            'items.*.expense_date' => ['nullable', 'date'],
            'receipts' => ['nullable', 'array'],
            'receipts.*' => ['file', 'max:20480', 'mimes:pdf,png,jpg,jpeg'],
        ]);

        DB::transaction(function () use ($request, $data, $requisition) {
            $paid = Money::normalize($requisition->amount_paid);
            $actual = Money::normalize($data['actual_amount']);
            $unspent = Money::sub($paid, $actual);
            $unspent = Money::compare($unspent, '0.00') < 0 ? '0.00' : $unspent;

            $submission = AccountabilitySubmission::create([
                'requisition_id' => $requisition->id,
                'submitted_by_user_id' => $request->user()->id,
                'advance_amount' => $paid,
                'actual_amount' => $actual,
                'unspent_balance' => $unspent,
                'refund_amount' => $unspent,
                'explanation' => $data['explanation'],
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            foreach ($data['items'] ?? [] as $item) {
                AccountabilityItem::create([
                    'accountability_submission_id' => $submission->id,
                    'description' => $item['description'],
                    'amount' => Money::normalize($item['amount']),
                    'expense_date' => $item['expense_date'] ?? null,
                ]);
            }

            $receiptFiles = $request->file('receipts', []);
            foreach ($receiptFiles as $index => $file) {
                $document = DocumentService::store($file, 'expense_receipt', $submission);
                if (isset($data['items'][$index])) {
                    AccountabilityItem::query()
                        ->where('accountability_submission_id', $submission->id)
                        ->where('description', $data['items'][$index]['description'])
                        ->update(['receipt_document_id' => $document->id]);
                }
            }

            $requisition->update([
                'amount_accounted' => $actual,
                'status' => RequisitionStatus::AccountabilityUnderReview,
                'current_stage' => 'accountability',
            ]);

            AuditLogger::log('accountability.submitted', AccountabilitySubmission::class, $submission->id,
                "Accountability submitted for {$requisition->requisition_number}");

            $reviewers = \App\Models\User::active()
                ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['hr', 'hr_finance']))
                ->get();

            foreach ($reviewers as $reviewer) {
                NotificationService::notify($reviewer, 'accountability.submitted',
                    'Accountability submission awaiting review',
                    "{$request->user()->displayName()} submitted accountability for {$requisition->requisition_number}.",
                    "/accountability", ['requisition_id' => $requisition->id]);
            }
        });

        return back()->with('success', 'Accountability submitted for review.');
    }

    public function review(Request $request, AccountabilitySubmission $submission)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::FINANCE_ACCOUNTABILITY_REVIEW)) {
            abort(403);
        }

        $data = $request->validate([
            'decision' => ['required', 'in:accepted,rejected'],
            'review_comments' => ['nullable', 'string', 'max:2000'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($request, $data, $submission) {
            $requisition = $submission->requisition;

            $submission->update([
                'status' => $data['decision'] === 'accepted' ? 'accepted' : 'rejected',
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(),
                'review_comments' => $data['review_comments'] ?? null,
            ]);

            if ($data['decision'] === 'accepted') {
                $refund = Money::normalize($data['refund_amount'] ?? $submission->unspent_balance);

                if (Money::gt($refund, '0.00')) {
                    Refund::create([
                        'requisition_id' => $requisition->id,
                        'accountability_submission_id' => $submission->id,
                        'amount' => $refund,
                        'currency' => $requisition->currency,
                        'refund_date' => now()->toDateString(),
                        'method' => 'cash',
                        'recorded_by_user_id' => $request->user()->id,
                        'status' => 'completed',
                        'notes' => 'Unspent balance refunded on accountability closure.',
                    ]);

                    $requisition->amount_refunded = Money::add((string) $requisition->amount_refunded, $refund);
                }

                $requisition->amount_accounted = Money::max((string) $requisition->amount_accounted, (string) $submission->actual_amount);
                $requisition->status = RequisitionStatus::Closed;
                $requisition->closed_at = now();
                $requisition->save();
            }

            AuditLogger::log('accountability.reviewed', AccountabilitySubmission::class, $submission->id,
                "Accountability {$data['decision']} for {$requisition->requisition_number}");

            NotificationService::notify($submission->submitter, 'accountability.reviewed',
                'Accountability ' . $data['decision'],
                "Your accountability submission for {$requisition->requisition_number} was {$data['decision']}.",
                "/accountability", ['requisition_id' => $requisition->id]);
        });

        return back()->with('success', 'Accountability review recorded.');
    }
}
