<?php
declare(strict_types=1);

namespace App\Screens;

use App\Html;
use App\Lang;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;

final class DayScreen
{
    /**
     * @param array<string,mixed> $d
     *  day, weekday, date_label, messages (list of who/time/body/files), page, pages,
     *  has_files, is_today, and optional: title, empty_text, nav_prefix, files_prefix,
     *  reply_callback, footer (list of button rows)
     */
    public static function make(array $d): Screen
    {
        $day = (string) ($d['day'] ?? '');
        $page = (int) ($d['page'] ?? 1);
        $pages = (int) ($d['pages'] ?? 1);
        $isToday = (bool) ($d['is_today'] ?? false);

        $title = (string) ($d['title'] ?? '');
        if ($title === '') {
            $title = Lang::t('day.title', [
                'weekday' => (string) ($d['weekday'] ?? ''),
                'date' => (string) ($d['date_label'] ?? $day),
            ]);
        }

        $emptyText = (string) ($d['empty_text'] ?? ($isToday ? Lang::t('day.empty_today') : Lang::t('day.empty')));

        /** @var list<array<string,mixed>> $messages */
        $messages = (array) ($d['messages'] ?? []);

        $lines = [];
        $lines[] = '<b>' . $title . '</b>';
        $lines[] = Html::SEP;

        if ($messages === []) {
            $lines[] = '';
            $lines[] = $emptyText;
        } else {
            $blocks = [];
            foreach ($messages as $message) {
                $blocks[] = self::renderMessage($message);
            }
            $lines[] = '';
            $lines[] = implode("\n\n", $blocks);
        }

        if ($pages > 1) {
            $lines[] = '';
            $lines[] = Lang::t('day.page', ['page' => $page, 'pages' => $pages]);
        }

        $navPrefix = (string) ($d['nav_prefix'] ?? KeyboardKit::CB_DAY);
        $filesPrefix = (string) ($d['files_prefix'] ?? KeyboardKit::CB_FILES);

        $keyboard = [];

        if (!empty($d['reply_callback'])) {
            $keyboard[] = [KeyboardKit::btn(Lang::t('btn.reply'), (string) $d['reply_callback'])];
        }

        if (!empty($d['has_files'])) {
            $keyboard[] = [KeyboardKit::btn(
                Lang::t('btn.files'),
                $filesPrefix . $day . ':' . $page
            )];
        }

        $nav = [];
        if ($page < $pages) {
            $nav[] = KeyboardKit::btn(Lang::t('btn.newer'), $navPrefix . $day . ':' . ($page + 1));
        }
        if ($page > 1) {
            $nav[] = KeyboardKit::btn(Lang::t('btn.older'), $navPrefix . $day . ':' . ($page - 1));
        }
        if ($nav !== []) {
            $keyboard[] = $nav;
        }

        $keyboard[] = [KeyboardKit::btn(Lang::t('btn.refresh'), $navPrefix . $day . ':' . $page)];

        $footer = $d['footer'] ?? null;
        if (is_array($footer) && $footer !== []) {
            foreach ($footer as $row) {
                $keyboard[] = $row;
            }
        } else {
            $footerRow = [];
            if (!$isToday) {
                $footerRow[] = KeyboardKit::btn(Lang::t('btn.week'), KeyboardKit::CB_WEEK);
            }
            $footerRow[] = KeyboardKit::btn(Lang::t('btn.home'), KeyboardKit::CB_HOME);
            $keyboard[] = $footerRow;
        }

        return new Screen(implode("\n", $lines), $keyboard);
    }

    /**
     * Render a single message:
     *   <b>who</b> · <code>time</code>
     *   <blockquote>body</blockquote>
     *   files
     *
     * @param array<string,mixed> $message
     */
    private static function renderMessage(array $message): string
    {
        $who  = (string) ($message['who'] ?? '');
        $time = (string) ($message['time'] ?? '');
        $body = (string) ($message['body'] ?? '');
        $files = $message['files'] ?? null;

        $parts = [];

        // 1) Header: who · time (outside the quote)
        $header = [];
        if ($who !== '') {
            $header[] = Html::bold($who);
        }
        if ($time !== '') {
            $header[] = Html::code($time);
        }
        if ($header !== []) {
            $parts[] = implode(' · ', $header);
        }

        // 2) Body: only this part goes inside the quote
        if ($body !== '') {
            $parts[] = Html::quote($body);
        }

        // 3) Files: outside the quote
        if (is_string($files) && $files !== '') {
            $parts[] = $files;
        }

        return implode("\n", $parts);
    }
}