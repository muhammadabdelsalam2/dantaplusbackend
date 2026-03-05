<?php

namespace App\Http\Requests\Owner\Material;

use Illuminate\Foundation\Http\FormRequest;

class ShowMaterialCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}

