<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape mirrors JSX SERVICES (line 42): id, cat, title, price.
 *
 * @property-read Service $resource
 */
class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Service $svc */
        $svc = $this->resource;

        return [
            'id' => $svc->id,
            'cat' => $svc->cat,
            'title' => $svc->title,
            'price' => (float) $svc->price,
            'active' => $svc->active,
        ];
    }
}
