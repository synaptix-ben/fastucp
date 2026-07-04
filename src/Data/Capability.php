<?php

namespace FastUcp\Data;

class Capability
{
    public function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly ?string $spec = null,
        public readonly ?string $schema = null,
        public readonly ?string $extends = null,
        public readonly ?array $config = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'version' => $this->version,
            'spec' => $this->spec,
            'schema' => $this->schema,
            'extends' => $this->extends,
            'config' => $this->config,
        ], fn ($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            version: $data['version'],
            spec: $data['spec'] ?? null,
            schema: $data['schema'] ?? null,
            extends: $data['extends'] ?? null,
            config: $data['config'] ?? null,
        );
    }
}
