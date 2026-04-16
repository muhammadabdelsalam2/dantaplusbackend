<?php

namespace App\Services\Company;

use App\Http\Resources\Company\ConversationResource;
use App\Http\Resources\Company\MessageResource;
use App\Models\Conversation;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\SharedFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CommunicationService
{
    public function conversations(): array
    {
        return ConversationResource::collection(
            Conversation::query()->with('clinic:id,name,email,phone')->withCount('files')->latest('last_message_at')->get()
        )->resolve();
    }

    public function messages(Conversation $conversation): array
    {
        return MessageResource::collection($conversation->messages()->latest('id')->get())->resolve();
    }

    public function sendMessage(Conversation $conversation, array $data): array
    {
        return DB::transaction(function () use ($conversation, $data) {
            $attachmentPath = ($data['attachment'] ?? null) instanceof UploadedFile
                ? $data['attachment']->store('company/messages', 'public')
                : null;

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'company_id' => auth()->user()->company_id,
                'sender_type' => 'company_user',
                'sender_id' => auth()->id(),
                'sender_name' => auth()->user()->name,
                'message_type' => $data['message_type'],
                'content' => $data['content'] ?? null,
                'related_type' => $data['related_type'] ?? null,
                'related_id' => $data['related_id'] ?? null,
                'attachment_path' => $attachmentPath,
                'text' => $data['content'] ?? null,
                'type' => in_array($data['message_type'], ['image', 'file'], true) ? 'attachment' : 'text',
                'attachment_url' => $attachmentPath,
            ]);

            $conversation->update([
                'last_message_text' => $message->content,
                'last_message_at' => now(),
                'last_message_sender_id' => auth()->id(),
            ]);

            return (new MessageResource($message))->resolve();
        });
    }

    public function uploadFile(Conversation $conversation, UploadedFile $file): array
    {
        $path = $file->store('company/shared-files', 'public');
        $shared = SharedFile::create([
            'conversation_id' => $conversation->id,
            'company_id' => auth()->user()->company_id,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
            'file_path' => $path,
            'uploaded_by_type' => 'company_user',
            'uploaded_by_id' => auth()->id(),
            'uploaded_by_name' => auth()->user()->name,
        ]);

        return [
            'id' => $shared->id,
            'file_name' => $shared->file_name,
            'file_type' => $shared->file_type,
            'file_url' => asset('storage/' . $shared->file_path),
        ];
    }

    public function files(Conversation $conversation): array
    {
        return $conversation->files()->latest('id')->get()->map(fn ($file) => [
            'id' => $file->id,
            'file_name' => $file->file_name,
            'file_type' => $file->file_type,
            'file_url' => asset('storage/' . $file->file_path),
        ])->all();
    }

    public function sendInvoice(Conversation $conversation, Invoice $invoice): array
    {
        return $this->sendMessage($conversation, [
            'message_type' => 'invoice',
            'content' => 'Invoice ' . $invoice->invoice_number,
            'related_type' => 'invoice',
            'related_id' => $invoice->id,
        ]);
    }
}
