<?php

namespace App\Http\Controllers;

use App\Enums\RequisitionStatus;
use App\Models\Requisition;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $myQueue = collect();
        $reviewed = collect();

        if ($user->hasPermission(\App\Support\Permissions::REQUISITIONS_REVIEW_TECHNICAL)) {
            $myQueue = $myQueue->merge(Requisition::where('status', RequisitionStatus::PendingTechnicalLead));
        }
        if ($user->hasPermission(\App\Support\Permissions::REQUISITIONS_REVIEW_HR)) {
            $myQueue = $myQueue->merge(Requisition::where('status', RequisitionStatus::PendingHrReview));
        }
        if ($user->hasPermission(\App\Support\Permissions::REQUISITIONS_APPROVE_MD)) {
            $myQueue = $myQueue->merge(Requisition::where('status', RequisitionStatus::PendingMdApproval));
        }

        $queueQuery = Requisition::with(['requesterEmployee:id,first_name,last_name,employee_code', 'category:id,name', 'department:id,name'])
            ->where(function ($q) use ($user) {
                if ($user->hasPermission(\App\Support\Permissions::REQUISITIONS_REVIEW_TECHNICAL)) {
                    $q->orWhere('status', RequisitionStatus::PendingTechnicalLead);
                }
                if ($user->hasPermission(\App\Support\Permissions::REQUISITIONS_REVIEW_HR)) {
                    $q->orWhere('status', RequisitionStatus::PendingHrReview);
                }
                if ($user->hasPermission(\App\Support\Permissions::REQUISITIONS_APPROVE_MD)) {
                    $q->orWhere('status', RequisitionStatus::PendingMdApproval);
                }
            })
            ->where('requester_user_id', '!=', $user->id);

        if ($search = $request->string('search')->toString()) {
            $queueQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('requisition_number', 'like', "%{$search}%");
            });
        }

        $queue = $queueQuery->orderBy('required_date')->orderBy('id')->paginate(15)->withQueryString();

        $decided = Requisition::with(['requesterEmployee:id,first_name,last_name,employee_code'])
            ->whereHas('approvals', fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return Inertia::render('Approvals/Index', [
            'queue' => $queue,
            'decided' => $decided->map(fn ($r) => [
                'id' => $r->id,
                'requisition_number' => $r->requisition_number,
                'title' => $r->title,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'requester' => $r->requesterEmployee?->full_name,
                'requested_amount' => (string) $r->requested_amount,
                'currency' => $r->currency,
            ]),
            'filters' => $request->only(['search']),
            'stageLabels' => [
                'technical_lead' => 'Technical Lead',
                'hr' => 'HR Review',
                'md' => 'MD Approval',
            ],
        ]);
    }
}
