<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Famo\ApiResult;
use App\Famo\ErrorMap;
use App\Lang;
use App\RawHtml;
use App\Router;
use App\Screens\HomeScreen;
use App\Screens\WeekScreen;
use App\State\StateStore;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;
use Telegram\Bot\Objects\Update;

$passed = 0;
$failed = 0;

function check(string $name, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "ok   {$name}\n";
    } else {
        $failed++;
        echo "FAIL {$name}\n";
    }
}

Lang::boot();

check('lang loads welcome text', Lang::t('welcome.text') !== 'welcome.text');
check('lang escapes params', str_contains(Lang::t('home.greeting', ['name' => '<b>x</b>']), '&lt;b&gt;'));
check('lang keeps raw html when asked', str_contains(Lang::t('home.greeting', ['name' => new RawHtml('<b>x</b>')]), '<b>x</b>'));

check('label route today', KeyboardKit::labelRoute(KeyboardKit::LBL_TODAY) === KeyboardKit::ROUTE_TODAY);
check('label route week', KeyboardKit::labelRoute(KeyboardKit::LBL_WEEK) === KeyboardKit::ROUTE_WEEK);
check('label route home', KeyboardKit::labelRoute(KeyboardKit::LBL_HOME) === KeyboardKit::ROUTE_HOME);
check('label route unknown', KeyboardKit::labelRoute('سلام') === null);

check('transport maps to unavailable', ErrorMap::toPersian(null, null, 'timeout') === Lang::t('error.system_unavailable'));
check('429 maps to daily limit', ErrorMap::toPersian(null, 429) === Lang::t('error.daily_limit'));
check('BOT_UNAUTHORIZED is unlinked', ErrorMap::isUnlinked(new ApiResult(401, null, 'BOT_UNAUTHORIZED')));
check('unknown code is generic', ErrorMap::toPersian('WHAT') === Lang::t('error.generic'));

$home = HomeScreen::make([
    'name' => 'علی', 'has_supporter' => true, 'today_date' => '۱۰ مهر',
    'today_sent' => false, 'unread' => 2,
]);
check('home returns screen', $home instanceof Screen);
check('home has inline keyboard', is_array($home->keyboard) && $home->keyboard !== []);
check('home keyboard rows <= 8', count($home->keyboard) <= 8);

$week = WeekScreen::make([
    'title' => '۱۰ تا ۱۶ مهر', 'done' => 3, 'total' => 7,
    'days' => [
        ['label' => 'شنبه', 'markers' => ' ✅', 'callback' => 'st:d:2026-10-03:1'],
        ['label' => 'یکشنبه', 'markers' => ' ⏳', 'callback' => 'nop'],
    ],
    'prev' => '2026-09-26', 'next' => null,
]);
check('week returns screen', $week instanceof Screen);
check('week keyboard rows <= 8', count($week->keyboard) <= 8);

$callbacks = [
    KeyboardKit::CB_CHECK,
    KeyboardKit::CB_ACCOUNT,
    KeyboardKit::CB_UNLINK,
    KeyboardKit::CB_UNLINK_OK,
    KeyboardKit::CB_TODAY,
    KeyboardKit::CB_NEW,
    KeyboardKit::CB_HOME,
];
foreach ($callbacks as $cb) {
    check("callback <= 64 bytes: {$cb}", strlen($cb) <= 64);
}

$dbPath = sys_get_temp_dir() . '/famo-test-' . uniqid() . '.sqlite';
$store = new StateStore($dbPath);
check('update not seen first time', $store->seen(111) === false);
check('update seen second time', $store->seen(111) === true);

$state = $store->load(555);
$state->role = 'student';
$state->payload = ['name' => 'مریم'];
$store->save($state);
$reloaded = $store->load(555);
check('state persists role', $reloaded->role === 'student');
check('state persists payload json', ($reloaded->payload['name'] ?? null) === 'مریم');

@unlink($dbPath);
@unlink($dbPath . '-wal');
@unlink($dbPath . '-shm');

$router = (new ReflectionClass(Router::class))->newInstanceWithoutConstructor();
$unknownUpdate = new Update([
    'update_id' => 0,
    'message_reaction' => [
        'chat' => ['id' => 1, 'type' => 'private'],
        'user' => ['id' => 1],
        'message_id' => 9,
        'date' => 1,
        'old_reaction' => [],
        'new_reaction' => [],
    ],
]);
$routerThrew = false;
try {
    $router->route($unknownUpdate);
} catch (Throwable $e) {
    $routerThrew = true;
}
check('router ignores update without a message without fatal', $routerThrew === false);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
