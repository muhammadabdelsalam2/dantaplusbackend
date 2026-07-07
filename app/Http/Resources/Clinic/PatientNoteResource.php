<?php

namespace App\Http\Resources\Clinic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'note' => $this->note,
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'file_name' => $attachment->file_name,
                'file_path' => $attachment->file_path,
                'file_url' => $attachment->file_path ? asset('storage/' . $attachment->file_path) : null,
                'mime_type' => $attachment->mime_type,
                'size' => (int) $attachment->size,
            ])->values()),
            'mentions' => $this->whenLoaded('mentions', fn () => $this->mentions->map(fn ($mention) => [
                'id' => $mention->user?->id,
                'name' => $mention->user?->name,
            ])->filter(fn ($user) => $user['id'] !== null)->values()),
            'created_at' => optional($this->created_at)?->toISOString(),
        ], static fn ($value) => $value !== null);
    }
}
