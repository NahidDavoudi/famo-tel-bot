<?php

namespace App\Handlers;

use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\Update;
use App\Services\MessageService;
use App\Logging\Logger;

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
            'guest.identify' => $this->guestIdentify((int) $chatId),
            default => null,
        };
    }

    private function guestIdentify(int $chatId): void
    {
        Logger::info('callback.guest_identify', ['chat_id' => $chatId]);

        $getPhoneNumberInline = $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => MessageService::get('identity.ask_phone'),
            'reply_markup' => Keyboard::make()->row([
                Keyboard::button([
                    'text' => MessageService::get('ico.phone') . ' ' . MessageService::get('btn.contact'),
                    'request_contact' => true,
                    'resize_keyboard'   => true,
                    'one_time_keyboard' => true,
                ]),
            ]),
        ]);        
    }
}
