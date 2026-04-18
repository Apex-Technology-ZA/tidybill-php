<?php

namespace ApexTechnology\TidyBill\Tests\Unit\DTOs;

use ApexTechnology\TidyBill\DTOs\InvoiceResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InvoiceResultTest extends TestCase
{
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'id'         => 1,
            'client_id'  => '42',
            'status'     => 'draft',
            'issue_date' => '2026-04-20',
            'currency'   => 'ZAR',
            'total'      => 1000,
            'line_items' => [],
        ], $overrides);
    }

    #[Test]
    public function from_response_maps_all_fields(): void
    {
        $result = InvoiceResult::fromResponse($this->basePayload());

        $this->assertSame(1, $result->id);
        $this->assertSame('42', $result->clientId);
        $this->assertSame('draft', $result->status);
        $this->assertSame('2026-04-20', $result->issueDate);
        $this->assertSame('ZAR', $result->currency);
        $this->assertSame(1000.0, $result->total);
        $this->assertSame([], $result->lineItems);
    }

    #[Test]
    public function from_response_raw_contains_original_data(): void
    {
        $payload = $this->basePayload();
        $result  = InvoiceResult::fromResponse($payload);

        $this->assertSame($payload, $result->raw);
    }

    #[Test]
    public function from_response_defaults_currency_to_zar_when_missing(): void
    {
        $payload = $this->basePayload();
        unset($payload['currency']);

        $result = InvoiceResult::fromResponse($payload);

        $this->assertSame('ZAR', $result->currency);
    }

    #[Test]
    public function from_response_defaults_total_to_zero_when_missing(): void
    {
        $payload = $this->basePayload();
        unset($payload['total']);

        $result = InvoiceResult::fromResponse($payload);

        $this->assertSame(0.0, $result->total);
    }

    #[Test]
    public function from_response_defaults_line_items_to_empty_array_when_missing(): void
    {
        $payload = $this->basePayload();
        unset($payload['line_items']);

        $result = InvoiceResult::fromResponse($payload);

        $this->assertSame([], $result->lineItems);
    }

    #[Test]
    public function from_response_casts_total_to_float(): void
    {
        $result = InvoiceResult::fromResponse($this->basePayload(['total' => '2500']));

        $this->assertIsFloat($result->total);
        $this->assertSame(2500.0, $result->total);
    }

    #[Test]
    public function from_response_casts_client_id_to_string(): void
    {
        $result = InvoiceResult::fromResponse($this->basePayload(['client_id' => 99]));

        $this->assertIsString($result->clientId);
        $this->assertSame('99', $result->clientId);
    }

    #[Test]
    public function from_response_preserves_line_items_array(): void
    {
        $lineItems = [
            ['id' => 1, 'description' => 'Widget', 'quantity' => 2, 'unit_price' => 500, 'total' => 1000],
        ];

        $result = InvoiceResult::fromResponse($this->basePayload(['line_items' => $lineItems]));

        $this->assertSame($lineItems, $result->lineItems);
    }

    #[Test]
    public function from_response_with_large_total(): void
    {
        $result = InvoiceResult::fromResponse($this->basePayload(['total' => 9999999.99]));

        $this->assertSame(9999999.99, $result->total);
    }

    #[Test]
    public function from_response_with_zero_total(): void
    {
        $result = InvoiceResult::fromResponse($this->basePayload(['total' => 0]));

        $this->assertSame(0.0, $result->total);
    }

    #[Test]
    public function from_response_ignores_extra_fields_gracefully(): void
    {
        $payload = $this->basePayload([
            'unknown_field' => 'some value',
            'another'       => 42,
        ]);

        $result = InvoiceResult::fromResponse($payload);

        // Should not throw; extra fields end up in raw.
        $this->assertArrayHasKey('unknown_field', $result->raw);
    }
}
