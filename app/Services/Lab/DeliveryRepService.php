<?php

namespace App\Services\Lab;

use App\Models\LabDeliveryRep;
use App\Models\User;
use App\Repositories\LabDeliveryRepRepository;
use App\Support\ServiceResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DeliveryRepService
{
    public function __construct(
        private LabDeliveryRepRepository $deliveryRepRepository
    ) {
    }

    public function index(array $filters): array
    {
        $user = auth()->user();

        if (!$user || !$user->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $rows = $this->deliveryRepRepository->paginateForLab((int) $user->lab_id, $filters, $perPage);

        $items = collect($rows->items())
            ->map(fn (LabDeliveryRep $rep) => $this->mapListItem($rep))
            ->values()
            ->all();

        return ServiceResult::success([
            'items' => $items,
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ], 'Delivery reps fetched successfully');
    }

    public function store(array $data): array
    {
        $authUser = auth()->user();

        if (!$authUser || !$authUser->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        return DB::transaction(function () use ($authUser, $data) {
            $phone = trim((string) $data['login_phone']);
            $whatsApp = trim((string) ($data['whatsapp_number'] ?? '')) ?: $phone;
            $email = trim((string) ($data['email'] ?? ''));
            $generatedEmail = $email !== '' ? $email : $this->generatePlaceholderEmail((int) $authUser->lab_id, $phone);

            $user = User::create([
                'name' => $data['full_name'],
                'email' => $generatedEmail,
                'phone' => $phone,
                'password' => $data['password'] ?? $phone,
                'is_active' => ($data['status'] ?? LabDeliveryRep::STATUS_ACTIVE) === LabDeliveryRep::STATUS_ACTIVE,
                'lab_id' => (int) $authUser->lab_id,
            ]);

            if (!$user->hasRole('lab')) {
                $user->assignRole('lab');
            }

            $profilePhotoPath = null;
            if (isset($data['profile_photo']) && $data['profile_photo'] instanceof UploadedFile) {
                $profilePhotoPath = $this->storeProfilePhoto($data['profile_photo']);
            }

            $rep = $this->deliveryRepRepository->create([
                'user_id' => $user->id,
                'lab_id' => (int) $authUser->lab_id,
                'assigned_region_city' => $data['assigned_region_city'] ?? null,
                'whatsapp_number' => $whatsApp,
                'profile_photo' => $profilePhotoPath,
                'status' => $data['status'] ?? LabDeliveryRep::STATUS_ACTIVE,
            ]);

            $fresh = $this->deliveryRepRepository->findForLabById((int) $authUser->lab_id, $rep->id);

            return ServiceResult::success(
                $this->mapDetails($fresh),
                'Delivery representative created successfully',
                201
            );
        });
    }

    public function show(int $id): array
    {
        $authUser = auth()->user();

        if (!$authUser || !$authUser->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        $rep = $this->deliveryRepRepository->findForLabById((int) $authUser->lab_id, $id);

        if (!$rep) {
            return ServiceResult::error('Delivery representative not found.', null, null, 404);
        }

        return ServiceResult::success(
            $this->mapDetails($rep),
            'Delivery representative fetched successfully'
        );
    }

    public function update(int $id, array $data): array
    {
        $authUser = auth()->user();

        if (!$authUser || !$authUser->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        return DB::transaction(function () use ($authUser, $id, $data) {
            $rep = $this->deliveryRepRepository->findForLabById((int) $authUser->lab_id, $id);

            if (!$rep) {
                return ServiceResult::error('Delivery representative not found.', null, null, 404);
            }

            $user = $rep->user;

            $phone = array_key_exists('login_phone', $data)
                ? trim((string) $data['login_phone'])
                : (string) $user->phone;

            $emailInputExists = array_key_exists('email', $data);
            $email = $emailInputExists ? trim((string) ($data['email'] ?? '')) : (string) $user->email;
            $finalEmail = $email !== '' ? $email : $this->generatePlaceholderEmail((int) $authUser->lab_id, $phone);

            $userUpdate = [];

            if (array_key_exists('full_name', $data)) {
                $userUpdate['name'] = $data['full_name'];
            }

            if (array_key_exists('login_phone', $data)) {
                $userUpdate['phone'] = $phone;
            }

            if ($emailInputExists) {
                $userUpdate['email'] = $finalEmail;
            }

            if (array_key_exists('password', $data) && filled($data['password'])) {
                $userUpdate['password'] = $data['password'];
            }

            if (array_key_exists('status', $data)) {
                $userUpdate['is_active'] = $data['status'] === LabDeliveryRep::STATUS_ACTIVE;
            }

            if (!empty($userUpdate)) {
                $user->update($userUpdate);
            }

            $repUpdate = [];

            if (array_key_exists('assigned_region_city', $data)) {
                $repUpdate['assigned_region_city'] = $data['assigned_region_city'];
            }

            if (array_key_exists('whatsapp_number', $data)) {
                $repUpdate['whatsapp_number'] = trim((string) ($data['whatsapp_number'] ?? '')) ?: $phone;
            }

            if (array_key_exists('status', $data)) {
                $repUpdate['status'] = $data['status'];
            }

            if (isset($data['profile_photo']) && $data['profile_photo'] instanceof UploadedFile) {
                $this->deletePublicFile($rep->profile_photo);
                $repUpdate['profile_photo'] = $this->storeProfilePhoto($data['profile_photo']);
            }

            $updated = !empty($repUpdate)
                ? $this->deliveryRepRepository->update($rep, $repUpdate)
                : $this->deliveryRepRepository->findForLabById((int) $authUser->lab_id, $id);

            return ServiceResult::success(
                $this->mapDetails($updated),
                'Delivery representative updated successfully'
            );
        });
    }

    public function destroy(int $id): array
    {
        $authUser = auth()->user();

        if (!$authUser || !$authUser->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        return DB::transaction(function () use ($authUser, $id) {
            $rep = $this->deliveryRepRepository->findForLabById((int) $authUser->lab_id, $id);

            if (!$rep) {
                return ServiceResult::error('Delivery representative not found.', null, null, 404);
            }

            $this->deletePublicFile($rep->profile_photo);

            $repUser = $rep->user;
            $this->deliveryRepRepository->delete($rep);

            if ($repUser) {
                $repUser->delete();
            }

            return ServiceResult::success(null, 'Delivery representative deleted successfully');
        });
    }

    private function mapListItem(LabDeliveryRep $rep): array
    {
        return [
            'id' => $rep->id,
            'full_name' => $rep->user?->name,
            'phone' => $rep->user?->phone,
            'email' => $this->normalizeEmailForOutput($rep->user?->email),
            'whatsapp_number' => $rep->whatsapp_number,
            'assigned_region_city' => $rep->assigned_region_city,
            'profile_photo_url' => $rep->profile_photo_url,
            'status' => $rep->status,
            'deliveries_month_count' => 0,
            'expenses_month_total' => 0,
            'joined_date' => optional($rep->user?->created_at)->format('Y-m-d'),
        ];
    }

    private function mapDetails(?LabDeliveryRep $rep): ?array
    {
        if (!$rep) {
            return null;
        }

        return [
            'id' => $rep->id,
            'full_name' => $rep->user?->name,
            'login_phone' => $rep->user?->phone,
            'email' => $this->normalizeEmailForOutput($rep->user?->email),
            'whatsapp_number' => $rep->whatsapp_number,
            'assigned_region_city' => $rep->assigned_region_city,
            'profile_photo_url' => $rep->profile_photo_url,
            'status' => $rep->status,
            'joined_date' => optional($rep->user?->created_at)->format('Y-m-d'),
            'stats' => [
                'deliveries_month_count' => 0,
                'expenses_month_total' => 0,
                'deliveries_total_count' => 0,
                'expenses_total' => 0,
            ],
        ];
    }

    private function generatePlaceholderEmail(int $labId, string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: Str::random(8);

        return 'labrep.l' . $labId . '.p' . $digits . '@delivery-reps.local';
    }

    private function normalizeEmailForOutput(?string $email): ?string
    {
        if (!$email) {
            return null;
        }

        if (str_ends_with($email, '@delivery-reps.local')) {
            return null;
        }

        return $email;
    }

    private function storeProfilePhoto(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $filename = (string) Str::uuid() . '.' . $extension;

        Storage::disk('public')->putFileAs('delivery-reps/photos', $file, $filename);

        return 'delivery-reps/photos/' . $filename;
    }

    private function deletePublicFile(?string $path): void
    {
        if (!$path) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
