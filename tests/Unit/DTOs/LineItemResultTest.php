<?php

namespace ApexTechnology\TidyBill\Tests\Unit\DTOs;

use ApexTechnology\TidyBill\DTOs\LineItemResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LineItemResultTest extends TestCase
{
    #[Test]
    public function from_response_converts_cents_to_float(): void
    {
        $result = LineItemResult::fromResponse([
            'id'          => 1,
            'description' => 'API call',
            'quantity'    => 5,
            'unit_price'  => '1.500000',
            'amount'      => '7.500000',
        ]);

        $this->assertSame(1.50, $result->unitPrice);
        $this->assertSame(7.50, $result->total);
    }

    #[Test]
    public function from_response_maps_all_fields(): void
    {
        $result = LineItemResult::fromResponse([
            'id'          => 10,
            'description' => 'Widget',
            'quantity'    => 3,
            'unit_price'  => '5.000000',
            'amount'      => '15.000000',
        ]);

        $this->assertSame(10, $result->id);
        $this->assertSame('Widget', $result->description);
        $this->assertSame(3, $result->quantity);
        $this->assertSame(5.00, $result->unitPrice);
        $this->assertSame(15.00, $result->total);
    }

    #[Test]
    public function from_response_defaults_unit_price_to_zero_when_missing(): void
    {
        $result = LineItemResult::fromResponse([
            'id'          => 1,
            'description' => 'Free item',
            'quantity'    => 1,
            'amount'      => '0.000000',
        ]);

        $this->assertSame(0.0, $result->unitPrice);
    }

    #[Test]
    public function from_response_defaults_total_to_zero_when_missing(): void
    {
        $result = LineItemResult::fromResponse([
            'id'          => 1,
            'description' => 'Free item',
            'quantity'    => 1,
            'unit_price'  => '0.000000',
        ]);

        $this->assertSame(0.0, $result->total);
    }

    #[Test]
    public function from_response_handles_zero_quantity(): void
    {
        $result = LineItemResult::fromResponse([
            'id'          => 1,
            'description' => 'Zero qty',
            'quantity'    => 0,
            'unit_price'  => '1.000000',
            'amount'      => '0.000000',
        ]);

        $this->assertSame(0, $result->quantity);
        $this->assertSame(1.00, $result->unitPrice);
        $this->assertSame(0.0, $result->total);
    }

    #[Test]
    public function from_response_handles_large_values(): void
    {
        $result = LineItemResult::fromResponse([
            'id'          => 1,
            'description' => 'Large',
            'quantity'    => 10000,
            'unit_price'  => '999999.000000',
            'amount'      => '9999990000.000000',
        ]);

        $this->assertSame(999999.0, $result->unitPrice);
        $this->assertSame(9999990000.0, $result->total);
    }
}
