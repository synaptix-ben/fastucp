<?php

namespace FastUcp\Data;

class UcpDiscoveryProfile
{
    /**
     * @param PaymentHandler[]|null $paymentHandlers
     * @param SigningKey[]|null $signingKeys
     */
    public function __construct(
        public readonly DiscoveryProfile $ucp,
        public readonly ?array $paymentHandlers = null,
        public readonly ?array $signingKeys = null,
    ) {}

    public function toArray(): array
    {
        $out = ['ucp' => $this->ucp->toArray()];

        if ($this->paymentHandlers !== null && $this->paymentHandlers !== []) {
            $out['payment'] = [
                'handlers' => array_map(
                    fn ($h) => $h instanceof PaymentHandler ? $h->toArray() : $h,
                    $this->paymentHandlers
                ),
            ];
        }

        if ($this->signingKeys !== null && $this->signingKeys !== []) {
            $out['signing_keys'] = array_map(
                fn ($k) => $k instanceof SigningKey ? $k->toArray() : $k,
                $this->signingKeys
            );
        }

        return $out;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ucp: DiscoveryProfile::fromArray($data['ucp']),
            paymentHandlers: isset($data['payment']['handlers'])
                ? array_map(fn ($h) => PaymentHandler::fromArray($h), $data['payment']['handlers'])
                : null,
            signingKeys: isset($data['signing_keys'])
                ? array_map(fn ($k) => SigningKey::fromArray($k), $data['signing_keys'])
                : null,
        );
    }
}
