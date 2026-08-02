<?php

namespace App\DTOs\Clinic\Insurance;

class InsuranceCompanyData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $code,
        public readonly ?string $contactPerson,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $coverage,
        public readonly ?string $paymentTerms,
        public readonly ?int $syndicatePriceListId,
        public readonly ?string $notes,
        public readonly bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            code: $data['code'] ?? null,
            contactPerson: $data['contact_person'] ?? $data['contact'] ?? null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            coverage: $data['coverage'] ?? null,
            paymentTerms: $data['payment_terms'] ?? null,
            syndicatePriceListId: $data['syndicate_price_list_id'] ?? null,
            notes: $data['notes'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'contact_person' => $this->contactPerson,
            'phone' => $this->phone,
            'email' => $this->email,
            'coverage' => $this->coverage,
            'payment_terms' => $this->paymentTerms,
            'syndicate_price_list_id' => $this->syndicatePriceListId,
            'notes' => $this->notes,
            'is_active' => $this->isActive,
        ];
    }
}
