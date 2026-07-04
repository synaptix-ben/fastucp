<?php

namespace FastUcp\Data;

/**
 * Discriminated union of MessageError | MessageWarning | MessageInfo,
 * discriminated by the 'type' field.
 */
class Message
{
    public function __construct(
        public readonly string $type,
        public readonly string $content,
        public readonly ?string $code = null,
        public readonly ?string $path = null,
        public readonly ?string $severity = null,
        public readonly string $contentType = 'plain',
    ) {}

    public static function error(
        string $code,
        string $content,
        ?string $path = null,
        string $severity = 'requires_buyer_input',
    ): self {
        return new self('error', $content, $code, $path, $severity);
    }

    public static function warning(string $code, string $content, ?string $path = null): self
    {
        return new self('warning', $content, $code, $path);
    }

    public static function info(string $content, ?string $code = null, ?string $path = null): self
    {
        return new self('info', $content, $code, $path);
    }

    public function isError(): bool
    {
        return $this->type === 'error';
    }

    public function toArray(): array
    {
        $out = [
            'type' => $this->type,
            'content' => $this->content,
        ];

        if ($this->code !== null) {
            $out['code'] = $this->code;
        }
        if ($this->path !== null) {
            $out['path'] = $this->path;
        }
        if ($this->type === 'error' && $this->severity !== null) {
            $out['severity'] = $this->severity;
        }
        if ($this->contentType !== 'plain') {
            $out['content_type'] = $this->contentType;
        }

        return $out;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            content: $data['content'],
            code: $data['code'] ?? null,
            path: $data['path'] ?? null,
            severity: $data['severity'] ?? null,
            contentType: $data['content_type'] ?? 'plain',
        );
    }
}
