<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\StoreClinicUserRequest;
use App\Http\Requests\Clinic\UpdateClinicUserRequest;
use App\Http\Resources\Clinic\ClinicUserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $this->ensureClinicAdmin();

        $users = User::query()
            ->where('clinic_id', auth()->user()->clinic_id)
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'clinic_admin'))
            ->latest('id')
            ->get();

        return ApiResponse::success(ClinicUserResource::collection($users)->resolve(), 'Clinic users fetched successfully');
    }

    public function store(StoreClinicUserRequest $request)
    {
        $this->ensureClinicAdmin();

        $data = $request->validated();

        $user = User::query()->create([
            'clinic_id' => auth()->user()->clinic_id,
            'name' => $data['name'],
            'username' => $data['username'] ?? Str::slug($data['name'], ''),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'status' => $data['status'] ?? 'Active',
            'is_active' => ($data['status'] ?? 'Active') === 'Active',
            'is_verified' => true,
        ]);

        $user->syncRoles([$data['role']]);

        return ApiResponse::success((new ClinicUserResource($user))->resolve(), 'Clinic user created successfully', 201);
    }

    public function show(User $id)
    {
        $this->ensureClinicAdmin();

        return ApiResponse::success((new ClinicUserResource($this->resolveClinicUser($id)))->resolve(), 'Clinic user fetched successfully');
    }

    public function update(UpdateClinicUserRequest $request, User $id)
    {
        $this->ensureClinicAdmin();

        $user = $this->resolveClinicUser($id);
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        unset($data['password_confirmation']);

        if (array_key_exists('status', $data)) {
            $data['is_active'] = $data['status'] === 'Active';
        }

        $role = $data['role'] ?? null;
        unset($data['role']);

        if ($role) {
            $data['role'] = $role;
        }

        $user->update($data);

        if ($role) {
            $user->syncRoles([$role]);
        }

        return ApiResponse::success((new ClinicUserResource($user->fresh()))->resolve(), 'Clinic user updated successfully');
    }

    public function destroy(User $id)
    {
        $this->ensureClinicAdmin();

        $this->resolveClinicUser($id)->delete();

        return ApiResponse::success(null, 'Clinic user deleted successfully');
    }

    private function resolveClinicUser(User $user): User
    {
        abort_unless($user->clinic_id === auth()->user()->clinic_id, 404);
        abort_if($user->hasRole('clinic_admin'), 404);

        return $user;
    }

    private function ensureClinicAdmin(): void
    {
        abort_unless(
            auth()->user()?->hasRole('clinic_admin') && auth()->user()?->clinic_id,
            403,
            'Only clinic admin can manage clinic users.'
        );
    }
}
