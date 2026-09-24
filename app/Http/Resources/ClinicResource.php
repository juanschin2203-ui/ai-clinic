<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Clinic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape mirrors JSX CLINICS (rocket-coding.jsx line 15):
 *   id, name, state, email, active, cases, city, addr, zip, phone, npi,
 *   taxId, timezone, logo, selfCoded, reportFavs, notif, users, providers
 *
 * `cases` / `users` / `providers` are DERIVED counts — computed on the fly
 * when the model has them loaded via withCount().
 *
 * @property-read Clinic $resource
 */
class ClinicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Clinic $clinic */
        $clinic = $this->resource;

        return [
            'id' => $clinic->id,
            'name' => $clinic->name,
            'state' => $clinic->state?->value,
            'email' => $clinic->email,
            'active' => $clinic->active,
            'cases' => $clinic->cases_count ?? null,
            'city' => $clinic->city,
            'addr' => $clinic->addr,
            'zip' => $clinic->zip,
            'phone' => $clinic->phone,
            'npi' => $clinic->npi,
            'taxId' => $clinic->taxId,
            'timezone' => $clinic->timezone,
            'logo' => $clinic->logo,
            'selfCoded' => $clinic->selfCoded,
            'reportFavs' => $clinic->reportFavs ?? [],
            'notif' => $clinic->notif ?? (object) [],
            'users' => $clinic->users_count ?? null,
            'providers' => $clinic->providers_count ?? null,
        ];
    }
}
