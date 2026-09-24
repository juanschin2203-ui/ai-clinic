<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\User;

/**
 * Patients are PHI. Tenant-scoping at the ORM layer already makes it
 * impossible for a clinic user to *see* another clinic's patients at all
 * (the global scope filters the query). These policy methods add the
 * explicit assertion at the controller layer for single-record ops,
 * ensuring a 403 rather than a 404 if something slipped through.
 */
class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== null;  // any authenticated role; scope filters
    }

    public function view(User $user, Patient $patient): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $user->cid !== null && $user->cid === $patient->clinicId;
    }

    public function create(User $user): bool
    {
        // Scheduler, coder, full can create patients. Viewer cannot.
        return in_array($user->role, [UserRole::Clinic, UserRole::Provider, UserRole::Staff], strict: true)
            && $user->permission?->canWrite() === true;
    }

    public function update(User $user, Patient $patient): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $user->cid !== null
            && $user->cid === $patient->clinicId
            && $user->permission?->canWrite() === true;
    }

    public function delete(User $user, Patient $patient): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        // Only `full` permission at the clinic can delete a patient.
        return $user->cid !== null
            && $user->cid === $patient->clinicId
            && $user->permission?->value === 'full';
    }
}
