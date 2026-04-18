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
        return new self(
            id: (string) $data['id'],
            name: $data['name'],
            email: $data['email'] ?? null,
            raw: $data,
        );
    }
}
