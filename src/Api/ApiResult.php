<?php
declare(strict_types=1);

namespace Api;

final class ApiResult
{
    /** @param array<string,mixed>|null $body */
    public function __construct(
        public readonly int $status,
        public readonly ?array $body,
        public readonly ?string $errorCode = null,
        public readonly ?string $transportError = null,
    ) {}

    public function ok(): bool
    {
        return $this->transportError === null
            && $this->status >= 200 && $this->status < 300
            && is_array($this->body) && ($this->body['success'] ?? false) === true;
    }

    public function data(): mixed
    {
        return $this->body['data'] ?? null;
    }
}
