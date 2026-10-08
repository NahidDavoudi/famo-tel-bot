<?php
declare(strict_types=1);

namespace App\Screens;

use App\Html;
use App\Lang;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;

final class HelpScreen
{
    public static function make(): Screen
    {
        $text = '<b>' . Lang::t('help.title') . '</b>'
              . "\n" . Html::SEP
              . "\n" . Lang::t('help.text');

        return new Screen($text, [[
            KeyboardKit::btn(Lang::t('btn.home'), KeyboardKit::CB_HOME),
        ]]);
    }
}