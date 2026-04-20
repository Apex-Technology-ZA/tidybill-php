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
        $invoice = $data['data'] ?? $data;

        return new self(
            id: $invoice['id'],
            clientId: (string) $invoice['client_id'],
            status: $invoice['status'],
            issueDate: $invoice['issue_date'],
            currency: $invoice['currency'] ?? 'ZAR',
            total: (float) ($invoice['total'] ?? 0),
            lineItems: $invoice['line_items'] ?? [],
            raw: $invoice,
        );
    }
}
