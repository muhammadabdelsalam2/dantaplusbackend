<?php

namespace App\DTOs\Clinic\Settings;

class ServicePricingData
{
    public function __construct(
        public readonly ?int $serviceId,
        public readonly ?string $name,
        public readonly ?int $categoryId,
        public readonly ?string $categoryName,
        public readonly ?string $description,
        public readonly ?float $price,
        public readonly ?float $cost,
        public readonly ?float $labCost,
        public readonly ?bool $hasLab,
        public readonly ?bool $isActive,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            serviceId: isset($data['service_id']) ? (int) $data['service_id'] : null,
            name: $data['name'] ?? null,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            categoryName: $data['category_name'] ?? null,
            description: $data['description'] ?? null,
            price: array_key_exists('price', $data) ? (float) $data['price'] : null,
            cost: array_key_exists('cost', $data) ? (float) $data['cost'] : null,
            labCost: array_key_exists('lab_cost', $data) ? (float) $data['lab_cost'] : null,
            hasLab: array_key_exists('has_lab', $data) ? (bool) $data['has_lab'] : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
        );
    }
}
