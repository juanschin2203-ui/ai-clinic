<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller. Adds the AuthorizesRequests trait back — Laravel 11+
 * removed it from the skeleton, but our controllers rely on
 * `$this->authorize(...)` for policy checks.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
