<?php

namespace App\DTOs\Clinic\Insurance;

class InsuranceCompanyData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $code,
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
            'coverage' => $this->coverage,
            'payment_terms' => $this->paymentTerms,
            'syndicate_price_list_id' => $this->syndicatePriceListId,
            'notes' => $this->notes,
            'is_active' => $this->isActive,
        ];
    }
}
