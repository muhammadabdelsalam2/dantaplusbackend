<?php
namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Resources\Json\JsonResource;

class DentalLabListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'email' => $this->email,
            'working_hours' => $this->working_hours,
            'status' => $this->status,
            'logo_url' => $this->logo_url,
            'avg_delivery_days' => $this->avg_delivery_days,
            'delivery_speed' => 'days ' . rtrim(rtrim((string) $this->avg_delivery_days, '0'), '.'),
            'response_speed' => $this->response_speed,
            'rating' => $this->rating !== null ? (float) $this->rating : 0,
            'active_clinics' => (int) $this->active_clinics,
            'on_time_percentage' => $this->on_time_percentage,
            'rejection_rate' => $this->rejection_rate,
            'is_external' => (bool) $this->is_external,
            'date_added' => $this->date_added,
            'services' => $this->whenLoaded('services'),
            'users' => $this->whenLoaded('users', fn () => $this->users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar_url,
                'is_active' => (bool) $u->is_active,
                'last_login_at' => $u->last_login_at,
                'role' => $u->roles->first() ? [
                    'id' => $u->roles->first()->id,
                    'name' => $u->roles->first()->name,
                ] : null,
            ])),
        ];
    }
}
