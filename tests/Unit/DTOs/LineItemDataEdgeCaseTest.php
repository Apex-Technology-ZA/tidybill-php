<?php

namespace ApexTechnology\TidyBill\Tests\Unit\DTOs;

use ApexTechnology\TidyBill\DTOs\LineItemData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LineItemDataEdgeCaseTest extends TestCase
{
    #[Test]
    public function to_array_with_zero_quantity(): void
    {
        $item = new LineItemData(description: 'Zero qty', quantity: 0, unitPrice: 1.00);

        $array = $item->toArray();

        $this->assertSame(0, $array['quantity']);
        $this->assertSame(100.0, $array['unit_price']);
    }

    #[Test]
    public function to_array_with_zero_unit_price(): void
    {
        $item = new LineItemData(description: 'Free', quantity: 1, unitPrice: 0.0);

        $array = $item->toArray();

        $this->assertSame(0.0, $array['unit_price']);
    }

    #[Test]
    public function to_array_with_large_price(): void
    {
        $item = new LineItemData(description: 'Enterprise', quantity: 1, unitPrice: 99999.99);

        $array = $item->toArray();

        $this->assertSame(9999999.0, $array['unit_price']);
    }

    #[Test]
    public function to_array_with_large_quantity(): void
    {
        $item = new LineItemData(description: 'Bulk', quantity: 1000000, unitPrice: 0.01);

        $array = $item->toArray();

        $this->assertSame(1000000, $array['quantity']);
        $this->assertSame(1.0, $array['unit_price']);
    }

    #[Test]
    public function to_array_preserves_description(): void
    {
        $description = 'Invoice line: special chars — & " \'';
        $item        = new LineItemData(description: $description, quantity: 1, unitPrice: 1.0);

        $this->assertSame($description, $item->toArray()['description']);
    }

    #[Test]
    public function unit_price_in_cents_is_float(): void
    {
        $item = new LineItemData(description: 'Test', quantity: 1, unitPrice: 12.34);

        $array = $item->toArray();

        $this->assertIsFloat($array['unit_price']);
        $this->assertSame(1234.0, $array['unit_price']);
    }
}
