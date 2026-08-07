<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Support\ApiResponse;
use App\Support\Clinic\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveClinicBranchContext
{
    public function __construct(private BranchContext $branchContext)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $headerBranchId = $request->header('X-Branch-Id');

        if ($headerBranchId === null || $headerBranchId === '' || strtolower((string) $headerBranchId) === 'all') {
            return $next($request);
        }

        if (! ctype_digit((string) $headerBranchId)) {
            return ApiResponse::error('Invalid branch context.', 422, [
                'X-Branch-Id' => ['The branch id header must be an integer.'],
            ]);
        }

        $clinicId = $request->user()?->clinic_id;
        if (! $clinicId) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $branch = Branch::query()->find((int) $headerBranchId);
        if (! $branch) {
            return ApiResponse::error('Branch not found.', 404);
        }

        if ((int) $branch->clinic_id !== (int) $clinicId) {
            return ApiResponse::error('Selected branch does not belong to this clinic.', 403);
        }

        $this->branchContext->set($request, $branch);

        $request->merge(['branch_id' => $branch->id]);
        $request->query->set('branch_id', $branch->id);

        return $next($request);
    }
}
