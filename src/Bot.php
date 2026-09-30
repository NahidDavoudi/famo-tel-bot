<?php

use Telegram\Bot\Api;
use App\Commands\StartCommand;
use App\Commands\HelpCommand;
use App\Commands\ReportCommand;

class Bot
{
    private Api $telegram;

    public function __construct(string $token)
    {
        $this->telegram = new Api($token);

        $this->registerCommands();
    }

    private function registerCommands(): void
    {
        $this->telegram->addCommands([
            StartCommand::class,
            HelpCommand::class,
            ReportCommand::class,
        ]);
    }

    public function handle(): void
    {
        $response = $this->telegram->getMe();
        $botId = $response->getId();
        $firstName = $response->getFirstName();
        $username = $response->getUsername();
        $this->telegram->commandsHandler(true);
        $response = $telegram->sendMessage([
            'chat_id' => 'CHAT_ID',
            'text' => 'Hello World'
        ]);
        $messageId = $response->getMessageId();
    }
}