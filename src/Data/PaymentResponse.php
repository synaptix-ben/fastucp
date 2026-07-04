<?php

namespace FastUcp\Data;

class PaymentResponse
{
    /**
     * @param PaymentHandler[] $handlers
     * @param PaymentInstrument[]|null $instruments
     */
    public function __construct(
        public readonly array $handlers = [],
        public readonly ?string $selectedInstrumentId = null,
        public readonly ?array $instruments = null,
    ) {}

    public function toArray(): array
    {
        $out = [
            'handlers' => array_map(
                fn ($h) => $h instanceof PaymentHandler ? $h->toArray() : $h,
                $this->handlers
            ),
        ];

        if ($this->selectedInstrumentId !== null) {
            $out['selected_instrument_id'] = $this->selectedInstrumentId;
        }
        if ($this->instruments !== null) {
            $out['instruments'] = array_map(
                fn ($i) => $i instanceof PaymentInstrument ? $i->toArray() : $i,
                $this->instruments
            );
        }

        return $out;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            handlers: array_map(fn ($h) => PaymentHandler::fromArray($h), $data['handlers'] ?? []),
            selectedInstrumentId: $data['selected_instrument_id'] ?? null,
            instruments: isset($data['instruments'])
                ? array_map(fn ($i) => PaymentInstrument::fromArray($i), $data['instruments'])
                : null,
        );
    }
}
