<?php

namespace FastUcp\Exceptions;

use Exception;

class UcpException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ?string $path = null,
        public readonly string $severity = 'requires_buyer_input',
        public readonly int $statusCode = 400,
    ) {
        parent::__construct($message, $statusCode);
    }

    public function toArray(): array
    {
        return [
            'messages' => [
                array_filter([
                    'type' => 'error',
                    'code' => $this->errorCode,
                    'path' => $this->path,
                    'severity' => $this->severity,
                    'content' => $this->getMessage(),
                ], fn ($v) => $v !== null),
            ],
        ];
    }

    /**
     * Laravel renders this automatically when the exception escapes a route.
     */
    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->toArray(), $this->statusCode);
    }
}
