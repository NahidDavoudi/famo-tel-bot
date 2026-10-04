<?php
declare(strict_types=1);

namespace App\Handlers;

use App\Famo\ApiResult;
use App\Famo\ErrorMap;
use App\Famo\FamoApi;
use App\Lang;
use App\Num;
use App\Screens\DayScreen;
use App\Screens\HelpScreen;
use App\Screens\HomeScreen;
use App\Screens\WeekScreen;
use App\Screens\WelcomeScreen;
use App\State\ChatState;
use App\Telegram\KeyboardKit;
use App\Telegram\ScreenManager;
use App\Telegram\TelegramApi;
use App\Telegram\UpdateContext;
use Telegram\Bot\Objects\Message;

final class StudentHandler
{
    private const TIMEZONE = 'Asia/Tehran';
    private const PER_PAGE = 10;
    private const MAX_FILES = 10;

    public function __construct(
        private readonly FamoApi $api,
        private readonly TelegramApi $tg,
        private readonly ScreenManager $screens,
    ) {}

    public function home(ChatState $s, UpdateContext $ctx, bool $new = false): void
    {
        if ($s->role === null) {
            return;
        }

        $result = $this->api->weekly($s->chatId, (int) $s->telegramUserId, $s->role);
        if (!$result->ok()) {
            $this->fail($s, $result);

            return;
        }

        $data = (array) $result->data();
        $days = (array) ($data['days'] ?? []);
        $today = $this->today();
        $todaySent = false;
        $todayLabel = '';
        $unread = 0;
        $hasSupporter = false;
        $supporterName = (string) ($data['supporter_name'] ?? '');

        foreach ($days as $day) {
            if (!empty($day['supporter_id'])) {
                $hasSupporter = true;
            }
            if ($supporterName === '' && !empty($day['supporter_name'])) {
                $supporterName = (string) $day['supporter_name'];
            }
            $unread += (int) ($day['unread_replies'] ?? 0);
            if (($day['day'] ?? null) === $today) {
                $todaySent = (bool) ($day['report_submitted'] ?? false);
                $todayLabel = (string) ($day['day_jalali'] ?? '');
            }
        }

        if ($supporterName !== '') {
            $s->payload['supporter'] = $supporterName;
        }

        $this->screens->show($s, HomeScreen::make([
            'name' => (string) ($s->payload['name'] ?? ''),
            'supporter' => $supporterName,
            'has_supporter' => $hasSupporter,
            'today_date' => $todayLabel,
            'today_sent' => $todaySent,
            'unread' => $unread,
        ]), !$new);
    }

    public function onLabel(ChatState $s, string $route, UpdateContext $ctx): void
    {
        $messageId = $ctx->messageId();
        if ($messageId !== null) {
            $this->tg->deleteMessage($s->chatId, $messageId);
        }

        match ($route) {
            KeyboardKit::ROUTE_TODAY => $this->day($s, $this->today(), 1, false),
            KeyboardKit::ROUTE_WEEK => $this->week($s, null, false),
            KeyboardKit::ROUTE_HOME => $this->home($s, $ctx, true),
            default => null,
        };
    }

    public function onCallback(ChatState $s, string $data, UpdateContext $ctx): void
    {
        if ($data === KeyboardKit::CB_TODAY) {
            $this->day($s, $this->today(), 1, true);

            return;
        }

        if ($data === KeyboardKit::CB_NEW) {
            $this->newReplies($s, $ctx);

            return;
        }

        if ($data === KeyboardKit::CB_WEEK) {
            $this->week($s, null, true);

            return;
        }

        if (str_starts_with($data, KeyboardKit::CB_WEEK_AT)) {
            $this->week($s, substr($data, strlen(KeyboardKit::CB_WEEK_AT)), true);

            return;
        }

        if (str_starts_with($data, KeyboardKit::CB_DAY)) {
            [$day, $page] = $this->parseDay($data, KeyboardKit::CB_DAY);
            $this->day($s, $day, $page, true);

            return;
        }

        if (str_starts_with($data, KeyboardKit::CB_FILES)) {
            [$day, $page] = $this->parseDay($data, KeyboardKit::CB_FILES);
            $this->files($s, $day, $page);
        }
    }

    public function onMessage(ChatState $s, UpdateContext $ctx): void
    {
        $message = $ctx->message;
        if ($message === null) {
            return;
        }

        $input = $this->buildInput($message);
        if ($input === null) {
            $this->tg->sendMessage($s->chatId, Lang::t('report.unsupported'));

            return;
        }

        $result = $this->api->sendStudentMessage($s->chatId, (int) $s->telegramUserId, $input);
        if (!$result->ok()) {
            $this->fail($s, $result);

            return;
        }

        $messageId = $ctx->messageId();
        if ($messageId !== null) {
            $this->tg->react($s->chatId, $messageId, '👍');
        }
    }

    public function help(ChatState $s, UpdateContext $ctx): void
    {
        $this->screens->show($s, HelpScreen::make(), false);
    }

    public function unknown(ChatState $s, UpdateContext $ctx): void
    {
        $this->tg->sendMessage($s->chatId, Lang::t('unknown.command'));
    }

    public function fail(ChatState $s, ApiResult $result): void
    {
        $text = ErrorMap::toPersian($result->errorCode, $result->status, $result->transportError);

        if (ErrorMap::isUnlinked($result) || ErrorMap::isDisabled($result)) {
            $this->reset($s);
            $this->tg->removeReplyKeyboard($s->chatId, $text);

            return;
        }

        $this->tg->sendMessage($s->chatId, $text);
    }

    public function reset(ChatState $s): void
    {
        $s->role = null;
        $s->mode = 'unlinked';
        $s->payload = [];
    }

    private function day(ChatState $s, string $day, int $page, bool $edit): void
    {
        $page = max(1, $page);
        $result = $this->api->day($s->chatId, (int) $s->telegramUserId, $s->role, $day, null, $page, self::PER_PAGE);
        if (!$result->ok()) {
            $this->fail($s, $result);

            return;
        }

        $data = (array) $result->data();
        $messages = $this->formatMessages((array) ($data['messages'] ?? []));
        $pagination = (array) ($data['pagination'] ?? []);
        $pages = max(1, (int) ($pagination['last_page'] ?? $pagination['total_pages'] ?? 1));
        $current = max(1, (int) ($pagination['current_page'] ?? $page));
        $dayValue = (string) ($data['day'] ?? $day);

        $this->screens->show($s, DayScreen::make([
            'day' => $dayValue,
            'weekday' => (string) ($data['weekday'] ?? ''),
            'date_label' => (string) ($data['day_jalali'] ?? $dayValue),
            'messages' => $messages,
            'page' => $current,
            'pages' => $pages,
            'has_files' => $this->hasFiles($messages),
            'is_today' => $dayValue === $this->today(),
        ]), $edit);

        $this->api->markRead($s->chatId, (int) $s->telegramUserId, $s->role, null, $dayValue);
    }

    private function week(ChatState $s, ?string $weekStart, bool $edit): void
    {
        $result = $this->api->weekly($s->chatId, (int) $s->telegramUserId, $s->role, $weekStart);
        if (!$result->ok()) {
            $this->fail($s, $result);

            return;
        }

        $data = (array) $result->data();
        $days = (array) ($data['days'] ?? []);
        $today = $this->today();
        $done = 0;
        $items = [];

        foreach ($days as $day) {
            $submitted = (bool) ($day['report_submitted'] ?? false);
            if ($submitted) {
                $done++;
            }

            $markers = match ((string) ($day['state'] ?? 'pending')) {
                'sent' => ' ✅',
                'missed' => ' ❌',
                default => ' ⏳',
            };

            if (!empty($day['reply_unread'])) {
                $markers .= ' 🔵';
            } elseif (!empty($day['has_reply'])) {
                $markers .= ' 💬';
            }

            $dayValue = (string) ($day['day'] ?? '');
            $items[] = [
                'label' => (string) ($day['weekday'] ?? $dayValue),
                'markers' => $markers,
                'callback' => $dayValue > $today
                    ? KeyboardKit::CB_NOP
                    : KeyboardKit::CB_DAY . $dayValue . ':1',
            ];
        }

        $start = (string) ($data['week_start'] ?? '');
        $prev = $start !== '' ? gmdate('Y-m-d', (int) strtotime($start . ' -7 days')) : null;
        $next = null;

        if ($start !== '') {
            $end = gmdate('Y-m-d', (int) strtotime($start . ' +6 days'));
            $isCurrent = $today >= $start && $today <= $end;
            if (!$isCurrent) {
                $next = gmdate('Y-m-d', (int) strtotime($start . ' +7 days'));
            }
        }

        $this->screens->show($s, WeekScreen::make([
            'title' => (string) ($data['week_start_jalali'] ?? $start),
            'done' => $done,
            'total' => count($days),
            'days' => $items,
            'prev' => $prev,
            'next' => $next,
        ]), $edit);
    }

    private function newReplies(ChatState $s, UpdateContext $ctx): void
    {
        $result = $this->api->weekly($s->chatId, (int) $s->telegramUserId, $s->role);
        if (!$result->ok()) {
            $this->fail($s, $result);

            return;
        }

        $days = (array) ($result->data()['days'] ?? []);
        $latest = null;

        foreach ($days as $day) {
            if (!empty($day['reply_unread']) && isset($day['day'])) {
                $latest = (string) $day['day'];
            }
        }

        if ($latest === null) {
            $this->home($s, $ctx, false);

            return;
        }

        $this->day($s, $latest, 1, true);
    }

    private function files(ChatState $s, string $day, int $page): void
    {
        $page = max(1, $page);
        $result = $this->api->day($s->chatId, (int) $s->telegramUserId, $s->role, $day, null, $page, self::PER_PAGE);
        if (!$result->ok()) {
            $this->fail($s, $result);

            return;
        }

        $messages = (array) ($result->data()['messages'] ?? []);
        $sent = 0;

        foreach ($messages as $message) {
            foreach ((array) ($message['attachments'] ?? []) as $attachment) {
                if ($sent >= self::MAX_FILES) {
                    return;
                }

                $fileId = (string) ($attachment['tg_file_id'] ?? '');
                if ($fileId === '') {
                    continue;
                }

                $caption = Lang::t('day.file_caption', [
                    'who' => $this->whoLabel((string) ($message['sender_role'] ?? '')),
                    'time' => $this->tehranTime($message['created_at'] ?? null),
                ]);

                $this->tg->sendAttachment(
                    $s->chatId,
                    (string) ($attachment['kind'] ?? 'document'),
                    $fileId,
                    $caption
                );
                $sent++;
            }
        }
    }

    /** @return array{0:string,1:int} */
    private function parseDay(string $data, string $prefix): array
    {
        $rest = substr($data, strlen($prefix));
        $parts = explode(':', $rest, 2);

        return [$parts[0] ?? '', max(1, (int) ($parts[1] ?? 1))];
    }

    /** @param array<string,mixed> $message */
    private function formatMessage(array $message): array
    {
        return [
            'who' => $this->whoLabel((string) ($message['sender_role'] ?? '')),
            'time' => $this->tehranTime($message['created_at'] ?? null),
            'body' => (string) ($message['body'] ?? ''),
            'files' => $this->filesSummary((array) ($message['attachments'] ?? [])),
        ];
    }

    /**
     * @param array<int,mixed> $messages
     * @return list<array<string,mixed>>
     */
    public function formatMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $message) {
            if (is_array($message)) {
                $out[] = $this->formatMessage($message);
            }
        }

        return $out;
    }

    /** @param list<array<string,mixed>> $messages */
    public function hasFiles(array $messages): bool
    {
        foreach ($messages as $message) {
            if (($message['files'] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    /** @param array<int,mixed> $attachments */
    private function filesSummary(array $attachments): string
    {
        $photos = 0;
        $parts = [];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            switch ((string) ($attachment['kind'] ?? '')) {
                case 'photo':
                    $photos++;
                    break;
                case 'document':
                    $parts[] = Lang::t('day.file_doc', ['name' => (string) ($attachment['file_name'] ?? '')]);
                    break;
                case 'voice':
                    $parts[] = Lang::t('day.file_voice');
                    break;
                case 'video':
                    $parts[] = Lang::t('day.file_video');
                    break;
                case 'audio':
                    $parts[] = Lang::t('day.file_audio');
                    break;
            }
        }

        if ($photos > 0) {
            array_unshift($parts, Lang::t('day.file_photo', ['n' => Num::fa($photos)]));
        }

        return $parts === [] ? '' : '📎 ' . implode('، ', $parts);
    }

    public function whoLabel(string $role): string
    {
        return match ($role) {
            'student' => Lang::t('day.you'),
            'supporter' => Lang::t('day.supporter'),
            'broadcast' => Lang::t('day.broadcast'),
            default => '',
        };
    }

    /** @return array<string,mixed>|null */
    public function buildInput(Message $message): ?array
    {
        $type = $message->objectType();
        $text = $message->getText();
        if (!is_string($text) || $text === '') {
            $caption = $message->getCaption();
            $text = is_string($caption) ? $caption : null;
        }

        $attachment = $this->attachmentFor($message, (string) $type);

        if (($text === null || $text === '') && $attachment === null) {
            return null;
        }

        $input = [];
        if ($text !== null && $text !== '') {
            $input['text'] = $text;
        }
        if ($attachment !== null) {
            $input['attachments'] = [$attachment];
        }

        $mediaGroupId = $message->getMediaGroupId();
        if (is_string($mediaGroupId) && $mediaGroupId !== '') {
            $input['media_group_id'] = $mediaGroupId;
        }

        return $input;
    }

    /** @return array<string,mixed>|null */
    private function attachmentFor(Message $message, string $type): ?array
    {
        switch ($type) {
            case 'photo':
                $sizes = $message->getPhoto();
                $fileId = null;
                if (is_iterable($sizes)) {
                    foreach ($sizes as $size) {
                        $fileId = $size->get('file_id');
                    }
                }

                return $fileId !== null
                    ? ['kind' => 'photo', 'tg_file_id' => (string) $fileId]
                    : null;
            case 'document':
                $doc = $message->getDocument();

                return $doc !== null ? [
                    'kind' => 'document',
                    'tg_file_id' => (string) $doc->get('file_id'),
                    'file_name' => $doc->get('file_name'),
                    'mime_type' => $doc->get('mime_type'),
                    'file_size' => $doc->get('file_size'),
                ] : null;
            case 'voice':
                $voice = $message->getVoice();

                return $voice !== null
                    ? ['kind' => 'voice', 'tg_file_id' => (string) $voice->get('file_id')]
                    : null;
            case 'video':
                $video = $message->getVideo();

                return $video !== null
                    ? ['kind' => 'video', 'tg_file_id' => (string) $video->get('file_id')]
                    : null;
            case 'video_note':
                $note = $message->getVideoNote();

                return $note !== null
                    ? ['kind' => 'video', 'tg_file_id' => (string) $note->get('file_id')]
                    : null;
            case 'audio':
                $audio = $message->getAudio();

                return $audio !== null
                    ? ['kind' => 'audio', 'tg_file_id' => (string) $audio->get('file_id')]
                    : null;
            default:
                return null;
        }
    }

    public function today(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)))->format('Y-m-d');
    }

    public function tehranTime(mixed $iso): string
    {
        if (!is_string($iso) || $iso === '') {
            return '';
        }

        try {
            return Num::fa(
                (new \DateTimeImmutable($iso))
                    ->setTimezone(new \DateTimeZone(self::TIMEZONE))
                    ->format('H:i')
            );
        } catch (\Throwable) {
            return '';
        }
    }
}
