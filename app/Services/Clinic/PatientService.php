<?php

namespace App\Services\Clinic;

use App\Http\Resources\Clinic\DentalChartResource;
use App\Http\Resources\Clinic\PatientLabCaseResource;
use App\Http\Resources\Clinic\PatientNoteResource;
use App\Http\Resources\Clinic\PatientResource;
use App\Http\Resources\Clinic\RadiologyResource;
use App\Models\CaseModel;
use App\Models\ClinicAppointment;
use App\Models\ClinicInvoice;
use App\Models\ClinicTreatment;
use App\Models\DentalLab;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PatientNote;
use App\Models\InsuranceCompany;
use App\Models\PatientRadiology;
use App\Models\PatientTooth;
use App\Models\User;
use App\Support\ServiceResult;
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
            'items' => PatientResource::collection($patients->items())->resolve(),
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

        $insuranceCompany = $this->resolveInsuranceCompanyId($clinicId, $data['insurance_company_id'] ?? null);
        if (! empty($data['insurance_company_id']) && ! $insuranceCompany) {
            return ServiceResult::error('Insurance company not found.', null, ['insurance_company_id' => ['Insurance company not found for this clinic.']], 422);
        }

        $patient = DB::transaction(function () use ($clinicId, $data, $insuranceCompany) {
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

            return Patient::query()->create([
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
                'insurance_provider' => $data['insurance_provider'] ?? $insuranceCompany?->name,
                'insurance_company_id' => $insuranceCompany?->id,
                'insurance_number' => $data['insurance_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
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

        $insuranceCompany = null;
        if (array_key_exists('insurance_company_id', $data)) {
            $insuranceCompany = $this->resolveInsuranceCompanyId($clinicId, $data['insurance_company_id']);
            if ($data['insurance_company_id'] !== null && ! $insuranceCompany) {
                return ServiceResult::error('Insurance company not found.', null, ['insurance_company_id' => ['Insurance company not found for this clinic.']], 422);
            }
        }

        DB::transaction(function () use ($data, $insuranceCompany, $patient) {
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
                'notes',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $patientData[$field] = $data[$field];
                }
            }

            if (array_key_exists('phone', $data)) {
                $patientData['phone'] = $data['phone'];
            }

            if (array_key_exists('insurance_company_id', $data)) {
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

        return ServiceResult::success(DentalChartResource::collection($rows)->resolve(), 'Dental chart fetched successfully');
    }

    public function recordDentalChart(int $patientId, array $data): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $entry = PatientTooth::query()->create([
            'patient_id' => $patient->id,
            'clinic_id' => $this->currentClinicId(),
            'tooth_number' => $data['tooth_number'],
            'status' => $data['status'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return ServiceResult::success((new DentalChartResource($entry))->resolve(), 'Dental chart entry recorded successfully', 201);
    }

    public function radiology(int $patientId): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $rows = PatientRadiology::query()
            ->where('clinic_id', $this->currentClinicId())
            ->where('patient_id', $patient->id)
            ->latest('id')
            ->get();

        return ServiceResult::success(RadiologyResource::collection($rows)->resolve(), 'Radiology archive fetched successfully');
    }

    public function uploadRadiology(int $patientId, array $data, $file = null): array
    {
        $patient = $this->findClinicPatient($patientId);
        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, null, 404);
        }

        $path = $file ? $file->store('clinic/radiology', 'public') : null;

        $entry = PatientRadiology::query()->create([
            'patient_id' => $patient->id,
            'clinic_id' => $this->currentClinicId(),
            'modality' => $data['modality'],
            'notes' => $data['notes'] ?? null,
            'file_path' => $path,
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

        return ServiceResult::success(PatientLabCaseResource::collection($cases)->resolve(), 'Patient lab cases fetched successfully');
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
                'note' => $data['note'],
            ]);

            foreach (($data['attachments'] ?? []) as $attachment) {
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
        ], 'Patient analytics fetched successfully');
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
}
