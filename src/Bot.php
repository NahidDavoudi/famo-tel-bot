<?php

use Telegram\Bot\Api;

class Bot
{
    private Api $telegram;

    public function __construct(Api $telegram)
    {
        $this->telegram = $telegram;
    }

    public function handle(array $update): void
    {
        if (!isset($update['message'])) {
            return;
        }

        if (!$this->isBotActive()) {
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

    private function isBotActive(): bool
    {
        $path = __DIR__ . '/../admin/data/bot-status.json';
        if (!file_exists($path)) {
            return true;
        }
        $data = json_decode(file_get_contents($path), true);
        return $data['active'] ?? true;
    }
}