<?php

namespace App\Services\Owner;

use App\Models\Clinic;
use App\Models\MaterialOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OwnerDashboardService
{
    public function dashboard(): array
    {
        $months = $this->firstSixMonths();
        $currentMonth = now();

        return [
            'cards' => [
                'new_this_month' => Clinic::query()
                    ->whereBetween('created_at', [$currentMonth->copy()->startOfMonth(), $currentMonth->copy()->endOfMonth()])
                    ->count(),
                'monthly_income' => $this->mrrForMonth($currentMonth),
                'active_subscriptions' => $this->activeClinicsQuery()->count(),
                'total_clinics' => Clinic::query()->count(),
            ],
            'clinic_growth' => $months->map(fn (Carbon $month) => [
                'month' => $month->format('M'),
                'clinics' => Clinic::query()
                    ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                    ->count(),
            ])->values()->all(),
            'revenue_trend' => $months->map(fn (Carbon $month) => [
                'month' => $month->format('M'),
                'revenue' => $this->mrrForMonth($month),
            ])->values()->all(),
        ];
    }

    public function analytics(): array
    {
        $months = $this->firstSixMonths();
        $currentMonth = now();
        $previousMonth = now()->subMonth();
        $currentMrr = $this->mrrForMonth($currentMonth);
        $previousMrr = $this->mrrForMonth($previousMonth);
        $activeSubscribers = $this->activeClinicsQuery()->count();
        $previousActiveSubscribers = $this->activeClinicsAt($previousMonth)->count();
        $currentArpu = $activeSubscribers > 0 ? round($currentMrr / $activeSubscribers, 2) : 0.0;
        $previousArpu = $previousActiveSubscribers > 0 ? round($previousMrr / $previousActiveSubscribers, 2) : 0.0;
        $currentLifespan = $this->averageClinicLifespan(now());
        $previousLifespan = $this->averageClinicLifespan($previousMonth);

        return [
            'cards' => [
                'average_clinic_lifespan' => [
                    'value_months' => $currentLifespan,
                    'vs_last_month' => round($currentLifespan - $previousLifespan, 1),
                ],
                'average_revenue_per_user' => [
                    'value' => $currentArpu,
                    'vs_last_month_percent' => $this->percentChange($currentArpu, $previousArpu),
                ],
                'active_subscribers' => [
                    'value' => $activeSubscribers,
                    'vs_last_month' => $activeSubscribers - $previousActiveSubscribers,
                ],
                'monthly_recurring_revenue' => [
                    'value' => $currentMrr,
                    'vs_last_month_percent' => $this->percentChange($currentMrr, $previousMrr),
                ],
            ],
            'subscription_plans' => collect(['Standard', 'Premium', 'Basic'])
                ->map(fn (string $plan) => [
                    'plan' => $plan,
                    'count' => $this->activeClinicsQuery()->where('subscription_plan', $plan)->count(),
                ])
                ->values()
                ->all(),
            'monthly_revenue' => $months->map(fn (Carbon $month) => [
                'month' => $month->format('M'),
                'revenue' => $this->mrrForMonth($month),
            ])->values()->all(),
            'top_performing_clinics' => Clinic::query()
                ->withCount(['users as active_users' => fn ($query) => $query->where('is_active', true)])
                ->orderByDesc('active_users')
                ->limit(5)
                ->get(['id', 'name', 'owner_name'])
                ->map(fn (Clinic $clinic) => [
                    'active_users' => (int) $clinic->active_users,
                    'owner' => $clinic->owner_name,
                    'clinic' => $clinic->name,
                ])
                ->values()
                ->all(),
        ];
    }

    public function materialOrderStats(): array
    {
        return [
            'total_orders' => MaterialOrder::query()->count(),
            'completed_orders' => MaterialOrder::query()->where('status', MaterialOrder::STATUS_COMPLETED)->count(),
            'total_sales' => round((float) MaterialOrder::query()->sum('amount_total'), 2),
        ];
    }

    private function activeClinicsQuery()
    {
        return Clinic::query()->where('status', 'Active');
    }

    private function activeClinicsAt(Carbon $month): Collection
    {
        return Clinic::query()
            ->where('status', 'Active')
            ->where('start_date', '<=', $month->copy()->endOfMonth())
            ->get();
    }

    private function mrrForMonth(Carbon $month): float
    {
        return round((float) $this->activeClinicsAt($month)->sum(
            fn (Clinic $clinic) => $this->planPrice($clinic->subscription_plan)
        ), 2);
    }

    private function averageClinicLifespan(Carbon $asOf): float
    {
        $clinics = Clinic::query()
            ->where('created_at', '<=', $asOf->copy()->endOfMonth())
            ->get(['created_at', 'expiry_date']);

        if ($clinics->isEmpty()) {
            return 0.0;
        }

        return round((float) $clinics->avg(
            fn (Clinic $clinic) => $clinic->created_at->diffInMonths($clinic->expiry_date ?? $asOf)
        ), 1);
    }

    private function planPrice(?string $plan): float
    {
        return (float) config('subscriptions.plan_prices.' . strtolower((string) $plan), 0);
    }

    private function percentChange(float $current, float $previous): float
    {
        return $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0.0;
    }

    private function firstSixMonths(): Collection
    {
        return collect(range(1, 6))->map(fn (int $month) => now()->copy()->month($month)->startOfMonth());
    }
}
