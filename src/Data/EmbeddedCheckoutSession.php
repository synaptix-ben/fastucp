<?php

namespace FastUcp\Data;

class EmbeddedCheckoutSession
{
    /**
     * Delegations the host may claim, per the UCP Embedded Checkout Protocol.
     */
    public const SUPPORTED_DELEGATES = [
        'payment.credential',
        'payment.instruments_change',
        'fulfillment.address_change',
    ];

    /**
     * @param string[] $requestedDelegates Delegates the host requested via ?ec_delegate=
     * @param string[] $acceptedDelegates Delegates this checkout accepts
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly array $requestedDelegates = [],
        public readonly array $acceptedDelegates = [],
        public readonly ?string $hostOrigin = null,
    ) {}

    /**
     * Parse a comma-separated ec_delegate query parameter and negotiate
     * against the delegates this checkout supports.
     */
    public static function negotiate(string $sessionId, ?string $ecDelegateParam, ?string $hostOrigin = null): self
    {
        $requested = $ecDelegateParam !== null && $ecDelegateParam !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $ecDelegateParam))))
            : [];

        $accepted = array_values(array_intersect($requested, self::SUPPORTED_DELEGATES));

        return new self($sessionId, $requested, $accepted, $hostOrigin);
    }

    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'requested_delegates' => $this->requestedDelegates,
            'accepted_delegates' => $this->acceptedDelegates,
            'host_origin' => $this->hostOrigin,
        ];
    }
}
