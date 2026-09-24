<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MedicalCase;
use App\Models\User;

/**
 * MedicalCase policy — identical tenant enforcement to Patient, with extra
 * granular abilities for the case-workflow actions (approve / send-back /
 * rerun-ai). `coder` permission is what gates these actions on the clinic
 * side; Rocket admins always pass.
 */
class MedicalCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== null;
    }

    public function view(User $user, MedicalCase $case): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $user->cid !== null && $user->cid === $case->clinic;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Clinic, UserRole::Provider, UserRole::Staff], strict: true)
            && $user->permission?->canWrite() === true;
    }

    public function update(User $user, MedicalCase $case): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $user->cid !== null
            && $user->cid === $case->clinic
            && $user->permission?->canWrite() === true;
    }

    public function delete(User $user, MedicalCase $case): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $user->cid !== null
            && $user->cid === $case->clinic
            && $user->permission?->value === 'full';
    }

    public function approve(User $user, MedicalCase $case): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        return $user->cid !== null
            && $user->cid === $case->clinic
            && $user->permission?->canCode() === true;
    }

    public function sendBack(User $user, MedicalCase $case): bool
    {
        return $this->approve($user, $case);  // same gate — it's a coder decision
    }

    public function rerunAi(User $user, MedicalCase $case): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            return true;
        }

        // Clinic side: `full` only — re-running the AI costs tokens and
        // is an admin/full-access operation at the clinic.
        return $user->cid !== null
            && $user->cid === $case->clinic
            && $user->permission?->value === 'full';
    }
}
