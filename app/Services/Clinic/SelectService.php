<?php

namespace App\Services\Clinic;

use App\Http\Resources\Common\SelectOptionResource;
use App\Repositories\Clinic\Select\ClinicSelectRepositoryInterface;
use App\Support\ServiceResult;

class SelectService
{
    private const RESOURCE_MAP = [
        'providers' => 'dentalLabs',
        'dental-labs' => 'dentalLabs',
        'dental_labs' => 'dentalLabs',
        'doctors' => 'doctors',
        'patients' => 'patients',
        'staff' => 'staff',
        'dentists' => 'dentists',
        'labs' => 'dentalLabs',
        'expense-categories' => 'expenseCategories',
        'insurance-companies' => 'insuranceCompanies',
        'insurance_companies' => 'insuranceCompanies',
        'response-speeds' => 'responseSpeeds',
            'suppliers' => 'materialCompanies',
    'material-companies' => 'materialCompanies',
    'material_companies' => 'materialCompanies',
    'material-categories' => 'materialCategories',
    'material_categories' => 'materialCategories',
    'inventory-categories' => 'materialCategories',
    ];

    public function __construct(private ClinicSelectRepositoryInterface $repository)
    {
    }

    public function options(string $resource, array $filters = []): array
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $method = self::RESOURCE_MAP[$resource] ?? null;
        if (! $method) {
            return ServiceResult::error('Select resource not found.', null, null, 404);
        }

        return ServiceResult::success(
            SelectOptionResource::collection($this->repository->{$method}($clinicId, $filters))->resolve(),
            'Select options fetched successfully'
        );
    }

}
