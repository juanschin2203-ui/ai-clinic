<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CaseStatus;
use App\Enums\InputSource;
use App\Enums\ReportType;
use App\Enums\UsState;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * MedicalCase — the core PHI-bearing working entity.
 *
 * Class is named MedicalCase because `Case` is a PHP reserved word; the
 * underlying table is `cases` to match the JSX/URL (`/api/cases`). Tenant FK
 * is `clinic` (not `clinicId`) preserving the JSX naming.
 *
 * JSON columns hold AI-pipeline output snapshots. See the design decision
 * log in STEP-2-NOTES.md for when to split into normalized tables.
 */
class MedicalCase extends AbstractRocketModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'cases';

    /** Tenant FK name matches JSX (`clinic`, not `clinicId`). */
    protected static string $tenantColumn = 'clinic';

    protected $fillable = [
        'num',
        'clinic',
        'clinicName',
        'patientId',
        'providerId',
        'assignedCoderId',
        'patient',
        'dob',
        'dos',
        'gender',
        'claimNum',
        'emrId',
        'state',
        'status',
        'reportType',
        'inputSource',
        'svcs',
        'total',
        'notes',
        'suggestedCPT',
        'suggestedDX',
        'modifiers',
        'stateCompliance',
        'emails',
        'audit',
        'timeline',
        'aiTokensUsed',
        'aiTokenBudget',
        'aiCompletedAt',
        'deliveredAt',
    ];

    protected $casts = [
        'dob' => 'date',
        'dos' => 'date',
        'status' => CaseStatus::class,
        'reportType' => ReportType::class,
        'inputSource' => InputSource::class,
        'state' => UsState::class,
        'svcs' => 'array',
        'total' => 'decimal:2',
        'suggestedCPT' => 'array',
        'suggestedDX' => 'array',
        'modifiers' => 'array',
        'stateCompliance' => 'array',
        'emails' => 'array',
        'audit' => 'array',
        'timeline' => 'array',
        'aiTokensUsed' => 'integer',
        'aiTokenBudget' => 'integer',
        'aiCompletedAt' => 'datetime',
        'deliveredAt' => 'datetime',
    ];

    // ----- domain methods ---------------------------------------------------

    public function hasAiSuggestions(): bool
    {
        return ! empty($this->suggestedCPT);
    }

    public function isWithinAiBudget(int $additionalTokens = 0): bool
    {
        return ($this->aiTokensUsed + $additionalTokens) <= $this->aiTokenBudget;
    }

    // ----- relationships ----------------------------------------------------

    public function clinicRelation(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinic');
    }

    public function patientModel(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patientId');
    }

    public function providerModel(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'providerId');
    }

    public function assignedCoder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignedCoderId');
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'caseId');
    }
}
