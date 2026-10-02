<?php
declare(strict_types=1);

namespace App\Screens;

use App\Lang;
use App\Num;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;

final class SupporterStudentsScreen
{
    /**
     * @param array<string,mixed> $d
     *  total, day_label, students (list of name/markers/callback)
     */
    public static function make(array $d): Screen
    {
        /** @var list<array<string,mixed>> $students */
        $students = (array) ($d['students'] ?? []);
        $total = (int) ($d['total'] ?? count($students));
        $dayLabel = (string) ($d['day_label'] ?? '');

        $text = Lang::t('sup.students_title');
        $text .= "\n" . Lang::t('sup.students_count', ['total' => Num::fa($total)]);
        if ($dayLabel !== '') {
            $text .= "\n" . Lang::t('sup.students_day', ['date' => $dayLabel]);
        }

        if ($students === []) {
            $text .= "\n\n" . Lang::t('sup.no_students');
        }

        $keyboard = [];
        foreach ($students as $student) {
            $keyboard[] = [KeyboardKit::btn(
                (string) ($student['name'] ?? '') . (string) ($student['markers'] ?? ''),
                (string) ($student['callback'] ?? KeyboardKit::CB_NOP)
            )];
        }

        foreach (SupporterInboxScreen::navRows() as $row) {
            $keyboard[] = $row;
        }

        return new Screen($text, $keyboard);
    }
}
