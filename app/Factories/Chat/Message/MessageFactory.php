<?php


namespace App\Factories\Chat\Message;

use App\Models\MessageChat;

class MessageFactory
{
    public function prepare($dto)
    {
        $dto->message = $dto->message ? trim($dto->message) : null;
        return $dto;
    }

    public function storeFiles(MessageChat $message, array $files)
    {
        foreach ($files as $file) {
            $path = $file->store('chat');

            $message->attachments()->create([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    public function handleMentions(MessageChat $message)
    {
        preg_match_all('/@([a-zA-Z0-9_]+)/', $message->message, $matches);

        foreach ($matches[1] ?? [] as $username) {

            $user = \App\Models\User::where('username', $username)->first();

            if ($user) {
                $message->mentions()->create([
                    'user_id' => $user->id
                ]);
            }
        }
    }
}