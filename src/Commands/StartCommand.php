<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;

class StartCommand extends Command
{
    protected string $name = 'start';

    protected string $description = 'شروع کار با ربات';

    public function handle()
    {
        $this->replyWithMessage([
            'text' => 'سلام 👋',
        ]);
    }
}