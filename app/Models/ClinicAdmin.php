<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicAdmin extends AbstractRocketModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'clinic_admins';

    /** Tenant FK name matches JSX. */
    protected static string $tenantColumn = 'clinic';

    protected $fillable = [
        'clinic',
        'name',
        'email',
        'role',
        'lastLogin',
    ];

    protected $casts = [
        'lastLogin' => 'date',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'clinic');
    }
}
