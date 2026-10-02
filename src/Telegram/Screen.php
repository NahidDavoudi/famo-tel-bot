<?php
declare(strict_types=1);

namespace App\Telegram;

final class Screen
{
    public function __construct(
        public readonly string $text,
        public readonly ?array $keyboard = null,
    ) {}
}
