<?php

namespace ApexTechnology\TidyBill\Tests\Unit\DTOs;

use ApexTechnology\TidyBill\DTOs\CreateInvoiceData;
use ApexTechnology\TidyBill\DTOs\LineItemData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CreateInvoiceDataTest extends TestCase
{
    #[Test]
    public function to_array_includes_required_fields(): void
    {
        $data = new CreateInvoiceData(
            clientId: '42',
            issueDate: '2026-04-20',
        );

        $array = $data->toArray();

        $this->assertSame('42', $array['client_id']);
        $this->assertSame('2026-04-20', $array['issue_date']);
        $this->assertSame('ZAR', $array['currency']);
    }

    #[Test]
    public function to_array_omits_null_notes(): void
    {
        $data = new CreateInvoiceData(clientId: '1', issueDate: '2026-01-01');

        $array = $data->toArray();

        $this->assertArrayNotHasKey('notes', $array);
    }

    #[Test]
    public function to_array_includes_notes_when_provided(): void
    {
        $data = new CreateInvoiceData(
            clientId: '1',
            issueDate: '2026-01-01',
            notes: 'Net 30',
        );

        $array = $data->toArray();

        $this->assertSame('Net 30', $array['notes']);
    }

    #[Test]
    public function to_array_includes_empty_line_items_array(): void
    {
        $data = new CreateInvoiceData(clientId: '1', issueDate: '2026-01-01');

        $array = $data->toArray();

        // array_filter only strips null — an empty array is retained.
        $this->assertArrayHasKey('line_items', $array);
        $this->assertSame([], $array['line_items']);
    }

    #[Test]
    public function to_array_serialises_line_items(): void
    {
        $data = new CreateInvoiceData(
            clientId: '1',
            issueDate: '2026-01-01',
            lineItems: [
                new LineItemData(description: 'Widget', quantity: 2, unitPrice: 5.00),
            ],
        );

        $array = $data->toArray();

        $this->assertCount(1, $array['line_items']);
        $this->assertSame('Widget', $array['line_items'][0]['description']);
        $this->assertSame(2, $array['line_items'][0]['quantity']);
        $this->assertSame(500, $array['line_items'][0]['unit_price']);
    }

    #[Test]
    public function to_array_serialises_multiple_line_items(): void
    {
        $data = new CreateInvoiceData(
            clientId: '1',
            issueDate: '2026-01-01',
            lineItems: [
                new LineItemData(description: 'A', quantity: 1, unitPrice: 1.00),
                new LineItemData(description: 'B', quantity: 3, unitPrice: 2.50),
            ],
        );

        $array = $data->toArray();

        $this->assertCount(2, $array['line_items']);
        $this->assertSame(100, $array['line_items'][0]['unit_price']);
        $this->assertSame(250, $array['line_items'][1]['unit_price']);
    }

    #[Test]
    public function to_array_uses_custom_currency(): void
    {
        $data = new CreateInvoiceData(
            clientId: '1',
            issueDate: '2026-01-01',
            currency: 'USD',
        );

        $this->assertSame('USD', $data->toArray()['currency']);
    }

    #[Test]
    public function defaults_to_zar_currency(): void
    {
        $data = new CreateInvoiceData(clientId: '1', issueDate: '2026-01-01');

        $this->assertSame('ZAR', $data->currency);
    }
}
