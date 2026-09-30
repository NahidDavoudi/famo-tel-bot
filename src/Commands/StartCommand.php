<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;
use App\Services\IdentityService;
use App\Services\MessageService;

class StartCommand extends Command
{
    protected string $name = 'start';

    public function __construct()
    {
        $this->description = MessageService::get('start.description');
    }

    public function handle()
    {
        $chatId = (int) $this->getUpdate()->getChat()->id;

        $this->replyWithMessage([
            'text' => MessageService::get('identity.loading'),
        ]);

        
        $isLinked = IdentityService::isLinked($chatId);

        
        $replyMarkup = $isLinked
            ? Keyboard::make()
                ->inline()
                ->row([
                    Keyboard::button([
                        'text' => MessageService::get('start.button.report'),
                        'url' => 'https://famoacademy.ir/report-student',
                    ]),
                ])
            : Keyboard::make()
                ->row([
                    Keyboard::button([
                        'text' => MessageService::get('start.button.link'),
                        'request_contact' => true,
                    ]),
                ]);

        $this->telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $this->getUpdate()->getMessage()->messageId,
            'text' => MessageService::get($isLinked ? 'start.linked' : 'start.not_linked'),
            'reply_markup' => $replyMarkup,
        ]);
    }
}
