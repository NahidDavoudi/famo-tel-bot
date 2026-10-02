<?php
declare(strict_types=1);

/**
 * Renders the student screens with sample data so the texts/buttons can be
 * eyeballed locally without Telegram or the Famo API.
 *
 * Usage: php tools/replay.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Lang;
use App\Screens\AccountScreen;
use App\Screens\DayScreen;
use App\Screens\HomeScreen;
use App\Screens\WeekScreen;
use App\Screens\WelcomeScreen;
use App\Telegram\Screen;

Lang::boot();

function dumpScreen(string $title, Screen $screen): void
{
    echo "===== {$title} =====\n";
    echo $screen->text . "\n";
    echo "-- keyboard --\n";
    if ($screen->keyboard === null || $screen->keyboard === []) {
        echo "(none)\n";
    } else {
        foreach ($screen->keyboard as $row) {
            $labels = array_map(
                static fn ($button) => $button['text'] . ' [' . ($button['callback_data'] ?? $button['url'] ?? '') . ']',
                $row
            );
            echo implode(' | ', $labels) . "\n";
        }
    }
    echo "\n";
}

dumpScreen('Welcome', WelcomeScreen::make('https://auth.famoacademy.ir'));

dumpScreen('Home', HomeScreen::make([
    'name' => 'مریم',
    'has_supporter' => true,
    'supporter' => 'خانم احمدی',
    'today_date' => '۱۰ مهر',
    'today_sent' => false,
    'unread' => 2,
]));

dumpScreen('Week', WeekScreen::make([
    'title' => '۱۰ تا ۱۶ مهر',
    'done' => 3,
    'total' => 7,
    'days' => [
        ['label' => 'شنبه', 'markers' => ' ✅💬', 'callback' => 'st:d:2026-10-03:1'],
        ['label' => 'یکشنبه', 'markers' => ' ❌', 'callback' => 'st:d:2026-10-04:1'],
        ['label' => 'دوشنبه', 'markers' => ' ✅🔵', 'callback' => 'st:d:2026-10-05:1'],
    ],
    'prev' => '2026-09-26',
    'next' => null,
]));

dumpScreen('Day', DayScreen::make([
    'day' => '2026-10-03',
    'weekday' => 'شنبه',
    'date_label' => '۱۰ مهر',
    'messages' => [
        ['who' => 'شما', 'time' => '14:32', 'body' => 'امروز ۳ ساعت ریاضی خوندم', 'files' => ''],
        ['who' => 'پشتیبان', 'time' => '15:10', 'body' => 'عالی! فردا حل تست رو شروع کن', 'files' => ''],
    ],
    'page' => 1,
    'pages' => 2,
    'has_files' => true,
    'is_today' => true,
]));

dumpScreen('Account', AccountScreen::make([
    'name' => 'مریم',
    'role' => 'دانشجو',
    'supporter' => 'خانم احمدی',
    'can_switch' => false,
    'login_url' => 'https://auth.famoacademy.ir',
    'confirm_unlink' => false,
]));
