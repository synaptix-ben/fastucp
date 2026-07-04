<?php

namespace FastUcp\Store;

use FastUcp\Contracts\SessionStore;
use FastUcp\Models\UcpCheckoutSession;

class DatabaseSessionStore implements SessionStore
{
    public function save(string $sessionId, array $data): void
    {
        UcpCheckoutSession::updateOrCreate(
            ['id' => $sessionId],
            [
                'status' => $data['status'] ?? 'incomplete',
                'currency' => $data['currency'] ?? config('ucp.currency', 'USD'),
                'data' => $data,
                'expires_at' => $data['expires_at'] ?? now()->addHours(6),
            ],
        );
    }

    public function get(string $sessionId): ?array
    {
        $session = UcpCheckoutSession::find($sessionId);

        if ($session === null) {
            return null;
        }

        if ($session->expires_at !== null && $session->expires_at->isPast()) {
            return null;
        }

        return $session->data;
    }

    public function delete(string $sessionId): void
    {
        UcpCheckoutSession::destroy($sessionId);
    }
}
