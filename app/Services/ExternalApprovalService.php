<?php

namespace App\Services;

use App\Models\ExternalRequisition;
use App\Models\ExternalRequisitionApproval;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * External requisition approval chain:
 *
 *   TECHNICAL LEAD (confirm all) → HR → MD
 *
 * The approval feedback is emailed to the external party ONLY after every
 * internal stage has approved — or immediately if the form is rejected, so the
 * party is never left waiting. Feedback is never sent mid-approval.
 */
class ExternalApprovalService
{
    private const CHAIN = ['technical_lead', 'hr', 'md'];

    /** Status meaning "awaiting this stage's decision". */
    private const AWAITING = [
        'technical_lead' => 'awaiting_lead',
        'hr' => 'awaiting_hr',
        'md' => 'awaiting_md',
    ];

    public function decide(ExternalRequisition $requisition, User $actor, string $action, ?string $comments = null): ExternalRequisition
    {
        return DB::transaction(function () use ($requisition, $actor, $action, $comments) {
            /** @var ExternalRequisition $requisition */
            $requisition = ExternalRequisition::whereKey($requisition->id)->lockForUpdate()->firstOrFail();

            if ($action === 'rejected' && trim((string) $comments) === '') {
                throw ValidationException::withMessages(['comments' => 'A rejection reason is mandatory.']);
            }

            $stage = $this->stageFor($requisition, $actor);
            $previous = $requisition->status;

            $chainIndex = array_search($stage, self::CHAIN, true);
            $isLast = $chainIndex === count(self::CHAIN) - 1;

            if ($action === 'approved') {
                $newStatus = $isLast ? 'approved' : self::AWAITING[self::CHAIN[$chainIndex + 1]];
            } else {
                $newStatus = 'rejected';
            }

            $requisition->update([
                'status' => $newStatus,
                'approval_feedback' => $comments ?: $requisition->approval_feedback,
                'is_approved' => $newStatus === 'approved',
                'approved_by_user_id' => $newStatus === 'approved' ? $actor->id : $requisition->approved_by_user_id,
                'approved_at' => $newStatus === 'approved' ? now() : $requisition->approved_at,
            ]);

            ExternalRequisitionApproval::create([
                'external_requisition_id' => $requisition->id,
                'stage' => $stage,
                'action' => $action,
                'user_id' => $actor->id,
                'comments' => $comments,
                'previous_status' => $previous,
                'new_status' => $newStatus,
                'decided_at' => now(),
            ]);

            AuditLogger::log("external_requisition.{$action}", ExternalRequisition::class, $requisition->id,
                ucfirst($action) . " by {$stage} for {$requisition->requisition_number}",
                ['status' => $previous], ['status' => $newStatus]);

            // THE GATE: the external party only hears the outcome once the full
            // internal chain has decided (all approved, or a rejection).
            if (in_array($newStatus, ['approved', 'rejected'], true)) {
                $this->sendFeedbackToExternal($requisition);
            }

            $this->notifyInternal($requisition, $stage, $action);

            return $requisition->refresh();
        });
    }

    private function stageFor(ExternalRequisition $requisition, User $actor): string
    {
        $expected = match ($requisition->status) {
            'submitted', 'awaiting_external' => 'technical_lead',
            'awaiting_hr' => 'hr',
            'awaiting_md' => 'md',
            default => null,
        };

        if ($expected === null) {
            throw ValidationException::withMessages([
                'status' => "This form is not awaiting an internal decision (status: {$requisition->status}).",
            ]);
        }

        $allowed = match ($expected) {
            'technical_lead' => $actor->isTechnicalLead() || $actor->hasPermission(\App\Support\Permissions::EXTERNAL_REQUISITIONS_MANAGE),
            'hr' => $actor->isHr(),
            'md' => $actor->isManagingDirector(),
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages([
                'actor' => 'You are not authorised to take this decision.',
            ]);
        }

        return $expected;
    }

    private function sendFeedbackToExternal(ExternalRequisition $requisition): void
    {
        if (! $requisition->external_email) {
            return;
        }

        try {
            Mail::to($requisition->external_email)->send(
                new \App\Mail\ExternalFeedbackMail($requisition->fresh(['items', 'approvals.user']))
            );

            $requisition->update(['feedback_sent_at' => now()]);

            AuditLogger::log('external_requisition.feedback_sent', ExternalRequisition::class, $requisition->id,
                "Sent approval feedback to {$requisition->external_email}");
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifyInternal(ExternalRequisition $requisition, string $stage, string $action): void
    {
        $targets = User::active()
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['technical_lead', 'hr', 'managing_director']))
            ->get();

        foreach ($targets as $user) {
            NotificationService::notify(
                $user,
                "external_requisition.{$action}",
                "External form {$action}",
                "{$requisition->requisition_number} was {$action} at the {$stage} stage.",
                "/external-requisitions/{$requisition->id}",
                ['external_requisition_id' => $requisition->id]
            );
        }
    }
}
