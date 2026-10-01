<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;
use App\Services\MessageService;
use App\Logging\Logger;
use App\Services\IdentityService;
use Telegram\Bot\Keyboard\Keyboard;

class ReportCommand extends Command
{
    protected string $name = 'report';
    protected string $description;
    protected IdentityService $identity;

    public function __construct()
    {
        $this->description = MessageService::get('report.desc');
        $this->identity = new IdentityService();
    }

    public function handle()
    {
        $chatId = (int) $this->getUpdate()->getChat()->get('id');
        $linkStatus = $this->identity->isLinked($chatId);
        Logger::info('command.report', ['chat_id' => $chatId, 'link_status' => $linkStatus]);
        if (!$linkStatus) {
            $replyMarkup = Keyboard::make()->inline()->row([
                Keyboard::inlineButton(['text' => 'Link Now', 'url' => 'https://auth.famoacademy.ir']),
                Keyboard::inlineButton(['text' => 'Return Home' , 'callback_param' => 'quest.backHome'])
            ]);
            $this->replyWithMessage([
                'text' => 'You are not linked to any account. Please link your account first.',
                'parse_mode' => 'HTML',
                'reply_markup' => $replyMarkup
            ]);
            $this->replyWithMessage(['text' => 'Send Your Report 📤']);
            return;
        }
    }
}
