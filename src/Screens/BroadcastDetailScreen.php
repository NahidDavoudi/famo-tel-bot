<?php
declare(strict_types=1);

namespace App\Screens;

use App\Html;
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
        $summary = (array) ($d['summary'] ?? []);

        $lines = [];
        $lines[] = '<b>' . Lang::t('bc.detail_title', ['id' => (string) ($d['id'] ?? '')]) . '</b>';
        $lines[] = Html::SEP;
        $lines[] = Lang::t('bc.preview_audience', ['audience' => (string) ($d['audience_label'] ?? '')]);

        if (!empty($d['day_label'])) {
            $lines[] = Lang::t('bc.detail_day', ['date' => (string) $d['day_label']]);
        }

        $lines[] = '';
        $lines[] = '<b>' . Lang::t('bc.summary_title') . '</b>';
        $lines[] = Lang::t('bc.summary_sent',    ['k' => (int) ($summary['sent'] ?? 0)]);
        $lines[] = Lang::t('bc.summary_failed',  ['k' => (int) ($summary['failed'] ?? 0)]);
        $lines[] = Lang::t('bc.summary_blocked', ['k' => (int) ($summary['blocked'] ?? 0)]);
        $lines[] = Lang::t('bc.summary_pending', ['k' => (int) ($summary['pending'] ?? 0)]);

        /** @var list<string> $recipients */
        $recipients = (array) ($d['recipients'] ?? []);
        if ($recipients !== []) {
            $lines[] = '';
            $lines[] = '<b>' . Lang::t('bc.recipients_title') . '</b>';
            foreach ($recipients as $recipient) {
                $lines[] = '· ' . Html::escape((string) $recipient);
            }
        }

        $keyboard = [
            [KeyboardKit::btn(Lang::t('btn.broadcast_history'), KeyboardKit::CB_BC_LIST)],
            [KeyboardKit::btn(Lang::t('btn.home'), KeyboardKit::CB_SUPPORTER_HOME)],
        ];

        return new Screen(implode("\n", $lines), $keyboard);
    }
}