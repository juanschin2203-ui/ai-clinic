<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backend keys match REPORT_TYPES[].k in rocket-coding.jsx (line 50).
 * Labels match REPORT_TYPES[].l; fullNames match REPORT_TYPES[].full.
 */
enum ReportType: string
{
    case Qme = 'qme';
    case Ame = 'ame';
    case Pr1 = 'pr1';
    case Pr2 = 'pr2';
    case Initial = 'initial';
    case FollowUp = 'followup';
    case Procedure = 'procedure';
    case Mmi = 'mmi';
    case Impairment = 'impairment';
    case Causation = 'causation';

    public function label(): string
    {
        return match ($this) {
            self::Qme => 'QME',
            self::Ame => 'AME',
            self::Pr1 => 'PR-1',
            self::Pr2 => 'PR-2',
            self::Initial => 'Initial',
            self::FollowUp => 'Follow-Up',
            self::Procedure => 'Procedure',
            self::Mmi => 'MMI',
            self::Impairment => 'Impairment',
            self::Causation => 'Causation',
        };
    }

    public function fullName(): string
    {
        return match ($this) {
            self::Qme => 'Qualified Medical Evaluation',
            self::Ame => 'Agreed Medical Evaluation',
            self::Pr1 => 'Primary Treating Physician Initial Report',
            self::Pr2 => 'Primary Treating Physician Progress Report',
            self::Initial => 'Initial Evaluation',
            self::FollowUp => 'Follow-Up Visit',
            self::Procedure => 'Procedure Note',
            self::Mmi => 'Maximum Medical Improvement',
            self::Impairment => 'Impairment Rating',
            self::Causation => 'Causation Analysis',
        };
    }

    /**
     * Required sections per report type — drives Step 6's AI "draft report" stage.
     * Keep these in sync with REPORT_TYPES[].sec in rocket-coding.jsx.
     */
    public function requiredSections(): array
    {
        return match ($this) {
            self::Qme => ['Disputed Issues', 'History', 'Records', 'Exam', 'Diagnoses', 'Causation', 'Apportionment', 'Treatment', 'Restrictions', 'WPI'],
            self::Ame => ['Agreed Issues', 'History', 'Records', 'Exam', 'Diagnoses', 'Causation', 'Impairment'],
            self::Pr1 => ['Subjective', 'Objective', 'Diagnosis', 'Treatment Plan', 'Work Status', 'Disability'],
            self::Pr2 => ['Interval History', 'Current Status', 'Treatment', 'Work Status', 'Next Visit'],
            self::Initial => ['History', 'Physical Exam', 'Assessment', 'Plan'],
            self::FollowUp => ['Interval History', 'Exam', 'Assessment', 'Plan'],
            self::Procedure => ['Indication', 'Procedure', 'Findings', 'Post-Op'],
            self::Mmi => ['History', 'Exam', 'Diagnoses', 'MMI Status', 'Work Restrictions', 'Future Medical'],
            self::Impairment => ['History', 'Exam', 'Diagnoses', 'Impairment Rating', 'WPI'],
            self::Causation => ['History', 'Medical Records', 'Exam', 'Causation Analysis', 'Conclusions'],
        };
    }
}
