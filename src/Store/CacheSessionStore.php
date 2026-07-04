<?php

namespace FastUcp\Store;

use FastUcp\Contracts\SessionStore;
use Illuminate\Contracts\Cache\Repository as Cache;

class CacheSessionStore implements SessionStore
{
    /**
     * Default TTL matches the UCP checkout session default of 6 hours.
     */
    public function __construct(
        protected Cache $cache,
        protected int $ttlSeconds = 21600,
        protected string $prefix = 'ucp:session:',
    ) {}

    public function save(string $sessionId, array $data): void
    {
        $this->cache->put($this->prefix.$sessionId, $data, $this->ttlSeconds);
    }

    public function get(string $sessionId): ?array
    {
        $value = $this->cache->get($this->prefix.$sessionId);

        return is_array($value) ? $value : null;
    }

    public function delete(string $sessionId): void
    {
        $this->cache->forget($this->prefix.$sessionId);
    }
}
