<?php

namespace App\Services\Owner;

use App\Models\FeedbackReport;
use App\Models\Clinic;
use App\Repositories\FeedbackReportRepository;
use App\Support\ServiceResult;

class FeedbackReportService
{
    public function __construct(private FeedbackReportRepository $repository) {}

    public function list(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $reports = $this->repository->paginate($filters, $perPage);

        $items = collect($reports->items())
            ->map(fn (FeedbackReport $report) => [
                'id' => $report->id,
                'testimonial' => (bool) $report->allow_testimonial,
                'comment' => $report->comment,
                'rating' => $report->rating,
                'clinic' => $report->clinic?->name,
                'patient' => $report->patient?->user?->name,
                'date' => optional($report->submitted_at)->format('d/m/Y'),
            ])
            ->values()
            ->all();

        return ServiceResult::success([
            'items' => $items,
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
            'filters' => [
                'ratings' => ['All Ratings', '1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                'clinics' => Clinic::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Clinic $clinic) => ['id' => $clinic->id, 'name' => $clinic->name])
                    ->values()
                    ->all(),
            ],
        ], 'Feedback reports fetched successfully');
    }
}
