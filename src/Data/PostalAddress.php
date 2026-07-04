<?php

namespace FastUcp\Data;

class PostalAddress
{
    public function __construct(
        public readonly ?string $streetAddress = null,
        public readonly ?string $extendedAddress = null,
        public readonly ?string $addressLocality = null,
        public readonly ?string $addressRegion = null,
        public readonly ?string $addressCountry = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $fullName = null,
        public readonly ?string $phoneNumber = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'street_address' => $this->streetAddress,
            'extended_address' => $this->extendedAddress,
            'address_locality' => $this->addressLocality,
            'address_region' => $this->addressRegion,
            'address_country' => $this->addressCountry,
            'postal_code' => $this->postalCode,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName,
            'phone_number' => $this->phoneNumber,
        ], fn ($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            streetAddress: $data['street_address'] ?? null,
            extendedAddress: $data['extended_address'] ?? null,
            addressLocality: $data['address_locality'] ?? null,
            addressRegion: $data['address_region'] ?? null,
            addressCountry: $data['address_country'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            fullName: $data['full_name'] ?? null,
            phoneNumber: $data['phone_number'] ?? null,
        );
    }
}
