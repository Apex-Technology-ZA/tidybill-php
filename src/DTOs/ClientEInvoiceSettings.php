<?php

namespace ApexTechnology\TidyBill\DTOs;

class ClientEInvoiceSettings
{
    public function __construct(
        public readonly ?bool $enabled,
        public readonly ?string $vatId,
        public readonly ?string $taxRegistrationNumber,
        public readonly ?string $electronicAddressScheme,
        public readonly ?string $electronicAddress,
        public readonly ?string $buyerReferenceDefault,
        public readonly array $raw,
    ) {}

    public static function fromResponse(array $data): self
    {
        $settings = $data['data'] ?? $data;

        return new self(
            enabled: $settings['enabled'] !== null ? (bool) $settings['enabled'] : null,
            vatId: $settings['vat_id'] ?? null,
            taxRegistrationNumber: $settings['tax_registration_number'] ?? null,
            electronicAddressScheme: $settings['electronic_address_scheme'] ?? null,
            electronicAddress: $settings['electronic_address'] ?? null,
            buyerReferenceDefault: $settings['buyer_reference_default'] ?? null,
            raw: $settings,
        );
    }
}
