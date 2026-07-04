<?php

namespace FastUcp\Data;

class PaymentHandler
{
    /**
     * @param string[] $instrumentSchemas
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly string $spec,
        public readonly string $configSchema,
        public readonly array $instrumentSchemas = [],
        public readonly array $config = [],
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'spec' => $this->spec,
            'config_schema' => $this->configSchema,
            'instrument_schemas' => $this->instrumentSchemas,
            'config' => $this->config,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            version: $data['version'],
            spec: $data['spec'],
            configSchema: $data['config_schema'],
            instrumentSchemas: $data['instrument_schemas'] ?? [],
            config: $data['config'] ?? [],
        );
    }
}
