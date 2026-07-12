<?php

namespace ApexTechnology\TidyBill\Tests\Unit\DTOs;

use ApexTechnology\TidyBill\DTOs\ClientEInvoiceSettings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClientEInvoiceSettingsTest extends TestCase
{
    #[Test]
    public function from_response_maps_all_fields(): void
    {
        $result = ClientEInvoiceSettings::fromResponse(['data' => [
            'enabled'                   => true,
            'vat_id'                    => 'GB999',
            'tax_registration_number'   => 'TRN-9',
            'electronic_address_scheme' => '0088',
            'electronic_address'        => '5790000435975',
            'buyer_reference_default'   => 'PO-1234',
        ]]);

        $this->assertTrue($result->enabled);
        $this->assertSame('GB999', $result->vatId);
        $this->assertSame('TRN-9', $result->taxRegistrationNumber);
        $this->assertSame('0088', $result->electronicAddressScheme);
        $this->assertSame('5790000435975', $result->electronicAddress);
        $this->assertSame('PO-1234', $result->buyerReferenceDefault);
    }

    #[Test]
    public function from_response_null_enabled_means_inherit(): void
    {
        $result = ClientEInvoiceSettings::fromResponse([
            'enabled'                   => null,
            'vat_id'                    => null,
            'tax_registration_number'   => null,
            'electronic_address_scheme' => null,
            'electronic_address'        => null,
            'buyer_reference_default'   => null,
        ]);

        $this->assertNull($result->enabled);
        $this->assertNull($result->vatId);
    }
}
