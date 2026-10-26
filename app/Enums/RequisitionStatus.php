<?php

namespace App\Enums;

enum RequisitionStatus: string
{
    case Draft = 'DRAFT';
    case PendingTechnicalLead = 'PENDING_TECHNICAL_LEAD';
    case PendingHrReview = 'PENDING_HR_REVIEW';
    case PendingMdApproval = 'PENDING_MD_APPROVAL';
    case ReturnedForCorrection = 'RETURNED_FOR_CORRECTION';
    case RejectedByTechnicalLead = 'REJECTED_BY_TECHNICAL_LEAD';
    case RejectedByHr = 'REJECTED_BY_HR';
    case RejectedByMd = 'REJECTED_BY_MD';
    case ApprovedAwaitingDisbursement = 'APPROVED_AWAITING_DISBURSEMENT';
    case PartiallyDisbursed = 'PARTIALLY_DISBURSED';
    case FullyDisbursed = 'FULLY_DISBURSED';
    case PendingAccountability = 'PENDING_ACCOUNTABILITY';
    case AccountabilityUnderReview = 'ACCOUNTABILITY_UNDER_REVIEW';
    case Closed = 'CLOSED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingTechnicalLead => 'Pending Technical Lead',
            self::PendingHrReview => 'Pending HR Review',
            self::PendingMdApproval => 'Pending MD Approval',
            self::ReturnedForCorrection => 'Returned for Correction',
            self::RejectedByTechnicalLead => 'Rejected by Technical Lead',
            self::RejectedByHr => 'Rejected by HR',
            self::RejectedByMd => 'Rejected by MD',
            self::ApprovedAwaitingDisbursement => 'Approved – Awaiting Disbursement',
            self::PartiallyDisbursed => 'Partially Disbursed',
            self::FullyDisbursed => 'Fully Disbursed',
            self::PendingAccountability => 'Pending Accountability',
            self::AccountabilityUnderReview => 'Accountability Under Review',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft, self::ReturnedForCorrection => 'bg-gray-100 text-gray-700',
            self::PendingTechnicalLead, self::PendingHrReview, self::PendingMdApproval => 'bg-amber-100 text-amber-800',
            self::RejectedByTechnicalLead, self::RejectedByHr, self::RejectedByMd, self::Cancelled => 'bg-red-100 text-red-800',
            self::ApprovedAwaitingDisbursement => 'bg-blue-100 text-blue-800',
            self::PartiallyDisbursed, self::FullyDisbursed, self::PendingAccountability, self::AccountabilityUnderReview => 'bg-indigo-100 text-indigo-800',
            self::Closed => 'bg-green-100 text-green-800',
        };
    }

    public function isRejected(): bool
    {
        return in_array($this, [
            self::RejectedByTechnicalLead,
            self::RejectedByHr,
            self::RejectedByMd,
        ], true);
    }

    public function isDisbursable(): bool
    {
        return in_array($this, [
            self::ApprovedAwaitingDisbursement,
            self::PartiallyDisbursed,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Closed,
            self::Cancelled,
            self::RejectedByTechnicalLead,
            self::RejectedByHr,
            self::RejectedByMd,
        ], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
