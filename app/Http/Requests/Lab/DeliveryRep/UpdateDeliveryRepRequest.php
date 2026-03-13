<?php

namespace App\Http\Requests\Lab\DeliveryRep;

use App\Models\LabDeliveryRep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliveryRepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function currentUserId(): ?int
    {
        $repId = (int) $this->route('id');

        if (!$repId) {
            return null;
        }

        return LabDeliveryRep::query()->where('id', $repId)->value('user_id');
    }

    public function rules(): array
    {
        $userId = $this->currentUserId();

        return [
            'full_name' => ['sometimes', 'string', 'max:255'],
            'login_phone' => ['sometimes', 'string', 'max:50', Rule::unique('users', 'phone')->ignore($userId)],
            'assigned_region_city' => ['nullable', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:6', 'max:255'],
            'status' => ['sometimes', Rule::in([
                LabDeliveryRep::STATUS_ACTIVE,
                LabDeliveryRep::STATUS_INACTIVE,
            ])],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
