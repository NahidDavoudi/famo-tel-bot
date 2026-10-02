<?php
declare(strict_types=1);

namespace App\Screens;

use App\Lang;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;

final class BroadcastMenuScreen
{
    public static function make(): Screen
    {
        $keyboard = [
            [KeyboardKit::btn(Lang::t('bc.audience_no_report'), KeyboardKit::CB_BC_AUDIENCE . 'no_report_today')],
            [KeyboardKit::btn(Lang::t('bc.audience_all'), KeyboardKit::CB_BC_AUDIENCE . 'all_students')],
            [KeyboardKit::btn(Lang::t('btn.broadcast_history'), KeyboardKit::CB_BC_LIST)],
            [KeyboardKit::btn(Lang::t('btn.home'), KeyboardKit::CB_SUPPORTER_HOME)],
        ];

        return new Screen(Lang::t('bc.menu_title') . "\n\n" . Lang::t('bc.menu_hint'), $keyboard);
    }
}
