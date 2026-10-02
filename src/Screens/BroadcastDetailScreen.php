<?php
declare(strict_types=1);

namespace App\Screens;

use App\Lang;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;

final class BroadcastDetailScreen
{
    /**
     * @param array<string,mixed> $d
     *  id, audience_label, day_label, summary (pending/sent/failed/blocked), recipients (list of label)
     */
    public static function make(array $d): Screen
    {
        $text = Lang::t('bc.detail_title', ['id' => (string) ($d['id'] ?? '')]);
        $text .= "\n" . Lang::t('bc.preview_audience', ['audience' => (string) ($d['audience_label'] ?? '')]);
        if (!empty($d['day_label'])) {
            $text .= "\n" . Lang::t('bc.detail_day', ['date' => (string) $d['day_label']]);
        }

        /** @var array<string,mixed> $summary */
        $summary = (array) ($d['summary'] ?? []);
        $text .= "\n" . Lang::t('bc.sent_summary', [
            'sent' => (string) ($summary['sent'] ?? 0),
            'blocked' => (string) ($summary['blocked'] ?? 0),
            'failed' => (string) ($summary['failed'] ?? 0),
            'pending' => (string) ($summary['pending'] ?? 0),
        ]);

        /** @var list<string> $recipients */
        $recipients = (array) ($d['recipients'] ?? []);
        if ($recipients !== []) {
            $text .= "\n\n" . implode("\n", $recipients);
        }

        $keyboard = [
            [KeyboardKit::btn(Lang::t('btn.broadcast_history'), KeyboardKit::CB_BC_LIST)],
            [KeyboardKit::btn(Lang::t('btn.home'), KeyboardKit::CB_SUPPORTER_HOME)],
        ];

        return new Screen($text, $keyboard);
    }
}
