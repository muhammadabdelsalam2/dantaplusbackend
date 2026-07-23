<?php

namespace App\Services\Lab;

use App\Models\LabDeliveryRep;
use App\Models\DeliveryTask;
use App\Models\User;
use App\Models\ImpersonationAudit;
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

        $profilePhotoPath = null;
        if (isset($data['profile_photo']) && $data['profile_photo'] instanceof UploadedFile) {
            $profilePhotoPath = $this->storeProfilePhoto($data['profile_photo']);
        }

        return DB::transaction(function () use ($authUser, $data, $profilePhotoPath) {
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

            $user->syncRoles(['delivery_representative']);

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

        $rep = $this->deliveryRepRepository->findForLabById((int) $authUser->lab_id, $id);

        if (!$rep) {
            return ServiceResult::error('Delivery representative not found.', null, null, 404);
        }

        $newProfilePhotoPath = null;
        if (isset($data['profile_photo']) && $data['profile_photo'] instanceof UploadedFile) {
            $newProfilePhotoPath = $this->storeProfilePhoto($data['profile_photo']);
        }

        try {
            return DB::transaction(function () use ($authUser, $id, $data, $rep, $newProfilePhotoPath) {
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

                if ($newProfilePhotoPath) {
                    $this->deletePublicFile($rep->profile_photo);
                    $repUpdate['profile_photo'] = $newProfilePhotoPath;
                }

                $updated = !empty($repUpdate)
                    ? $this->deliveryRepRepository->update($rep, $repUpdate)
                    : $this->deliveryRepRepository->findForLabById((int) $authUser->lab_id, $id);

                return ServiceResult::success(
                    $this->mapDetails($updated),
                    'Delivery representative updated successfully'
                );
            });
        } catch (\Throwable $e) {
            if ($newProfilePhotoPath) {
                $this->deletePublicFile($newProfilePhotoPath);
            }
            throw $e;
        }
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

    public function loginAs(int $id, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $authUser = auth()->user();

        if (!$authUser || !$authUser->lab_id || !$authUser->hasRole('lab_admin')) {
            return ServiceResult::error('Only lab admins can impersonate delivery representatives.', null, null, 403);
        }

        $rep = $this->deliveryRepRepository->findForLabById((int) $authUser->lab_id, $id);
        if (!$rep || !$rep->user || !$rep->user->hasRole('delivery_representative')) {
            return ServiceResult::error('Delivery representative not found.', null, null, 404);
        }

        ImpersonationAudit::query()->create([
            'impersonator_id' => $authUser->id,
            'impersonated_user_id' => $rep->user->id,
            'impersonated_role' => 'delivery_representative',
            'guard' => 'sanctum',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        $token = $rep->user->createToken('delivery-rep-impersonation')->plainTextToken;
        $portalPayload = $this->deliveryPortalPayloadForUser($rep->user);

        return ServiceResult::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $rep->user->id,
                'name' => $rep->user->name,
                'email' => $this->normalizeEmailForOutput($rep->user->email),
                'phone' => $rep->user->phone,
                'role' => 'delivery_representative',
                'lab_id' => $rep->user->lab_id,
            ],
            'delivery_rep' => $this->mapDetails($rep),
            'my_deliveries' => $portalPayload['my_deliveries'],
            'my_reports' => $portalPayload['my_reports'],
            'redirect_to' => '/delivery/dashboard',
        ], 'Delivery representative impersonation token created successfully');
    }

    public function myDeliveries(?User $user = null, array $filters = []): array
    {
        $user ??= auth()->user();
        $rep = $this->deliveryRepForUser($user);

        if (! $rep) {
            return ServiceResult::error('Delivery representative profile not found.', null, null, 404);
        }

        $tasks = $this->deliveryTasksQuery($rep)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 100)));

        return ServiceResult::success([
            'delivery_rep' => $this->mapDetails($rep),
            'items' => collect($tasks->items())->map(fn (DeliveryTask $task) => $this->mapDeliveryCard($task))->values()->all(),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ], 'Delivery representative deliveries fetched successfully');
    }

    public function myReports(?User $user = null, array $filters = []): array
    {
        $user ??= auth()->user();
        $rep = $this->deliveryRepForUser($user);

        if (! $rep) {
            return ServiceResult::error('Delivery representative profile not found.', null, null, 404);
        }

        $tasks = $this->deliveryTasksQuery($rep)
            ->when($filters['start_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['end_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')
            ->get();

        $deliveredCount = $tasks->where('status', DeliveryTask::STATUS_DELIVERED)->count();
        $completedCount = $tasks->whereIn('status', [DeliveryTask::STATUS_DELIVERED, DeliveryTask::STATUS_PICKED_UP, DeliveryTask::STATUS_IN_TRANSIT])->count();

        return ServiceResult::success([
            'delivery_rep' => $this->mapDetails($rep),
            'summary' => [
                'on_time_rate' => $completedCount > 0 ? 100 : 0,
                'total_expenses' => round((float) $tasks->sum('trip_expense'), 2),
                'total_deliveries' => $tasks->count(),
                'delivered_count' => $deliveredCount,
            ],
            'history' => $tasks->map(fn (DeliveryTask $task) => [
                'status' => $this->displayTaskStatus($task->status),
                'expense' => round((float) ($task->trip_expense ?? 0), 2),
                'clinic' => $task->case?->clinic?->name,
                'case_id' => $task->case?->case_number,
                'date' => optional($task->created_at)->format('d/m/Y'),
            ])->values()->all(),
        ], 'Delivery representative reports fetched successfully');
    }

    public function deliveryTaskDetails(int $taskId, ?User $user = null): array
    {
        $user ??= auth()->user();
        $rep = $this->deliveryRepForUser($user);

        if (! $rep) {
            return ServiceResult::error('Delivery representative profile not found.', null, null, 404);
        }

        $task = $this->deliveryTasksQuery($rep)->find($taskId);

        if (! $task) {
            return ServiceResult::error('Delivery task not found.', null, null, 404);
        }

        return ServiceResult::success($this->mapDeliveryDetails($task), 'Delivery task details fetched successfully');
    }

    public function confirmPickup(int $taskId, array $data, ?User $user = null): array
    {
        $user ??= auth()->user();
        $rep = $this->deliveryRepForUser($user);

        if (! $rep) {
            return ServiceResult::error('Delivery representative profile not found.', null, null, 404);
        }

        $task = $this->deliveryTasksQuery($rep)->find($taskId);

        if (! $task) {
            return ServiceResult::error('Delivery task not found.', null, null, 404);
        }

        $photoPath = $this->storePickupPhoto($data['photo']);

        try {
            return DB::transaction(function () use ($task, $data, $photoPath, $user) {
                $tripCost = $data['trip_cost'] ?? $data['expenses'] ?? 0;
                $tripCost = is_numeric($tripCost) ? (float) $tripCost : 0.0;

                $task->update([
                    'status' => DeliveryTask::STATUS_PICKED_UP,
                    'picked_up_at' => now(),
                    'receipt_proof_path' => $photoPath,
                    'receipt_proof_original_name' => $data['photo']->getClientOriginalName(),
                    'receipt_proof_mime_type' => $data['photo']->getClientMimeType(),
                    'receipt_proof_size' => $data['photo']->getSize(),
                    'trip_expense' => $tripCost,
                    'receipt_confirmed_at' => now(),
                    'receipt_confirmed_by' => $user?->id,
                ]);

                if ($task->case) {
                    app(\App\Repositories\CaseRepository::class)->createActivityLog($task->case, [
                        'actor_id' => $user?->id,
                        'actor_name' => $user?->name,
                        'action' => 'pickup_confirmed',
                        'payload' => [
                            'task_id' => $task->id,
                            'trip_cost' => $tripCost,
                            'photo_path' => $photoPath,
                        ],
                    ]);
                }

                return ServiceResult::success($this->mapDeliveryDetails($task->fresh(['case.clinic', 'case.patient.user', 'deliveryRep'])), 'Pickup confirmed successfully');
            });
        } catch (\Throwable $e) {
            $this->deletePublicFile($photoPath);
            throw $e;
        }
    }

    public function updateLiveLocation(array $data, ?User $user = null): array
    {
        $user ??= auth()->user();
        $rep = $this->deliveryRepForUser($user);

        if (! $rep) {
            return ServiceResult::error('Delivery representative profile not found.', null, null, 404);
        }

        $rep->update([
            'last_latitude' => $data['latitude'],
            'last_longitude' => $data['longitude'],
            'tracking_status' => $data['status'] ?? $rep->tracking_status ?? 'In Transit',
            'last_location_at' => now(),
        ]);

        $activeTask = $this->deliveryTasksQuery($rep)
            ->whereIn('status', [DeliveryTask::STATUS_ASSIGNED, DeliveryTask::STATUS_PICKED_UP, DeliveryTask::STATUS_IN_TRANSIT])
            ->latest('id')
            ->first();

        if ($activeTask) {
            $activeTask->update([
                'last_location_lat' => $data['latitude'],
                'last_location_lng' => $data['longitude'],
                'last_location_at' => now(),
            ]);
        }

        return ServiceResult::success([
            'delivery_rep' => $this->mapLiveRep($rep->fresh('user'), $activeTask?->fresh(['case.clinic', 'case.patient.user'])),
        ], 'Delivery representative location updated successfully');
    }

    public function liveTracking(array $filters = []): array
    {
        $authUser = auth()->user();

        if (! $authUser?->lab_id) {
            return ServiceResult::error('Authenticated lab account is required.', null, null, 403);
        }

        $reps = LabDeliveryRep::query()
            ->with('user')
            ->where('lab_id', $authUser->lab_id)
            ->where('status', LabDeliveryRep::STATUS_ACTIVE)
            ->whereNotNull('last_latitude')
            ->whereNotNull('last_longitude')
            ->get();

        return ServiceResult::success([
            'items' => $reps->map(function (LabDeliveryRep $rep) {
                $task = $this->deliveryTasksQuery($rep)
                    ->whereIn('status', [DeliveryTask::STATUS_ASSIGNED, DeliveryTask::STATUS_PICKED_UP, DeliveryTask::STATUS_IN_TRANSIT])
                    ->latest('id')
                    ->first();

                return $this->mapLiveRep($rep, $task);
            })->values()->all(),
        ], 'Live delivery tracking fetched successfully');
    }

    private function deliveryPortalPayloadForUser(User $user): array
    {
        $deliveries = $this->myDeliveries($user);
        $reports = $this->myReports($user);

        return [
            'my_deliveries' => $deliveries['data'] ?? null,
            'my_reports' => $reports['data'] ?? null,
        ];
    }

    private function deliveryRepForUser(?User $user): ?LabDeliveryRep
    {
        if (! $user?->lab_id || ! $user->hasRole('delivery_representative')) {
            return null;
        }

        return LabDeliveryRep::query()
            ->with('user')
            ->where('lab_id', $user->lab_id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function deliveryTasksQuery(LabDeliveryRep $rep)
    {
        return DeliveryTask::query()
            ->with(['deliveryRep:id,name,phone,avatar_url', 'case:id,case_number,status,clinic_id,patient_id,due_date,case_type', 'case.clinic:id,name,email,phone,address', 'case.patient.user:id,name'])
            ->where('lab_id', $rep->lab_id)
            ->where('delivery_rep_user_id', $rep->user_id);
    }

    private function mapDeliveryCard(DeliveryTask $task): array
    {
        return [
            'id' => $task->id,
            'task_id' => $task->id,
            'status' => $task->status,
            'status_label' => $this->displayTaskStatus($task->status),
            'case_id' => $task->case?->case_number,
            'case_type' => $task->case?->case_type,
            'clinic_name' => $task->case?->clinic?->name,
            'patient_name' => $task->case?->patient?->user?->name,
            'pickup_address' => $task->pickup_address ?: $task->case?->clinic?->address,
            'delivery_address' => $task->delivery_address,
            'scheduled_for' => optional($task->scheduled_for)->toISOString(),
            'trip_cost' => round((float) ($task->trip_expense ?? 0), 2),
        ];
    }

    private function mapDeliveryDetails(DeliveryTask $task): array
    {
        $clinic = $task->case?->clinic;

        return array_merge($this->mapDeliveryCard($task), [
            'case' => [
                'id' => $task->case?->id,
                'case_number' => $task->case?->case_number,
                'status' => $task->case?->status,
                'case_type' => $task->case?->case_type,
                'due_date' => optional($task->case?->due_date)->format('d/m/Y'),
            ],
            'clinic' => $clinic ? [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'email' => $clinic->email,
                'phone' => $clinic->phone,
                'address' => $clinic->address,
                'contact' => $clinic->phone,
            ] : null,
            'pickup' => [
                'address' => $task->pickup_address ?: $clinic?->address,
                'latitude' => $task->last_location_lat,
                'longitude' => $task->last_location_lng,
                'contact' => $clinic?->phone,
            ],
            'location' => [
                'latitude' => $task->last_location_lat,
                'longitude' => $task->last_location_lng,
                'updated_at' => optional($task->last_location_at)->toISOString(),
            ],
            'proof_photo_url' => $task->receipt_proof_path ? asset('storage/' . $task->receipt_proof_path) : null,
        ]);
    }

    private function mapLiveRep(LabDeliveryRep $rep, ?DeliveryTask $task): array
    {
        return [
            'delivery_rep_id' => $rep->id,
            'user_id' => $rep->user_id,
            'name' => $rep->user?->name,
            'phone' => $rep->user?->phone,
            'status' => $rep->tracking_status ?: $this->mapTrackingStatus($task?->status),
            'latitude' => $rep->last_latitude,
            'longitude' => $rep->last_longitude,
            'last_location_at' => optional($rep->last_location_at)->toISOString(),
            'case' => $task?->case ? [
                'id' => $task->case->id,
                'case_id' => $task->case->case_number,
                'case_number' => $task->case->case_number,
                'status' => $task->case->status,
                'clinic_name' => $task->case?->clinic?->name,
                'patient_name' => $task->case?->patient?->user?->name,
            ] : null,
            'task' => $task ? [
                'id' => $task->id,
                'status' => $task->status,
                'status_label' => $this->displayTaskStatus($task->status),
            ] : null,
        ];
    }

    private function mapTrackingStatus(?string $taskStatus): string
    {
        return match ($taskStatus) {
            DeliveryTask::STATUS_DELIVERED => 'Delivered',
            DeliveryTask::STATUS_PICKED_UP,
            DeliveryTask::STATUS_IN_TRANSIT => 'In Transit',
            default => 'Delivering',
        };
    }

    private function displayTaskStatus(?string $status): string
    {
        return match ($status) {
            DeliveryTask::STATUS_ASSIGNED => 'Assigned',
            DeliveryTask::STATUS_PICKED_UP => 'Picked Up',
            DeliveryTask::STATUS_IN_TRANSIT => 'In Transit',
            DeliveryTask::STATUS_DELIVERED => 'Delivered',
            DeliveryTask::STATUS_CANCELLED => 'Cancelled',
            default => (string) $status,
        };
    }

    private function storePickupPhoto(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $filename = (string) Str::uuid() . '.' . $extension;

        Storage::disk('public')->putFileAs('delivery-pickups', $file, $filename);

        return 'delivery-pickups/' . $filename;
    }

    private function mapListItem(LabDeliveryRep $rep): array
    {
        $monthTasks = DeliveryTask::query()
            ->where('lab_id', $rep->lab_id)
            ->where('delivery_rep_user_id', $rep->user_id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        return [
            'id' => $rep->id,
            'mandoub_name' => $rep->user?->name,
            'full_name' => $rep->user?->name,
            'phone' => $rep->user?->phone,
            'email' => $this->normalizeEmailForOutput($rep->user?->email),
            'whatsapp_number' => $rep->whatsapp_number,
            'assigned_region_city' => $rep->assigned_region_city,
            'area' => $rep->assigned_region_city,
            'profile_photo_url' => $rep->profile_photo_url,
            'status' => $rep->status,
            'location' => [
                'latitude' => $rep->last_latitude,
                'longitude' => $rep->last_longitude,
                'status' => $rep->tracking_status,
                'updated_at' => optional($rep->last_location_at)->toISOString(),
            ],
            'deliveries_month_count' => (clone $monthTasks)->count(),
            'expenses_month_total' => round((float) (clone $monthTasks)->sum('trip_expense'), 2),
            'joined_date' => optional($rep->user?->created_at)->format('Y-m-d'),
        ];
    }

    private function mapDetails(?LabDeliveryRep $rep): ?array
    {
        if (!$rep) {
            return null;
        }

        $monthTasks = DeliveryTask::query()
            ->where('lab_id', $rep->lab_id)
            ->where('delivery_rep_user_id', $rep->user_id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        $allTasks = DeliveryTask::query()
            ->where('lab_id', $rep->lab_id)
            ->where('delivery_rep_user_id', $rep->user_id);

        return [
            'id' => $rep->id,
            'mandoub_name' => $rep->user?->name,
            'full_name' => $rep->user?->name,
            'login_phone' => $rep->user?->phone,
            'email' => $this->normalizeEmailForOutput($rep->user?->email),
            'whatsapp_number' => $rep->whatsapp_number,
            'assigned_region_city' => $rep->assigned_region_city,
            'profile_photo_url' => $rep->profile_photo_url,
            'status' => $rep->status,
            'location' => [
                'latitude' => $rep->last_latitude,
                'longitude' => $rep->last_longitude,
                'status' => $rep->tracking_status,
                'updated_at' => optional($rep->last_location_at)->toISOString(),
            ],
            'joined_date' => optional($rep->user?->created_at)->format('Y-m-d'),
            'stats' => [
                'deliveries_month_count' => (clone $monthTasks)->count(),
                'expenses_month_total' => round((float) (clone $monthTasks)->sum('trip_expense'), 2),
                'deliveries_total_count' => (clone $allTasks)->count(),
                'expenses_total' => round((float) (clone $allTasks)->sum('trip_expense'), 2),
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
