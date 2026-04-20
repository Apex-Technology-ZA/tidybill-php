<?php

namespace ApexTechnology\TidyBill\DTOs;

class ClientData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $email,
        public readonly array $raw,
    ) {}

    public static function fromResponse(array $data): self
    {
        $client = $data['data'] ?? $data;

        return new self(
            id: (string) $client['id'],
            name: $client['name'],
            email: $client['email'] ?? null,
            raw: $client,
        );
    }
}
