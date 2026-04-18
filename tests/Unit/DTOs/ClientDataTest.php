<?php

namespace ApexTechnology\TidyBill\Tests\Unit\DTOs;

use ApexTechnology\TidyBill\DTOs\ClientData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClientDataTest extends TestCase
{
    #[Test]
    public function from_response_maps_fields_correctly(): void
    {
        $data = [
            'id'    => 42,
            'name'  => 'Acme Corp',
            'email' => 'billing@acme.com',
        ];

        $client = ClientData::fromResponse($data);

        $this->assertSame('42', $client->id);
        $this->assertSame('Acme Corp', $client->name);
        $this->assertSame('billing@acme.com', $client->email);
        $this->assertSame($data, $client->raw);
    }

    #[Test]
    public function from_response_casts_integer_id_to_string(): void
    {
        $client = ClientData::fromResponse(['id' => 99, 'name' => 'Test', 'email' => null]);

        $this->assertSame('99', $client->id);
        $this->assertIsString($client->id);
    }

    #[Test]
    public function from_response_handles_missing_email(): void
    {
        $client = ClientData::fromResponse(['id' => 1, 'name' => 'No Email Co']);

        $this->assertNull($client->email);
    }

    #[Test]
    public function from_response_handles_null_email_explicitly(): void
    {
        $client = ClientData::fromResponse(['id' => 1, 'name' => 'Null Email Co', 'email' => null]);

        $this->assertNull($client->email);
    }

    #[Test]
    public function from_response_preserves_extra_fields_in_raw(): void
    {
        $data = [
            'id'      => 5,
            'name'    => 'Extra Fields Co',
            'email'   => 'x@x.com',
            'phone'   => '+27821234567',
            'address' => '1 Main St',
        ];

        $client = ClientData::fromResponse($data);

        $this->assertSame($data, $client->raw);
        $this->assertArrayHasKey('phone', $client->raw);
    }

    #[Test]
    public function from_response_with_string_id_stays_string(): void
    {
        $client = ClientData::fromResponse(['id' => 'uuid-abc-123', 'name' => 'UUID Client']);

        $this->assertSame('uuid-abc-123', $client->id);
    }
}
