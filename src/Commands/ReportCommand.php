<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;

class ReportCommand extends Command
{
    protected string $name = 'report';

    protected string $description = 'دریافت گزارش';

    public function handle()
    {
        $this->replyWithMessage([
            'text' => 'گزارش بهزودی در دسترس قرار میگیرد.',
        ]);
    }
}
