<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/fakes.php';

use App\Config;
use App\Famo\ApiResult;
use App\Famo\ErrorMap;
use App\Famo\FamoApi;
use App\Handlers\AccountHandler;
use App\Handlers\BroadcastHandler;
use App\Handlers\LinkHandler;
use App\Handlers\StudentHandler;
use App\Handlers\SupporterHandler;
use App\Lang;
use App\RawHtml;
use App\Router;
use App\Screens\DayScreen;
use App\Screens\HomeScreen;
use App\Screens\WeekScreen;
use App\State\ChatState;
use App\State\StateStore;
use App\Telegram\KeyboardKit;
use App\Telegram\Screen;
use App\Telegram\ScreenManager;
use App\Telegram\TelegramApi;
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
check('CHAT_ALREADY_LINKED has dedicated text', ErrorMap::toPersian('CHAT_ALREADY_LINKED') === Lang::t('error.chat_already_linked'));
check('USER_ALREADY_LINKED has dedicated text', ErrorMap::toPersian('USER_ALREADY_LINKED') === Lang::t('error.user_already_linked'));
check('PHONE_ALREADY_REGISTERED has dedicated text', ErrorMap::toPersian('PHONE_ALREADY_REGISTERED') === Lang::t('error.phone_already_registered'));
check('VALIDATION_ERROR has dedicated text', ErrorMap::toPersian('VALIDATION_ERROR') === Lang::t('error.validation'));

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
$state->mode = 'signup_grade';
$state->payload = ['phone' => '+989121234567', 'full_name' => 'مریم'];
$store->save($state);
$reloadedSignup = $store->load(555);
check('signup state persists in existing SQLite payload', $reloadedSignup->mode === 'signup_grade'
    && ($reloadedSignup->payload['phone'] ?? null) === '+989121234567'
    && ($reloadedSignup->payload['full_name'] ?? null) === 'مریم');

$freshPath = sys_get_temp_dir() . '/famo-test-' . uniqid() . '.sqlite';
$freshStore = new StateStore($freshPath);
$freshState = $freshStore->load(31337);
$freshPdo = new PDO('sqlite:' . $freshPath);
$freshRow = $freshPdo->query('SELECT mode, payload, updated_at FROM chat_state WHERE chat_id = 31337')->fetch(PDO::FETCH_ASSOC);
$freshPdo = null;
check('load inserts a row for a missing chat', $freshRow !== false
    && $freshRow['mode'] === 'idle'
    && $freshRow['payload'] === '[]'
    && (int) $freshRow['updated_at'] > 0
    && $freshState->updatedAt === (int) $freshRow['updated_at']);

$stalePath = sys_get_temp_dir() . '/famo-test-' . uniqid() . '.sqlite';
$staleStore = new StateStore($stalePath);
$staleState = $staleStore->load(424242);
$staleState->mode = 'signup_name';
$staleState->role = 'student';
$staleState->payload = ['phone' => '+989121000000'];
$staleStore->save($staleState);
$stalePdo = new PDO('sqlite:' . $stalePath);
$stalePdo->exec('UPDATE chat_state SET updated_at = ' . (time() - 3600) . ' WHERE chat_id = 424242');
$stalePdo = null;
$expiredState = $staleStore->load(424242);
check('idle expiry resets mode and payload in memory', $expiredState->mode === 'idle'
    && $expiredState->payload === []
    && $expiredState->role === 'student'
    && (time() - $expiredState->updatedAt) < 60);
$expiredPdo = new PDO('sqlite:' . $stalePath);
$expiredRow = $expiredPdo->query('SELECT mode, payload, updated_at FROM chat_state WHERE chat_id = 424242')->fetch(PDO::FETCH_ASSOC);
$expiredPdo = null;
check('idle expiry persists the reset to the database', $expiredRow !== false
    && $expiredRow['mode'] === 'idle'
    && $expiredRow['payload'] === '[]'
    && (time() - (int) $expiredRow['updated_at']) < 60);

$api = (new ReflectionClass(FamoApi::class))->newInstanceWithoutConstructor();
$normalize = new ReflectionMethod(FamoApi::class, 'normalizeUserResult');
$normalize->setAccessible(true);
$resolved = $normalize->invoke($api, new ApiResult(200, [
    'success' => true,
    'data' => ['user' => [
        'id' => 41,
        'role' => 'student',
        'full_name' => 'مریم',
        'username' => '+989121234567',
        'chat_id' => '555',
        'supporter_id' => 8,
        'linked_id' => 41,
    ]],
]));
$normalizedLink = $resolved->data()['links'][0] ?? [];
check('resolved user normalized to legacy role link', ($normalizedLink['role'] ?? null) === 'student'
    && ($normalizedLink['account_id'] ?? null) === 41
    && ($normalizedLink['name'] ?? null) === 'مریم');
$unlinked = $normalize->invoke($api, new ApiResult(200, ['success' => true, 'data' => ['user' => null]]));
check('null resolved user stays unlinked without a synthetic role', !array_key_exists('user', $unlinked->data())
    && ($unlinked->data()['links'] ?? null) === []);

$logDir = sys_get_temp_dir() . '/famo-log-test-' . uniqid();
mkdir($logDir, 0770, true);
\App\Logger::boot($logDir . '/bot.log');
$responseResult = new ReflectionMethod(FamoApi::class, 'responseResult');
$responseResult->setAccessible(true);
$failedApiResult = $responseResult->invoke(
    $api,
    'POST',
    '/bot/link-phone',
    422,
    '{"success":false,"error":{"code":"VALIDATION_ERROR","message":"private response detail"}}'
);
$apiLogFiles = glob($logDir . '/bot-*.log') ?: [];
$apiLog = $apiLogFiles !== [] ? (string) file_get_contents($apiLogFiles[0]) : '';
check('failed Famo API response is logged with status and code', $failedApiResult instanceof ApiResult
    && str_contains($apiLog, '/bot/link-phone')
    && str_contains($apiLog, '422')
    && str_contains($apiLog, 'VALIDATION_ERROR'));
check('failed Famo API response log omits response details', !str_contains($apiLog, 'private response detail'));
foreach ($apiLogFiles as $apiLogFile) {
    @unlink($apiLogFile);
}
@rmdir($logDir);
\App\Logger::boot();

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

$fakeA = new FakeTelegramApi();
$storeAPath = sys_get_temp_dir() . '/famo-test-' . uniqid() . '.sqlite';
$storeA = new StateStore($storeAPath);
$smA = new ScreenManager(new TelegramApi($fakeA), $storeA);

$sA = $storeA->load(9101);
$sA->activeScreenMessageId = 500;
$smA->show($sA, new Screen('callback screen'), true);
check('callback show edits the active message', count($fakeA->edits) === 1
    && (int) $fakeA->edits[0]['message_id'] === 500);

$sB = $storeA->load(9102);
$sB->activeScreenMessageId = 500;
$sB->forceNewScreen = true;
$sentBefore = count($fakeA->sends);
$smA->show($sB, new Screen('message reply screen'), true);
check('forceNewScreen sends a new message instead of editing', count($fakeA->sends) === $sentBefore + 1
    && count($fakeA->edits) === 1);
check('forceNewScreen is consumed by show', $sB->forceNewScreen === false);

$smA->show($sB, new Screen('next callback screen'), true);
check('callback edits again after the flag was consumed', count($fakeA->edits) === 2
    && count($fakeA->sends) === $sentBefore + 1);

$fakeR = new FakeTelegramApi();
$storeRPath = sys_get_temp_dir() . '/famo-test-' . uniqid() . '.sqlite';
$storeR = new StateStore($storeRPath);
$tgR = new TelegramApi($fakeR);
$smR = new ScreenManager($tgR, $storeR);
$apiR = new FamoApi('test-key', 'http://127.0.0.1:1');
$cfgR = Config::fromEnv();
$studentR = new StudentHandler($apiR, $tgR, $smR);
$supporterR = new SupporterHandler($apiR, $tgR, $smR, $studentR);
$linkR = new LinkHandler($apiR, $tgR, $smR, $studentR, $supporterR, $cfgR);
$accountR = new AccountHandler($apiR, $tgR, $smR, $studentR, $linkR, $cfgR);
$broadcastR = new BroadcastHandler($apiR, $tgR, $smR, $studentR, $supporterR);
$routerR = new Router($storeR, $tgR, $linkR, $studentR, $accountR, $supporterR, $broadcastR);

$sR = $storeR->load(9200);
$sR->mode = 'signup_name';
$sR->activeScreenMessageId = 600;
$storeR->save($sR);

$routerR->route(new Update([
    'update_id' => 987001,
    'message' => [
        'message_id' => 5,
        'date' => 1,
        'text' => 'علی رضایی',
        'chat' => ['id' => 9200, 'type' => 'private'],
        'from' => ['id' => 5, 'is_bot' => false, 'first_name' => 'Ali'],
    ],
]));
check('router answers a user message with a new screen message', $fakeR->edits === []
    && str_contains($fakeR->sends[0]['text'] ?? '', 'کد ملی'));

$routerR->route(new Update([
    'update_id' => 987002,
    'callback_query' => [
        'id' => 'cb-typing-1',
        'chat_instance' => 'ci-1',
        'data' => KeyboardKit::CB_NOP,
        'from' => ['id' => 5, 'is_bot' => false, 'first_name' => 'Ali'],
        'message' => [
            'message_id' => 7,
            'date' => 1,
            'chat' => ['id' => 9200, 'type' => 'private'],
        ],
    ],
]));
$chatActionsAfterFast = $fakeR->chatActions;

$routerR->route(new Update([
    'update_id' => 987003,
    'callback_query' => [
        'id' => 'cb-typing-2',
        'chat_instance' => 'ci-1',
        'data' => 'zz:unknown',
        'from' => ['id' => 5, 'is_bot' => false, 'first_name' => 'Ali'],
        'message' => [
            'message_id' => 8,
            'date' => 1,
            'chat' => ['id' => 9200, 'type' => 'private'],
        ],
    ],
]));
check('router sends a typing chat action only for non-fast callbacks', $chatActionsAfterFast === []
    && ($fakeR->chatActions[0]['action'] ?? null) === 'typing'
    && ($fakeR->chatActions[0]['chat_id'] ?? null) === 9200);

$dayScreen = DayScreen::make([
    'day' => '2026-10-08',
    'weekday' => 'سه‌شنبه',
    'date_label' => '۱۴۰۴/۰۷/۱۷',
    'messages' => [
        ['who' => 'شما', 'time' => '۱۴:۳۰', 'body' => '<script>alert(1)</script>', 'files' => '📎 عکس (۲)'],
        ['who' => 'پشتیبان', 'time' => '۱۴:۳۵', 'body' => 'متن پیام', 'files' => ''],
    ],
    'page' => 1,
    'pages' => 2,
]);
check('day screen wraps every message in blockquote', substr_count($dayScreen->text, '<blockquote>') === 2
    && substr_count($dayScreen->text, '</blockquote>') === 2);
check('day screen escapes message bodies', ! str_contains($dayScreen->text, '<script>')
    && str_contains($dayScreen->text, '&lt;script&gt;'));
check('day screen puts the date in the title', str_contains($dayScreen->text, '<b>سه‌شنبه · ۱۴۰۴/۰۷/۱۷</b>'));
check('day screen puts page numbers in code', str_contains($dayScreen->text, '<code>1</code> از <code>2</code>'));

$homeStyling = HomeScreen::make([
    'name' => 'علی', 'has_supporter' => true, 'today_date' => '۱۰ مهر',
    'today_sent' => false, 'unread' => 2,
]);
check('home screen bolds the greeting', str_contains($homeStyling->text, '<b>علی عزیز</b>'));
check('home screen puts today date in code', str_contains($homeStyling->text, '<code>۱۰ مهر</code>'));

$weekStyling = WeekScreen::make([
    'title' => '۱۰ تا ۱۶ مهر', 'done' => 3, 'total' => 7,
    'days' => [], 'prev' => null, 'next' => null,
]);
check('week screen puts the range in the title', str_contains($weekStyling->text, '<b>هفته ۱۰ تا ۱۶ مهر</b>'));
check('week screen puts the counts in code', str_contains($weekStyling->text, '<code>3</code> از <code>7</code>'));

check('chat state declares telegramUserId', (new ReflectionClass(ChatState::class))->hasProperty('telegramUserId'));

$fakeG = new FakeTelegramApi();
$fakeG->failNextSendWithParseError = true;
$tgG = new TelegramApi($fakeG);
$guardCaught = null;
$guardId = 0;
try {
    $guardId = $tgG->sendMessage(4242, '<b>سلام</b> & دنیا');
} catch (Throwable $e) {
    $guardCaught = $e;
}
check('parse error does not break sending', $guardCaught === null && $guardId > 0);
check('parse error falls back to tag-free text', count($fakeG->sends) === 1
    && ! str_contains($fakeG->sends[0]['text'], '<b>')
    && str_contains($fakeG->sends[0]['text'], 'سلام'));

$fakeC = new FakeTelegramApi();
$tgC = new TelegramApi($fakeC);
$tgC->sendChatAction(4242);
$tgC->sendChatAction(4242, 'upload_photo');
check('sendChatAction records chat actions on the api', count($fakeC->chatActions) === 2
    && ($fakeC->chatActions[0]['chat_id'] ?? null) === 4242
    && ($fakeC->chatActions[0]['action'] ?? null) === 'typing'
    && ($fakeC->chatActions[1]['action'] ?? null) === 'upload_photo');

foreach ([$storeAPath, $storeRPath, $freshPath, $stalePath] as $tempStorePath) {
    @unlink($tempStorePath);
    @unlink($tempStorePath . '-wal');
    @unlink($tempStorePath . '-shm');
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
