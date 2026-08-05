<?php

namespace App\Services\Clinic;

use App\Models\Patient;
use App\Models\PatientRadiology;
use App\Support\ServiceResult;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RadiologyService
{
    public function index(int $patientId): array
    {
        $patient = Patient::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->find($patientId);

        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $records = PatientRadiology::query()
            ->with(['linkedAppointment.doctor:id,name'])
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->where('patient_id', $patient->id)
            ->latest('record_date')
            ->latest('id')
            ->get();

        return ServiceResult::success(
            $records->map(fn (PatientRadiology $record) => $this->mapRecord($record))->values()->all(),
            'Radiology records fetched successfully'
        );
    }

    public function compare(int $case1Id, int $case2Id): array
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $records = PatientRadiology::query()
            ->with(['linkedAppointment.doctor:id,name'])
            ->where('clinic_id', $clinicId)
            ->whereIn('id', [$case1Id, $case2Id])
            ->get()
            ->keyBy('id');

        if (! $records->has($case1Id) || ! $records->has($case2Id)) {
            return ServiceResult::error('Two radiology records are required for comparison.', null, null, 422);
        }

        return ServiceResult::success([
            'case_1' => $this->mapRecord($records->get($case1Id)),
            'case_2' => $this->mapRecord($records->get($case2Id)),
        ], 'Radiology cases compared successfully');
    }

    public function downloadPdf(int $radiologyId): array
    {
        $record = PatientRadiology::query()
            ->with(['patient.user', 'linkedAppointment.doctor:id,name'])
            ->find($radiologyId);

        if (! $record) {
            return ServiceResult::error('Radiology record not found.', null, null, 404);
        }

        $data = $this->mapRecord($record);
        $html = $this->renderPdfHtml($record, $data);
        $date = $data['date'] ?: now()->toDateString();

        return ServiceResult::success([
            'filename' => 'radiology-' . $date . '.pdf',
            'content_type' => 'application/pdf',
            'content' => Pdf::loadHTML($html)->setPaper('a4')->output(),
        ], 'Radiology PDF generated successfully');
    }

    private function mapRecord(PatientRadiology $record): array
    {
        return [
            'id' => $record->id,
            'appointment_id' => $record->linked_appointment_id,
            'date' => optional($record->record_date ?: $record->created_at)?->toDateString(),
            'type' => $record->modality,
            'notes' => $record->notes,
            'images' => $this->images($record),
        ];
    }

    private function images(PatientRadiology $record): array
    {
        return collect([
            ['type' => 'before', 'path' => $record->before_image_path ?: $record->file_path, 'created_at' => $record->created_at],
            ['type' => 'after', 'path' => $record->after_image_path, 'created_at' => $record->updated_at ?: $record->created_at],
        ])
            ->filter(fn (array $image) => filled($image['path']))
            ->values()
            ->map(fn (array $image, int $index) => [
                'id' => ($record->id * 10) + $index + 1,
                'type' => $image['type'],
                'image_url' => $this->publicUrl($image['path']),
                'created_at' => optional($image['created_at'])->toISOString(),
            ])
            ->all();
    }

    private function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $path = ltrim($path, '/');
        if (Str::startsWith($path, 'storage/')) {
            return url($path);
        }

        if (Str::startsWith($path, 'public/')) {
            $path = Str::after($path, 'public/');
        }

        return url(Storage::url($path));
    }

    private function imageAsBase64(?string $path): ?string
    {
        if (! $path || Str::startsWith($path, ['http://', 'https://'])) {
            return null;
        }

        $path = ltrim(Str::after(str_replace('\\', '/', $path), 'public/'), '/');
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($path));
    }

    private function renderPdfHtml(PatientRadiology $record, array $data): string
    {
        $patientName = e($record->patient?->user?->name ?? 'N/A');
        $doctorName = e($record->linkedAppointment?->doctor?->name ?? 'N/A');
        $before = $this->imageAsBase64($record->before_image_path ?: $record->file_path);
        $after = $this->imageAsBase64($record->after_image_path);

        return '<html><body style="font-family: DejaVu Sans, sans-serif; color:#111827;">'
            . '<h1 style="font-size:24px;border-bottom:1px solid #d1d5db;padding-bottom:10px;">Radiology Report</h1>'
            . '<table style="width:100%;border-collapse:collapse;margin-bottom:18px;">'
            . '<tr><td><strong>Patient:</strong> ' . $patientName . '</td><td><strong>Appointment:</strong> ' . e((string) ($data['appointment_id'] ?? 'N/A')) . '</td></tr>'
            . '<tr><td><strong>Date:</strong> ' . e((string) $data['date']) . '</td><td><strong>Type:</strong> ' . e((string) $data['type']) . '</td></tr>'
            . '<tr><td colspan="2"><strong>Doctor:</strong> ' . $doctorName . '</td></tr>'
            . '</table>'
            . '<h2 style="font-size:16px;">Before Image</h2>' . ($before ? '<img src="' . $before . '" style="max-width:100%;max-height:260px;">' : '<p>No before image.</p>')
            . '<h2 style="font-size:16px;margin-top:18px;">After Image</h2>' . ($after ? '<img src="' . $after . '" style="max-width:100%;max-height:260px;">' : '<p>No after image.</p>')
            . '<h2 style="font-size:16px;margin-top:18px;">Notes</h2><p>' . nl2br(e((string) ($data['notes'] ?? ''))) . '</p>'
            . '</body></html>';
    }
}
