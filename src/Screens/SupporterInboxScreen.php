<?php
declare(strict_types=1);

namespace App\Screens;

use App\Html;
use App\Lang;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;

final class SupporterInboxScreen
{
    /**
     * @param array<string,mixed> $d
     *  total, page, pages, students (list of name/unread/callback)
     */
    public static function make(array $d): Screen
    {
        /** @var list<array<string,mixed>> $students */
        $students = (array) ($d['students'] ?? []);
        $total = (int) ($d['total'] ?? count($students));
        $page = (int) ($d['page'] ?? 1);
        $pages = (int) ($d['pages'] ?? 1);

        $lines = [];
        $lines[] = '<b>' . Lang::t('sup.inbox_title') . '</b>';
        $lines[] = Html::SEP;

        if ($students === []) {
            $lines[] = Lang::t('sup.inbox_empty');
        } else {
            $lines[] = Lang::t('sup.inbox_count', ['k' => $total]);
        }

        if ($pages > 1) {
            $lines[] = '';
            $lines[] = Lang::t('day.page', ['page' => $page, 'pages' => $pages]);
        }

        $keyboard = [];
        foreach ($students as $student) {
            $name = (string) ($student['name'] ?? '');
            $unread = (int) ($student['unread'] ?? 0);
            $label = $unread > 0
                ? Lang::t('sup.student_unread', ['name' => $name, 'k' => $unread])
                : $name;
            $keyboard[] = [KeyboardKit::btn($label, (string) ($student['callback'] ?? KeyboardKit::CB_NOP))];
        }

        $nav = [];
        if ($page < $pages) {
            $nav[] = KeyboardKit::btn(Lang::t('btn.newer'), KeyboardKit::CB_INBOX_AT . ($page + 1));
        }
        if ($page > 1) {
            $nav[] = KeyboardKit::btn(Lang::t('btn.older'), KeyboardKit::CB_INBOX_AT . ($page - 1));
        }
        if ($nav !== []) {
            $keyboard[] = $nav;
        }

        foreach (self::navRows() as $row) {
            $keyboard[] = $row;
        }

        return new Screen(implode("\n", $lines), $keyboard);
    }

    /** @return list<list<array<string,string>>> */
    public static function navRows(): array
    {
        return [
            [
                KeyboardKit::btn(Lang::t('btn.inbox'), KeyboardKit::CB_SUPPORTER_HOME),
                KeyboardKit::btn(Lang::t('btn.students'), KeyboardKit::CB_STUDENTS),
            ],
            [
                KeyboardKit::btn(Lang::t('btn.broadcast'), KeyboardKit::CB_BROADCAST),
                KeyboardKit::btn(Lang::t('btn.account'), KeyboardKit::CB_ACCOUNT),
            ],
        ];
    }
}