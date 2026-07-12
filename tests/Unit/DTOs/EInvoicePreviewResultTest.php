<?php

namespace ApexTechnology\TidyBill\Tests\Unit\DTOs;

use ApexTechnology\TidyBill\DTOs\EInvoicePreviewResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EInvoicePreviewResultTest extends TestCase
{
    #[Test]
    public function from_response_maps_valid_and_issues(): void
    {
        $issues = [['severity' => 'error', 'message' => 'x']];

        $result = EInvoicePreviewResult::fromResponse(['data' => [
            'valid'  => false,
            'issues' => $issues,
        ]]);

        $this->assertFalse($result->valid);
        $this->assertSame($issues, $result->issues);
    }

    #[Test]
    public function from_response_defaults_issues_to_empty_array(): void
    {
        $result = EInvoicePreviewResult::fromResponse(['valid' => true]);

        $this->assertTrue($result->valid);
        $this->assertSame([], $result->issues);
    }
}
