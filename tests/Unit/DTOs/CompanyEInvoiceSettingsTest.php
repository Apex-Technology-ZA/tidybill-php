<?php

namespace ApexTechnology\TidyBill\Tests\Unit\DTOs;

use ApexTechnology\TidyBill\DTOs\CompanyEInvoiceSettings;
use ApexTechnology\TidyBill\Enums\EInvoiceFormat;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CompanyEInvoiceSettingsTest extends TestCase
{
    #[Test]
    public function from_response_maps_all_fields(): void
    {
        $result = CompanyEInvoiceSettings::fromResponse(['data' => [
            'enabled'                   => true,
            'format'                    => 'zugferd_en16931',
            'electronic_address_scheme' => '0088',
            'electronic_address'        => '7300010000001',
            'tax_registration_number'   => 'TRN-1',
            'vat_id'                    => 'GB123456789',
        ]]);

        $this->assertTrue($result->enabled);
        $this->assertSame(EInvoiceFormat::ZugferdEn16931, $result->format);
        $this->assertSame('0088', $result->electronicAddressScheme);
        $this->assertSame('7300010000001', $result->electronicAddress);
        $this->assertSame('TRN-1', $result->taxRegistrationNumber);
        $this->assertSame('GB123456789', $result->vatId);
    }

    #[Test]
    public function from_response_handles_null_optionals(): void
    {
        $result = CompanyEInvoiceSettings::fromResponse([
            'enabled'                   => false,
            'format'                    => 'ubl_peppol_bis3',
            'electronic_address_scheme' => null,
            'electronic_address'        => null,
            'tax_registration_number'   => null,
            'vat_id'                    => null,
        ]);

        $this->assertFalse($result->enabled);
        $this->assertSame(EInvoiceFormat::UblPeppolBis3, $result->format);
        $this->assertNull($result->electronicAddressScheme);
        $this->assertNull($result->vatId);
    }
}
