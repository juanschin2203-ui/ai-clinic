<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Clinic;
use Illuminate\Support\Collection;

/**
 * Clinic-specific repository contract. Extends the base contract with
 * domain-specific query methods (active, byState, with case counts, etc.).
 *
 * Return type refinement: methods return Clinic (not Model) so callers get
 * proper IDE autocomplete.
 */
interface ClinicRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email): ?Clinic;

    public function findByNpi(string $npi): ?Clinic;

    /** Active clinics only (clinics.active=true and not soft-deleted). */
    public function active(): Collection;

    /** Self-coded clinics (clinic codes its own cases). */
    public function selfCoded(): Collection;

    /** We-code clinics (Rocket codes on their behalf). */
    public function rocketCoded(): Collection;

    public function byState(string $stateCode): Collection;
}
