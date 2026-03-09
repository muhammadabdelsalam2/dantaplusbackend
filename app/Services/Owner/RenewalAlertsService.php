<?php

namespace App\Services\Owner;

use App\Models\Clinic;
use App\Support\ServiceResult;

class RenewalAlertsService
{
    public function listRenewalAlerts(array $filters): array
    {
        $tab = $filters['tab'] ?? 'expiring_soon';
        $perPage = (int) ($filters['per_page'] ?? 15);
        $days = (int) ($filters['within_days'] ?? 30);
        $today = now()->startOfDay();

        $baseQuery = Clinic::query()->select([
            'id',
            'name',
            'owner_name',
            'email',
            'phone',
            'subscription_plan',
            'status',
            'start_date',
            'expiry_date',
            'payment_method',
        ]);

        $query = clone $baseQuery;

        if ($tab === 'expiring_soon') {
            $query->whereIn('status', ['Active', 'Trial'])
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', $today->copy()->addDays($days));
        } elseif ($tab === 'overdue_payments') {
            $query->where(function ($q) use ($today) {
                $q->whereIn('status', ['Expired', 'Suspended'])
                    ->orWhere(function ($overdueByDate) use ($today) {
                        $overdueByDate->whereNotNull('expiry_date')
                            ->whereDate('expiry_date', '<', $today);
                    });
            });
        } elseif ($tab === 'recently_renewed') {
            $query->where('status', 'Active')
                ->whereNotNull('start_date')
                ->whereDate('start_date', '>=', $today->copy()->subDays($days))
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>', $today);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderBy('expiry_date')->orderByDesc('id')->paginate($perPage);

        $items = collect($rows->items())->map(fn (Clinic $clinic) => [
            'id' => $clinic->id,
            'clinicName' => $clinic->name,
            'ownerName' => $clinic->owner_name,
            'email' => $clinic->email,
            'phone' => $clinic->phone,
            'plan' => $clinic->subscription_plan,
            'status' => $clinic->status,
            'paymentMethod' => $clinic->payment_method,
            'expiryDate' => optional($clinic->expiry_date)?->toDateString(),
            'startDate' => optional($clinic->start_date)?->toDateString(),
            'daysToExpiry' => $clinic->expiry_date ? $today->diffInDays($clinic->expiry_date, false) : null,
        ])->all();

        return ServiceResult::success([
            'tab' => $tab,
            'items' => $items,
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'summary' => [
                'expiring_soon' => $this->countExpiringSoon($baseQuery, $today, $days),
                'overdue_payments' => $this->countOverdue($baseQuery, $today),
                'recently_renewed' => $this->countRenewed($baseQuery, $today, $days),
            ],
        ], 'Renewal alerts fetched successfully');
    }

    public function sendReminder(array $data): array
    {
        $clinics = Clinic::query()
            ->whereIn('id', $data['clinic_ids'])
            ->get(['id', 'name', 'email', 'phone']);

        $sentAt = now()->toISOString();

        $deliveries = $clinics->map(fn (Clinic $clinic) => [
            'clinicId' => $clinic->id,
            'clinicName' => $clinic->name,
            'channel' => $data['channel'],
            'to' => $data['channel'] === 'email' ? $clinic->email : $clinic->phone,
            'message' => $data['message'],
            'sentAt' => $sentAt,
        ])->values()->all();

        return ServiceResult::success([
            'requested' => count($data['clinic_ids']),
            'sent' => count($deliveries),
            'deliveries' => $deliveries,
        ], 'Renewal reminders queued successfully', 201);
    }

    private function countExpiringSoon($query, $today, int $days): int
    {
        return (clone $query)
            ->whereIn('status', ['Active', 'Trial'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', $today->copy()->addDays($days))
            ->count();
    }

    private function countOverdue($query, $today): int
    {
        return (clone $query)
            ->where(function ($q) use ($today) {
                $q->whereIn('status', ['Expired', 'Suspended'])
                    ->orWhere(function ($overdueByDate) use ($today) {
                        $overdueByDate->whereNotNull('expiry_date')
                            ->whereDate('expiry_date', '<', $today);
                    });
            })
            ->count();
    }

    private function countRenewed($query, $today, int $days): int
    {
        return (clone $query)
            ->where('status', 'Active')
            ->whereNotNull('start_date')
            ->whereDate('start_date', '>=', $today->copy()->subDays($days))
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', $today)
            ->count();
    }
}
