<?php
declare(strict_types=1);

namespace App\Screens;

use App\Lang;
use App\Num;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;

final class BroadcastPreviewScreen
{
    /**
     * @param array<string,mixed> $d
     *  audience_label, count, remaining, limit
     */
    public static function make(array $d): Screen
    {
        $text = Lang::t('bc.preview_title');
        $text .= "\n" . Lang::t('bc.preview_audience', ['audience' => (string) ($d['audience_label'] ?? '')]);
        $text .= "\n" . Lang::t('bc.preview_count', ['k' => Num::fa((int) ($d['count'] ?? 0))]);

        $remaining = $d['remaining'] ?? null;
        $limit = $d['limit'] ?? null;
        if ($remaining !== null && $limit !== null) {
            $text .= "\n" . Lang::t('bc.preview_remaining', [
                'remaining' => Num::fa((int) $remaining),
                'limit' => Num::fa((int) $limit),
            ]);
        }

        $text .= "\n\n" . Lang::t('bc.preview_hint');

        $keyboard = [[
            KeyboardKit::btn(Lang::t('bc.send'), KeyboardKit::CB_BC_SEND),
            KeyboardKit::btn(Lang::t('bc.cancel'), KeyboardKit::CB_BC_CANCEL),
        ]];

        return new Screen($text, $keyboard);
    }
}
