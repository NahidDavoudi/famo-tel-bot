<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;
use App\Services\MessageService;
use App\Logging\Logger;

class HelpCommand extends Command
{
    protected string $name = 'help';

    public function __construct()
    {
        $this->description = MessageService::get('help.desc');
    }

    public function handle()
    {
        $chatId = (int) $this->getUpdate()->getChat()->get('id');
        Logger::info('command.help', ['chat_id' => $chatId]);

        $this->replyWithMessage([
            'text' => MessageService::get('help.title') . "\n\n"
                . MessageService::get('help.body') . "\n\n"
                . MessageService::get('help.footer'),
            'parse_mode' => 'HTML',
        ]);
    }
}
