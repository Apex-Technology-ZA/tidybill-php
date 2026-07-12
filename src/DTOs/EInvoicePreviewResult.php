<?php

namespace ApexTechnology\TidyBill\DTOs;

class EInvoicePreviewResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly array $issues,
        public readonly array $raw,
    ) {}

    public static function fromResponse(array $data): self
    {
        $result = $data['data'] ?? $data;

        return new self(
            valid: (bool) $result['valid'],
            issues: $result['issues'] ?? [],
            raw: $result,
        );
    }
}
