<?php

namespace App\Handlers;

use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\Update;
use App\Services\MessageService;

class CallBackHandler
{
    public function __construct(private Api $telegram) {}

    public function handle(Update $update): void
    {
        $callback = $update->getCallbackQuery();
        if ($callback === null) {
            return;
        }

        try {
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $callback->getId(),
            ]);
        } catch (\Throwable $e) {
            error_log('answerCallbackQuery failed: ' . $e->getMessage());
        }

        $chatId = $update->getChat()->get('id');
        if ($chatId === null || $chatId === '') {
            return;
        }

        match ((string) $callback->getData()) {
            'guest.identify' => $this->guestIdentify((int) $chatId),
            default => null,
        };
    }

    private function guestIdentify(int $chatId): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => MessageService::get('identity.ask_phone'),
            'reply_markup' => Keyboard::make()->row([
                Keyboard::button([
                    'text' => MessageService::get('ico.phone') . ' ' . MessageService::get('btn.contact'),
                    'request_contact' => true,
                ]),
            ]),
        ]);
    }
}
