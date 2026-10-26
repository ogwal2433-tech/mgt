<?php

namespace App\Services;

use App\Enums\ApprovalStage;
use App\Enums\RequisitionStatus;
use App\Models\Requisition;
use App\Models\RequisitionApproval;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Money;
use App\Support\NotificationService;
use App\Support\Permissions;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approval routing by requester department:
 *
 *   Technical Department : Technical Lead → HR → MD   (mandatory sequence)
 *   HR Department        : HR review → MD             (assigned to MD approval)
 *   Managing Director    : HR review                  (HR must approve too)
 *
 * A user can never approve their own requisition, so any stage the requester
 * personally holds is removed from the chain. No stage may be skipped:
 * each decision validates that the previous stages are complete.
 */
class RequisitionWorkflowService
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * The approval chain for a requisition, excluding stages the requester holds
     * (self-approval is never permitted).
     *
     * @return list<ApprovalStage>
     */
    public function chainFor(Requisition $requisition): array
    {
        $requester = $requisition->requester ?? User::find($requisition->requester_user_id);

        $base = match (true) {
            $requester?->isManagingDirector() => [ApprovalStage::Hr],
            $requester?->isHr() => [ApprovalStage::Hr, ApprovalStage::Md],
            default => [ApprovalStage::TechnicalLead, ApprovalStage::Hr, ApprovalStage::Md],
        };

        $permissionFor = fn (ApprovalStage $stage) => match ($stage) {
            ApprovalStage::TechnicalLead => Permissions::REQUISITIONS_REVIEW_TECHNICAL,
            ApprovalStage::Hr => Permissions::REQUISITIONS_REVIEW_HR,
            ApprovalStage::Md => Permissions::REQUISITIONS_APPROVE_MD,
            default => null,
        };

        return array_values(array_filter($base, function (ApprovalStage $stage) use ($requester, $permissionFor) {
            $permission = $permissionFor($stage);

            return ! ($permission && $requester?->hasPermission($permission));
        }));
    }

    /** @return list<ApprovalStage> */
    public function chainOf(Requisition $requisition): array
    {
        if (is_array($requisition->approval_chain) && $requisition->approval_chain) {
            return array_values(array_filter(array_map(
                fn ($value) => ApprovalStage::tryFrom($value),
                $requisition->approval_chain
            )));
        }

        return $this->chainFor($requisition);
    }

    private function statusFor(ApprovalStage $stage): RequisitionStatus
    {
        return match ($stage) {
            ApprovalStage::TechnicalLead => RequisitionStatus::PendingTechnicalLead,
            ApprovalStage::Hr => RequisitionStatus::PendingHrReview,
            ApprovalStage::Md => RequisitionStatus::PendingMdApproval,
            default => RequisitionStatus::PendingHrReview,
        };
    }

    public function submit(Requisition $requisition, User $actor): Requisition
    {
        return DB::transaction(function () use ($requisition, $actor) {
            /** @var Requisition $requisition */
            $requisition = Requisition::whereKey($requisition->id)->lockForUpdate()->firstOrFail();

            if ($requisition->status !== RequisitionStatus::Draft && $requisition->status !== RequisitionStatus::ReturnedForCorrection) {
                throw ValidationException::withMessages([
                    'status' => 'Only a draft or returned requisition can be submitted.',
                ]);
            }

            if (Money::compare((string) $requisition->requested_amount, '0.00') <= 0) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'The requested amount must be greater than zero.',
                ]);
            }

            if (! $requisition->requisition_number) {
                $requisition->requisition_number = Sequence::next('requisition', 'REQ');
            }

            $chain = $this->chainFor($requisition);
            $requisition->approval_chain = array_map(fn (ApprovalStage $s) => $s->value, $chain);

            $previous = $requisition->status->value;

            if ($chain === []) {
                // The requester holds every approval authority — nothing to approve.
                $requisition->status = RequisitionStatus::ApprovedAwaitingDisbursement;
                $requisition->current_stage = ApprovalStage::Disbursement->value;
                $requisition->approved_amount = $requisition->requested_amount;
                $requisition->submitted_at = now();
                $requisition->save();

                $this->record($requisition, ApprovalStage::Hr, 'submitted', $actor, null,
                    'Submitted — no approval stage required (requester holds all approvals)', $previous, $requisition->status->value);
            } else {
                $first = $chain[0];
                $requisition->status = $this->statusFor($first);
                $requisition->current_stage = $first->value;
                $requisition->submitted_at = now();
                $requisition->save();

                $this->record($requisition, $first, 'submitted', $actor, null,
                    'Requisition submitted for approval', $previous, $requisition->status->value);

                $this->notifyStageActors($requisition, $first, 'New requisition awaiting your review',
                    "Requisition {$requisition->requisition_number} ({$requisition->title}) is awaiting {$first->label()} review.");
            }

            AuditLogger::log('requisition.submitted', Requisition::class, $requisition->id,
                "Submitted {$requisition->requisition_number}",
                ['status' => $previous], ['status' => $requisition->status->value]);

            return $requisition->refresh();
        });
    }

    public function approve(Requisition $requisition, User $actor, ?string $approvedAmount = null, ?string $comments = null): Requisition
    {
        return $this->decide($requisition, $actor, 'approved', $approvedAmount, $comments, null);
    }

    public function reject(Requisition $requisition, User $actor, string $reason, ?string $comments = null): Requisition
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A rejection reason is mandatory.']);
        }

        return $this->decide($requisition, $actor, 'rejected', null, $comments, $reason);
    }

    public function returnForCorrection(Requisition $requisition, User $actor, string $reason): Requisition
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is mandatory when returning a requisition.']);
        }

        return $this->decide($requisition, $actor, 'returned', null, null, $reason);
    }

    private function decide(Requisition $requisition, User $actor, string $action, ?string $approvedAmount, ?string $comments, ?string $reason): Requisition
    {
        return DB::transaction(function () use ($requisition, $actor, $action, $approvedAmount, $comments, $reason) {
            /** @var Requisition $requisition */
            $requisition = Requisition::whereKey($requisition->id)->lockForUpdate()->firstOrFail();

            $stage = $this->stageForActor($requisition, $actor);

            if ($action === 'approved' && $requisition->requester_user_id === $actor->id) {
                throw ValidationException::withMessages(['actor' => 'You cannot approve your own requisition.']);
            }

            $previous = $requisition->status->value;
            $chain = $this->chainOf($requisition);
            $index = array_search($stage, $chain, true);
            $isLast = $index === false || $index === count($chain) - 1;

            if ($action === 'approved') {
                $newStatus = $isLast
                    ? RequisitionStatus::ApprovedAwaitingDisbursement
                    : $this->statusFor($chain[$index + 1]);
            } elseif ($action === 'rejected') {
                $newStatus = match ($stage) {
                    ApprovalStage::TechnicalLead => RequisitionStatus::RejectedByTechnicalLead,
                    ApprovalStage::Hr => RequisitionStatus::RejectedByHr,
                    ApprovalStage::Md => RequisitionStatus::RejectedByMd,
                    default => RequisitionStatus::Cancelled,
                };
            } else {
                $newStatus = RequisitionStatus::ReturnedForCorrection;
            }

            $requisition->status = $newStatus;
            $requisition->current_stage = match (true) {
                $action !== 'approved' => null,
                $isLast => ApprovalStage::Disbursement->value,
                default => $chain[$index + 1]->value,
            };

            if ($action === 'approved') {
                if ($isLast) {
                    // The final approving stage fixes the payable amount.
                    $requisition->approved_amount = Money::normalize($approvedAmount ?? $requisition->requested_amount);
                } elseif ($approvedAmount !== null) {
                    // Earlier stages may endorse a suggested amount.
                    $requisition->approved_amount = Money::normalize($approvedAmount);
                }
            }

            $requisition->save();

            $this->record($requisition, $stage, $action, $actor, $approvedAmount, $comments ?? $reason, $previous, $newStatus->value);

            AuditLogger::log("requisition.{$action}", Requisition::class, $requisition->id,
                ucfirst($action) . " by {$stage->label()} for {$requisition->requisition_number}",
                ['status' => $previous, 'approved_amount' => $requisition->getOriginal('approved_amount')],
                ['status' => $newStatus->value, 'approved_amount' => $requisition->approved_amount]);

            $this->notifyOnDecision($requisition, $stage, $action, $actor, $chain, $isLast);

            return $requisition->refresh();
        });
    }

    private function stageForActor(Requisition $requisition, User $actor): ApprovalStage
    {
        $status = $requisition->status;

        $expected = match ($status) {
            RequisitionStatus::PendingTechnicalLead => ApprovalStage::TechnicalLead,
            RequisitionStatus::PendingHrReview => ApprovalStage::Hr,
            RequisitionStatus::PendingMdApproval => ApprovalStage::Md,
            default => null,
        };

        if ($expected === null) {
            throw ValidationException::withMessages([
                'status' => "This requisition is not awaiting a decision (current status: {$status->label()}).",
            ]);
        }

        $allowed = match ($expected) {
            ApprovalStage::TechnicalLead => $actor->hasPermission(Permissions::REQUISITIONS_REVIEW_TECHNICAL),
            ApprovalStage::Hr => $actor->hasPermission(Permissions::REQUISITIONS_REVIEW_HR),
            ApprovalStage::Md => $actor->hasPermission(Permissions::REQUISITIONS_APPROVE_MD),
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages([
                'actor' => 'You are not authorised to take this approval decision.',
            ]);
        }

        return $expected;
    }

    private function record(Requisition $requisition, ApprovalStage $stage, string $action, User $actor, ?string $amount, ?string $comment, ?string $previous, ?string $new): void
    {
        RequisitionApproval::create([
            'requisition_id' => $requisition->id,
            'stage' => $stage->value,
            'action' => $action,
            'user_id' => $actor->id,
            'employee_id' => $actor->employee_id,
            'decision_amount' => $amount !== null ? Money::normalize($amount) : null,
            'reason' => $action === 'rejected' || $action === 'returned' ? $comment : null,
            'comments' => $action === 'approved' || $action === 'submitted' ? $comment : null,
            'previous_status' => $previous,
            'new_status' => $new,
            'decided_at' => now(),
        ]);
    }

    private function notifyStageActors(Requisition $requisition, ApprovalStage $stage, string $title, string $message): void
    {
        $users = match ($stage) {
            ApprovalStage::TechnicalLead => User::active()->whereHas('roles', fn ($q) => $q->where('slug', 'technical_lead'))->get(),
            ApprovalStage::Hr => User::active()->whereHas('roles', fn ($q) => $q->whereIn('slug', ['hr', 'hr_finance']))->get(),
            ApprovalStage::Md => User::active()->whereHas('roles', fn ($q) => $q->where('slug', 'managing_director'))->get(),
            default => collect(),
        };

        foreach ($users as $user) {
            if ($user->id === $requisition->requester_user_id) {
                continue;
            }

            NotificationService::notify($user, 'requisition.' . $stage->value, $title, $message,
                "/requisitions/{$requisition->id}", ['requisition_id' => $requisition->id]);
        }
    }

    private function notifyOnDecision(Requisition $requisition, ApprovalStage $stage, string $action, User $actor, array $chain, bool $isLast): void
    {
        $requester = $requisition->requester;
        if ($requester) {
            NotificationService::notify(
                $requester,
                "requisition.{$action}",
                "Requisition {$requisition->requisition_number} {$action}",
                "Your requisition {$requisition->requisition_number} was {$action} by {$stage->label()}.",
                "/requisitions/{$requisition->id}",
                ['requisition_id' => $requisition->id, 'stage' => $stage->value]
            );
        }

        if ($action === 'approved' && ! $isLast) {
            $index = array_search($stage, $chain, true);
            $next = $chain[$index + 1] ?? null;
            if ($next) {
                $this->notifyStageActors($requisition, $next, 'Requisition awaiting your decision',
                    "Requisition {$requisition->requisition_number} is awaiting {$next->label()} decision.");
            }
        }
    }
}
