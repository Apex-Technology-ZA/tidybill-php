<?php

namespace ApexTechnology\TidyBill\DTOs;

use ApexTechnology\TidyBill\Enums\EInvoiceFormat;

class EInvoiceStatus
{
    public function __construct(
        public readonly bool $enabled,
        public readonly ?EInvoiceFormat $format,
        public readonly ?string $generatedAt,
        public readonly array $warnings,
        public readonly bool $hasXml,
        public readonly array $raw,
    ) {}

    public static function fromResponse(array $data): self
    {
        $status = $data['data'] ?? $data;
        $format = $status['format'] ?? null;

        return new self(
            enabled: (bool) $status['enabled'],
            format: $format !== null ? EInvoiceFormat::from($format) : null,
            generatedAt: $status['generated_at'] ?? null,
            warnings: $status['warnings'] ?? [],
            hasXml: (bool) $status['has_xml'],
            raw: $status,
        );
    }
}
