<?php

namespace ApexTechnology\TidyBill\DTOs;

class LineItemResult
{
    public function __construct(
        public readonly int $id,
        public readonly string $description,
        public readonly int $quantity,
        public readonly float $unitPrice,
        public readonly float $total,
    ) {}

    public static function fromResponse(array $data): self
    {
        return new self(
            id: $data['id'],
            description: $data['description'],
            quantity: $data['quantity'],
            unitPrice: (float) ($data['unit_price'] ?? 0) / 100,
            total: (float) ($data['total'] ?? 0) / 100,
        );
    }
}
