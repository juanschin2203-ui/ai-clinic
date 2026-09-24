<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== null;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $user->cid !== null && $user->cid === $appointment->clinicId;
    }

    public function create(User $user): bool
    {
        // Schedulers, clinic users, and admins can create appointments.
        return in_array($user->role, [UserRole::Clinic, UserRole::Staff, UserRole::Provider, UserRole::Admin, UserRole::Deployer], strict: true)
            && ($user->permission?->canWrite() === true
                || in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true));
    }

    public function update(User $user, Appointment $appointment): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $user->cid !== null
            && $user->cid === $appointment->clinicId
            && $user->permission?->canWrite() === true;
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }
}
