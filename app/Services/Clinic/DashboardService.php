<?php

namespace App\Services\Clinic;

use App\Models\ClinicAppointment;
use App\Models\ClinicInvoice;
use App\Models\Patient;
use App\Support\ServiceResult;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function cards(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $today = now();
        $lastMonth = now()->subMonth();

        return ServiceResult::success([
            'cards' => [
                $this->card(
                    "Today's Appointments",
                    ClinicAppointment::query()->where('clinic_id', $clinicId)->whereDate('appointment_at', $today)->count(),
                    ClinicAppointment::query()->where('clinic_id', $clinicId)->whereDate('appointment_at', $lastMonth)->count()
                ),
                $this->card(
                    'Patients',
                    Patient::query()->where('clinic_id', $clinicId)->count(),
                    Patient::query()->where('clinic_id', $clinicId)->where('created_at', '<=', $lastMonth->copy()->endOfDay())->count()
                ),
                $this->card(
                    'Monthly Revenue',
                    $this->paidRevenue($clinicId, $today->copy()->startOfMonth(), $today->copy()->endOfMonth()),
                    $this->paidRevenue($clinicId, $lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth())
                ),
                $this->card(
                    'New Patients',
                    Patient::query()->where('clinic_id', $clinicId)->whereBetween('created_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])->count(),
                    Patient::query()->where('clinic_id', $clinicId)->whereBetween('created_at', [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()])->count()
                ),
            ],
        ], 'Dashboard cards fetched successfully');
    }

    public function revenueTracking(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $start = now()->startOfYear();
        $end = now()->endOfMonth();

        $series = collect(CarbonPeriod::create($start, '1 month', $end))
            ->map(fn (Carbon $month) => [
                'month' => $month->format('M'),
                'revenue' => $this->paidRevenue($clinicId, $month->copy()->startOfMonth(), $month->copy()->endOfMonth()),
            ])
            ->values()
            ->all();

        return ServiceResult::success($series, 'Revenue tracking fetched successfully');
    }

    public function servicesDistribution(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $buckets = ['Cleanings' => 0, 'Crowns' => 0, 'Fillings' => 0, 'Other' => 0];

        ClinicAppointment::query()
            ->where('clinic_id', $clinicId)
            ->select('service_name', DB::raw('count(*) as aggregate'))
            ->groupBy('service_name')
            ->get()
            ->each(function ($row) use (&$buckets) {
                $name = strtolower((string) $row->service_name);
                $bucket = match (true) {
                    str_contains($name, 'clean') => 'Cleanings',
                    str_contains($name, 'crown') => 'Crowns',
                    str_contains($name, 'fill') => 'Fillings',
                    default => 'Other',
                };
                $buckets[$bucket] += (int) $row->aggregate;
            });

        $total = max(array_sum($buckets), 1);

        return ServiceResult::success(
            collect($buckets)->map(fn (int $count, string $type) => [
                'service_type' => $type,
                'percentage' => round(($count / $total) * 100, 2),
            ])->values()->all(),
            'Services distribution fetched successfully'
        );
    }

    public function marketingLeadAttribution(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $phone = ClinicAppointment::query()->where('clinic_id', $clinicId)->whereNotNull('patient_phone')->count();
        $website = max(ClinicAppointment::query()->where('clinic_id', $clinicId)->count() - $phone, 0);
        $total = max($phone + $website, 1);

        $platforms = ['Facebook', 'Instagram', 'Google', 'TikTok'];
        $last30Days = ClinicAppointment::query()
            ->where('clinic_id', $clinicId)
            ->where('appointment_at', '>=', now()->subDays(30))
            ->count();

        return ServiceResult::success([
            'source_distribution' => [
                ['source' => 'Phone', 'percentage' => round(($phone / $total) * 100, 2)],
                ['source' => 'Website', 'percentage' => round(($website / $total) * 100, 2)],
            ],
            'platform_roi' => collect($platforms)->map(function (string $platform) use ($last30Days) {
                $bookings = intdiv($last30Days, 4);

                return [
                    'platform' => $platform,
                    'bookings_count' => $bookings,
                    'conversion_rate' => $last30Days > 0 ? round(($bookings / $last30Days) * 100, 2) : 0,
                    'cost' => 0,
                ];
            })->all(),
        ], 'Marketing lead attribution fetched successfully');
    }

    private function card(string $label, int|float $current, int|float $previous): array
    {
        $change = $previous > 0 ? (($current - $previous) / $previous) * 100 : ($current > 0 ? 100 : 0);

        return [
            'label' => $label,
            'value' => $current,
            'change_percentage' => round(abs($change), 2),
            'trend' => $change >= 0 ? 'up' : 'down',
        ];
    }

    private function paidRevenue(int $clinicId, Carbon $from, Carbon $to): float
    {
        return (float) ClinicInvoice::query()
            ->where('clinic_id', $clinicId)
            ->whereBetween('issued_at', [$from->toDateString(), $to->toDateString()])
            ->sum('paid');
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }
}
