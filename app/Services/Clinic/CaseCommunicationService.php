<?php

namespace App\Services\Clinic;

use App\Models\CaseModel;
use App\Http\Resources\CaseMessageResource;
use App\Http\Resources\CaseAttachmentResource;
use App\Repositories\CaseMessageRepository;
use App\Repositories\CaseRepository;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\Storage;

class CaseCommunicationService
{
    public function __construct(
        private CaseRepository $caseRepository,
        private CaseMessageRepository $caseMessageRepository,
    ) {}

    public function listMessages(int $caseId, int $perPage = 30): array
    {
        $case = $this->findCaseForClinic($caseId);
        if (! $case) {
            return ServiceResult::error('Case not found.', null, null, 404);
        }

        $messages = $this->caseMessageRepository->paginateByCaseForViewer($caseId, 'clinic', $perPage);

        return ServiceResult::success([
            'items' => CaseMessageResource::collection($messages->items())->resolve(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ], 'Messages fetched successfully');
    }

    public function sendMessage(int $caseId, array $data): array
    {
        $case = $this->findCaseForClinic($caseId);
        if (! $case) {
            return ServiceResult::error('Case not found.', null, null, 404);
        }

        $sender = auth()->user();

        $message = $this->caseMessageRepository->create([
            'case_id' => $case->id,
            'sender_id' => $sender?->id,
            'sender_name' => $sender?->name,
            'sender_type' => 'clinic',
            'message' => $data['message'],
            'is_internal' => (bool) ($data['is_internal'] ?? false),
            'is_read' => false,
            'attachment_url' => $data['attachment_url'] ?? null,
        ]);

        return ServiceResult::success((new CaseMessageResource($message))->resolve(), 'Message sent successfully', 201);
    }

    public function addAttachment(int $caseId, array $data): array
    {
        $case = $this->findCaseForClinic($caseId);
        if (! $case) {
            return ServiceResult::error('Case not found.', null, null, 404);
        }

        $file = $data['file'];
        $filename = uniqid('att_') . '.' . $file->getClientOriginalExtension();
        $path = Storage::disk('public')->putFileAs('cases/attachments', $file, $filename);

        $attachment = $this->caseRepository->createAttachment($case, [
            'uploaded_by' => auth()->id(),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'attachment_type' => $data['attachment_type'] ?? null,
        ]);

        return ServiceResult::success((new CaseAttachmentResource($attachment))->resolve(), 'Attachment uploaded successfully', 201);
    }

    public function listAttachments(int $caseId): array
    {
        $case = $this->findCaseForClinic($caseId);
        if (! $case) {
            return ServiceResult::error('Case not found.', null, null, 404);
        }

        $case->load('attachments');

        return ServiceResult::success(
            CaseAttachmentResource::collection($case->attachments)->resolve(),
            'Attachments fetched successfully'
        );
    }

    private function findCaseForClinic(int $caseId): ?CaseModel
    {
        $clinicId = auth()->user()?->clinic_id;
        if (! $clinicId) {
            return null;
        }

        return CaseModel::query()
            ->where('clinic_id', $clinicId)
            ->find($caseId);
    }
}
