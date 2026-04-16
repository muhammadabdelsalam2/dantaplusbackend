<?php

namespace App\Http\Requests\Company;

class UpdateShippingZoneRequest extends StoreShippingZoneRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        foreach ($rules as $key => $rule) {
            $rules[$key] = is_array($rule) ? array_merge(['sometimes'], $rule) : 'sometimes|' . $rule;
        }

        return $rules;
    }
}
