<?php
declare(strict_types=1);

namespace App\Handlers;

use App\Config;
use App\Famo\FamoApi;
use App\Lang;
use App\Screens\AccountScreen;
use App\State\ChatState;
use App\Telegram\KeyboardKit;
use App\Telegram\ScreenManager;
use App\Telegram\TelegramApi;
use App\Telegram\UpdateContext;

final class AccountHandler
{
    public function __construct(
        private readonly FamoApi $api,
        private readonly TelegramApi $tg,
        private readonly ScreenManager $screens,
        private readonly StudentHandler $student,
        private readonly LinkHandler $link,
        private readonly Config $config,
    ) {}

    public function onCallback(ChatState $s, string $data, UpdateContext $ctx): void
    {
        match ($data) {
            KeyboardKit::CB_ACCOUNT => $this->show($s, false),
            KeyboardKit::CB_UNLINK => $this->show($s, true),
            KeyboardKit::CB_AC_HOME => $this->student->home($s, $ctx, false),
            KeyboardKit::CB_SWITCH => $this->link->pickRole($s, $ctx),
            KeyboardKit::CB_UNLINK_OK => $this->unlink($s, $ctx),
            default => null,
        };
    }

    private function show(ChatState $s, bool $confirm): void
    {
        if ($s->role === null) {
            return;
        }

        $this->screens->show($s, AccountScreen::make([
            'name' => (string) ($s->payload['name'] ?? ''),
            'role' => $s->role === 'student' ? Lang::t('btn.role_student') : Lang::t('btn.role_supporter'),
            'supporter' => (string) ($s->payload['supporter'] ?? ''),
            'can_switch' => (bool) ($s->payload['multi'] ?? false),
            'login_url' => (string) ($this->config->get('BOT_LOGIN_URL', '') ?? ''),
            'confirm_unlink' => $confirm,
        ]), true);
    }

    private function unlink(ChatState $s, UpdateContext $ctx): void
    {
        $userId = (int) ($s->telegramUserId ?? $ctx->userId);
        $result = $this->api->unlink($userId, $s->role);

        if (!$result->ok()) {
            $this->student->fail($s, $result);

            return;
        }

        $this->student->reset($s);
        $this->tg->removeReplyKeyboard($s->chatId, Lang::t('link.unlinked'));
        $this->link->pickRole($s, $ctx);
    }
}
