<?php
declare(strict_types=1);

namespace App;

use App\Handlers\AccountHandler;
use App\Handlers\BroadcastHandler;
use App\Handlers\LinkHandler;
use App\Handlers\StudentHandler;
use App\Handlers\SupporterHandler;
use App\State\ChatState;
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
        private readonly SupporterHandler $supporter,
        private readonly BroadcastHandler $broadcast,
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
        if (! $message instanceof Message) {
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
            || str_starts_with($data, 'st')
            || str_starts_with($data, 'sp')
            || str_starts_with($data, 'bc');

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
            $this->homeForRole($s, $ctx, false);
        } elseif (str_starts_with($data, 'ac:role:')) {
            $this->link->onCallback($s, $data, $ctx);
        } elseif (str_starts_with($data, 'ln:')) {
            $this->link->onCallback($s, $data, $ctx);
        } elseif ($data === 'ac' || str_starts_with($data, 'ac:')) {
            $this->account->onCallback($s, $data, $ctx);
        } elseif (str_starts_with($data, 'sp')) {
            $this->supporter->onCallback($s, $data, $ctx);
        } elseif (str_starts_with($data, 'bc')) {
            $this->broadcast->onCallback($s, $data, $ctx);
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
                $this->routeLabel($s, $route, $ctx);
                $this->state->save($s);

                return;
            }

            if (str_starts_with($trimmed, '/')) {
                $command = strtolower(explode(' ', $trimmed)[0]);
                match ($command) {
                    '/start' => $this->link->start($s, $ctx),
                    '/menu' => $this->homeForRole($s, $ctx, true),
                    '/cancel' => $this->cancel($s, $ctx),
                    '/help' => $this->helpForRole($s, $ctx),
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
        } elseif ($s->role === 'supporter') {
            if ($s->mode === 'replying') {
                $this->supporter->onReply($s, $ctx);
            } elseif ($s->mode === 'composing_broadcast') {
                $this->broadcast->onCompose($s, $ctx);
            }
        }

        $this->state->save($s);
    }

    private function routeLabel(ChatState $s, string $route, UpdateContext $ctx): void
    {
        if ($route === KeyboardKit::ROUTE_TODAY || $route === KeyboardKit::ROUTE_WEEK) {
            $this->student->onLabel($s, $route, $ctx);

            return;
        }

        if ($route === KeyboardKit::ROUTE_HOME) {
            $this->homeForRole($s, $ctx, true);

            return;
        }

        if ($route === KeyboardKit::ROUTE_BROADCAST) {
            $this->broadcast->onLabel($s, $route, $ctx);

            return;
        }

        if ($route === KeyboardKit::ROUTE_INBOX || $route === KeyboardKit::ROUTE_STUDENTS) {
            $this->supporter->onLabel($s, $route, $ctx);
        }
    }

    private function homeForRole(ChatState $s, UpdateContext $ctx, bool $new): void
    {
        if ($s->role === 'supporter') {
            $this->supporter->home($s, !$new);

            return;
        }

        $this->student->home($s, $ctx, $new);
    }

    private function cancel(ChatState $s, UpdateContext $ctx): void
    {
        $s->mode = 'idle';
        unset($s->payload['student_id'], $s->payload['day'], $s->payload['audience'], $s->payload['draft']);
        $this->homeForRole($s, $ctx, true);
    }

    private function helpForRole(ChatState $s, UpdateContext $ctx): void
    {
        if ($s->role === 'supporter') {
            $this->tg->sendMessage($s->chatId, Lang::t('help.supporter.text'));

            return;
        }

        $this->student->help($s, $ctx);
    }
}
