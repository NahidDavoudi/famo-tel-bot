<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;

class HelpCommand extends Command
{
    protected string $name = 'help';

    protected string $description = 'راهنمای ربات';

    public function handle()
    {
        $this->replyWithMessage([
            'text' => "راهنمای ربات\n\n/start - شروع\n/report - ارسال گزارش کار",
        ]);
    }
}