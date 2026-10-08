<?php
declare(strict_types=1);

namespace App\Screens;

use App\Html;
use App\Lang;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;

final class AccountScreen
{
    /** @param array<string,mixed> $d */
    public static function make(array $d): Screen
    {
        if (!empty($d['confirm_unlink'])) {
            return new Screen(Lang::t('account.confirm'), [[
                KeyboardKit::btn(Lang::t('btn.confirm_unlink'), KeyboardKit::CB_UNLINK_OK),
                KeyboardKit::btn(Lang::t('btn.cancel'), KeyboardKit::CB_AC_HOME),
            ]]);
        }

        $lines = [];
        $lines[] = '<b>' . Lang::t('account.title') . '</b>';
        $lines[] = Html::SEP;
        $lines[] = Lang::t('account.name', ['name' => (string) ($d['name'] ?? '')]);
        $lines[] = Lang::t('account.role', ['role' => (string) ($d['role'] ?? '')]);

        $supporter = (string) ($d['supporter'] ?? '');
        if ($supporter !== '') {
            $lines[] = Lang::t('account.supporter', ['name' => $supporter]);
        }

        $keyboard = [];
        if (!empty($d['can_switch'])) {
            $keyboard[] = [KeyboardKit::btn(Lang::t('btn.switch'), KeyboardKit::CB_SWITCH)];
        }

        $loginUrl = (string) ($d['login_url'] ?? '');
        if ($loginUrl !== '') {
            $keyboard[] = [KeyboardKit::urlBtn(Lang::t('btn.link_other'), $loginUrl)];
        }

        $keyboard[] = [KeyboardKit::btn(Lang::t('btn.unlink'), KeyboardKit::CB_UNLINK)];
        $keyboard[] = [KeyboardKit::btn(Lang::t('btn.back'), KeyboardKit::CB_HOME)];

        return new Screen(implode("\n", $lines), $keyboard);
    }
}