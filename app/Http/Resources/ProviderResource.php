<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape mirrors JSX PROVIDERS (line 19):
 *   id, clinic, name, npi, license, specialty, sigBlock
 * Plus backend-only fields: userId (linked User account), active.
 *
 * @property-read Provider $resource
 */
class ProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Provider $provider */
        $provider = $this->resource;

        return [
            'id' => $provider->id,
            'clinic' => $provider->clinic,
            'userId' => $provider->userId,
            'name' => $provider->name,
            'npi' => $provider->npi,
            'license' => $provider->license,
            'specialty' => $provider->specialty,
            'sigBlock' => $provider->sigBlock,
            'active' => $provider->active,
        ];
    }
}
