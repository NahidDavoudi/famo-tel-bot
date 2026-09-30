<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;
use App\Services\MessageService;

class ReportCommand extends Command
{
    protected string $name = 'report';

    public function __construct()
    {
        $this->description = MessageService::get('report.description');
    }

    public function handle()
    {
        $this->replyWithMessage([
            'text' => MessageService::get('report.text'),
        ]);
    }
}
