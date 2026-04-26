<?php

namespace App\Http\Requests\Clinic\Settings;

class UpdateBranchRequest extends StoreBranchRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        foreach ($rules as $key => $rule) {
            array_unshift($rules[$key], 'sometimes');
        }

        return $rules;
    }
}
