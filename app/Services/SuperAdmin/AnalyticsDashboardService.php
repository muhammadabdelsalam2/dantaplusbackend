<?php

namespace App\Services\SuperAdmin;

use App\Models\Clinic;
use Carbon\Carbon;

class AnalyticsDashboardService
{
    public function dashboard(): array
    {
        try {
            $data = [
                'kpis' => $this->getKpis(),
                'mrr_chart' => $this->getMrrChart(),
                'subscription_plans' => $this->getSubscriptionPlansBreakdown(),
                'top_performing_clinics' => $this->getTopPerformingClinics(),
                'stripe_integration' => [
                    'connected' => false,
                    'message' => 'Connect your Stripe account to see detailed payment analytics, churn rates, and lifetime value.',
                ],
            ];

            return [
                'success' => true,
                'data' => $data,
                'message' => 'Analytics dashboard fetched successfully',
                'code' => 200,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch analytics dashboard',
                'code' => 500,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    private function getKpis(): array
    {
        $activeClinics = Clinic::where('status', 'active')->get();

        $currentMrr = $activeClinics->sum(fn ($clinic) => $this->planPrice($clinic->subscription_plan));
        $lastMonthMrr = $this->calculateMrrForMonth(now()->subMonth());

        $mrrChangePercent = $lastMonthMrr > 0
            ? round((($currentMrr - $lastMonthMrr) / $lastMonthMrr) * 100, 1)
            : 0;

        $activeSubscribers = $activeClinics->count();
        $lastMonthSubscribers = Clinic::where('status', 'active')
            ->where('start_date', '<=', now()->subMonth())
            ->count();

        $arpu = $activeSubscribers > 0 ? round($currentMrr / $activeSubscribers, 2) : 0;
        $lastMonthArpu = $lastMonthSubscribers > 0 ? round($lastMonthMrr / $lastMonthSubscribers, 2) : 0;
        $arpuChangePercent = $lastMonthArpu > 0
            ? round((($arpu - $lastMonthArpu) / $lastMonthArpu) * 100, 1)
            : 0;

        $avgLifespanMonths = round(
            Clinic::whereNotNull('start_date')->get()
                ->avg(fn ($clinic) => $clinic->start_date->diffInMonths($clinic->expiry_date ?? now())) ?? 0,
            1
        );

        return [
            'monthly_recurring_revenue' => [
                'value' => $currentMrr,
                'change_percent' => $mrrChangePercent,
            ],
            'active_subscribers' => [
                'value' => $activeSubscribers,
                'change' => $activeSubscribers - $lastMonthSubscribers,
            ],
            'average_revenue_per_user' => [
                'value' => $arpu,
                'change_percent' => $arpuChangePercent,
            ],
            'average_clinic_lifespan' => [
                'value_months' => $avgLifespanMonths,
                'change_months' => 0.5, // TODO: قارنها بـ snapshot الشهر اللي فات لو عندك جدول history
            ],
        ];
    }

    private function getMrrChart(): array
    {
        return collect(range(5, 0))
            ->map(fn ($i) => now()->subMonths($i))
            ->map(fn ($month) => [
                'month' => $month->format('M'),
                'revenue' => $this->calculateMrrForMonth($month),
            ])
            ->values()
            ->toArray();
    }

    private function calculateMrrForMonth(Carbon $month): float
    {
        return Clinic::where('status', 'active')
            ->where('start_date', '<=', $month->copy()->endOfMonth())
            ->get()
            ->sum(fn ($clinic) => $this->planPrice($clinic->subscription_plan));
    }

    private function planPrice(?string $plan): float
    {
        
        return match ($plan) {
            'basic' => 99,
            'standard' => 149,
            'premium' => 249,
            default => 0,
        };
    }

    private function getSubscriptionPlansBreakdown(): array
    {
        return Clinic::where('status', 'active')
            ->selectRaw('subscription_plan, count(*) as total')
            ->groupBy('subscription_plan')
            ->get()
            ->map(fn ($row) => [
                'plan' => $row->subscription_plan,
                'count' => $row->total,
            ])
            ->toArray();
    }

    private function getTopPerformingClinics(): array
    {
        return Clinic::withCount('users')
            ->orderByDesc('users_count')
            ->take(5)
            ->get()
            ->map(fn ($clinic) => [
                'clinic' => $clinic->name,
                'owner' => $clinic->owner_name,
                'active_users' => $clinic->users_count,
            ])
            ->toArray();
    }
}
