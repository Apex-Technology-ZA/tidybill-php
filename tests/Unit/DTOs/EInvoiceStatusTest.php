<?php

namespace ApexTechnology\TidyBill\Tests\Unit\DTOs;

use ApexTechnology\TidyBill\DTOs\EInvoiceStatus;
use ApexTechnology\TidyBill\Enums\EInvoiceFormat;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

class EInvoiceStatusTest extends TestCase
{
    #[Test]
    public function from_response_unwraps_data_envelope_and_maps_fields(): void
    {
        $result = EInvoiceStatus::fromResponse(['data' => [
            'enabled'      => true,
            'format'       => 'ubl_peppol_bis3',
            'generated_at' => '2026-07-01T10:00:00Z',
            'warnings'     => ['w1'],
            'has_xml'      => true,
        ]]);

        $this->assertTrue($result->enabled);
        $this->assertSame(EInvoiceFormat::UblPeppolBis3, $result->format);
        $this->assertSame('2026-07-01T10:00:00Z', $result->generatedAt);
        $this->assertSame(['w1'], $result->warnings);
        $this->assertTrue($result->hasXml);
    }

    #[Test]
    public function from_response_handles_null_format(): void
    {
        $result = EInvoiceStatus::fromResponse([
            'enabled'      => false,
            'format'       => null,
            'generated_at' => null,
            'warnings'     => [],
            'has_xml'      => false,
        ]);

        $this->assertNull($result->format);
        $this->assertNull($result->generatedAt);
    }

    #[Test]
    public function from_response_throws_on_unknown_format(): void
    {
        $this->expectException(ValueError::class);

        EInvoiceStatus::fromResponse([
            'enabled'  => true,
            'format'   => 'not_a_format',
            'warnings' => [],
            'has_xml'  => false,
        ]);
    }
}
