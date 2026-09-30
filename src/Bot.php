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
        $this->telegram->commandsHandler(true);
    }
}