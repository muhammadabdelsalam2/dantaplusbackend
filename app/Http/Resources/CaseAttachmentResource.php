<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'caseId' => $this->case_id,
            'uploadedBy' => $this->uploaded_by,
            'fileName' => $this->file_name,
            'filePath' => $this->file_path,
            'mimeType' => $this->mime_type,
            'fileSize' => $this->file_size,
            'attachmentType' => $this->attachment_type,
            'createdAt' => optional($this->created_at)->toISOString(),
        ];
    }
}
