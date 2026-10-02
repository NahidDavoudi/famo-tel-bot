<?php
declare(strict_types=1);

namespace App\Telegram;

use App\State\ChatState;
use App\State\StateStore;
use Telegram\Bot\Exceptions\TelegramSDKException;

final class ScreenManager
{
    public function __construct(
        private readonly TelegramApi $tg,
        private readonly StateStore $state,
    ) {}

    public function show(ChatState $state, Screen $screen, bool $edit = false): int
    {
        $previous = $state->activeScreenMessageId;
        $messageId = 0;

        if ($edit && $previous !== null) {
            try {
                $this->tg->editMessageText($state->chatId, $previous, $screen->text, $screen->keyboard);
                $messageId = $previous;
            } catch (TelegramSDKException $e) {
                if (str_contains(strtolower($e->getMessage()), 'not modified')) {
                    $messageId = $previous;
                } else {
                    $messageId = $this->tg->sendMessage($state->chatId, $screen->text, $screen->keyboard);
                }
            }
        } else {
            $messageId = $this->tg->sendMessage($state->chatId, $screen->text, $screen->keyboard);
        }

        if ($previous !== null && $previous !== $messageId) {
            try {
                $this->tg->editMessageReplyMarkup($state->chatId, $previous, []);
            } catch (TelegramSDKException) {
            }
        }

        $state->activeScreenMessageId = $messageId;

        return $messageId;
    }
}
