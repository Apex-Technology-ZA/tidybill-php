<?php

namespace ApexTechnology\TidyBill\DTOs;

use ApexTechnology\TidyBill\Enums\EInvoiceFormat;

class CompanyEInvoiceSettings
{
    public function __construct(
        public readonly bool $enabled,
        public readonly EInvoiceFormat $format,
        public readonly ?string $electronicAddressScheme,
        public readonly ?string $electronicAddress,
        public readonly ?string $taxRegistrationNumber,
        public readonly ?string $vatId,
        public readonly array $raw,
    ) {}

    public static function fromResponse(array $data): self
    {
        $settings = $data['data'] ?? $data;

        return new self(
            enabled: (bool) $settings['enabled'],
            format: EInvoiceFormat::from($settings['format']),
            electronicAddressScheme: $settings['electronic_address_scheme'] ?? null,
            electronicAddress: $settings['electronic_address'] ?? null,
            taxRegistrationNumber: $settings['tax_registration_number'] ?? null,
            vatId: $settings['vat_id'] ?? null,
            raw: $settings,
        );
    }
}
