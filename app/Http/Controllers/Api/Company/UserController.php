<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyUserRequest;
use App\Http\Requests\Company\UpdateCompanyUserRequest;
use App\Models\User;
use App\Services\Company\CompanyUserService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(private CompanyUserService $service) {}

    public function index(Request $request) { return ApiResponse::success($this->service->index($request->only(['search', 'role'])), 'Company users fetched successfully'); }
    public function store(StoreCompanyUserRequest $request) { return ApiResponse::success($this->service->create($request->validated()), 'Company user created successfully', 201); }
    public function show(User $id) { return ApiResponse::success($this->service->show($this->resolveCompanyUser($id)), 'Company user fetched successfully'); }
    public function update(UpdateCompanyUserRequest $request, User $id) { return ApiResponse::success($this->service->update($this->resolveCompanyUser($id), $request->validated()), 'Company user updated successfully'); }
    public function destroy(User $id) { $this->service->delete($this->resolveCompanyUser($id)); return ApiResponse::success(null, 'Company user deleted successfully'); }

    private function resolveCompanyUser(User $user): User
    {
        abort_unless($user->company_id === auth()->user()->company_id, 404);

        return $user;
    }
}
