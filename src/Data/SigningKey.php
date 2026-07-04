<?php

namespace FastUcp\Data;

class SigningKey
{
    public function __construct(
        public readonly string $kid,
        public readonly string $kty,
        public readonly ?string $crv = null,
        public readonly ?string $x = null,
        public readonly ?string $y = null,
        public readonly ?string $n = null,
        public readonly ?string $e = null,
        public readonly ?string $use = null,
        public readonly ?string $alg = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'kid' => $this->kid,
            'kty' => $this->kty,
            'crv' => $this->crv,
            'x' => $this->x,
            'y' => $this->y,
            'n' => $this->n,
            'e' => $this->e,
            'use' => $this->use,
            'alg' => $this->alg,
        ], fn ($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            kid: $data['kid'],
            kty: $data['kty'],
            crv: $data['crv'] ?? null,
            x: $data['x'] ?? null,
            y: $data['y'] ?? null,
            n: $data['n'] ?? null,
            e: $data['e'] ?? null,
            use: $data['use'] ?? null,
            alg: $data['alg'] ?? null,
        );
    }
}
