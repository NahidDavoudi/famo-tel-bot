<?php
declare(strict_types=1);

namespace App\Screens;

use App\Lang;
use App\Num;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;

final class DayScreen
{
    /**
     * @param array<string,mixed> $d
     *  day, weekday, date_label, messages (list of who/time/body/files), page, pages,
     *  has_files, is_today
     */
    public static function make(array $d): Screen
    {
        $day = (string) ($d['day'] ?? '');
        $page = (int) ($d['page'] ?? 1);
        $pages = (int) ($d['pages'] ?? 1);
        $isToday = (bool) ($d['is_today'] ?? false);

        $title = Lang::t('day.title', [
            'weekday' => (string) ($d['weekday'] ?? ''),
            'date' => (string) ($d['date_label'] ?? $day),
        ]);

        /** @var list<array<string,mixed>> $messages */
        $messages = (array) ($d['messages'] ?? []);

        if ($messages === []) {
            $body = $isToday ? Lang::t('day.empty_today') : Lang::t('day.empty');
            $text = $title . "\n\n" . $body;
        } else {
            $blocks = [];
            foreach ($messages as $message) {
                $line = Lang::t('day.line', [
                    'who' => (string) ($message['who'] ?? ''),
                    'time' => (string) ($message['time'] ?? ''),
                    'text' => (string) ($message['body'] ?? ''),
                ]);
                $files = $message['files'] ?? null;
                if (is_string($files) && $files !== '') {
                    $line .= "\n" . $files;
                }
                $blocks[] = $line;
            }
            $text = $title . "\n\n" . implode("\n\n", $blocks);
        }

        if ($pages > 1) {
            $text .= "\n\n" . Lang::t('day.page', ['page' => Num::fa($page), 'pages' => Num::fa($pages)]);
        }

        $keyboard = [];

        if (!empty($d['has_files'])) {
            $keyboard[] = [KeyboardKit::btn(
                Lang::t('btn.files'),
                KeyboardKit::CB_FILES . $day . ':' . $page
            )];
        }

        $nav = [];
        if ($page < $pages) {
            $nav[] = KeyboardKit::btn(Lang::t('btn.newer'), KeyboardKit::CB_DAY . $day . ':' . ($page + 1));
        }
        if ($page > 1) {
            $nav[] = KeyboardKit::btn(Lang::t('btn.older'), KeyboardKit::CB_DAY . $day . ':' . ($page - 1));
        }
        if ($nav !== []) {
            $keyboard[] = $nav;
        }

        $keyboard[] = [KeyboardKit::btn(Lang::t('btn.refresh'), KeyboardKit::CB_DAY . $day . ':' . $page)];

        $footer = [];
        if (!$isToday) {
            $footer[] = KeyboardKit::btn(Lang::t('btn.week'), KeyboardKit::CB_WEEK);
        }
        $footer[] = KeyboardKit::btn(Lang::t('btn.home'), KeyboardKit::CB_HOME);
        $keyboard[] = $footer;

        return new Screen($text, $keyboard);
    }
}
