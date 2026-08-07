<?php

namespace App\Services\Clinic;

use App\Http\Resources\Common\SelectOptionResource;
use App\Repositories\Clinic\Select\ClinicSelectRepositoryInterface;
use App\Support\ServiceResult;

class SelectService
{
    private const RESOURCE_MAP = [
        'providers'              => 'dentalLabs',
        'dental-labs'            => 'dentalLabs',
        'dental_labs'            => 'dentalLabs',
        'doctors'                => 'doctors',
        'patients'               => 'patients',
        'staff'                  => 'staff',
        'services'               => 'services',
        'clinic-services'        => 'services',
        'clinic_services'        => 'services',
        'dentists'               => 'dentists',
        'labs'                   => 'dentalLabs',
        'expense-categories'     => 'expenseCategories',
        'insurance-companies'    => 'insuranceCompanies',
        'insurance_companies'    => 'insuranceCompanies',
        'syndicate-price-lists'  => 'syndicatePriceLists',
        'syndicate_price_lists'  => 'syndicatePriceLists',
        'insurance-price-lists'  => 'syndicatePriceLists',
        'claim-statuses'         => 'claimStatuses',
        'claim_statuses'         => 'claimStatuses',
        'insurance-claim-statuses' => 'claimStatuses',
        'response-speeds'        => 'responseSpeeds',
        'suppliers'              => 'materialCompanies',
        'material-companies'     => 'materialCompanies',
        'material_companies'     => 'materialCompanies',
        'material-categories'    => 'materialCategories',
        'material_categories'    => 'materialCategories',
        'inventory-categories'   => 'materialCategories',
        'inventory-units'        => 'inventoryUnits',
        'inventory_units'        => 'inventoryUnits',
        'rooms'                  => 'rooms',
        'invoices'               => 'invoices',
        'branches'               => 'branches',
    ];

    public function __construct(private ClinicSelectRepositoryInterface $repository)
    {
    }

    /**
     * No auth/clinic restriction — every select returns data for everyone.
     * If the caller passes an explicit clinic_id filter (e.g. for the
     * dentists resource), it's forwarded to the repository as-is; otherwise
     * null is passed so the repository returns unscoped data.
     */
    public function options(string $resource, array $filters = []): array
    {
        $clinicId = $filters['clinic_id'] ?? null;

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
