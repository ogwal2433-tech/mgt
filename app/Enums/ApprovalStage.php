<?php

namespace App\Enums;

enum ApprovalStage: string
{
    case TechnicalLead = 'technical_lead';
    case Hr = 'hr';
    case Md = 'md';
    case Disbursement = 'disbursement';
    case Accountability = 'accountability';

    public function label(): string
    {
        return match ($this) {
            self::TechnicalLead => 'Technical Lead',
            self::Hr => 'Human Resources',
            self::Md => 'Managing Director',
            self::Disbursement => 'HR Finance & Accounts',
            self::Accountability => 'Accountability Review',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::TechnicalLead => 1,
            self::Hr => 2,
            self::Md => 3,
            self::Disbursement => 4,
            self::Accountability => 5,
        };
    }
}
