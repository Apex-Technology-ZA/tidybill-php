<?php

namespace ApexTechnology\TidyBill\DTOs;

class CreateInvoiceData
{
    /**
     * @param LineItemData[] $lineItems
     */
    public function __construct(
        public readonly string $clientId,
        public readonly string $issueDate,
        public readonly string $currency = 'ZAR',
        public readonly ?string $notes = null,
        public readonly array $lineItems = [],
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'client_id' => $this->clientId,
            'issue_date' => $this->issueDate,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'line_items' => array_map(fn (LineItemData $item) => $item->toArray(), $this->lineItems),
        ], fn ($value) => $value !== null);
    }
}
