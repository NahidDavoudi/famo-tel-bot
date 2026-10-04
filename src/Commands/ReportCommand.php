<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;

class ReportCommand extends Command
{
    protected string $name = 'report';

    protected string $description = 'ارسال گزارش کار';

    public function handle()
    {
        $this->replyWithMessage([
            'text' => 'گزارش کارت رو ارسال کن 📝',
        ]);
    }
}