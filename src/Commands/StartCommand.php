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
        $Name = (string) $this->getName();
        // Logger::info('command.start', ['chat_id' => $chatId]);
        $role = IdentityService::getRole($chatId);
        if ($role == 'student'){
            $replyMarkup = Keyboard::make()->inline()
                ->row([
                    Keyboard::inlineButton(['text' => 'Send Report', 'callback_data' => 'sendReport']),
                    Keyboard::inlineButton(['text' => 'See My Profile', 'callback_data' => 'seeProfile'])
                ]);
            $this->replyWithMessage(['text' => 'Welcome To the Famo Bot' . $Name]);
        } elseif ($role == 'supporter') {
            $replyMarkup = Keyboard::make()->inline()
                ->row([
                    Keyboard::inlineButton(['text' => 'Inbox', 'callback_data' => 'seeInbox.supporter']),
                    Keyboard::inlineButton(['text' => 'See My Profile', 'callback_data' => 'seeProfile.supporter'])
                ]);
            $this->replyWithMessage(['text' => 'Welcome To the Famo Bot' . $Name]);
        }

        $replyMarkup = Keyboard::make()->inlineb()
            ->row([
                Keyboard::inlineButton(['text' => 'Login', 'url' => 'https://auth.famoacademy.ir']),
                Keyboard::inlineButton(['text' => 'See Courses', 'callback_data' => 'seeCourse'])])
            ->row([Keyboard::inlineButton(['text' => 'Open website' , 'style' => 'primary'])]);
        $this->replyWithMessage(
            ['text' => MessageService::get('common.title') . "Welcome To Famo Academy's Bot" . '\n\n' . 'To use this Bot Click buttons below' , 'reply_markup' => $replyMarkup ]
        );
    }
}
