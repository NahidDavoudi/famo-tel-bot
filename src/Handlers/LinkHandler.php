<?php
declare(strict_types=1);

namespace App\Handlers;

use App\Config;
use App\Famo\ErrorMap;
use App\Famo\FamoApi;
use App\Lang;
use App\Screens\WelcomeScreen;
use App\State\ChatState;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;
use App\Telegram\ScreenManager;
use App\Telegram\TelegramApi;
use App\Telegram\UpdateContext;

final class LinkHandler
{
    public function __construct(
        private readonly FamoApi $api,
        private readonly TelegramApi $tg,
        private readonly ScreenManager $screens,
        private readonly StudentHandler $student,
        private readonly Config $config,
    ) {}

    public function start(ChatState $s, UpdateContext $ctx): void
    {
        if ($ctx->userId > 0) {
            $s->telegramUserId = $ctx->userId;
        }
        $s->mode = 'idle';
        $this->resolve($s, $ctx);
    }

    public function onCallback(ChatState $s, string $data, UpdateContext $ctx): void
    {
        match ($data) {
            KeyboardKit::CB_CHECK => $this->resolve($s, $ctx),
            KeyboardKit::CB_ROLE_STUDENT => $this->activateRole($s, $ctx, 'student'),
            KeyboardKit::CB_ROLE_SUPPORTER => $this->activateRole($s, $ctx, 'supporter'),
            default => null,
        };
    }

    public function pickRole(ChatState $s, UpdateContext $ctx): void
    {
        $this->resolve($s, $ctx);
    }

    private function resolve(ChatState $s, UpdateContext $ctx): void
    {
        $result = $this->api->resolveByChat($s->chatId);

        if (!$result->ok()) {
            if (ErrorMap::isTransport($result) || ErrorMap::isDisabled($result) || $result->status >= 500) {
                $this->student->fail($s, $result);

                return;
            }

            $this->welcome($s);

            return;
        }

        $links = (array) ($result->data()['links'] ?? []);
        $active = array_values(array_filter(
            $links,
            static fn ($link) => ($link['is_active'] ?? true) !== false
        ));

        if ($active === []) {
            $this->welcome($s);

            return;
        }

        if (count($active) === 1) {
            $this->activate($s, $ctx, $active[0]);

            return;
        }

        $s->payload['multi'] = true;
        $this->chooseRole($s, $active);
    }

    private function welcome(ChatState $s): void
    {
        $this->student->reset($s);
        $loginUrl = (string) ($this->config->get('BOT_LOGIN_URL', '') ?? '');
        $this->screens->show($s, WelcomeScreen::make($loginUrl), false);
    }

    /** @param list<array<string,mixed>> $links */
    private function chooseRole(ChatState $s, array $links): void
    {
        $rows = [];
        foreach ($links as $link) {
            $role = (string) ($link['role'] ?? '');
            $callback = $role === 'student' ? KeyboardKit::CB_ROLE_STUDENT : KeyboardKit::CB_ROLE_SUPPORTER;
            $label = $role === 'student' ? Lang::t('btn.role_student') : Lang::t('btn.role_supporter');
            $rows[] = [KeyboardKit::btn($label, $callback)];
        }

        $this->screens->show($s, new Screen(Lang::t('link.choose_role'), $rows), false);
    }

    private function activateRole(ChatState $s, UpdateContext $ctx, string $role): void
    {
        $result = $this->api->resolveByChat($s->chatId);
        $links = $result->ok() ? (array) ($result->data()['links'] ?? []) : [];

        foreach ($links as $link) {
            if (($link['role'] ?? null) === $role) {
                $this->activate($s, $ctx, $link);

                return;
            }
        }

        $this->welcome($s);
    }

    /** @param array<string,mixed> $link */
    private function activate(ChatState $s, UpdateContext $ctx, array $link): void
    {
        $role = (string) ($link['role'] ?? '');

        if ($role !== 'student') {
            $this->student->reset($s);
            $this->tg->removeReplyKeyboard($s->chatId, Lang::t('link.supporter_soon'));

            return;
        }

        $s->role = 'student';
        $s->mode = 'idle';
        if ($ctx->userId > 0) {
            $s->telegramUserId = $ctx->userId;
        }
        $s->payload['name'] = (string) ($link['name'] ?? '');
        $s->payload['account_id'] = (int) ($link['account_id'] ?? 0);

        $this->tg->setReplyKeyboard(
            $s->chatId,
            KeyboardKit::studentMenu(),
            Lang::t('link.success', ['name' => $s->payload['name']])
        );

        $this->student->home($s, $ctx, true);
    }
}
