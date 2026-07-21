<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Resources\Patient\PatientDocumentResource;
use App\Http\Resources\Patient\PatientRadiologyResource;
use App\Models\PatientDocument;
use App\Models\PatientNote;
use App\Models\PatientRadiology;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PatientDocumentController extends BasePatientController
{
    public function index(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $documents = PatientDocument::query()
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success(PatientDocumentResource::collection($documents), 'Patient documents retrieved successfully');
    }

    public function show(Request $request, int $id)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $document = PatientDocument::query()
            ->where('id', $id)
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->first();

        if (! $document) {
            return ApiResponse::error('Document not found', 404);
        }

        return ApiResponse::success(new PatientDocumentResource($document), 'Patient document retrieved successfully');
    }

    public function radiology(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $radiology = PatientRadiology::query()
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success(PatientRadiologyResource::collection($radiology), 'Patient radiology files retrieved successfully');
    }

    public function download(Request $request, int $id)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $document = PatientDocument::query()
            ->where('id', $id)
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->first();

        $path = $document ? $this->publicStoragePath($document->file_path) : null;

        if (! $document || ! $path || ! Storage::disk('public')->exists($path)) {
            return ApiResponse::error('Document file not found', 404);
        }

        return Storage::disk('public')->download(
            $path,
            $document->original_name ?: basename($path)
        );
    }

    public function downloadRadiology(Request $request, int $id)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $radiology = PatientRadiology::query()
            ->where('id', $id)
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->first();

        $path = $radiology ? $this->publicStoragePath($radiology->file_path) : null;

        if (! $radiology || ! $path || ! Storage::disk('public')->exists($path)) {
            return ApiResponse::error('Radiology file not found', 404);
        }

        return Storage::disk('public')->download($path, basename($path));
    }

    public function notes(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $notes = PatientNote::query()
            ->select(['id', 'patient_id', 'clinic_id', 'note', 'created_at'])
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id)
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success($notes, 'Patient medical notes retrieved successfully');
    }

    private function publicStoragePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = trim($path);

        if (Str::startsWith($path, ['http://', 'https://'])) {
            $path = parse_url($path, PHP_URL_PATH) ?: $path;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (Str::startsWith($path, 'storage/')) {
            $path = Str::after($path, 'storage/');
        }

        if (Str::startsWith($path, 'public/')) {
            $path = Str::after($path, 'public/');
        }

        return $path ?: null;
    }
    public function downloadRadiologyImage(Request $request, int $id, string $type)
{
    $patient = $this->currentPatient($request);

    if ($this->isResponse($patient)) {
        return $patient;
    }

    if (! in_array($type, ['before', 'after'], true)) {
        return ApiResponse::error('Invalid image type', 422);
    }

    $radiology = PatientRadiology::query()
        ->where('id', $id)
        ->where('patient_id', $patient->id)
        ->where('clinic_id', $patient->clinic_id)
        ->first();

    $column = $type === 'before' ? 'before_image_path' : 'after_image_path';
    $path = $radiology ? $this->publicStoragePath($radiology->{$column}) : null;

    if (! $radiology || ! $path || ! Storage::disk('public')->exists($path)) {
        return ApiResponse::error('Image not found', 404);
    }

    return Storage::disk('public')->download($path, basename($path));
}
}
