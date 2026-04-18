<?php

namespace ApexTechnology\TidyBill\Tests\Unit\DTOs;

use ApexTechnology\TidyBill\DTOs\LineItemData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LineItemDataTest extends TestCase
{
    #[Test]
    public function to_array_converts_unit_price_to_cents(): void
    {
        $item = new LineItemData(
            description: 'API call',
            quantity: 10,
            unitPrice: 1.50,
        );

        $array = $item->toArray();

        $this->assertSame('API call', $array['description']);
        $this->assertSame(10, $array['quantity']);
        $this->assertSame(150, $array['unit_price']);
    }

    #[Test]
    public function to_array_rounds_fractional_cents(): void
    {
        $item = new LineItemData(
            description: 'Cheap call',
            quantity: 100,
            unitPrice: 0.035,
        );

        $array = $item->toArray();

        $this->assertSame(4, $array['unit_price']);
    }

    #[Test]
    public function to_array_handles_whole_rands(): void
    {
        $item = new LineItemData(
            description: 'Standard call',
            quantity: 5,
            unitPrice: 1.0,
        );

        $array = $item->toArray();

        $this->assertSame(100, $array['unit_price']);
    }
}
