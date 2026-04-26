<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppearanceSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'theme' => $this->theme ?? 'light',
            'primary_color' => $this->primary_color ?? '#4f46e5',
            'language' => $this->language ?? 'en',
        ];
    }
}
