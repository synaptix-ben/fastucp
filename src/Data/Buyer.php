<?php

namespace FastUcp\Data;

class Buyer
{
    public function __construct(
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $fullName = null,
        public readonly ?string $email = null,
        public readonly ?string $phoneNumber = null,
        public readonly ?array $consent = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'phone_number' => $this->phoneNumber,
            'consent' => $this->consent,
        ], fn ($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            fullName: $data['full_name'] ?? null,
            email: $data['email'] ?? null,
            phoneNumber: $data['phone_number'] ?? null,
            consent: $data['consent'] ?? null,
        );
    }
}
