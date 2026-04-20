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
        $item = $data['data'] ?? $data;

        return new self(
            id: $item['id'],
            description: $item['description'],
            quantity: $item['quantity'],
            unitPrice: (float) ($item['unit_price'] ?? 0),
            total: (float) ($item['amount'] ?? $item['total'] ?? 0),
        );
    }
}
