<?php

namespace App\Services\Company;

use App\Http\Resources\Company\CompanyUserResource;
use App\Models\User;
use App\Support\UserRoleManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyUserService
{
    private const MANAGEABLE_ROLES = [
        'sales_rep',
        'delivery_staff',
    ];

  public function index(array $filters = []): array
{
    return CompanyUserResource::collection(
        User::query()
            ->where('company_id', auth()->user()->company_id)
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->latest('id')
            ->get()
    )->resolve();
}

    public function show(User $user): array
    {
        return (new CompanyUserResource($user))->resolve();
    }

    public function create(array $data): array
    {
        $this->ensureManageableRole($data['role']);
        UserRoleManager::ensureRoleExists($data['role']);
        unset($data['password_confirmation']);

        $user = User::create([
            'company_id' => auth()->user()->company_id,
            'name' => $data['name'],
            'username' => $data['username'] ?? Str::slug($data['name'], ''),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'status' => $data['status'] ?? 'Active',
            'is_active' => ($data['status'] ?? 'Active') === 'Active',
        ]);
        $user->syncRoles([$data['role']]);
        return $this->show($user);
    }

    public function update(User $user, array $data): array
    {
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        unset($data['password_confirmation']);

        if (isset($data['status'])) {
            $data['is_active'] = $data['status'] === 'Active';
        }

        if (isset($data['role'])) {
            $this->ensureManageableRole($data['role']);
        }

        $user->update($data);
        if (! empty($data['role'])) {
            UserRoleManager::ensureRoleExists($data['role']);
            $user->syncRoles([$data['role']]);
        }
        return $this->show($user->fresh());
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    private function ensureManageableRole(string $role): void
    {
        if (! in_array($role, self::MANAGEABLE_ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => ['Super Admin is the only role allowed to create or assign admin accounts.'],
            ]);
        }
    }
}
