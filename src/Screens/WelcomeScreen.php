<?php
declare(strict_types=1);

namespace App\Screens;

use App\Lang;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;

final class WelcomeScreen
{
    public static function make(string $loginUrl): Screen
    {
        $keyboard = [
            [KeyboardKit::urlBtn(Lang::t('welcome.login'), $loginUrl)],
            [KeyboardKit::btn(Lang::t('welcome.check'), KeyboardKit::CB_CHECK)],
        ];

        return new Screen(Lang::t('welcome.text'), $keyboard);
    }
}