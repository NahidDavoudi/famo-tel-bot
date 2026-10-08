<?php
declare(strict_types=1);

namespace App\Handlers;

use App\Famo\ApiResult;
use App\Famo\FamoApi;
use App\Logger;
use App\Lang;
use App\Num;
use App\Screens\DayScreen;
use App\Screens\SupporterInboxScreen;
use App\Screens\SupporterStudentsScreen;
use App\State\ChatState;
use App\Telegram\KeyboardKit;
use App\Telegram\ScreenManager;
use App\Telegram\TelegramApi;
use App\Telegram\UpdateContext;

final class SupporterHandler
{
    private const PER_PAGE = 10;
    private const MAX_FILES = 10;

    public function __construct(
        private readonly FamoApi $api,
        private readonly TelegramApi $tg,
        private readonly ScreenManager $screens,
        private readonly StudentHandler $student,
    ) {}

    public function home(ChatState $s, bool $edit = true): void
    {
        $this->inbox($s, 1, $edit);
    }

    public function onLabel(ChatState $s, string $route, UpdateContext $ctx): void
    {
        if ($s->role !== 'supporter') {
            return;
        }

        $messageId = $ctx->messageId();
        if ($messageId !== null) {
            $this->tg->deleteMessage($s->chatId, $messageId);
        }

        match ($route) {
            KeyboardKit::ROUTE_INBOX => $this->inbox($s, 1, false),
            KeyboardKit::ROUTE_STUDENTS => $this->students($s, false),
            KeyboardKit::ROUTE_HOME => $this->home($s, false),
            default => null,
        };
    }

    public function onCallback(ChatState $s, string $data, UpdateContext $ctx): void
    {
        if ($s->role !== 'supporter') {
            return;
        }

        if ($data === KeyboardKit::CB_SUPPORTER_HOME) {
            $this->inbox($s, 1, true);

            return;
        }

        if (str_starts_with($data, KeyboardKit::CB_INBOX_AT)) {
            $page = max(1, (int) substr($data, strlen(KeyboardKit::CB_INBOX_AT)));
            $this->inbox($s, $page, true);

            return;
        }

        if ($data === KeyboardKit::CB_STUDENTS) {
            $this->students($s, true);

            return;
        }

        if (str_starts_with($data, KeyboardKit::CB_STUDENT)) {
            $id = (int) substr($data, strlen(KeyboardKit::CB_STUDENT));
            $this->openStudent($s, $id, true);

            return;
        }

        if (str_starts_with($data, KeyboardKit::CB_SUPPORTER_DAY)) {
            [$id, $day, $page] = $this->parseDay($data, KeyboardKit::CB_SUPPORTER_DAY);
            $this->day($s, $id, $day, $page, true);

            return;
        }

        if (str_starts_with($data, KeyboardKit::CB_SUPPORTER_FILES)) {
            [$id, $day, $page] = $this->parseDay($data, KeyboardKit::CB_SUPPORTER_FILES);
            $this->files($s, $id, $day, $page);

            return;
        }

        if (str_starts_with($data, KeyboardKit::CB_REPLY)) {
            [$id, $day] = $this->parseReply($data);
            $this->beginReply($s, $id, $day);
        }
    }

    public function onReply(ChatState $s, UpdateContext $ctx): void
    {
        $message = $ctx->message;
        if ($message === null) {
            return;
        }

        $input = $this->student->buildInput($message);
        if ($input === null) {
            $this->tg->sendMessage($s->chatId, Lang::t('report.unsupported'));

            return;
        }

        $studentId = (int) ($s->payload['student_id'] ?? 0);
        if ($studentId <= 0) {
            $s->mode = 'idle';

            return;
        }

        $day = (string) ($s->payload['day'] ?? '');
        $input['student_id'] = $studentId;
        if ($day !== '') {
            $input['day'] = $day;
        }

        $result = $this->api->supporterReply($s->chatId, $s->accountId(), $input);
        if (!$result->ok()) {
            $this->fail($s, $result);

            return;
        }

        $messageId = $ctx->messageId();
        if ($messageId !== null) {
            $this->tg->react($s->chatId, $messageId, '👍');
        }

        $s->mode = 'idle';
        unset($s->payload['student_id'], $s->payload['day']);

        $name = $this->studentName($s, $studentId);
        $this->tg->sendMessage($s->chatId, Lang::t('sup.reply_sent', ['name' => $name]));
        $this->day($s, $studentId, $day !== '' ? $day : $this->student->today(), 1, true);
    }

    private function inbox(ChatState $s, int $page, bool $edit): void
    {
        $page = max(1, $page);
        $result = $this->api->supporterInbox($s->chatId, $s->accountId(), (string) $s->role, $page, self::PER_PAGE);
        if (!$result->ok()) {
            Logger::error('API ERROR', ['error' => $result]);
            $this->fail($s, $result);

            return;
        }

        $data = (array) $result->data();
        $rows = (array) ($data['students'] ?? []);
        $total = (int) ($data['total_students'] ?? count($rows));
        $pages = max(1, (int) ceil($total / self::PER_PAGE));

        $items = [];
        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['student_id'] ?? 0);
            $name = (string) ($row['student_name'] ?? '');
            $map[$id] = $name;
            $items[] = [
                'name' => $name,
                'unread' => (int) ($row['unread_count'] ?? 0),
                'callback' => KeyboardKit::CB_STUDENT . $id,
            ];
        }
        $this->mergeStudents($s, $map);

        $this->screens->show($s, SupporterInboxScreen::make([
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'students' => $items,
        ]), $edit);
    }

    private function students(ChatState $s, bool $edit): void
    {
        $result = $this->api->supporterStudents($s->chatId, $s->accountId(), (string) $s->role);
        if (!$result->ok()) {
            $this->fail($s, $result);

            return;
        }

        $data = (array) $result->data();
        $rows = (array) ($data['students'] ?? []);

        $items = [];
        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['student_id'] ?? 0);
            $name = (string) ($row['name'] ?? '');
            $map[$id] = $name;
            $markers = !empty($row['report_submitted']) ? ' ✅' : ' ⏳';
            if ((int) ($row['unread_count'] ?? 0) > 0) {
                $markers .= ' 🔵';
            }
            $items[] = [
                'name' => $name,
                'markers' => $markers,
                'callback' => KeyboardKit::CB_STUDENT . $id,
            ];
        }
        $this->mergeStudents($s, $map);

        $this->screens->show($s, SupporterStudentsScreen::make([
            'total' => (int) ($data['total_students'] ?? count($items)),
            'day_label' => (string) ($data['day_jalali'] ?? ''),
            'students' => $items,
        ]), $edit);
    }

    private function openStudent(ChatState $s, int $studentId, bool $edit): void
    {
        if ($studentId <= 0) {
            $this->inbox($s, 1, $edit);

            return;
        }

        $day = $this->student->today();
        $unread = $this->api->supporterStudentUnread($s->chatId, $s->accountId(), (string) $s->role, $studentId);
        if ($unread->ok()) {
            $messages = (array) ($unread->data()['messages'] ?? []);
            $latest = null;
            foreach ($messages as $message) {
                if (is_array($message) && !empty($message['day'])) {
                    $value = (string) $message['day'];
                    if ($latest === null || $value > $latest) {
                        $latest = $value;
                    }
                }
            }
            if ($latest !== null) {
                $day = $latest;
            }
        }

        $this->day($s, $studentId, $day, 1, $edit);
    }

    private function day(ChatState $s, int $studentId, string $day, int $page, bool $edit): void
    {
        $page = max(1, $page);
        $result = $this->api->day($s->chatId, $s->accountId(), (string) $s->role, $day, $studentId, $page, self::PER_PAGE);
        if (!$result->ok()) {
            $this->fail($s, $result);

            return;
        }

        $data = (array) $result->data();
        $messages = $this->student->formatMessages((array) ($data['messages'] ?? []));
        $pagination = (array) ($data['pagination'] ?? []);
        $pages = max(1, (int) ($pagination['last_page'] ?? $pagination['total_pages'] ?? 1));
        $current = max(1, (int) ($pagination['current_page'] ?? $page));
        $dayValue = (string) ($data['day'] ?? $day);
        $dateLabel = (string) ($data['day_jalali'] ?? $dayValue);
        $name = $this->studentName($s, $studentId);

        $this->screens->show($s, DayScreen::make([
            'title' => Lang::t('sup.thread_title', ['name' => $name, 'date' => $dateLabel]),
            'day' => $dayValue,
            'messages' => $messages,
            'page' => $current,
            'pages' => $pages,
            'has_files' => $this->student->hasFiles($messages),
            'is_today' => $dayValue === $this->student->today(),
            'empty_text' => Lang::t('day.empty'),
            'nav_prefix' => KeyboardKit::CB_SUPPORTER_DAY . $studentId . ':',
            'files_prefix' => KeyboardKit::CB_SUPPORTER_FILES . $studentId . ':',
            'reply_callback' => KeyboardKit::CB_REPLY . $studentId . ':' . $dayValue,
            'footer' => [
                [KeyboardKit::btn(Lang::t('btn.students'), KeyboardKit::CB_STUDENTS)],
                [KeyboardKit::btn(Lang::t('btn.home'), KeyboardKit::CB_SUPPORTER_HOME)],
            ],
        ]), $edit);

        $this->api->markRead($s->chatId, $s->accountId(), (string) $s->role, $studentId, $dayValue);
    }

    private function files(ChatState $s, int $studentId, string $day, int $page): void
    {
        $page = max(1, $page);
        $result = $this->api->day($s->chatId, $s->accountId(), (string) $s->role, $day, $studentId, $page, self::PER_PAGE);
        if (!$result->ok()) {
            $this->fail($s, $result);

            return;
        }

        $messages = (array) ($result->data()['messages'] ?? []);
        $sent = 0;

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            foreach ((array) ($message['attachments'] ?? []) as $attachment) {
                if ($sent >= self::MAX_FILES) {
                    return;
                }

                $fileId = (string) ($attachment['tg_file_id'] ?? '');
                if ($fileId === '') {
                    continue;
                }

                $caption = Lang::t('day.file_caption', [
                    'who' => $this->student->whoLabel((string) ($message['sender_role'] ?? '')),
                    'time' => $this->student->tehranTime($message['created_at'] ?? null),
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

    private function beginReply(ChatState $s, int $studentId, string $day): void
    {
        if ($studentId <= 0) {
            return;
        }

        $s->mode = 'replying';
        $s->payload['student_id'] = $studentId;
        $s->payload['day'] = $day;

        $this->tg->sendMessage($s->chatId, Lang::t('sup.reply_prompt', [
            'name' => $this->studentName($s, $studentId),
        ]));
    }

    /** @return array{0:int,1:string,2:int} */
    private function parseDay(string $data, string $prefix): array
    {
        $parts = explode(':', substr($data, strlen($prefix)));
        $id = (int) ($parts[0] ?? 0);
        $day = (string) ($parts[1] ?? '');
        $page = max(1, (int) ($parts[2] ?? 1));

        return [$id, $day, $page];
    }

    /** @return array{0:int,1:string} */
    private function parseReply(string $data): array
    {
        $parts = explode(':', substr($data, strlen(KeyboardKit::CB_REPLY)));
        $id = (int) ($parts[0] ?? 0);
        $day = (string) ($parts[1] ?? '');

        return [$id, $day];
    }

    private function studentName(ChatState $s, int $studentId): string
    {
        $map = (array) ($s->payload['students'] ?? []);
        $name = $map[$studentId] ?? '';

        return is_string($name) ? $name : (string) $name;
    }

    /** @param array<int,string> $map */
    private function mergeStudents(ChatState $s, array $map): void
    {
        $existing = (array) ($s->payload['students'] ?? []);
        $s->payload['students'] = array_merge($existing, $map);
    }

    public function fail(ChatState $s, ApiResult $result): void
    {
        $this->student->fail($s, $result);
    }
}
