<?php
declare(strict_types=1);

namespace App\Screens;

use App\Html;
use App\Lang;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;

final class HomeScreen
{
    /** @param array<string,mixed> $d */
    public static function make(array $d): Screen
    {
        $name = (string) ($d['name'] ?? '');
        $hasSupporter = (bool) ($d['has_supporter'] ?? false);
        $unread = (int) ($d['unread'] ?? 0);
        $supporter = (string) ($d['supporter'] ?? '');
        $date = (string) ($d['today_date'] ?? '');
        $todaySent = (bool) ($d['today_sent'] ?? false);

        $lines = [];
        $lines[] = '<b>' . Lang::t('home.greeting', ['name' => $name]) . '</b>';
        $lines[] = Html::SEP;

        if (!$hasSupporter) {
            $lines[] = Lang::t('home.no_supporter');

            return new Screen(
                implode("\n", $lines),
                [[KeyboardKit::btn(Lang::t('btn.account'), KeyboardKit::CB_ACCOUNT)]]
            );
        }

        if ($supporter !== '') {
            $lines[] = Lang::t('home.supporter', ['name' => $supporter]);
        }

        $status = $todaySent ? Lang::t('home.status_sent') : Lang::t('home.status_pending');
        $lines[] = '';
        $lines[] = Lang::t('home.today_label') . ' ' . Html::code($date) . ' · ' . $status;

        if ($unread > 0) {
            $lines[] = Lang::t('home.unread_label') . ' ' . Html::num($unread);
        }

        $keyboard = [[
            KeyboardKit::btn(Lang::t('btn.today'), KeyboardKit::CB_TODAY),
            KeyboardKit::btn(Lang::t('btn.week'), KeyboardKit::CB_WEEK),
        ]];

        if ($unread > 0) {
            $keyboard[] = [KeyboardKit::btn(Lang::t('btn.new_replies'), KeyboardKit::CB_NEW)];
        }

        $keyboard[] = [KeyboardKit::btn(Lang::t('btn.account'), KeyboardKit::CB_ACCOUNT)];

        return new Screen(implode("\n", $lines), $keyboard);
    }
}