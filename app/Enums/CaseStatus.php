<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Values are the backend keys (stored in DB).
 * Labels match what the JSX prototype renders in status pills.
 */
enum CaseStatus: string
{
    case Processing = 'processing';
    case NeedsReview = 'needsReview';
    case PendingDiagnosisApproval = 'pendingDiagnosisApproval';
    case Completed = 'completed';
    case Delivered = 'delivered';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Processing',
            self::NeedsReview => 'Needs Review',
            self::PendingDiagnosisApproval => 'Pending Diagnosis Approval',
            self::Completed => 'Completed',
            self::Delivered => 'Delivered',
            self::Deleted => 'Deleted',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Processing, self::NeedsReview, self::PendingDiagnosisApproval], strict: true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Deleted], strict: true);
    }
}
