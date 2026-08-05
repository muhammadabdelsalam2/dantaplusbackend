<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Models\Clinic\Message;
use App\Models\Clinic\MessageLog;
use App\Models\Clinic\MessageTemplate;
use App\Jobs\Clinic\SendPatientWhatsAppMessageJob;
use App\Selects\Clinic\MessagingSelects;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function patients(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'patient_ids' => ['nullable', 'array'],
            'patient_ids.*' => ['integer', 'exists:patients,id'],
        ]);

        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return response()->json(['success' => false, 'message' => 'Clinic account is not linked to a clinic.'], 403);
        }

        $filters = [
            'clinic_id' => $clinicId,
            'appointment_date' => $data['date'] ?? null,
            'doctor_user_id' => $data['doctor_id'] ?? null,
            'patient_ids' => $data['patient_ids'] ?? (! empty($data['patient_id']) ? [$data['patient_id']] : null),
        ];

        $items = $this->patientRows($filters);
        $patients = $items->map(fn (array $row) => [
            'id' => $row['patient_id'],
            'name' => $row['patient_name'],
            'phone' => $row['patient_phone'],
            'appointment_time' => $row['appointment_time'],
            'doctor' => $row['doctor_name'],
        ])->values();

        return response()->json([
            'success' => true,
            'message' => 'Messaging patients fetched successfully.',
            'patients' => $patients,
            'data' => [
                'date' => $data['date'] ?? null,
                'doctor_id' => $data['doctor_id'] ?? null,
                'count' => $items->count(),
                'items' => $items,
            ],
        ]);
    }

    public function sendDirect(Request $request): JsonResponse
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return response()->json(['success' => false, 'message' => 'Clinic account is not linked to a clinic.'], 403);
        }

        $request->merge([
            'clinic_id' => $clinicId,
            'appointment_date' => $request->input('date', $request->input('appointment_date')),
            'doctor_user_id' => $request->input('doctor_id', $request->input('doctor_user_id')),
            'patient_ids' => $request->input('patient_ids', $request->filled('patient_id') ? [$request->input('patient_id')] : null),
            'sent_by' => auth()->id(),
            'message_type' => $request->input('message_type', 'custom'),
            'message_body' => $request->input('message', $request->input('message_body')),
        ]);

        return $this->send($request);
    }

    public function historyDirect(Request $request): JsonResponse
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return response()->json(['success' => false, 'message' => 'Clinic account is not linked to a clinic.'], 403);
        }

        $request->merge([
            'clinic_id' => $clinicId,
            'appointment_date' => $request->input('date', $request->input('appointment_date')),
            'doctor_user_id' => $request->input('doctor_id', $request->input('doctor_user_id')),
        ]);

        return $this->history($request);
    }

    public function selects(MessagingSelects $selects): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Messaging selects fetched successfully.',
            'data' => $selects->toArray(auth()->user()?->clinic_id),
        ]);
    }

    public function filterPatients(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinic_id'        => ['required', 'integer', 'exists:clinics,id'],
            'appointment_date' => ['nullable', 'date'],
            'doctor_user_id'   => ['nullable', 'integer', 'exists:users,id'],
            'patient_ids'      => ['nullable', 'array'],
            'patient_ids.*'    => ['integer', 'exists:patients,id'],
            'status'           => ['nullable', 'string', 'in:pending,confirmed,cancelled,completed,no_show'],
        ]);

        $appointments = $this->patientRows($data);

        return response()->json([
            'success' => true,
            'message' => 'Patients fetched successfully.',
            'data'    => [
                'count' => $appointments->count(),
                'items' => $appointments,
            ],
        ]);
    }

    private function patientRows(array $data)
    {
        return $this->appointmentQuery($data)
            ->select([
                'clinic_appointments.id as appointment_id',
                'clinic_appointments.patient_id',
                'clinic_appointments.doctor_user_id',
                'clinic_appointments.patient_name',
                'clinic_appointments.patient_phone',
                'clinic_appointments.service_name',
                'clinic_appointments.appointment_at',
                'clinic_appointments.status',
                'users.name as doctor_name',
                'patient_users.name as patient_user_name',
                'patient_users.phone as patient_user_phone',
            ])
            ->leftJoin('users', 'users.id', '=', 'clinic_appointments.doctor_user_id')
            ->leftJoin('patients', 'patients.id', '=', 'clinic_appointments.patient_id')
            ->leftJoin('users as patient_users', 'patient_users.id', '=', 'patients.user_id')
            ->get()
            ->map(function ($row) {
                return [
                    'appointment_id' => $row->appointment_id,
                    'id' => $row->patient_id,
                    'patient_id'     => $row->patient_id,
                    'name'           => $row->patient_user_name ?: $row->patient_name,
                    'patient_name'   => $row->patient_user_name ?: $row->patient_name,
                    'phone'          => $row->patient_user_phone ?: $row->patient_phone,
                    'patient_phone'  => $row->patient_user_phone ?: $row->patient_phone,
                    'doctor_user_id' => $row->doctor_user_id,
                    'doctor'         => $row->doctor_name,
                    'doctor_name'    => $row->doctor_name,
                    'service_name'   => $row->service_name,
                    'appointment_time' => $row->appointment_at ? date('H:i', strtotime($row->appointment_at)) : null,
                    'appointment_at' => $row->appointment_at,
                    'status'         => $row->status,
                ];
            });
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinic_id'      => ['required', 'integer', 'exists:clinics,id'],
            'sent_by'        => ['nullable', 'integer', 'exists:users,id'],
            'appointment_date' => ['nullable', 'date'],
            'doctor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'patient_ids'    => ['nullable', 'array'],
            'patient_ids.*'  => ['integer', 'exists:patients,id'],
            'template_id'    => ['nullable', 'integer', 'exists:message_templates,id'],
            'channel'        => ['required', 'in:sms,whatsapp'],
            'message_type'   => ['required', 'string', 'in:confirmation,reminder,follow_up,custom'],
            'message_body'   => ['nullable', 'string'],
        ]);

        if (empty($data['message_body']) && empty($data['template_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'message_body or template_id is required.',
                'errors'  => [
                    'message_body' => ['message_body or template_id is required.'],
                ],
            ], 422);
        }

        $template = null;
        if (! empty($data['template_id'])) {
            $template = MessageTemplate::query()
                ->where('clinic_id', $data['clinic_id'])
                ->find($data['template_id']);

            if (! $template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found for this clinic.',
                    'errors'  => [
                        'template_id' => ['Template not found for this clinic.'],
                    ],
                ], 422);
            }
        }

        $appointments = $this->appointmentQuery($data)
            ->leftJoin('patients', 'patients.id', '=', 'clinic_appointments.patient_id')
            ->leftJoin('users as patient_users', 'patient_users.id', '=', 'patients.user_id')
            ->leftJoin('users as doctor_users', 'doctor_users.id', '=', 'clinic_appointments.doctor_user_id')
            ->select([
                'clinic_appointments.id',
                'clinic_appointments.patient_id',
                'clinic_appointments.doctor_user_id',
                'clinic_appointments.patient_name',
                'clinic_appointments.patient_phone',
                'clinic_appointments.service_name',
                'clinic_appointments.appointment_at',
                'patient_users.name as patient_user_name',
                'patient_users.phone as patient_user_phone',
                'doctor_users.name as doctor_name',
            ])
            ->get();

        if ($appointments->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No patients matched the selected filters.',
                'errors'  => [
                    'patient_ids' => ['No patients matched the selected filters.'],
                ],
            ], 422);
        }

        // Skip patients with no phone number
        $appointments = $appointments->filter(function ($appointment) {
            $phone = $appointment->patient_user_phone ?: $appointment->patient_phone;
            return ! empty($phone);
        });

        if ($appointments->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'None of the matched patients have a phone number.',
                'errors'  => [
                    'patient_ids' => ['None of the matched patients have a phone number.'],
                ],
            ], 422);
        }

        $batchUuid   = (string) Str::uuid();
        $messageBody = $data['message_body'] ?? $template->body;

        $message = Message::query()->create([
            'clinic_id'    => $data['clinic_id'],
            'created_by'   => $data['sent_by'] ?? null,
            'template_id'  => $template->id ?? null,
            'channel'      => $data['channel'],
            'message_type' => $data['message_type'],
            'message'      => $messageBody,
            'batch_uuid'   => $batchUuid,
            'sent_at'      => now(),
        ]);

        $rows         = [];
        $skippedCount = 0;

        foreach ($appointments as $appointment) {
            $resolvedPhone = $appointment->patient_user_phone ?: $appointment->patient_phone;
            $resolvedName  = $appointment->patient_user_name ?: $appointment->patient_name;

            $rows[] = [
                'message_id'   => $message->id,
                'clinic_id'    => $data['clinic_id'],
                'patient_id'   => $appointment->patient_id,
                'appointment_id' => $appointment->id,
                'doctor_user_id' => $appointment->doctor_user_id,
                'template_id'  => $template->id ?? null,
                'sent_by'      => $data['sent_by'] ?? null,
                'batch_uuid'   => $batchUuid,
                'channel'      => $data['channel'],
                'message_type' => $data['message_type'],
                'status'       => 'pending',
                'message_body' => $this->renderMessage(
                    $messageBody,
                    $resolvedName,
                    $appointment->doctor_name,
                    $appointment->appointment_at,
                    $appointment->service_name
                ),
                'phone'        => $resolvedPhone,
                'sent_at'      => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        MessageLog::query()->insert($rows);

        MessageLog::query()
            ->where('batch_uuid', $batchUuid)
            ->orderBy('id')
            ->get(['id'])
            ->values()
            ->each(function (MessageLog $log, int $index) {
                SendPatientWhatsAppMessageJob::dispatch($log->id)
                    ->delay(now()->addMinutes(5 * $index));
            });

        return response()->json([
            'success' => true,
            'message' => 'Messages queued successfully.',
            'data'    => [
                'message_id'    => $message->id,
                'batch_uuid'    => $batchUuid,
                'queued_count'  => count($rows),
                'skipped_count' => $skippedCount,
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinic_id'        => ['required', 'integer', 'exists:clinics,id'],
            'appointment_date' => ['nullable', 'date'],
            'doctor_user_id'   => ['nullable', 'integer', 'exists:users,id'],
            'patient_id'       => ['nullable', 'integer', 'exists:patients,id'],
            'channel'          => ['nullable', 'in:sms,whatsapp'],
            'message_type'     => ['nullable', 'string', 'in:confirmation,reminder,follow_up,custom'],
            'per_page'         => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $data['per_page'] ?? 25;

        $query = DB::table('messages')
            ->leftJoin('message_logs', 'message_logs.message_id', '=', 'messages.id')
            ->leftJoin('clinic_appointments', 'clinic_appointments.id', '=', 'message_logs.appointment_id')
            ->leftJoin('patients', 'patients.id', '=', 'message_logs.patient_id')
            ->leftJoin('users as patient_users', 'patient_users.id', '=', 'patients.user_id')
            ->leftJoin('users as doctor_users', 'doctor_users.id', '=', 'message_logs.doctor_user_id')
            ->where('messages.clinic_id', $data['clinic_id'])
            ->when(! empty($data['doctor_user_id']), fn ($q) => $q->where('message_logs.doctor_user_id', $data['doctor_user_id']))
            ->when(! empty($data['patient_id']), fn ($q) => $q->where('message_logs.patient_id', $data['patient_id']))
            ->when(! empty($data['channel']), fn ($q) => $q->where('messages.channel', $data['channel']))
            ->when(! empty($data['message_type']), fn ($q) => $q->where('messages.message_type', $data['message_type']))
            ->when(! empty($data['appointment_date']), fn ($q) => $q->whereDate('clinic_appointments.appointment_at', $data['appointment_date']))
            ->orderByDesc('messages.id')
            ->select([
                'messages.id as message_id',
                'messages.message',
                'messages.message_type',
                'messages.channel',
                'messages.created_by',
                'messages.batch_uuid',
                'messages.sent_at as message_sent_at',
                'message_logs.id as log_id',
                'message_logs.status',
                'message_logs.message_body',
                'message_logs.phone',
                'message_logs.sent_at',
                'message_logs.patient_id',
                'message_logs.appointment_id',
                'message_logs.doctor_user_id',
                'patient_users.name as patient_name',
                'doctor_users.name as doctor_name',
                'clinic_appointments.appointment_at',
            ]);

        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Message history fetched successfully.',
            'data'    => $paginated->items(),
            'meta'    => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    public function templates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'integer', 'exists:clinics,id'],
            'channel'   => ['nullable', 'in:sms,whatsapp'],
        ]);

        $templates = DB::table('message_templates')
            ->where('clinic_id', $data['clinic_id'])
            ->where('is_active', true)
            ->when(! empty($data['channel']), fn ($q) => $q->where('channel', $data['channel']))
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Templates fetched successfully.',
            'data'    => $templates,
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinic_id'    => ['required', 'integer', 'exists:clinics,id'],
            'created_by'   => ['nullable', 'integer', 'exists:users,id'],
            'name'         => ['required', 'string', 'max:255'],
            'message_type' => ['required', 'string', 'in:confirmation,reminder,follow_up,custom'],
            'channel'      => ['required', 'in:sms,whatsapp'],
            'body'         => ['required', 'string'],
        ]);

        $template = MessageTemplate::query()->create([
            'clinic_id'    => $data['clinic_id'],
            'created_by'   => $data['created_by'] ?? null,
            'name'         => $data['name'],
            'message_type' => $data['message_type'],
            'channel'      => $data['channel'],
            'body'         => $data['body'],
            'placeholders' => ['[Patient Name]', '[Doctor Name]', '[Date]', '[Time]', '[Service Name]'],
            'is_active'    => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully.',
            'data'    => [
                'id' => $template->id,
            ],
        ], 201);
    }

    private function appointmentQuery(array $filters)
    {
        return DB::table('clinic_appointments')
            ->where('clinic_appointments.clinic_id', $filters['clinic_id'])
            ->whereNotNull('clinic_appointments.patient_id')
            ->when(! empty($filters['appointment_date']), function ($query) use ($filters) {
                $query->whereDate('clinic_appointments.appointment_at', $filters['appointment_date']);
            })
            ->when(! empty($filters['doctor_user_id']), function ($query) use ($filters) {
                $query->where('clinic_appointments.doctor_user_id', $filters['doctor_user_id']);
            })
            ->when(! empty($filters['patient_ids']), function ($query) use ($filters) {
                $query->whereIn('clinic_appointments.patient_id', $filters['patient_ids']);
            })
            ->when(! empty($filters['status']), function ($query) use ($filters) {
                $query->where('clinic_appointments.status', $filters['status']);
            });
    }

    private function renderMessage(
        string $body,
        ?string $patientName,
        ?string $doctorName,
        ?string $appointmentAt,
        ?string $serviceName
    ): string {
        $date = $appointmentAt ? date('Y-m-d', strtotime($appointmentAt)) : '--';
        $time = $appointmentAt ? date('H:i', strtotime($appointmentAt)) : '--:--';

        return str_replace(
            ['[Patient Name]', '[Doctor Name]', '[Date]', '[Time]', '[Service Name]'],
            [
                $patientName ?: 'Patient',
                $doctorName  ?: 'Doctor',
                $date,
                $time,
                $serviceName ?: 'Service',
            ],
            $body
        );
    }
}
