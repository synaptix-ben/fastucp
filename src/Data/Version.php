<?php

namespace FastUcp\Data;

class Version
{
    public function __construct(
        public readonly string $value,
    ) {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new \InvalidArgumentException("UCP version must be in YYYY-MM-DD format, got: {$value}");
        }
    }

    public function toArray(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
