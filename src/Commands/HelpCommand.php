<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;

class HelpCommand extends Command
{
    protected string $name = 'help';

    protected string $description = 'راهنمای دستورها';

    public function handle()
    {
        $this->replyWithMessage([
            'text' => "دستورهای موجود:\n/start - شروع کار با ربات\n/help - راهنما\n/report - گزارش",
        ]);
    }
}
