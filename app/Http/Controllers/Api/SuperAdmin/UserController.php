<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\User\StoreUserRequest;
use App\Http\Requests\SuperAdmin\User\UpdateUserRequest;
use App\Http\Resources\SuperAdmin\UserResource;
use App\Models\User;
use App\Services\SuperAdmin\UserManagementService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserManagementService $service) {}

    public function index(Request $request)
    {
        $users = $this->service->list(
            $request->query('q'),
            $request->query('role'),
            $request->query('status'),  // active | inactive
            (int) $request->query('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'items' => UserResource::collection($users->items()),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'last_page' => $users->lastPage(),
                ],
            ],
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ], 201);
    }

    public function show(User $user)
    {
        $user = $this->service->show($user->id);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $updated = $this->service->update($user, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new UserResource($updated),
        ]);
    }

    public function toggleStatus(User $user)
    {
        $updated = $this->service->toggleStatus($user);

        return response()->json([
            'success' => true,
            'data' => new UserResource($updated),
        ]);
    }

    public function destroy(User $user)
    {
        $this->service->delete($user);

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }
}
