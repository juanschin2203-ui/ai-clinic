<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ClinicAdmin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ClinicAdmin $resource
 */
class ClinicAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ClinicAdmin $admin */
        $admin = $this->resource;

        return [
            'id' => $admin->id,
            'clinic' => $admin->clinic,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role,
            'lastLogin' => $admin->lastLogin?->toDateString(),
        ];
    }
}
