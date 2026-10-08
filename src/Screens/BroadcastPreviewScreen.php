<?php
declare(strict_types=1);

namespace App\Screens;

use App\Html;
use App\Lang;
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
        $lines = [];
        $lines[] = '<b>' . Lang::t('bc.preview_title') . '</b>';
        $lines[] = Html::SEP;
        $lines[] = Lang::t('bc.preview_audience', ['audience' => (string) ($d['audience_label'] ?? '')]);
        $lines[] = Lang::t('bc.preview_count', ['k' => (int) ($d['count'] ?? 0)]);

        $remaining = $d['remaining'] ?? null;
        $limit = $d['limit'] ?? null;
        if ($remaining !== null && $limit !== null) {
            $lines[] = Lang::t('bc.preview_remaining', [
                'remaining' => (int) $remaining,
                'limit' => (int) $limit,
            ]);
        }

        $lines[] = '';
        $lines[] = Lang::t('bc.preview_hint');

        $keyboard = [[
            KeyboardKit::btn(Lang::t('bc.send'), KeyboardKit::CB_BC_SEND),
            KeyboardKit::btn(Lang::t('bc.cancel'), KeyboardKit::CB_BC_CANCEL),
        ]];

        return new Screen(implode("\n", $lines), $keyboard);
    }
}