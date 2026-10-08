<?php
declare(strict_types=1);

namespace App\Screens;

use App\Lang;
use App\Num;
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

        $text = Lang::t('home.greeting', ['name' => $name]) . "\n━━━━━━━━━━━━━━━━";

        if (!$hasSupporter) {
            $text .= "\n\n" . Lang::t('home.no_supporter');
            $keyboard = [[KeyboardKit::btn(Lang::t('btn.account'), KeyboardKit::CB_ACCOUNT)]];

            return new Screen($text, $keyboard);
        }

        $supporter = (string) ($d['supporter'] ?? '');
        $date = (string) ($d['today_date'] ?? '');
        $todaySent = (bool) ($d['today_sent'] ?? false);

        if ($supporter !== '') {
            $text .= "\n" . Lang::t('home.supporter', ['name' => $supporter]);
        }

        $text .= "\n\n" . Lang::t('home.today', ['date' => $date]) . "\n";
        $text .= $todaySent ? Lang::t('home.today_sent') : Lang::t('home.today_pending');

        if ($unread > 0) {
            $text .= "\n" . Lang::t('home.unread', ['k' => Num::fa($unread)]);
        }

        $text .= "\n\n" . Lang::t('home.hint');

        $keyboard = [[
            KeyboardKit::btn(Lang::t('btn.today'), KeyboardKit::CB_TODAY),
            KeyboardKit::btn(Lang::t('btn.week'), KeyboardKit::CB_WEEK),
        ]];

        if ($unread > 0) {
            $keyboard[] = [KeyboardKit::btn(Lang::t('btn.new_replies', ['k' => Num::fa($unread)]), KeyboardKit::CB_NEW)];
        }

        $keyboard[] = [KeyboardKit::btn(Lang::t('btn.account'), KeyboardKit::CB_ACCOUNT)];

        return new Screen($text, $keyboard);
    }
}
