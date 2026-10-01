<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;
use App\Services\MessageService;

class HelpCommand extends Command
{
    protected string $name = 'help';

    public function __construct()
    {
        $this->description = MessageService::get('help.desc');
    }

    public function handle()
    {
        $this->replyWithMessage([
            'text' => MessageService::get('help.title') . "\n\n"
                . MessageService::get('help.body') . "\n\n"
                . MessageService::get('help.footer'),
            'parse_mode' => 'HTML',
        ]);
    }
}
