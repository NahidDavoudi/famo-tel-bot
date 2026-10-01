<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;
use App\Services\MessageService;

class StartCommand extends Command
{
    protected string $name = 'start';

    public function __construct()
    {
        $this->description = MessageService::get('start.desc');
    }

    public function handle()
    {
        $keyboard = Keyboard::make()
            ->inline()
            ->row([
                Keyboard::inlineButton([
                    'text' => MessageService::get('ico.web') . ' ' . MessageService::get('btn.web'),
                    'url' => 'https://famoacademy.ir',
                ]),
                Keyboard::inlineButton([
                    'text' => MessageService::get('ico.user') . ' ' . MessageService::get('btn.identify'),
                    'callback_data' => 'guest.identify',
                ]),
            ]);

        $this->replyWithMessage([
            'text' => MessageService::get('common.title')
                . MessageService::get('common.intro')
                . MessageService::get('start.link_hint'),
            'parse_mode' => 'HTML',
            'reply_markup' => $keyboard,
        ]);
    }
}
