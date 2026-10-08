<?php
declare(strict_types=1);

namespace App\Screens;

use App\Html;
use App\Lang;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;

final class BroadcastListScreen
{
    /**
     * @param array<string,mixed> $d
     *  items (list of label/callback)
     */
    public static function make(array $d): Screen
    {
        /** @var list<array<string,mixed>> $items */
        $items = (array) ($d['items'] ?? []);

        $lines = [];
        $lines[] = '<b>' . Lang::t('bc.list_title') . '</b>';
        $lines[] = Html::SEP;

        if ($items === []) {
            $lines[] = Lang::t('bc.list_empty');
        }

        $keyboard = [];
        foreach ($items as $item) {
            $keyboard[] = [KeyboardKit::btn(
                (string) ($item['label'] ?? ''),
                (string) ($item['callback'] ?? KeyboardKit::CB_NOP)
            )];
        }

        $keyboard[] = [KeyboardKit::btn(Lang::t('btn.home'), KeyboardKit::CB_SUPPORTER_HOME)];

        return new Screen(implode("\n", $lines), $keyboard);
    }
}