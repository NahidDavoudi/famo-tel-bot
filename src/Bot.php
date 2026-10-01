<?php

use Telegram\Bot\Api;
use App\Commands\StartCommand;
use App\Commands\HelpCommand;
use App\Commands\ReportCommand;
use App\Handlers\CallBackHandler;
use App\Errors\ErrorHandler;

class Bot
{
    private Api $telegram;
    private CallBackHandler $callbackHandler;

    public function __construct(string $token)
    {
        $this->telegram = new Api($token);
        $this->registerCommands();
        $this->callbackHandler = new CallBackHandler($this->telegram);
    }

    private function registerCommands(): void
    {
        $this->telegram->addCommands([
            StartCommand::class,
            HelpCommand::class,
            ReportCommand::class,
        ]);
    }

    public function handle(): void
    {
        $update = $this->telegram->getWebhookUpdate();

        try {
            if ($update->getCallbackQuery()) {
                $this->callbackHandler->handle($update);
                return;
            }

            $this->telegram->processCommand($update);
        } catch (\Throwable $e) {
            error_log('bot handle error: ' . $e->getMessage());

            $chatId = $update->getChat()->get('id');
            if ($chatId === null || $chatId === '') {
                return;
            }

            try {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => ErrorHandler::message($e),
                ]);
            } catch (\Throwable $sendError) {
                error_log('bot error reply failed: ' . $sendError->getMessage());
            }
        }
    }
}
