<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape:
 * {
 *   accessToken: string,
 *   accessTokenExpiresAt: ISO8601,
 *   refreshToken: string,
 *   refreshTokenExpiresAt: ISO8601,
 *   user: UserResource
 * }
 *
 * Caller passes an associative array matching AuthService::login() output.
 */
class AuthTokensResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'accessToken' => $this->resource['accessToken'],
            'accessTokenExpiresAt' => ($this->resource['accessTokenExpiresAt'] instanceof CarbonImmutable)
                ? $this->resource['accessTokenExpiresAt']->toIso8601String()
                : $this->resource['accessTokenExpiresAt'],
            'refreshToken' => $this->resource['refreshToken'],
            'refreshTokenExpiresAt' => ($this->resource['refreshTokenExpiresAt'] instanceof CarbonImmutable)
                ? $this->resource['refreshTokenExpiresAt']->toIso8601String()
                : $this->resource['refreshTokenExpiresAt'],
            'user' => isset($this->resource['user'])
                ? (new UserResource($this->resource['user']))->toArray($request)
                : null,
        ];
    }
}
