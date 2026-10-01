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
        Logger::info('command.start', ['chat_id' => $chatId]);
        $identityService = new IdentityService();
        $linkStatus = $identityService->isLinked($chatId);
        if ($linkStatus) {
            $name = $linkStatus['name'] ?? '';
            $replyMarkup = Keyboard::make()->inline()->row([
                Keyboard::inlineButton(['text' => 'Send a Report']),
                Keyboard::inlineButton(['text' => "Profile"])
            ]);
            $this->replyWithMessage([
            'text' => 'Welcome to 𝗙𝗮𝗺𝗼 𝗔𝗰𝗮𝗱𝗲𝗺𝘆 '.$name.' 💠'. '\n\n' .'Select an action from list below',
            'reply_markup' => $replyMarkup,
            ]);
        } else {
            $replyMarkup = Keyboard::make()->inline()->row([
                Keyboard::inlineButton(['text' => 'Login in Web', 'url' => 'https://auth.famoacademy.ir']),
                Keyboard::inlineButton(['text' => 'Write Your Number', 'callback_data' => 'get.phone'])
            ]);
            $this->replyWithMessage(['text' => '𝗙𝗮𝗺𝗼 𝗔𝗰𝗮𝗱𝗲𝗺𝘆 To start using the bot, please write down your number or login in web 👇 ', 'parse_mode' => 'HTML', 'reply_markup' => $replyMarkup]);
        }
    }
}
