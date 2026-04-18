<?php

namespace ApexTechnology\TidyBill\DTOs;

class InvoiceResult
{
    public function __construct(
        public readonly int $id,
        public readonly string $clientId,
        public readonly string $status,
        public readonly string $issueDate,
        public readonly string $currency,
        public readonly float $total,
        public readonly array $lineItems,
        public readonly array $raw,
    ) {}

    public static function fromResponse(array $data): self
    {
        return new self(
            id: $data['id'],
            clientId: (string) $data['client_id'],
            status: $data['status'],
            issueDate: $data['issue_date'],
            currency: $data['currency'] ?? 'ZAR',
            total: (float) ($data['total'] ?? 0),
            lineItems: $data['line_items'] ?? [],
            raw: $data,
        );
    }
}
