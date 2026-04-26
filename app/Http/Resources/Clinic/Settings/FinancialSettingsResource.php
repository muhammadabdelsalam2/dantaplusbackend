<?php

namespace App\Http\Resources\Clinic\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currency' => $this['currency'] ?? 'EGP',
            'tax_rate' => isset($this['tax_rate']) ? (float) $this['tax_rate'] : null,
            'tax_number' => $this['tax_number'] ?? null,
            'invoice_prefix' => $this['invoice_prefix'] ?? 'INV',
            'invoice_notes' => $this['invoice_notes'] ?? null,
            'payment_methods' => $this['payment_methods'] ?? ['cash'],
            'bank_name' => $this['bank_name'] ?? null,
            'bank_account_name' => $this['bank_account_name'] ?? null,
            'bank_account_number' => $this['bank_account_number'] ?? null,
            'iban' => $this['iban'] ?? null,
            'swift_code' => $this['swift_code'] ?? null,
        ];
    }
}
