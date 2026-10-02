<?php
declare(strict_types=1);

namespace App\Screens;

use App\Lang;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;

final class HelpScreen
{
    public static function make(): Screen
    {
        return new Screen(Lang::t('help.text'), [[
            KeyboardKit::btn(Lang::t('btn.home'), KeyboardKit::CB_HOME),
        ]]);
    }
}
