<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;
use App\Services\IdentityService;
class StartCommand extends Command
{
    protected string $name = 'start';

    protected string $description = 'شروع کار با ربات {logo-icon}';

    public function __construct(private IdentityService $identityService){ }

    public function handle()
    {
        $chatId = (int) $this->getUpdate()->getChat()->id;
        $isLinked = $this->identityService->isLinked($chatId);

        $replyMarkup = $isLinked ? 
        Keyboard::make()
            ->inline()
            ->row(
                Keyboard::Button([
                    'text' => 'Report Student',
                    'url' => 'https://famoacademy.ir/report-student',
                ])                
        ):
        Keyboard::make()
            ->inline()
            ->row(
                Keyboard::Button([
                    'text' => 'Share Contact {phone}',
                    'request_contact' => true,
                ])                
            );

        $this->replyWithMessage([
            'text' => $isLinked ? 
            '<b>{header}</b>
            <span>{intro}</span>'
            :'<b>{header}</b>
            <span>{intro}</span>
            <span>You need to link your account first. Please Share your Contact or visit the Website:</span>
            ',
            'reply_markup' => $replyMarkup,
        ]);

    }
}