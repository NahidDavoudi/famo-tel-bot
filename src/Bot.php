<?php


namespace App;

use Telegram\Bot\Api;
use App\Commands\StartCommand;
use App\Commands\HelpCommand;
use App\Commands\ReportCommand;
use App\Logging\Logger;

class Bot
{
    private Api $telegram;

    public function __construct(string $token)
    {
        $this->telegram = new Api($token);
    }


    public function handle(): void
    {
        $update = $this->telegram->getWebhookUpdate();

        $this->telegram->addCommands([
            StartCommand::class,
            HelpCommand::class,
            ReportCommand::class,
        ]);
        Logger::info('telegram.update', [
            'type' => $update->objectType(),
            'chat_id' => $update->getChat()->get('id'),
            'raw' => $update->getRawResponse(),
        ]);

    }
}
