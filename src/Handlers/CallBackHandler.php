<?php

namespace App\Handlers;

use Telegram\Bot\Api;
// use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\Update;
// use App\Services\MessageService;
use App\Logging\Logger;
// use GuzzleHttp\Psr7\Message;
use Telegram\Bot\Objects\Message as ObjectsMessage;

class CallBackHandler
{
    public function __construct(private Api $telegram) {}

    public function handle(Update $update): void
    {
        $callback = $update->getCallbackQuery();
        if ($callback === null) {
            return;
        }

        $chatId = $update->getChat()->get('id');

        Logger::info('callback.received', [
            'data' => $callback->getData(),
            'chat_id' => $chatId,
            'from_id' => $callback->getFrom()->getId(),
            'callback_query_id' => $callback->getId(),
        ]);

        try {
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $callback->getId(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('callback.answer_failed', ['message' => $e->getMessage()]);
        }

        if ($chatId === null || $chatId === '') {
            return;
        }

        match ((string) $callback->getData()) {
            'get.phone' => $this->getPhone((int) $chatId),
            default => null,
        };
    }

    private function getPhone(int $chatId): ObjectsMessage
    {
        $tmp = $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Send Your Registred Phone number',
        ]);
        $messageId = $tmp->messageId();
        $copyedMessage = $this->telegram->copyMessage([
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
        return $copyedMessage;
    }
}
