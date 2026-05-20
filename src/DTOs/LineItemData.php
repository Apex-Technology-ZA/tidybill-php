<?php

namespace ApexTechnology\TidyBill\DTOs;

class LineItemData
{
    public function __construct(
        public readonly string $description,
        public readonly int $quantity,
        public readonly float $unitPrice,
    ) {}

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => round($this->unitPrice * 100, 6),
        ];
    }
}
