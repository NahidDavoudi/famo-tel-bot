<?php
declare(strict_types=1);

namespace App\Telegram;

use Telegram\Bot\Objects\CallbackQuery;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Objects\Update;

final class UpdateContext
{
    public function __construct(
        public readonly int $chatId,
        public readonly int $userId,
        public readonly ?string $callbackId = null,
        public readonly ?string $callbackData = null,
        public readonly ?Message $message = null,
    ) {}

    public static function fromMessage(Message $message): ?self
    {
        $chat = $message->getChat();
        $from = $message->getFrom();
        if ($chat === null || $from === null) {
            return null;
        }

        return new self((int) $chat->get('id'), (int) $from->getId(), message: $message);
    }

    public static function fromCallback(CallbackQuery $callback): ?self
    {
        $from = $callback->getFrom();
        if ($from === null) {
            return null;
        }

        $chatId = (int) $from->getId();
        $source = $callback->getMessage();
        if ($source !== null && $source->getChat() !== null) {
            $chatId = (int) $source->getChat()->get('id');
        }

        return new self(
            $chatId,
            (int) $from->getId(),
            callbackId: (string) $callback->getId(),
            callbackData: (string) $callback->getData(),
            message: $source,
        );
    }

    public function text(): ?string
    {
        if ($this->message === null) {
            return null;
        }

        $text = $this->message->getText();

        return is_string($text) ? $text : null;
    }

    public function messageType(): ?string
    {
        return $this->message?->objectType();
    }

    public function messageId(): ?int
    {
        if ($this->message === null) {
            return null;
        }

        $id = $this->message->getMessageId();

        return $id !== null ? (int) $id : null;
    }

    public function isCallback(): bool
    {
        return $this->callbackId !== null;
    }
}
