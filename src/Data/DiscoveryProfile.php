<?php

namespace FastUcp\Data;

class DiscoveryProfile
{
    /**
     * @param array<string, UcpService> $services
     * @param Capability[] $capabilities
     */
    public function __construct(
        public readonly string $version,
        public readonly array $services,
        public readonly array $capabilities,
    ) {}

    public function toArray(): array
    {
        $services = [];
        foreach ($this->services as $name => $service) {
            $services[$name] = $service instanceof UcpService ? $service->toArray() : $service;
        }

        return [
            'version' => $this->version,
            'services' => $services,
            'capabilities' => array_map(
                fn ($c) => $c instanceof Capability ? $c->toArray() : $c,
                $this->capabilities
            ),
        ];
    }

    public static function fromArray(array $data): self
    {
        $services = [];
        foreach ($data['services'] ?? [] as $name => $service) {
            $services[$name] = UcpService::fromArray($service);
        }

        return new self(
            version: $data['version'],
            services: $services,
            capabilities: array_map(
                fn ($c) => Capability::fromArray($c),
                $data['capabilities'] ?? []
            ),
        );
    }
}
