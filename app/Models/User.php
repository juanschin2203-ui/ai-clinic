<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    public const DELETED_AT = 'deletedAt';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'cid',
        'initials',
        'permission',
        'active',
        'lastLogin',
    ];

    protected $hidden = [
        'password',
        'rememberToken',
        'loginAttempts',
        'lockedUntil',
    ];

    public function getRememberTokenName(): string
    {
        return 'rememberToken';
    }

    protected function casts(): array
    {
        return [
            'emailVerifiedAt' => 'datetime',
            'lastLogin' => 'date',
            'lockedUntil' => 'datetime',
            'active' => 'boolean',
            'loginAttempts' => 'integer',
            'password' => 'hashed',
            'role' => UserRole::class,
            'permission' => Permission::class,
        ];
    }

    // ----- domain methods ---------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isDeployer(): bool
    {
        return $this->role === UserRole::Deployer;
    }

    public function belongsToClinic(string $clinicId): bool
    {
        return $this->cid === $clinicId;
    }

    public function isLocked(): bool
    {
        return $this->lockedUntil !== null
            && CarbonImmutable::parse($this->lockedUntil)->isFuture();
    }

    // ----- relationships ----------------------------------------------------

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'cid');
    }

    public function assignedCases(): HasMany
    {
        return $this->hasMany(MedicalCase::class, 'assignedCoderId');
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class, 'userId');
    }

    public function auditEntries(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'userId');
    }
}
