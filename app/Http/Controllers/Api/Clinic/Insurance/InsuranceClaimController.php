<?php

namespace App\Http\Controllers\Api\Clinic\Insurance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Insurance\PatientLookupRequest;
use App\Http\Requests\Clinic\Insurance\StoreInsuranceClaimRequest;
use App\Http\Requests\Clinic\Insurance\UpdateInsuranceClaimRequest;
use App\Http\Requests\Clinic\Insurance\UploadPatientConsentRequest;
use App\Services\Clinic\Insurance\InsuranceClaimService;
use App\Services\Clinic\Insurance\PatientLookupService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class InsuranceClaimController extends Controller
{
    use ApiResponse;

    public function __construct(private InsuranceClaimService $service)
    {
    }

    public function index(Request $request)
    {
        $result = $this->service->index([
            'status' => $request->string('status')->toString() ?: null,
            'patient_id' => $request->integer('patient_id') ?: null,
            'insurance_company_id' => $request->integer('insurance_company_id') ?: null,
        ]);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreInsuranceClaimRequest $request)
    {
        $result = $this->service->store($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(int $id)
    {
        $result = $this->service->show($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function update(UpdateInsuranceClaimRequest $request, int $id)
    {
        $result = $this->service->update($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function destroy(int $id)
    {
        $result = $this->service->destroy($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function patientLookup(PatientLookupRequest $request, PatientLookupService $lookupService)
    {
        $clinicId = auth()->user()?->clinic_id;
        if (!$clinicId) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $patients = $lookupService->search(
            clinicId: $clinicId,
            patientId: $request->integer('patient_id'),
            patientNumber: $request->string('patient_number')->toString() ?: null,
            query: $request->string('query')->toString() ?: null,
            limit: $request->integer('limit', 10)
        );

        $formatted = $patients->map(fn ($patient) => $lookupService->format($patient))->toArray();

        return ApiResponse::success($formatted, 'Patients retrieved successfully', 200);
    }

    public function uploadConsent(UploadPatientConsentRequest $request, int $id)
    {
        $clinicId = auth()->user()?->clinic_id;
        if (!$clinicId) {
            return ApiResponse::error('Clinic account is not linked to a clinic.', 403);
        }

        $claim = \App\Models\Clinic\Insurance\InsuranceClaim::where('clinic_id', $clinicId)->find($id);
        if (!$claim) {
            return ApiResponse::error('Insurance claim not found.', 404);
        }

        if (!$request->hasFile('file')) {
            return ApiResponse::error('File is required.', 422, ['file' => ['File is required.']]);
        }

        $file = $request->file('file');
        $path = $file->store('patient-documents/' . $claim->patient_id, 'public');

        $document = \App\Models\PatientDocument::create([
            'clinic_id' => $clinicId,
            'patient_id' => $claim->patient_id,
            'uploaded_by' => auth()->id(),
            'document_type' => 'insurance_consent',
            'title' => $request->string('title')->toString() ?: 'Patient Consent',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'related_type' => 'insurance_claim',
            'related_id' => $claim->id,
            'notes' => $request->string('notes')->toString() ?: null,
        ]);

        $claim->update([
            'patient_consent_document_id' => $document->id,
            'patient_consent_uploaded_at' => now(),
        ]);

        return ApiResponse::success([
            'claim' => (new \App\Http\Resources\Clinic\Insurance\InsuranceClaimResource($claim->fresh()))->resolve(),
            'document' => (new \App\Http\Resources\PatientDocumentResource($document))->resolve(),
        ], 'Patient consent document uploaded successfully', 201);
    }
