<?php
declare(strict_types=1);

namespace App\State;

final class ChatState
{
    public int $chatId;

    public ?string $role = null;

    public string $mode = 'idle';

    /** @var array<string,mixed> */
    public array $payload = [];

    public ?int $activeScreenMessageId = null;

    public int $updatedAt = 0;

    public function __construct(int $chatId)
    {
        $this->chatId = $chatId;
    }

    public static function create(int $chatId): self
    {
        return new self($chatId);
    }
    public function accountId(): int
    {
        return (int) ($this->payload['account_id'] ?? 0);
    }
}

