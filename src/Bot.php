<?php

use Telegram\Bot\Api;

class Bot
{
    private Api $telegram;

    public function __construct(string $token)
    {
        $this->telegram = new Api($token);
    }

    public function handle(array $update): void
    {
        if (!isset($update['message'])) {
            return;
        }

        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        if ($text === '/start') {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'سلام 👋',
            ]);
        }
    }
}
