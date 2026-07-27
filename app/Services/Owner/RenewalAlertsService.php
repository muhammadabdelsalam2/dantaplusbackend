<?php

namespace App\Services\Owner;

use App\Models\Clinic;
use App\Models\Notification;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\Mail;

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
            'clinic_name' => $clinic->name,
            'avatar' => $this->avatar($clinic->name),
            'expires_in' => $this->expiryLabel($clinic, $today),
            'can_send_reminder' => true,
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
                'recently_renewed' => $this->countRenewed($baseQuery, $today, $days),
                'overdue_payments' => $this->countOverdue($baseQuery, $today),
                'expiring_soon' => $this->countExpiringSoon($baseQuery, $today, $days),
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

        foreach ($clinics as $clinic) {
            Notification::query()->create([
                'title' => 'Subscription Renewal Reminder',
                'message' => $data['message'],
                'type' => 'subscription',
                'status' => 'sent',
                'audience_type' => 'clinic',
                'audience_id' => $clinic->id,
                'priority' => 'normal',
                'delivery_method' => [$data['channel']],
                'delivery_methods' => [$data['channel']],
                'role' => 'clinic',
                'is_read' => false,
                'sender_id' => auth()->id(),
                'sender_name' => auth()->user()?->name,
            ]);

            if ($data['channel'] === 'email' && $clinic->email) {
                Mail::raw($data['message'], fn ($message) => $message
                    ->to($clinic->email)
                    ->subject('Subscription Renewal Reminder'));
            }
        }

        $message = $clinics->count() === 1
            ? 'Reminder sent to ' . $clinics->first()->name
            : 'Reminders sent successfully';

        return ServiceResult::success([
            'requested' => count($data['clinic_ids']),
            'sent' => count($deliveries),
            'deliveries' => $deliveries,
        ], $message, 201);
    }

    private function avatar(string $name): string
    {
        return collect(explode(' ', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
            ->implode('');
    }

    private function expiryLabel(Clinic $clinic, $today): string
    {
        if (! $clinic->expiry_date) {
            return 'No expiry date';
        }

        $days = $today->diffInDays($clinic->expiry_date, false);

        if ($days < 0) {
            return 'Expired ' . abs($days) . ' days ago';
        }

        return 'Expires in ' . $days . ' days';
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
