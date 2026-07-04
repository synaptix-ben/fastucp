<?php

namespace FastUcp\Data;

class UcpService
{
    public function __construct(
        public readonly string $version,
        public readonly string $spec,
        public readonly ?array $rest = null,
        public readonly ?array $mcp = null,
        public readonly ?array $a2a = null,
        public readonly ?array $embedded = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'version' => $this->version,
            'spec' => $this->spec,
            'rest' => $this->rest,
            'mcp' => $this->mcp,
            'a2a' => $this->a2a,
            'embedded' => $this->embedded,
        ], fn ($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            version: $data['version'],
            spec: $data['spec'],
            rest: $data['rest'] ?? null,
            mcp: $data['mcp'] ?? null,
            a2a: $data['a2a'] ?? null,
            embedded: $data['embedded'] ?? null,
        );
    }
}
