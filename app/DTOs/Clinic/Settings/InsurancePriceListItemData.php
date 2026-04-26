<?php

namespace App\DTOs\Clinic\Settings;

class InsurancePriceListItemData
{
    public function __construct(
        public readonly ?int $serviceId,
        public readonly ?string $code,
        public readonly ?string $serviceName,
        public readonly ?int $categoryId,
        public readonly ?string $categoryName,
        public readonly float $price,
        public readonly ?string $notes,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            serviceId: isset($data['service_id']) ? (int) $data['service_id'] : null,
            code: $data['code'] ?? $data['item_code'] ?? null,
            serviceName: $data['service_name'] ?? $data['name'] ?? null,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            categoryName: $data['category_name'] ?? $data['category'] ?? null,
            price: (float) $data['price'],
            notes: $data['notes'] ?? null,
        );
    }
}
