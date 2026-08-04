<?php

namespace App\Services\Clinic;

use App\Http\Resources\Clinic\DentalChartResource;
use App\Http\Resources\Clinic\ClinicInvoiceResource;
use App\Http\Resources\Clinic\PatientLabCaseResource;
use App\Http\Resources\Clinic\PatientNoteResource;
use App\Http\Resources\Clinic\PatientResource;
use App\Http\Resources\Clinic\RadiologyResource;
use App\Http\Resources\Clinic\PatientDocumentResource;
use App\Http\Resources\Clinic\TreatmentResource;
use App\Models\CaseActivityLog;
use App\Models\PatientDocument;
use App\Models\CaseModel;
use App\Models\ClinicAppointment;
use App\Models\ClinicInvoice;
use App\Models\ClinicPayment;
use App\Models\ClinicTreatment;
use App\Models\DentalLab;
use App\Models\Doctor;
use App\Models\InsuranceApproval;
use App\Models\InsuranceCompany;
use App\Models\Patient;
use App\Models\PatientNote;
use App\Models\PatientRadiology;
use App\Models\PatientTooth;
use App\Models\Service;
use App\Models\User;
use App\Support\ServiceResult;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PatientService
{
  public function index(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $query = Patient::query()
            ->with('user:id,name,email,phone')
            ->with('insuranceCompany:id,name')
            ->where('clinic_id', $clinicId)
            // ← search على name, phone, email (من جدول users)
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($nested) use ($search) {
                    $nested->where('phone', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%")
                               ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            // ← فلتر الجنس: male أو female
            ->when($filters['gender'] ?? null, fn ($q, $gender) => $q->where('gender', $gender))
            ->latest('id');

        $patients = $query->paginate($perPage);

        return ServiceResult::success([
            'items' => collect(PatientResource::collection($patients->items())->resolve())->map(function (array $patient) {
                return $patient + [
                    'patient_name' => $patient['name'] ?? null,
                    'contact' => $patient['phone'] ?? null,
                    'age' => isset($patient['date_of_birth']) ? Carbon::parse($patient['date_of_birth'])->age : null,
                ];
            })->all(),
            'pagination' => [
                'current_page' => $patients->currentPage(),
                'last_page'    => $patients->lastPage(),
                'per_page'     => $patients->perPage(),
                'total'        => $patients->total(),
            ],
        ], 'Patients fetched successfully');
    }

    public function show(int $patientId): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        return ServiceResult::success((new PatientResource($patient))->resolve(), 'Patient fetched successfully');
    }

    public function create(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $paymentType = $data['payment_type'] ?? null;
        $insuranceCompany = null;

        if ($paymentType === 'insurance' || ($paymentType === null && array_key_exists('insurance_company_id', $data) && $data['insurance_company_id'] !== null)) {
            $insuranceCompany = $this->resolveInsuranceCompanyId($clinicId, $data['insurance_company_id'] ?? null);
            if (array_key_exists('insurance_company_id', $data) && $data['insurance_company_id'] !== null && ! $insuranceCompany) {
                return ServiceResult::error('Insurance company not found.', null, ['insurance_company_id' => ['Insurance company not found for this clinic.']], 422);
            }
        }

        $patient = DB::transaction(function () use ($clinicId, $data, $insuranceCompany, $paymentType) {
            $user = User::query()->create([
                'clinic_id' => $clinicId,
                'name' => $data['name'],
                'username' => Str::slug($data['name'], '') ?: ('patient' . now()->timestamp),
                'email' => $data['email'] ?? $this->generatedPatientEmail($clinicId),
                'phone' => $data['phone'],
                'password' => bcrypt($data['password'] ?? Str::random(12)),
                'status' => 'Active',
                'is_active' => true,
                'is_verified' => true,
                'role' => 'patient',
            ]);

            $user->syncRoles(['patient']);

            $finalPaymentType = $paymentType ?? ($insuranceCompany ? 'insurance' : 'cash');
            $patientData = [
                'user_id' => $user->id,
                'clinic_id' => $clinicId,
                'patient_number' => $this->generatePatientNumber(),
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'phone' => $data['phone'],
                'address' => $data['address'] ?? null,
                'medical_history' => $data['medical_history'] ?? null,
                'allergies' => $data['allergies'] ?? null,
                'current_medication' => $data['current_medication'] ?? null,
                'payment_type' => $finalPaymentType,
                'notes' => $data['notes'] ?? null,
            ];

            if ($finalPaymentType === 'insurance') {
                $patientData['insurance_provider'] = $data['insurance_provider'] ?? $insuranceCompany?->name;
                $patientData['insurance_company_id'] = $insuranceCompany?->id;
                $patientData['insurance_number'] = $data['insurance_number'] ?? null;
            } else {
                $patientData['insurance_provider'] = null;
                $patientData['insurance_company_id'] = null;
                $patientData['insurance_number'] = null;
            }

            return Patient::query()->create($patientData);
        });

        return $this->show($patient->id);
    }

    public function update(int $patientId, array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $isCashUpdate = array_key_exists('payment_type', $data) && $data['payment_type'] === 'cash';
        $insuranceCompany = null;

        if (! $isCashUpdate && array_key_exists('insurance_company_id', $data)) {
            $insuranceCompany = $this->resolveInsuranceCompanyId($clinicId, $data['insurance_company_id']);
            if ($data['insurance_company_id'] !== null && ! $insuranceCompany) {
                return ServiceResult::error('Insurance company not found.', null, ['insurance_company_id' => ['Insurance company not found for this clinic.']], 422);
            }
        }

        if (array_key_exists('full_name', $data) && ! array_key_exists('name', $data)) {
            $data['name'] = $data['full_name'];
        }
        if (array_key_exists('policy_number', $data) && ! array_key_exists('insurance_number', $data)) {
            $data['insurance_number'] = $data['policy_number'];
        }
        if (array_key_exists('age', $data) && ! array_key_exists('date_of_birth', $data) && $data['age'] !== null) {
            $data['date_of_birth'] = now()->subYears((int) $data['age'])->startOfYear()->toDateString();
        }

        DB::transaction(function () use ($data, $insuranceCompany, $isCashUpdate, $patient) {
            $userData = array_filter([
                'name' => $data['name'] ?? null,
                'email' => array_key_exists('email', $data) ? $data['email'] : null,
                'phone' => $data['phone'] ?? null,
                'password' => ! empty($data['password']) ? bcrypt($data['password']) : null,
            ], static fn ($value) => $value !== null);

            if ($userData !== []) {
                $patient->user?->update($userData);
            }

            $patientData = [];
            foreach ([
                'date_of_birth',
                'gender',
                'address',
                'medical_history',
                'allergies',
                'current_medication',
                'insurance_provider',
                'insurance_number',
                'payment_type',
                'notes',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $patientData[$field] = $data[$field];
                }
            }

            if (array_key_exists('phone', $data)) {
                $patientData['phone'] = $data['phone'];
            }

            if ($isCashUpdate) {
                $patientData['insurance_company_id'] = null;
                $patientData['insurance_provider'] = null;
                $patientData['insurance_number'] = null;
            } elseif (array_key_exists('insurance_company_id', $data)) {
                $patientData['insurance_company_id'] = $insuranceCompany?->id;
                if (! array_key_exists('insurance_provider', $data)) {
                    $patientData['insurance_provider'] = $insuranceCompany?->name;
                }
            }

            if ($patientData !== []) {
                $patient->update($patientData);
            }
        });

        return $this->show($patient->id);
    }

    private function findClinicPatient(int $patientId): ?Patient
    {
        return Patient::query()
            ->with('user:id,name,email,phone')
            ->with('insuranceCompany:id,name')
            ->where('clinic_id', $this->currentClinicId())
            ->find($patientId);
    }

    private function findRadiologyRecord(int $patientId, int $recordId): ?PatientRadiology
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return null;
        }

        return PatientRadiology::query()
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->find($recordId);
    }

    private function fdiToothNumbers(): array
    {
        return array_map('strval', [
            18, 17, 16, 15, 14, 13, 12, 11,
            21, 22, 23, 24, 25, 26, 27, 28,
            48, 47, 46, 45, 44, 43, 42, 41,
            31, 32, 33, 34, 35, 36, 37, 38,
        ]);
    }

    private function normalizeToothStatus(string $status): string
    {
        $normalized = strtolower(str_replace([' ', '-', '_'], '', $status));

        return match ($normalized) {
            'treated', 'completed', 'done' => 'treated',
            'inprogress' => 'inprogress',
            'planned' => 'planned',
            'problematic' => 'problematic',
            default => 'healthy',
        };
    }

    private function appointmentStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'completed', 'attended', 'done' => 'Attended',
            'cancelled', 'canceled' => 'Cancelled',
            'no_show', 'no-show', 'noshow' => 'No Show',
            default => 'Scheduled',
        };
    }

    /**
     * Generate a temporary signed download URL for a radiology PDF.
     */
    private function radiologyDownloadUrl(PatientRadiology $record): string
    {
        return URL::temporarySignedRoute(
            self::RADIOLOGY_DOWNLOAD_ROUTE,
            now()->addMinutes(self::RADIOLOGY_DOWNLOAD_TTL_MINUTES),
            [
                'patient'   => $record->patient_id,
                'record'    => $record->id,
                'clinic_id' => $record->clinic_id,
            ]
        );
    }

    /**
     * Build & render a radiology PDF for download via signed URL.
     * Returns ['filename', 'content_type', 'content'] on success.
     */
    public function radiologyPdfPayloadForClinic(int $clinicId, int $patientId, int $recordId): array
    {
        $record = PatientRadiology::query()
            ->with(['patient.user', 'clinic', 'reportDoctor'])
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $patientId)
            ->find($recordId);

        if (! $record) {
            return ServiceResult::error('Radiology record not found.', null, null, 404);
        }

        $report  = $this->radiologyReportPayload($record);
        $content = Pdf::loadView('pdf.radiology-report', ['report' => $report])
            ->setPaper('a4')
            ->output();

        $filename = 'radiology-report-' . ($report['reference_code'] ?? $recordId) . '.pdf';

        return ServiceResult::success([
            'filename'     => $filename,
            'content_type' => 'application/pdf',
            'content'      => $content,
        ], 'Radiology PDF generated successfully');
    }

    private function radiologyReportPayload(PatientRadiology $record): array
    {
        $record->loadMissing(['patient.user', 'clinic', 'reportDoctor']);
        $patient = $record->patient;
        $doctor = $record->reportDoctor ?: auth()->user();
        $reference = $record->report_reference_code ?: 'RAD-' . str_pad((string) $record->id, 6, '0', STR_PAD_LEFT);

        return [
            'record_id' => $record->id,
            'report_format' => $record->report_format,
            'case_selection' => $record->report_case_selection ?: [$record->id],
            'clinic' => [
                'name' => $record->clinic?->name,
                'department' => 'Radiology Department',
            ],
            'reference_code' => $reference,
            'patient_information' => [
                'name' => $patient?->user?->name,
                'file_id' => $patient?->patient_number ?: ('PID-' . $patient?->id),
                'gender' => $patient?->gender,
                'age' => $patient?->age,
            ],
            'ordering_clinician' => [
                'name' => $doctor?->name,
                'department' => 'Dental Clinic',
            ],
            'findings' => $record->report_findings,
            'diagnosis' => $record->report_diagnosis,
            'electronic_signature' => [
                'doctor_name' => $doctor?->name,
                'signed_at' => optional($record->report_generated_at)->toISOString(),
            ],
            'qr_code_data' => url('/verify/radiology-reports/' . $reference),
            'images' => [
                'before_image_url' => (new RadiologyResource($record))->resolve()['before_image_url'] ?? null,
                'after_image_url'  => (new RadiologyResource($record))->resolve()['after_image_url'] ?? null,
            ],
            'created_at'       => optional($record->created_at)?->toISOString(),
            // Signed PDF download link
            'download_pdf_url' => $this->radiologyDownloadUrl($record),
        ];
    }

    public function dentalChart(int $patientId): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $rows = PatientTooth::query()
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->latest('id')
            ->get();

        $latestByTooth = $rows->groupBy('tooth_number')->map->first();
        $teeth = collect($this->fdiToothNumbers())->map(function (string $number) use ($latestByTooth) {
            $entry = $latestByTooth->get($number);

            return [
                'tooth_number' => $number,
                'status' => $entry?->status ?: 'healthy',
                'is_present' => (bool) ($entry?->is_present ?? true),
                'notes' => $entry?->notes,
                'record_id' => $entry?->id,
            ];
        })->values()->all();

        $labModel = CaseModel::query()
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->whereNotNull('tooth_chart_3d')
            ->latest('updated_at')
            ->first();

        return ServiceResult::success([
            'model_type' => '3d_dental_chart',
            'numbering_system' => 'FDI',
            'statuses' => ['healthy', 'treated', 'inprogress', 'planned', 'problematic'],
            'teeth' => $teeth,
            'records' => DentalChartResource::collection($rows->load(['procedure:id,name', 'treatingDoctor:id,name']))->resolve(),
            'lab_3d_source' => $labModel ? [
                'case_id' => $labModel->id,
                'case_number' => $labModel->case_number,
                'case_type' => $labModel->case_type,
                'tooth_chart_3d' => $labModel->tooth_chart_3d,
            ] : null,
        ], 'Dental chart fetched successfully');
    }

    public function recordDentalChart(int $patientId, array $data): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        if (! empty($data['procedure_id']) && ! Service::query()
            ->whereKey($data['procedure_id'])
            ->where(function ($query) {
                $query->whereNull('created_by_clinic_id')->orWhere('created_by_clinic_id', $this->currentClinicId());
            })
            ->exists()) {
            return ServiceResult::error('Procedure not found for this clinic.', null, ['procedure_id' => ['Procedure not found for this clinic.']], 422);
        }

        if (! empty($data['treating_doctor_id']) && ! User::query()
            ->where('clinic_id', $this->currentClinicId())
            ->role('doctor')
            ->whereKey($data['treating_doctor_id'])
            ->exists()) {
            return ServiceResult::error('Treating doctor not found for this clinic.', null, ['treating_doctor_id' => ['Treating doctor not found for this clinic.']], 422);
        }

        $toothNumbers = $data['tooth_numbers'] ?? [$data['tooth_number']];

        $entries = collect($toothNumbers)->map(fn (string $toothNumber) => PatientTooth::query()->create([
            'patient_id' => $patient->id,
            'clinic_id' => $this->currentClinicId(),
            'tooth_number' => $toothNumber,
            'status' => $this->normalizeToothStatus($data['status'] ?? 'healthy'),
            'is_present' => $data['is_present'] ?? true,
            'target_area' => $data['target_area'] ?? null,
            'procedure_id' => $data['procedure_id'] ?? null,
            'treating_doctor_id' => $data['treating_doctor_id'] ?? null,
            'billing_method' => $data['billing_method'] ?? null,
            'clinical_notes' => $data['clinical_notes'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]));

        $entries->each->load(['procedure:id,name', 'treatingDoctor:id,name']);

        return ServiceResult::success(DentalChartResource::collection($entries)->resolve(), 'Dental chart entry recorded successfully', 201);
    }

    public function updateToothPresence(int $patientId, array $data): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $toothNumbers = $data['tooth_numbers'] ?? [$data['tooth_number']];
        $existing = PatientTooth::query()
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->latest('id')
            ->get()
            ->groupBy('tooth_number')
            ->map->first();

        $entries = collect($toothNumbers)->map(function (string $toothNumber) use ($data, $existing, $patient) {
            $latest = $existing->get($toothNumber);

            return PatientTooth::query()->create([
                'patient_id' => $patient->id,
                'clinic_id' => $this->currentClinicId(),
                'tooth_number' => $toothNumber,
                'status' => $latest?->status ?? 'healthy',
                'is_present' => (bool) $data['is_present'],
                'target_area' => $latest?->target_area,
                'procedure_id' => $latest?->procedure_id,
                'treating_doctor_id' => $latest?->treating_doctor_id,
                'billing_method' => $latest?->billing_method,
                'clinical_notes' => $latest?->clinical_notes,
                'notes' => $latest?->notes,
            ]);
        });

        $entries->each->load(['procedure:id,name', 'treatingDoctor:id,name']);

        return ServiceResult::success(DentalChartResource::collection($entries)->resolve(), 'Tooth presence updated successfully');
    }

    public function radiology(int $patientId, array $filters = []): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $rows = PatientRadiology::query()
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('notes', 'like', "%{$search}%");
            })
            ->when($filters['teeth'] ?? null, fn ($query, $teeth) => $query->where('teeth', 'like', "%{$teeth}%"))
            ->when($filters['modality'] ?? null, fn ($query, $modality) => $query->where('modality', $modality))
            ->latest('id')
            ->get();

        $total    = $rows->count();
        $finished = $rows->filter(fn ($row) => in_array(strtolower((string) $row->status), ['finished', 'completed', 'done'], true))->count();

        // Build resource collection and attach download_pdf_url per item using original $rows
        $items = collect(RadiologyResource::collection($rows)->resolve())
            ->map(function (array $resource, int $index) use ($rows) {
                /** @var PatientRadiology $record */
                $record = $rows->values()->get($index);

                return $resource + [
                    'download_pdf_url' => $record ? $this->radiologyDownloadUrl($record) : null,
                ];
            })
            ->values()
            ->all();

        return ServiceResult::success([
            'header' => [
                'total_records'  => $total,
                'cases_finished' => $finished . ' / ' . $total,
            ],
            'modalities' => ['Periapical', 'Bitewing', 'Panoramic', 'CBCT'],
            'items'      => $items,
        ], 'Radiology archive fetched successfully');
    }

   public function uploadRadiology(int $patientId, array $data, $file = null, $beforeImage = null, $afterImage = null): array
{
    $patient = $this->findClinicPatient($patientId);
    if (! $patient) {
        return ServiceResult::error('Patient not found.', null, null, 404);
    }

    $folder = 'clinic/radiology';

    if (! empty($data['linked_appointment_id']) && ! ClinicAppointment::query()
        ->where('clinic_id', $this->currentClinicId())
        ->where('patient_id', $patient->id)
        ->whereKey($data['linked_appointment_id'])
        ->exists()) {
        return ServiceResult::error('Linked appointment not found for this patient.', null, ['linked_appointment_id' => ['Linked appointment not found for this patient.']], 422);
    }

    if (! empty($data['linked_treatment_id']) && ! ClinicTreatment::query()
        ->where('clinic_id', $this->currentClinicId())
        ->where('patient_id', $patient->id)
        ->whereKey($data['linked_treatment_id'])
        ->exists()) {
        return ServiceResult::error('Linked treatment not found for this patient.', null, ['linked_treatment_id' => ['Linked treatment not found for this patient.']], 422);
    }

    $entry = PatientRadiology::query()->create([
        'patient_id' => $patient->id,
        'clinic_id' => $this->currentClinicId(),
        'modality' => $data['modality'],
        'teeth' => $data['teeth'] ?? null,
        'record_date' => $data['date'] ?? $data['record_date'] ?? null,
        'linked_appointment_id' => $data['linked_appointment_id'] ?? null,
        'linked_treatment_id' => $data['linked_treatment_id'] ?? null,
        'notes' => $data['notes'] ?? null,
        'file_path' => $file ? $file->store($folder, 'public') : null,
        'before_image_path' => $beforeImage ? $beforeImage->store($folder, 'public') : null,
        'after_image_path' => $afterImage ? $afterImage->store($folder, 'public') : null,
        'status' => $data['status'] ?? null,
    ]);

    return ServiceResult::success((new RadiologyResource($entry))->resolve(), 'Radiology uploaded successfully', 201);
}

    public function labCases(int $patientId): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $cases = CaseModel::query()
            ->with('lab:id,name')
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->latest('id')
            ->get();

        return ServiceResult::success([
            'items' => collect(PatientLabCaseResource::collection($cases)->resolve())->map(fn (array $case) => [
                'id' => $case['id'] ?? null,
                'case_id' => $case['case_number'] ?? null,
                'lab_name' => $case['lab']['name'] ?? null,
                'case_type' => $case['case_type'] ?? null,
                'delivery_date' => $case['due_date'] ?? null,
                'status' => $case['status'] ?? null,
                'action' => 'Track Case',
                'source' => 'lab',
            ])->all(),
        ], 'Patient lab cases fetched successfully');
    }

    public function sendLabCase(int $patientId, array $data): array
    {
        $clinicId = $this->currentClinicId();
        $patient = $this->findClinicPatient($patientId);

        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $lab = DentalLab::query()->find($data['lab_id']);
        if (! $lab) {
            return ServiceResult::error('Lab not found.', null, ['lab_id' => ['Lab not found.']], 422);
        }

        $doctorProfile = Doctor::query()
            ->whereHas('user', function ($query) use ($clinicId) {
                $query->where('clinic_id', $clinicId);
            })
            ->first();

        if (! $doctorProfile) {
            return ServiceResult::error('No doctor profile is linked to this clinic yet.', null, null, 422);
        }

        $case = CaseModel::query()->create([
            'case_number' => $this->generateCaseNumber(),
            'clinic_id' => $clinicId,
            'lab_id' => $lab->id,
            'patient_id' => $patient->id,
            'dentist_id' => $doctorProfile->id,
            'status' => CaseModel::STATUS_PENDING,
            'priority' => $data['priority'] ?? CaseModel::PRIORITY_NORMAL,
            'due_date' => $data['due_date'],
            'case_type' => $data['case_type'],
            'tooth_numbers' => $data['tooth_numbers'] ?? null,
            'description' => $data['description'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return ServiceResult::success(
            (new PatientLabCaseResource($case->load('lab:id,name')))->resolve(),
            'Lab case sent successfully.',
            201
        );
    }

    public function discussion(int $patientId): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $rows = PatientNote::query()
            ->with(['user:id,name', 'attachments', 'mentions.user:id,name'])
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->latest('id')
            ->get();

        return ServiceResult::success(PatientNoteResource::collection($rows)->resolve(), 'Discussion fetched successfully');
    }

    public function addDiscussion(int $patientId, array $data): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $note = DB::transaction(function () use ($data, $patient) {
            $note = PatientNote::query()->create([
                'patient_id' => $patient->id,
                'user_id' => auth()->id(),
                'clinic_id' => $this->currentClinicId(),
                'note' => $data['text'] ?? $data['note'] ?? '',
            ]);

            $attachments = $data['attachments'] ?? [];
            if (! empty($data['attachment'])) {
                $attachments[] = $data['attachment'];
            }
            if (! empty($data['voice_note'])) {
                $attachments[] = $data['voice_note'];
            }

            foreach ($attachments as $attachment) {
                $path = $attachment->store('clinic/patient-notes', 'public');
                $note->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $attachment->getClientOriginalName(),
                    'mime_type' => $attachment->getClientMimeType(),
                    'size' => $attachment->getSize(),
                ]);
            }

            $mentionIds = collect($data['mentions'] ?? [])
                ->unique()
                ->filter(fn ($userId) => User::query()
                    ->where('clinic_id', $this->currentClinicId())
                    ->whereKey($userId)
                    ->exists());

            foreach ($mentionIds as $userId) {
                $note->mentions()->create(['user_id' => $userId]);
            }

            return $note;
        });

        return ServiceResult::success(
            (new PatientNoteResource($note->load(['user:id,name', 'attachments', 'mentions.user:id,name'])))->resolve(),
            'Discussion note added successfully.',
            201
        );
    }

    public function financialPerformance(int $patientId): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $invoices = ClinicInvoice::query()
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->get();

        $visits = max(ClinicAppointment::query()
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->count(), 1);

        $totalBilled = (float) $invoices->sum('total');

        return ServiceResult::success([
            'cards' => [
                ['label' => 'Total Billed', 'value' => $totalBilled],
                ['label' => 'Total Paid', 'value' => (float) $invoices->sum('paid')],
                ['label' => 'Outstanding', 'value' => (float) $invoices->sum('remaining')],
                ['label' => 'Avg / Visit', 'value' => round($totalBilled / $visits, 2)],
            ],
        ], 'Financial performance fetched successfully');
    }

    public function revenueOverTime(int $patientId): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $start = now()->startOfYear();

        return ServiceResult::success(collect(CarbonPeriod::create($start, '1 month', now()->endOfMonth()))
            ->map(fn (Carbon $month) => [
                'month' => $month->format('M'),
                'revenue' => (float) ClinicInvoice::query()
                    ->where('clinic_id', $this->currentClinicId())
                    ->where('patient_id', $patient->id)
                    ->whereBetween('issued_at', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                    ->sum('paid'),
            ])->values()->all(), 'Revenue over time fetched successfully');
    }

    public function paymentMethodDistribution(int $patientId): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $rows = ClinicPayment::query()
            ->join('clinic_invoices', 'clinic_payments.clinic_invoice_id', '=', 'clinic_invoices.id')
            ->where('clinic_payments.clinic_id', $this->currentClinicId())
            ->where('clinic_invoices.patient_id', $patient->id)
            ->select('clinic_payments.method', DB::raw('sum(clinic_payments.amount) as total'))
            ->groupBy('clinic_payments.method')
            ->get();

        $total = max((float) $rows->sum('total'), 1);

        return ServiceResult::success($rows->map(fn ($row) => [
            'payment_method' => $row->method ?: 'Unknown',
            'percentage' => round(((float) $row->total / $total) * 100, 2),
            'total' => (float) $row->total,
        ])->values()->all(), 'Payment method distribution fetched successfully');
    }

    public function visitBehavioralTrends(int $patientId): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $appointments = ClinicAppointment::query()
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->orderBy('appointment_at')
            ->get();
        $total = max($appointments->count(), 1);
        $attended = $appointments->filter(fn ($row) => $this->appointmentStatus($row->status) === 'Attended')->count();
        $cancelled = $appointments->filter(fn ($row) => $this->appointmentStatus($row->status) === 'Cancelled')->count();

        return ServiceResult::success([
            'attendance_rate' => round(($attended / $total) * 100, 2),
            'cancellation_rate' => round(($cancelled / $total) * 100, 2),
            'attendance_breakdown' => [
                ['status' => 'Attended', 'count' => $attended],
                ['status' => 'Cancelled', 'count' => $cancelled],
                ['status' => 'No-Show', 'count' => $appointments->filter(fn ($row) => $this->appointmentStatus($row->status) === 'No Show')->count()],
            ],
            'history_log' => $appointments->map(fn ($row) => [
                'procedure' => $row->service_name ?: 'Visit',
                'date' => optional($row->appointment_at)?->toDateString(),
                'status' => $this->appointmentStatus($row->status),
            ])->values()->all(),
        ], 'Visit and behavioral trends fetched successfully');
    }

    public function radiologyReport(int $patientId, int $recordId): array
    {
        $record = $this->findRadiologyRecord($patientId, $recordId);
        if (! $record) {
            return ServiceResult::error('Radiology record not found.', null, null, 404);
        }

        return ServiceResult::success([
            'report' => $this->radiologyReportPayload($record),
        ], 'Radiology report fetched successfully');
    }

    public function generateRadiologyReport(int $patientId, array $data): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $selectedCaseIds = $data['case_selection'] ?? null;

        $query = PatientRadiology::query()
            ->with(['patient.user:id,name', 'clinic:id,name'])
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id);

        if (! empty($selectedCaseIds)) {
            $query->whereIn('id', $selectedCaseIds);
        }

        $records = $query->get();

        if (! empty($selectedCaseIds) && $records->count() !== count(array_unique($selectedCaseIds))) {
            return ServiceResult::error('One or more radiology records were not found.', null, ['case_selection' => ['Invalid case selection.']], 422);
        }

        if ($records->isEmpty()) {
            return ServiceResult::error('No radiology records found for this patient.', null, null, 404);
        }

        $reference = 'RAD-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5));
        $primary = $records->first();
        $caseSelection = ! empty($selectedCaseIds)
            ? array_values($selectedCaseIds)
            : $records->pluck('id')->values()->all();

        $primary->update([
            'report_reference_code' => $reference,
            'report_format' => $data['report_format'] ?? 'clinical_summary',
            'report_case_selection' => $caseSelection,
            'report_findings' => $data['findings'] ?? null,
            'report_diagnosis' => $data['diagnosis'] ?? null,
            'report_generated_by' => auth()->id(),
            'report_generated_at' => now(),
        ]);

        return ServiceResult::success([
            'report' => $this->radiologyReportPayload($primary->fresh(['patient.user', 'clinic', 'reportDoctor'])),
        ], 'Radiology report generated successfully', 201);
    }

    public function radiologyAppointmentSelect(int $patientId): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        return ServiceResult::success(ClinicAppointment::query()
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->latest('appointment_at')
            ->get(['id', 'appointment_at', 'service_name'])
            ->map(fn (ClinicAppointment $appointment) => [
                'id' => $appointment->id,
                'label' => trim(optional($appointment->appointment_at)?->format('d/m/Y') . ' - ' . ($appointment->service_name ?: 'Visit'), ' -'),
            ])->values()->all(), 'Patient appointments select fetched successfully');
    }

    public function radiologyTreatmentSelect(int $patientId): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        return ServiceResult::success(ClinicTreatment::query()
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->latest('treatment_date')
            ->get(['id', 'treatment_date', 'title'])
            ->map(fn (ClinicTreatment $treatment) => [
                'id' => $treatment->id,
                'label' => trim(optional($treatment->treatment_date)?->format('d/m/Y') . ' - ' . $treatment->title, ' -'),
            ])->values()->all(), 'Patient treatments select fetched successfully');
    }

    public function radiologyCompare(int $patientId, array $recordIds): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $records = PatientRadiology::query()
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->whereIn('id', $recordIds)
            ->get();

        if ($records->count() !== 2) {
            return ServiceResult::error('Two radiology records are required for comparison.', null, null, 422);
        }

        return ServiceResult::success([
            'records' => RadiologyResource::collection($records)->resolve(),
        ], 'Radiology records fetched for comparison');
    }

    public function treatmentsHistory(int $patientId): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $rows = ClinicTreatment::query()
            ->with(['doctor:id,name'])
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->latest('treatment_date')
            ->get();

        return ServiceResult::success([
            'active_planned_queue' => $rows
                ->filter(fn ($row) => in_array(strtolower((string) $row->status), ['active', 'planned', 'in_progress', 'in progress'], true))
                ->map(fn ($row) => [
                    'record' => $row->title,
                    'teeth' => $row->tooth_number,
                    'sessions' => $row->sessions_count,
                    'clinic_status' => $row->status,
                    'cost' => (float) $row->cost,
                    'action' => 'Open',
                ])->values()->all(),
            'clinical_history' => $rows->map(fn ($row) => [
                'date' => optional($row->treatment_date)?->toDateString(),
                'procedure' => $row->title,
                'teeth' => $row->tooth_number,
                'fee' => (float) $row->cost,
                'status' => $row->status,
            ])->values()->all(),
        ], 'Clinical services history fetched successfully');
    }

    public function storeTreatment(int $patientId, array $data): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $doctorId = $data['dentist'] ?? $data['doctor_id'] ?? null;
        if ($doctorId && ! User::query()->where('clinic_id', $this->currentClinicId())->role('doctor')->whereKey($doctorId)->exists()) {
            return ServiceResult::error('Dentist not found for this clinic.', null, ['dentist' => ['Dentist not found for this clinic.']], 422);
        }

        $treatment = ClinicTreatment::query()->create([
            'clinic_id' => $this->currentClinicId(),
            'patient_id' => $patient->id,
            'doctor_user_id' => $doctorId,
            'title' => $data['service_name'] ?? $data['title'],
            'description' => $data['description'] ?? null,
            'tooth_number' => $data['tooth_number'] ?? null,
            'sessions_count' => $data['sessions_count'] ?? 1,
            'treatment_date' => $data['date'] ?? $data['treatment_date'] ?? now()->toDateString(),
            'cost' => $data['cost'] ?? 0,
            'status' => $data['status'],
        ]);

        return ServiceResult::success((new TreatmentResource($treatment->load('doctor:id,name')))->resolve(), 'Treatment added successfully', 201);
    }

    public function clinicServicesList(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(Service::query()
            ->where('is_active', true)
            ->where(function ($query) use ($clinicId) {
                $query->whereNull('created_by_clinic_id')->orWhere('created_by_clinic_id', $clinicId);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'base_price'])
            ->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'service_name' => $service->name,
                'base_price' => (float) $service->base_price,
            ])->all(), 'Clinic services fetched successfully');
    }

    public function clinicDentistsList(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(User::query()
            ->where('clinic_id', $clinicId)
            ->role('doctor')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'dentist' => $user->name,
                'email' => $user->email,
            ])->all(), 'Clinic dentists fetched successfully');
    }

    public function invoices(int $patientId): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $rows = ClinicInvoice::query()
            ->with(['patient.user:id,name', 'doctor:id,name'])
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->latest('issued_at')
            ->get();

        return ServiceResult::success(ClinicInvoiceResource::collection($rows)->resolve(), 'Patient invoices fetched successfully');
    }

    public function addPayment(int $patientId, int $invoiceId, array $data): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $invoice = ClinicInvoice::query()
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->find($invoiceId);

        if (! $invoice) {
            return ServiceResult::error('Invoice not found.', null, null, 404);
        }

        // Compute remaining live to avoid stale stored value causing false 422s
        $currentRemaining = round((float) $invoice->total - (float) $invoice->paid, 2);

        if ((float) $data['amount_to_pay'] > $currentRemaining + 0.01) {
            return ServiceResult::error('Payment amount exceeds remaining balance.', null, [
                'amount_to_pay' => ["Payment amount exceeds remaining balance ({$currentRemaining})."],
            ], 422);
        }

        DB::transaction(function () use ($data, $invoice) {
            ClinicPayment::query()->create([
                'clinic_invoice_id' => $invoice->id,
                'clinic_id' => $invoice->clinic_id,
                'recorded_by' => auth()->id(),
                'amount' => $data['amount_to_pay'],
                'method' => $data['payment_method'],
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $paid = round((float) $invoice->paid + (float) $data['amount_to_pay'], 2);
            $remaining = max(round((float) $invoice->total - $paid, 2), 0);
            $invoice->update([
                'paid' => $paid,
                'remaining' => $remaining,
                'status' => $remaining <= 0 ? 'paid' : 'partial',
            ]);
        });

        return ServiceResult::success((new ClinicInvoiceResource($invoice->fresh(['patient.user:id,name', 'doctor:id,name', 'payments'])))->resolve(), 'Payment recorded successfully', 201);
    }

    public function trackLabCase(int $patientId, int $caseId): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $case = CaseModel::query()
            ->with(['patient.user:id,name', 'activityLogs.actor:id,name'])
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->find($caseId);

        if (! $case) {
            return ServiceResult::error('Lab case not found.', null, null, 404);
        }

        $steps = ['Pending', 'Accepted', 'In Progress', 'Completed', 'Delivered'];
        $currentIndex = array_search($case->status, $steps, true);

        return ServiceResult::success([
            'title' => trim($case->case_type . ' - ' . ($case->patient?->user?->name ?? 'Patient')),
            'live_progress' => collect($steps)->map(fn (string $step, int $index) => [
                'step' => $step,
                'status' => $currentIndex === false ? 'pending' : ($index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'current' : 'pending')),
            ])->all(),
            'activity_log' => $case->activityLogs
                ->sortBy('created_at')
                ->map(fn (CaseActivityLog $log) => [
                    'actor' => $log->actor?->name ?: $log->actor_name,
                    'action' => $log->action,
                    'old_status' => $log->old_status,
                    'new_status' => $log->new_status,
                    'notes' => $log->notes,
                    'created_at' => optional($log->created_at)?->toISOString(),
                ])->values()->all(),
        ], 'Lab case tracking fetched successfully');
    }

   public function analytics(int $patientId): array
{
    $patient = $this->findClinicPatient($patientId);
    if (! $patient) {
        return ServiceResult::error('Patient not found.', null, null, 404);
    }

    $clinicId = $this->currentClinicId();

    return ServiceResult::success([
        'outstanding_invoices_count' => ClinicInvoice::query()
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $patient->id)
            ->where('remaining', '>', 0)
            ->count(),
        'completed_treatments_count' => ClinicTreatment::query()
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $patient->id)
            ->where('status', 'completed')
            ->count(),
        'upcoming_appointments' => ClinicAppointment::query()
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $patient->id)
            ->where('appointment_at', '>=', now())
            ->count(),
        'patient_behavior_classification' => $this->patientBehaviorClassification($patient->id, $clinicId),
        'insurance_coverage_utilization' => $this->insuranceCoverageUtilization($patient->id, $clinicId),
    ], 'Patient analytics fetched successfully');
}

private function patientBehaviorClassification(int $patientId, int $clinicId): array
{
    $appointments = ClinicAppointment::query()
        ->where('clinic_id', $clinicId)
        ->where('patient_id', $patientId)
        ->get();

    $total = $appointments->count();

    if ($total < 3) {
        return [
            'label' => 'New Patient',
            'description' => 'Not enough visit history yet to classify this patient.',
            'confidence' => null,
        ];
    }

    $attended = $appointments->filter(fn ($row) => $this->appointmentStatus($row->status) === 'Attended')->count();
    $noShow = $appointments->filter(fn ($row) => $this->appointmentStatus($row->status) === 'No Show')->count();
    $cancelled = $appointments->filter(fn ($row) => $this->appointmentStatus($row->status) === 'Cancelled')->count();

    $attendanceRate = round(($attended / $total) * 100, 2);
    $noShowRate = round(($noShow / $total) * 100, 2);

    [$label, $description] = match (true) {
        $noShowRate >= 25 => ['At Risk', 'Frequent no-shows detected. May need reminder calls or deposit policy.'],
        $attendanceRate >= 85 => ['Reliable Patient', 'Consistently attends scheduled appointments.'],
        $cancelled > $attended => ['Frequently Reschedules', 'Tends to cancel or reschedule visits often.'],
        default => ['Moderate Engagement', 'Attendance pattern is average, no major red flags.'],
    };

    return [
        'label' => $label,
        'description' => $description,
        'attendance_rate' => $attendanceRate,
        'no_show_rate' => $noShowRate,
        'total_visits' => $total,
    ];
}

private function insuranceCoverageUtilization(int $patientId, int $clinicId): array
{
    $approvals = InsuranceApproval::query()
        ->with('services')
        ->where('clinic_id', $clinicId)
        ->where('patient_id', $patientId)
        ->get();

    $activeApprovals = $approvals->filter(fn ($a) => $a->status === 'Approved'
        && (! $a->expiry_date || $a->expiry_date->isFuture()));

    $usedTotal = (float) $approvals->sum('used_amount');
    $limitTotal = (float) $approvals->sum('approved_amount');
    $utilizationPercent = $limitTotal > 0 ? round(($usedTotal / $limitTotal) * 100, 1) : 0;

    $decided = $approvals->whereIn('status', ['Approved', 'Rejected']);
    $successRate = $decided->count() > 0
        ? round(($approvals->where('status', 'Approved')->count() / $decided->count()) * 100)
        : 0;

    $mostApprovedServices = $approvals->where('status', 'Approved')
        ->flatMap(fn ($a) => $a->services)
        ->groupBy('service_name')
        ->map(fn ($group, $name) => ['service_name' => $name, 'count' => $group->count()])
        ->sortByDesc('count')
        ->take(3)
        ->values()
        ->all();

    return [
        'coverage_utilization_percent' => $utilizationPercent,
        'used_amount' => $usedTotal,
        'limit_amount' => $limitTotal,
        'active_approvals' => $activeApprovals->count(),
        'avg_success_rate' => $successRate,
        'most_approved_services' => $mostApprovedServices,
    ];
}

    private function generatedPatientEmail(int $clinicId): string
    {
        return 'patient-' . $clinicId . '-' . Str::lower(Str::random(8)) . '@dentaplus.local';
    }

    private function generatePatientNumber(): string
    {
        do {
            $number = 'PID-' . str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT);
        } while (Patient::query()->where('patient_number', $number)->exists());

        return $number;
    }

    private function generateCaseNumber(): string
    {
        do {
            $number = 'CASE-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (CaseModel::query()->where('case_number', $number)->exists());

        return $number;
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }

    private function resolveInsuranceCompanyId(int $clinicId, ?int $companyId): ?InsuranceCompany
    {
        if (! $companyId) {
            return null;
        }

        return InsuranceCompany::query()
            ->where('clinic_id', $clinicId)
            ->find($companyId);
    }
    public function documents(int $patientId): array
{
    $patient = $this->findClinicPatient($patientId);
    if (! $patient) {
        return ServiceResult::error('Patient not found.', null, null, 404);
    }

    $rows = PatientDocument::query()
        ->where('clinic_id', $this->currentClinicId())
        ->where('patient_id', $patient->id)
        ->latest('id')
        ->get();

    return ServiceResult::success(PatientDocumentResource::collection($rows)->resolve(), 'Documents fetched successfully');
}

public function uploadDocument(int $patientId, array $data, $file): array
{
    $patient = $this->findClinicPatient($patientId);
    if (! $patient) {
        return ServiceResult::error('Patient not found.', null, null, 404);
    }

    $path = $file->store('clinic/patient-documents', 'public');

    $document = PatientDocument::query()->create([
        'patient_id' => $patient->id,
        'clinic_id' => $this->currentClinicId(),
        'uploaded_by' => auth()->id(),
        'document_type' => $data['document_type'] ?? 'general',
        'title' => $data['title'],
        'file_path' => $path,
        'original_name' => $file->getClientOriginalName(),
        'mime_type' => $file->getClientMimeType(),
        'size' => $file->getSize(),
        'notes' => $data['notes'] ?? null,
    ]);

    return ServiceResult::success((new PatientDocumentResource($document))->resolve(), 'Document uploaded successfully', 201);
}

public function approvals(int $patientId, array $filters = []): array
{
    $patient = $this->findClinicPatient($patientId);
    if (! $patient) {
        return ServiceResult::error('Patient not found.', null, null, 404);
    }

    $rows = InsuranceApproval::query()
        ->with(['company:id,name,code', 'services'])
        ->where('clinic_id', $this->currentClinicId())
        ->where('patient_id', $patient->id)
        ->when($filters['status'] ?? null, function ($query, string $status) {
            if (strtolower($status) !== 'all') {
                $query->where('status', $this->approvalStatus($status));
            }
        })
        ->when($filters['search'] ?? null, function ($query, string $search) {
            $query->where(function ($nested) use ($search) {
                $nested->where('code', 'like', "%{$search}%")
                    ->orWhere('approval_number', 'like', "%{$search}%")
                    ->orWhere('ref_id', 'like', "%{$search}%")
                    ->orWhereHas('company', fn ($company) => $company->where('name', 'like', "%{$search}%"));
            });
        })
        ->latest('date')
        ->get();

    return ServiceResult::success([
        'patient_id' => $patient->id,
        'is_insurance_case' => (bool) ($patient->insurance_company_id || $patient->insurance_provider || $rows->isNotEmpty()),
        'items' => $rows->map(fn (InsuranceApproval $approval) => $this->mapApproval($approval))->values()->all(),
    ], 'Insurance approvals fetched successfully');
}

public function createApproval(int $patientId, array $data): array
{
    $patient = $this->findClinicPatient($patientId);
    if (! $patient) {
        return ServiceResult::error('Patient not found.', null, null, 404);
    }

    $clinicId = $this->currentClinicId();
    $company = InsuranceCompany::query()
        ->where('clinic_id', $clinicId)
        ->find($data['insurance_company_id']);

    if (! $company) {
        return ServiceResult::error('Insurance company not found.', null, ['insurance_company_id' => ['Insurance company not found.']], 422);
    }

    $approval = DB::transaction(function () use ($clinicId, $company, $data, $patient) {
        $services = collect($data['services'] ?? []);
        $approval = InsuranceApproval::query()->create([
            'clinic_id' => $clinicId,
            'patient_id' => $patient->id,
            'insurance_company_id' => $company->id,
            'code' => $data['code'] ?? $data['approval_number'] ?? $data['ref_id'] ?? ('APR-' . now()->format('YmdHis')),
            'approval_number' => $data['approval_number'] ?? $data['ref_id'] ?? null,
            'ref_id' => $data['ref_id'] ?? $data['approval_number'] ?? null,
            'status' => $this->approvalStatus($data['status'] ?? 'Approved'),
            'date' => $data['date'],
            'expiry_date' => $data['expiry_date'] ?? null,
            'coverage_percent' => $data['coverage_percent'] ?? $data['coverage'] ?? 0,
            'approved_amount' => $data['approved_amount'] ?? $services->sum(fn ($item) => (float) ($item['amount'] ?? 0)),
            'used_amount' => $data['used_amount'] ?? 0,
            'documents' => $this->normalizeApprovalDocuments($data['documents'] ?? []),
            'notes' => $data['notes'] ?? null,
        ]);

        $services->each(fn (array $item) => $approval->services()->create([
            'service_name' => $item['service_name'] ?? $item['name'] ?? 'Service',
            'service_code' => $item['service_code'] ?? $item['code'] ?? null,
            'amount' => $item['amount'] ?? $item['value'] ?? 0,
            'co_pay' => $item['co_pay'] ?? $item['copay'] ?? 0,
            'tooth_number' => $item['tooth_number'] ?? $item['tooth'] ?? null,
        ]));

        return $approval->load(['company:id,name,code', 'services']);
    });

    return ServiceResult::success($this->mapApproval($approval), 'Insurance approval created successfully', 201);
}

public function approvalPdfPayloadForClinic(int $clinicId, int $approvalId): array
{
    $approval = InsuranceApproval::query()
        ->with(['clinic:id,name', 'patient.user:id,name', 'company:id,name,code', 'services'])
        ->where('clinic_id', $clinicId)
        ->find($approvalId);

    if (! $approval) {
        return ServiceResult::error('Insurance approval not found.', null, null, 404);
    }

    return ServiceResult::success([
        'filename' => 'insurance-approval-' . $approval->id . '.pdf',
        'content_type' => 'application/pdf',
        'content' => Pdf::loadView('pdf.insurance-approval', ['approval' => $approval])->setPaper('a4')->output(),
    ], 'Insurance approval PDF generated successfully');
}
public function addApprovalService(int $patientId, int $approvalId, array $data): array
{
    $patient = $this->findClinicPatient($patientId);
    if (! $patient) {
        return ServiceResult::error('Patient not found.', null, null, 404);
    }

    $approval = InsuranceApproval::query()
        ->where('clinic_id', $this->currentClinicId())
        ->where('patient_id', $patient->id)
        ->find($approvalId);

    if (! $approval) {
        return ServiceResult::error('Insurance approval not found.', null, null, 404);
    }

    $approval->services()->create([
        'service_name' => $data['service_name'],
        'service_code' => $data['service_code'] ?? null,
        'amount' => $data['amount'],          // ← بدل price
        'co_pay' => $data['co_pay'] ?? 0,
        'tooth_number' => $data['tooth_number'] ?? null,
    ]);

    $approval->update([
        'approved_amount' => $approval->services()->sum('amount'),
    ]);

    return ServiceResult::success(
        $this->mapApproval($approval->fresh(['company:id,name,code', 'services'])),
        'Service added to approval successfully',
        201
    );
}



private function mapApproval(InsuranceApproval $approval): array
{
    return [
        'id' => $approval->id,
        'code' => $approval->code,
        'approval_number' => $approval->approval_number,
        'ref_id' => $approval->ref_id,
        'insurance_company_id' => $approval->insurance_company_id,
        'insurance_company' => $approval->company?->name,
        'company' => [
            'id' => $approval->company?->id,
            'name' => $approval->company?->name,
            'code' => $approval->company?->code,
        ],
        'status' => $approval->status,
        'date' => optional($approval->date)?->toDateString(),
        'expiry_date' => optional($approval->expiry_date)?->toDateString(),
        'coverage_percent' => (float) $approval->coverage_percent,
        'approved_amount' => (float) $approval->approved_amount,
        'used_amount' => (float) $approval->used_amount,
        'services' => $approval->services->map(fn ($service) => [
            'service_name' => $service->service_name,
            'service_code' => $service->service_code,
            'amount' => (float) $service->amount,
            'co_pay' => (float) $service->co_pay,
            'tooth_number' => $service->tooth_number,
        ])->values()->all(),
        'documents' => collect($approval->documents ?? [])->map(fn ($document) => [
            'type' => $document['type'] ?? 'Document',
            'name' => $document['name'] ?? basename((string) ($document['path'] ?? $document['url'] ?? 'document')),
            'url' => $document['url'] ?? $this->documentUrl($document['path'] ?? null),
        ])->values()->all(),
        'download_full_approval_pdf_url' => $this->approvalDownloadUrl($approval),
        'downloadUrl' => $this->approvalDownloadUrl($approval),
        'created_at' => optional($approval->created_at)?->toISOString(),
    ];
}

private function approvalDownloadUrl(InsuranceApproval $approval): string
{
    return URL::temporarySignedRoute(self::APPROVAL_DOWNLOAD_ROUTE, now()->addMinutes(self::APPROVAL_DOWNLOAD_TTL_MINUTES), [
        'approval' => $approval->id,
        'clinic_id' => $approval->clinic_id,
    ]);
}

private function normalizeApprovalDocuments(array $documents): array
{
    return collect($documents)->map(fn ($document) => is_array($document) ? $document : [
        'type' => 'Document',
        'url' => (string) $document,
    ])->values()->all();
}

private function documentUrl(?string $path): ?string
{
    if (! $path) {
        return null;
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    return asset(Storage::url($path));
}

private function approvalStatus(string $status): string
{
    return match (strtolower($status)) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default => 'Pending',
    };
}
}
