<?php

namespace App\Services\Clinic;

use App\Models\Patient;
use App\Models\PatientRadiology;
use App\Support\ServiceResult;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
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

    $case1 = $records->get($case1Id);
    $case2 = $records->get($case2Id);

    return ServiceResult::success([
        'case_1' => $this->mapRecord($case1),
        'case_2' => $this->mapRecord($case2),

        'download' => [
            // ⭐ signed URL بدل url() العادي — كده اللينك يشتغل مباشرة من المتصفح من غير Authorization header
            'pdf_url' => URL::temporarySignedRoute(
                'clinic.radiology.compare.pdf-file',
                now()->addMinutes(30),
                [
                    'case_1' => $case1Id,
                    'case_2' => $case2Id,
                    'clinic_id' => $clinicId, // نبعت clinic_id هنا لأن الراوت بقى مش هيعتمد على auth()
                ]
            ),
            'filename' => 'radiology-compare-' . ($case1->record_date?->format('Y-m-d') ?? now()->format('Y-m-d')) . '.pdf',
        ],
    ], 'Radiology cases compared successfully');
}
 public function downloadComparePdf(int $case1Id, int $case2Id, int $clinicId): array
{
    $records = PatientRadiology::query()
        ->with(['linkedAppointment.doctor:id,name', 'patient.user'])
        ->where('clinic_id', $clinicId)
        ->whereIn('id', [$case1Id, $case2Id])
        ->get()
        ->keyBy('id');

    if (! $records->has($case1Id) || ! $records->has($case2Id)) {
        return ServiceResult::error('Radiology records not found.', null, null, 404);
    }

    $case1 = $records->get($case1Id);
    $case2 = $records->get($case2Id);
    $case1Data = $this->mapRecord($case1);
    $case2Data = $this->mapRecord($case2);

    $html = $this->renderComparePdfHtml($case1, $case2, $case1Data, $case2Data);

    return ServiceResult::success([
        'filename' => 'radiology-compare-' . now()->format('Y-m-d-His') . '.pdf',
        'content_type' => 'application/pdf',
        'content' => Pdf::loadHTML($html)->setPaper('a4')->output(),
    ], 'Radiology comparison PDF generated successfully');
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

    public function downloadPdfLink(int $radiologyId): array
    {
        $record = PatientRadiology::query()->find($radiologyId);

        if (! $record) {
            return ServiceResult::error('Radiology record not found.', null, null, 404);
        }

        $data = $this->mapRecord($record);
        $date = $data['date'] ?: now()->toDateString();

        return ServiceResult::success([
            'radiology_id' => $record->id,
            'filename' => 'radiology-' . $date . '.pdf',
            'pdf_url' => url('/api/clinic/radiology/' . $record->id . '/pdf-file'),
        ], 'Radiology PDF link generated successfully');
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
    if (!$path) {
        return null;
    }

    if (Str::startsWith($path, ['http://', 'https://'])) {

        $content = @file_get_contents($path);

        if (!$content) {
            return null;
        }

        $mime = getimagesizefromstring($content)['mime'] ?? 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    $path = ltrim(Str::after(str_replace('\\', '/', $path), 'public/'), '/');

    if (!Storage::disk('public')->exists($path)) {
        return null;
    }

    $mime = Storage::disk('public')->mimeType($path);

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
      private function renderComparePdfHtml(
    PatientRadiology $case1,
    PatientRadiology $case2,
    array $case1Data,
    array $case2Data
): string {
    $patientName = e($case1->patient?->user?->name ?? 'N/A');
    $case1Doctor = e($case1->linkedAppointment?->doctor?->name ?? 'N/A');
    $case2Doctor = e($case2->linkedAppointment?->doctor?->name ?? 'N/A');

    $before = $this->imageAsBase64($case1->before_image_path ?: $case1->file_path);
    $after = $this->imageAsBase64($case2->before_image_path ?: $case2->file_path);


    $html = '
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: "DejaVu Sans", Arial, sans-serif; color: #111827; margin: 20px; background: #f9fafb; }
            .header { border-bottom: 2px solid #3b82f6; padding-bottom: 15px; margin-bottom: 20px; }
            .header h1 { margin: 0; font-size: 28px; color: #1f2937; }
            .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; background: white; border: 1px solid #e5e7eb; }
            .info-table td { padding: 10px; border: 1px solid #e5e7eb; }
            .info-table td:first-child { font-weight: bold; width: 20%; background: #f3f4f6; }
            .cases-container { display: flex; gap: 20px; page-break-inside: avoid; }
            .case { flex: 1; background: white; border: 1px solid #e5e7eb; border-radius: 6px; padding: 15px; page-break-inside: avoid; }
            .case h2 { font-size: 18px; margin: 0 0 12px 0; color: #3b82f6; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; }
            .case-detail { margin: 8px 0; font-size: 14px; }
            .case-detail strong { display: inline-block; width: 80px; color: #374151; }
            .image-container { margin: 12px 0; text-align: center; }
            .image-container img { max-width: 100%; max-height: 350px; border: 1px solid #ddd; padding: 5px; border-radius: 4px; }
            .no-image { color: #999; font-style: italic; }
            .notes { margin-top: 12px; padding: 10px; background: #f9fafb; border-left: 3px solid #3b82f6; font-size: 13px; line-height: 1.5; }
            .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280; text-align: center; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Radiology Comparison Report</h1>
        </div>

        <table class="info-table">
            <tr>
                <td>Patient</td>
                <td>' . $patientName . '</td>
            </tr>
            <tr>
                <td>Generated</td>
                <td>' . now()->format('Y-m-d H:i') . '</td>
            </tr>
            <tr>
                <td>Report Type</td>
                <td>Before & After Comparison</td>
            </tr>
        </table>

        <div class="cases-container">
            <div class="case">
                <h2>Case 1 (Before)</h2>
                <div class="case-detail">
                    <strong>Date:</strong> ' . $case1Data['date'] . '
                </div>
                <div class="case-detail">
                    <strong>Type:</strong> ' . $case1Data['type'] . '
                </div>
                <div class="case-detail">
                    <strong>Doctor:</strong> ' . $case1Doctor . '
                </div>
                <div class="image-container">';

    if ($before) {
        $html .= '<img src="' . $before . '" />';
    } else {
        $html .= '<p class="no-image">No image available</p>';
    }

    $html .= '
                </div>
                <div class="notes">
                    <strong>Notes:</strong><br>
                    ' . nl2br(e($case1Data['notes'] ?: 'No notes provided.')) . '
                </div>
            </div>

            <div class="case">
                <h2>Case 2 (After)</h2>
                <div class="case-detail">
                    <strong>Date:</strong> ' . $case2Data['date'] . '
                </div>
                <div class="case-detail">
                    <strong>Type:</strong> ' . $case2Data['type'] . '
                </div>
                <div class="case-detail">
                    <strong>Doctor:</strong> ' . $case2Doctor . '
                </div>
                <div class="image-container">';

    if ($after) {
        $html .= '<img src="' . $after . '" />';
    } else {
        $html .= '<p class="no-image">No image available</p>';
    }

    $html .= '
                </div>
                <div class="notes">
                    <strong>Notes:</strong><br>
                    ' . nl2br(e($case2Data['notes'] ?: 'No notes provided.')) . '
                </div>
            </div>
        </div>

        <div class="footer">
            <p>This is an automatically generated radiology comparison report.</p>
            <p>For medical decisions, please consult with your healthcare provider.</p>
        </div>
    </body>
    </html>';

    return $html;
}
}
