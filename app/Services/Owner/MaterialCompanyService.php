<?php

namespace App\Services\Owner;

use App\Repositories\MaterialCompanyRepository;
use App\Support\ServiceResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MaterialCompanyService
{
    public function __construct(private MaterialCompanyRepository $materialCompanyRepository)
    {
    }

    public function index(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $companies = $this->materialCompanyRepository->paginate($filters, $perPage);

        $data = [
            'items' => $companies->items(),
            'pagination' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
            ],
            'stats' => $this->materialCompanyRepository->stats(),
        ];

        return ServiceResult::success($data, 'Material companies fetched successfully');
    }

    public function store(array $data): array
    {
        return DB::transaction(function () use ($data) {
            if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
                $path = Storage::disk('public')->putFile('material/companies', $data['logo']);
                $data['logo_url'] = Storage::url($path);
            }

            unset($data['logo']);

            $company = $this->materialCompanyRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'commission_percentage' => $data['commission_percentage'],
                'logo_url' => $data['logo_url'] ?? null,
                'description' => $data['description'] ?? null,
                'phone' => $data['phone'] ?? null,
                'website' => $data['website'] ?? null,
                'country' => $data['country'],
                'city' => $data['city'] ?? null,
                'address' => $data['address'] ?? null,
                'categories' => $data['categories'] ?? null,
                'status' => $data['status'] ?? 'Active',
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'rating' => $data['rating'] ?? null,
            ]);

            return ServiceResult::success($company, 'Material company created successfully', 201);
        });
    }

    public function show(int $companyId): array
    {
        $company = $this->materialCompanyRepository->findById($companyId, ['products']);

        if (!$company) {
            return ServiceResult::error('Material company not found', null, null, 404);
        }

        return ServiceResult::success($company, 'Material company fetched successfully');
    }

    public function update(int $companyId, array $data): array
    {
        return DB::transaction(function () use ($companyId, $data) {
            $company = $this->materialCompanyRepository->findById($companyId);

            if (!$company) {
                return ServiceResult::error('Material company not found', null, null, 404);
            }

            if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
                $this->deletePublicFileByUrl($company->logo_url);
                $path = Storage::disk('public')->putFile('material/companies', $data['logo']);
                $data['logo_url'] = Storage::url($path);
            }

            unset($data['logo']);

            $updated = $this->materialCompanyRepository->update($company, $data);

            return ServiceResult::success($updated, 'Material company updated successfully');
        });
    }

    public function updateStatus(int $companyId, string $status): array
    {
        $company = $this->materialCompanyRepository->findById($companyId);

        if (!$company) {
            return ServiceResult::error('Material company not found', null, null, 404);
        }

        $updated = $this->materialCompanyRepository->update($company, ['status' => $status]);

        return ServiceResult::success($updated, 'Material company status updated successfully');
    }

    public function updateCommission(int $companyId, float $commissionPercentage): array
    {
        $company = $this->materialCompanyRepository->findById($companyId);

        if (!$company) {
            return ServiceResult::error('Material company not found', null, null, 404);
        }

        $updated = $this->materialCompanyRepository->update($company, [
            'commission_percentage' => $commissionPercentage,
            'last_commission_update' => now(),
        ]);

        return ServiceResult::success($updated, 'Material company commission updated successfully');
    }

    public function destroy(int $companyId): array
    {
        $company = $this->materialCompanyRepository->findById($companyId);

        if (!$company) {
            return ServiceResult::error('Material company not found', null, null, 404);
        }

        $this->deletePublicFileByUrl($company->logo_url);
        $this->materialCompanyRepository->delete($company);

        return ServiceResult::success(null, 'Material company deleted successfully');
    }

    private function deletePublicFileByUrl(?string $url): void
    {
        if (!$url) {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return;
        }

        $path = Str::replaceFirst('/storage/', '', $path);
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
