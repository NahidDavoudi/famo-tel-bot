<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;
use App\Services\MessageService;
use App\Logging\Logger;
use App\Services\IdentityService;

class StartCommand extends Command
{
    protected string $name = 'start';
    protected string $description; 
    public function __construct()
    {
        $this->description = MessageService::get('start.desc');
    }

    public function handle()
    {
        $chatId = (int) $this->getUpdate()->getChat()->get('id');
        $this->replyWithMessage(['text' => 'welcome to Famoacademy']);

    }
}
