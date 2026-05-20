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
        $this->assertSame(150.0, $array['unit_price']);
    }

    #[Test]
    public function to_array_preserves_fractional_cents(): void
    {
        $item = new LineItemData(
            description: 'Cheap call',
            quantity: 100,
            unitPrice: 0.035,
        );

        $array = $item->toArray();

        $this->assertSame(3.5, $array['unit_price']);
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

        $this->assertSame(100.0, $array['unit_price']);
    }

    #[Test]
    public function to_array_does_not_truncate_sub_cent_unit_price(): void
    {
        $item = new LineItemData(
            description: 'My Estate Life regression: TidyBill invoice 1671',
            quantity: 1,
            unitPrice: 0.035,
        );

        $array = $item->toArray();

        $this->assertSame(3.5, $array['unit_price']);
    }
}
