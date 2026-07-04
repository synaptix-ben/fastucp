<?php

namespace FastUcp\Contracts;

interface SessionStore
{
    public function save(string $sessionId, array $data): void;

    public function get(string $sessionId): ?array;

    public function delete(string $sessionId): void;
}
