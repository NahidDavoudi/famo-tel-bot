<?php
declare(strict_types=1);

namespace App\Screens;

use App\Html;
use App\Lang;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;

final class WeekScreen
{
    /**
     * @param array<string,mixed> $d
     *  title, done, total, days (label, callback, markers), prev, next
     */
    public static function make(array $d): Screen
    {
        $done = (int) ($d['done'] ?? 0);
        $total = (int) ($d['total'] ?? 0);

        $lines = [];
        $lines[] = '<b>' . Lang::t('week.title', ['range' => (string) ($d['title'] ?? '')]) . '</b>';
        $lines[] = Html::SEP;
        $lines[] = Lang::t('week.summary', [
            'done' => $done,
            'total' => $total,
        ]);
        $lines[] = '';
        $lines[] = Lang::t('week.legend');

        /** @var list<array<string,mixed>> $days */
        $days = (array) ($d['days'] ?? []);

        $keyboard = [];
        $row = [];
        foreach ($days as $day) {
            $row[] = KeyboardKit::btn(
                (string) ($day['label'] ?? '') . (string) ($day['markers'] ?? ''),
                (string) ($day['callback'] ?? KeyboardKit::CB_NOP)
            );
            if (count($row) === 2) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $keyboard[] = $row;
        }

        $weekNav = [];
        if (!empty($d['next'])) {
            $weekNav[] = KeyboardKit::btn(Lang::t('btn.next_week'), KeyboardKit::CB_WEEK_AT . (string) $d['next']);
        }
        if (!empty($d['prev'])) {
            $weekNav[] = KeyboardKit::btn(Lang::t('btn.prev_week'), KeyboardKit::CB_WEEK_AT . (string) $d['prev']);
        }
        if ($weekNav !== []) {
            $keyboard[] = $weekNav;
        }

        $keyboard[] = [KeyboardKit::btn(Lang::t('btn.home'), KeyboardKit::CB_HOME)];

        return new Screen(implode("\n", $lines), $keyboard);
    }
}