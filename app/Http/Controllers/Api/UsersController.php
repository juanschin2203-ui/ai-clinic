<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\InviteUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $query = $this->users->query();

        // Clinic users see only their clinic's users. Admins see all.
        $actor = $request->user();
        if (! in_array($actor->role, [UserRole::Admin, UserRole::Deployer], strict: true)) {
            $query->where('cid', $actor->cid);
        } elseif ($request->filled('clinicId')) {
            $query->where('cid', $request->string('clinicId'));
        }

        if ($request->filled('role')) {
            $query->where('role', $request->string('role'));
        }

        return UserResource::collection($query->with('clinic')->orderBy('name')->get());
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->loadMissing('clinic');

        return (new UserResource($user))->response();
    }

    public function store(InviteUserRequest $request): JsonResponse
    {
        // Temp password; invitation email (Step 6) will prompt reset flow.
        $tempPassword = Str::random(16);

        $user = $this->users->create(
            $request->validated() + ['password' => Hash::make($tempPassword)],
        );

        $user->loadMissing('clinic');

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updated = $this->users->update($user->id, $request->validated());
        $updated->loadMissing('clinic');

        return (new UserResource($updated))->response();
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->users->delete($user->id);

        return response()->json(null, 204);
    }
}
