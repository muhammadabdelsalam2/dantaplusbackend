<?php

namespace App\Services\Owner;

use App\Models\FeedbackReport;
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
                'appointmentId' => $report->appointment_id,
                'clinicId' => $report->clinic_id,
                'clinicName' => $report->clinic?->name,
                'patientId' => $report->patient_id,
                'patientName' => $report->patient?->user?->name,
                'rating' => $report->rating,
                'comment' => $report->comment,
                'allowTestimonial' => (bool) $report->allow_testimonial,
                'submittedAt' => optional($report->submitted_at)->toISOString(),
                'createdAt' => optional($report->created_at)->toISOString(),
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
        ], 'Feedback reports fetched successfully');
    }
}
