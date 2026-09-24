<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum's access-token model, overridden to use a UUID primary key.
 *
 * Sanctum's default model expects auto-incrementing bigint ids; we use
 * UUIDs throughout the app. Wired via Sanctum::usePersonalAccessTokenModel()
 * in AppServiceProvider. Table columns retain snake_case (tokenable_id,
 * expires_at, etc.) because Sanctum's internal queries hard-code them.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;
}
