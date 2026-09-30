<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;
use App\Services\MessageService;

class HelpCommand extends Command
{
    protected string $name = 'help';

    public function __construct()
    {
        $this->description = MessageService::get('help.description');
    }

    public function handle()
    {
        $this->replyWithMessage([
            'text' => MessageService::get('help.text'),
        ]);
    }
}
