<?php
declare(strict_types=1);

namespace App;

use App\Handlers\AccountHandler;
use App\Handlers\LinkHandler;
use App\Handlers\StudentHandler;
use App\State\StateStore;
use App\Telegram\KeyboardKit;
use App\Telegram\TelegramApi;
use App\Telegram\UpdateContext;
use Telegram\Bot\Objects\CallbackQuery;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Objects\Update;

final class Router
{
    public function __construct(
        private readonly StateStore $state,
        private readonly TelegramApi $tg,
        private readonly LinkHandler $link,
        private readonly StudentHandler $student,
        private readonly AccountHandler $account,
    ) {}

    public function route(Update $update): void
    {
        $updateId = (int) $update->get('update_id');
        if ($updateId > 0 && $this->state->seen($updateId)) {
            return;
        }

        $callback = $update->getCallbackQuery();
        if ($callback !== null) {
            $this->routeCallback($callback);

            return;
        }

        $message = $update->getMessage();
        if ($message === null) {
            return;
        }

        $this->routeMessage($message);
    }

    private function routeCallback(CallbackQuery $callback): void
    {
        $ctx = UpdateContext::fromCallback($callback);
        if ($ctx === null || $ctx->callbackId === null) {
            return;
        }

        $data = $ctx->callbackData ?? '';
        $known = $data === KeyboardKit::CB_NOP
            || $data === KeyboardKit::CB_HOME
            || str_starts_with($data, 'ln:')
            || str_starts_with($data, 'ac')
            || str_starts_with($data, 'st');

        $this->tg->answerCallbackQuery($ctx->callbackId, $known ? '' : Lang::t('error.callback_expired'));

        if (!$known) {
            return;
        }

        $s = $this->state->load($ctx->chatId);

        if ($data === KeyboardKit::CB_NOP) {
            $this->state->save($s);

            return;
        }

        if ($data === KeyboardKit::CB_HOME) {
            $this->student->home($s, $ctx, false);
        } elseif (str_starts_with($data, 'ac:role:')) {
            $this->link->onCallback($s, $data, $ctx);
        } elseif (str_starts_with($data, 'ln:')) {
            $this->link->onCallback($s, $data, $ctx);
        } elseif ($data === 'ac' || str_starts_with($data, 'ac:')) {
            $this->account->onCallback($s, $data, $ctx);
        } else {
            $this->student->onCallback($s, $data, $ctx);
        }

        $this->state->save($s);
    }

    private function routeMessage(Message $message): void
    {
        $chat = $message->getChat();
        if ($chat === null || (string) ($chat->get('type') ?? 'private') !== 'private') {
            return;
        }

        $ctx = UpdateContext::fromMessage($message);
        if ($ctx === null) {
            return;
        }

        $s = $this->state->load($ctx->chatId);
        $text = $ctx->text();

        if ($text !== null) {
            $trimmed = trim($text);

            $route = KeyboardKit::labelRoute($trimmed);
            if ($route !== null) {
                $this->student->onLabel($s, $route, $ctx);
                $this->state->save($s);

                return;
            }

            if (str_starts_with($trimmed, '/')) {
                $command = strtolower(explode(' ', $trimmed)[0]);
                match ($command) {
                    '/start' => $this->link->start($s, $ctx),
                    '/menu', '/cancel' => $this->student->home($s, $ctx, true),
                    '/help' => $this->student->help($s, $ctx),
                    default => $this->student->unknown($s, $ctx),
                };
                $this->state->save($s);

                return;
            }
        }

        if ($s->role === null) {
            $this->link->start($s, $ctx);
        } elseif ($s->role === 'student') {
            $this->student->onMessage($s, $ctx);
        }

        $this->state->save($s);
    }
}
