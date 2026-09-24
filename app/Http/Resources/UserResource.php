<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape matches JSX USERS exactly so the frontend can consume without
 * field-rename adapters. Notably `cn` (clinic name) is computed on the
 * fly from the clinic relation — not stored, per STEP-2-NOTES decision.
 *
 * @property-read User $resource
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value,
            'cid' => $user->cid,
            'cn' => $user->clinic?->name,
            'initials' => $user->initials,
            'permission' => $user->permission?->value,
            'active' => $user->active,
            'lastLogin' => $user->lastLogin?->toDateString(),
        ];
    }
}
