<?php

namespace App\Services\Lab\Settings;

use App\Http\Resources\Lab\Settings\LabProfileResource;
use App\Repositories\Lab\Settings\SettingsRepositoryInterface;
use App\Support\ServiceResult;
use Illuminate\Http\Request;

class LabProfileService
{
    public function __construct(private SettingsRepositoryInterface $settingsRepository)
    {
    }

    public function showProfile(): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $lab = $this->settingsRepository->findLabById($labId);
        if (! $lab) {
            return ServiceResult::error('Lab not found', null, null, 404);
        }

        return ServiceResult::success(
            (new LabProfileResource($lab))->resolve(),
            'Lab profile fetched successfully'
        );
    }

    public function updateProfile(Request $request): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab', null, null, 403);
        }

        $lab = $this->settingsRepository->findLabById($labId);
        if (! $lab) {
            return ServiceResult::error('Lab not found', null, null, 404);
        }

        $data = $request->validated();
        $payload = [];

        if (array_key_exists('lab_name', $data)) {
            $payload['name'] = $data['lab_name'];
        }

        if (array_key_exists('contact_person', $data)) {
            $payload['contact_person'] = $data['contact_person'];
        }

        if (array_key_exists('phone', $data)) {
            $payload['phone'] = $data['phone'];
        }

        if (array_key_exists('email', $data)) {
            $payload['email'] = $data['email'];
        }

        if (array_key_exists('address', $data)) {
            $payload['address'] = $data['address'];
        }

        if (array_key_exists('working_hours', $data)) {
            $payload['working_hours'] = $data['working_hours'];
        }

        if ($request->hasFile('logo_url')) {
            $path = $request->file('logo_url')->store('labs/logos', 'public');
            $payload['logo_url'] = asset('storage/' . $path);
        }

        $updated = ! empty($payload)
            ? $this->settingsRepository->updateLab($lab, $payload)
            : $lab->refresh();

        return ServiceResult::success(
            (new LabProfileResource($updated))->resolve(),
            'Lab profile updated.'
        );
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }
}
