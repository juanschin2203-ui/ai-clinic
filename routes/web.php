<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// The Rocket Coding backend is headless — the React prototype (see
// files/rocket-coding.jsx) is the frontend and will be served separately
// in production. The root route here is informational only: it tells a
// human who hits http://<host>/ in a browser where the API lives.
Route::get('/', fn () => view('landing'));
