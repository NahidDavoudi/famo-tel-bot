<?php
declare(strict_types=1);

namespace App\Handlers;

use App\Famo\ApiResult;
use App\Famo\FamoApi;
use App\Lang;
use App\Num;
use App\Screens\BroadcastDetailScreen;
use App\Screens\BroadcastListScreen;
use App\Screens\BroadcastMenuScreen;
use App\Screens\BroadcastPreviewScreen;
use App\State\ChatState;
use App\Telegram\KeyboardKit;
use App\Telegram\ScreenManager;
use App\Telegram\TelegramApi;
use App\Telegram\UpdateContext;

final class BroadcastHandler
{
    private const AUDIENCES = ['no_report_today', 'all_students'];

    public function __construct(
        private readonly FamoApi $api,
        private readonly TelegramApi $tg,
        private readonly ScreenManager $screens,
        private readonly StudentHandler $student,
        private readonly SupporterHandler $supporter,
    ) {}

    public function menu(ChatState $s, bool $edit = false): void
    {
        if ($s->role !== 'supporter') {
            return;
        }

        $this->screens->show($s, BroadcastMenuScreen::make(), $edit);
    }

    public function onLabel(ChatState $s, string $route, UpdateContext $ctx): void
    {
        if ($route !== KeyboardKit::ROUTE_BROADCAST || $s->role !== 'supporter') {
            return;
        }

        $messageId = $ctx->messageId();
        if ($messageId !== null) {
            $this->tg->deleteMessage($s->chatId, $messageId);
        }

        $this->menu($s, false);
    }

    public function onCallback(ChatState $s, string $data, UpdateContext $ctx): void
    {
        if ($s->role !== 'supporter') {
            return;
        }

        if ($data === KeyboardKit::CB_BROADCAST) {
            $this->menu($s, true);

            return;
        }

        if (str_starts_with($data, KeyboardKit::CB_BC_AUDIENCE)) {
            $this->beginCompose($s, substr($data, strlen(KeyboardKit::CB_BC_AUDIENCE)));

            return;
        }

        if ($data === KeyboardKit::CB_BC_SEND) {
            $this->confirm($s);

            return;
        }

        if ($data === KeyboardKit::CB_BC_CANCEL) {
            $this->cancel($s);

            return;
        }

        if ($data === KeyboardKit::CB_BC_LIST) {
            $this->list($s, true);

            return;
        }

        if (str_starts_with($data, KeyboardKit::CB_BC_VIEW)) {
            $id = (int) substr($data, strlen(KeyboardKit::CB_BC_VIEW));
            $this->detail($s, $id);
        }
    }

    public function onCompose(ChatState $s, UpdateContext $ctx): void
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

        $s->payload['draft'] = $input;
        $s->mode = 'confirming_broadcast';
        $this->preview($s);
    }

    private function beginCompose(ChatState $s, string $audience): void
    {
        if (!in_array($audience, self::AUDIENCES, true)) {
            return;
        }

        $s->mode = 'composing_broadcast';
        $s->payload['audience'] = $audience;
        $s->payload['day'] = $this->student->today();
        unset($s->payload['draft']);

        $this->tg->sendMessage($s->chatId, Lang::t('bc.compose_prompt', [
            'audience' => $this->audienceLabel($audience),
        ]));
    }

    private function preview(ChatState $s): void
    {
        $result = $this->api->broadcastPreview(
            $s->chatId,
            (int) $s->telegramUserId,
            (string) $s->role,
            $this->scopeInput($s)
        );
        if (!$result->ok()) {
            $this->fail($s, $result);

            return;
        }

        $data = (array) $result->data();
        $this->screens->show($s, BroadcastPreviewScreen::make([
            'audience_label' => $this->audienceLabel((string) ($data['audience'] ?? ($s->payload['audience'] ?? ''))),
            'count' => (int) ($data['recipient_count'] ?? 0),
            'remaining' => $data['remaining_today'] ?? null,
            'limit' => $data['daily_limit'] ?? null,
        ]), true);
    }

    private function confirm(ChatState $s): void
    {
        if (($s->mode ?? '') !== 'confirming_broadcast') {
            return;
        }

        $result = $this->api->broadcastConfirm(
            $s->chatId,
            (int) $s->telegramUserId,
            (string) $s->role,
            $this->messageInput($s)
        );
        if (!$result->ok()) {
            $this->fail($s, $result);

            return;
        }

        $data = (array) $result->data();
        $summary = (array) ($data['summary'] ?? []);

        $this->clear($s);
        $this->tg->sendMessage($s->chatId, Lang::t('bc.sent'));
        $this->tg->sendMessage($s->chatId, Lang::t('bc.sent_summary', [
            'sent' => Num::fa((int) ($summary['sent'] ?? 0)),
            'blocked' => Num::fa((int) ($summary['blocked'] ?? 0)),
            'failed' => Num::fa((int) ($summary['failed'] ?? 0)),
            'pending' => Num::fa((int) ($summary['pending'] ?? 0)),
        ]));

        $this->supporter->home($s, false);
    }

    private function cancel(ChatState $s): void
    {
        $this->clear($s);
        $this->tg->sendMessage($s->chatId, Lang::t('bc.cancelled'));
        $this->supporter->home($s, false);
    }

    private function list(ChatState $s, bool $edit): void
    {
        $result = $this->api->broadcastList($s->chatId, (int) $s->telegramUserId, (string) $s->role);
        if (!$result->ok()) {
            $this->fail($s, $result);

            return;
        }

        $data = (array) $result->data();
        $rows = $data['broadcasts'] ?? $data['items'] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? $row['broadcast_id'] ?? 0);
            $items[] = [
                'label' => Lang::t('bc.list_line', [
                    'id' => Num::fa($id),
                    'audience' => $this->audienceLabel((string) ($row['audience'] ?? '')),
                    'day' => (string) ($row['day_jalali'] ?? $row['day'] ?? ''),
                ]),
                'callback' => KeyboardKit::CB_BC_VIEW . $id,
            ];
        }

        $this->screens->show($s, BroadcastListScreen::make(['items' => $items]), $edit);
    }

    private function detail(ChatState $s, int $id): void
    {
        if ($id <= 0) {
            $this->list($s, true);

            return;
        }

        $result = $this->api->broadcastGet($s->chatId, (int) $s->telegramUserId, (string) $s->role, $id);
        if (!$result->ok()) {
            $this->fail($s, $result);

            return;
        }

        $data = (array) $result->data();
        $broadcast = (array) ($data['broadcast'] ?? []);
        $summary = (array) ($data['summary'] ?? []);

        $recipients = [];
        foreach ((array) ($data['recipients'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = (string) ($row['name'] ?? $row['student_name'] ?? '');
            $status = (string) ($row['status'] ?? '');
            if ($name === '') {
                continue;
            }
            $recipients[] = $name . ($status !== '' ? ' · ' . $status : '');
        }

        $this->screens->show($s, BroadcastDetailScreen::make([
            'id' => Num::fa($id),
            'audience_label' => $this->audienceLabel((string) ($broadcast['audience'] ?? '')),
            'day_label' => (string) ($broadcast['day_jalali'] ?? $broadcast['day'] ?? ''),
            'summary' => $summary,
            'recipients' => $recipients,
        ]), true);
    }

    private function clear(ChatState $s): void
    {
        $s->mode = 'idle';
        unset($s->payload['draft'], $s->payload['audience'], $s->payload['day']);
    }

    /** @return array<string,mixed> */
    private function scopeInput(ChatState $s): array
    {
        $input = ['audience' => (string) ($s->payload['audience'] ?? 'no_report_today')];
        $day = (string) ($s->payload['day'] ?? '');
        if ($day !== '') {
            $input['day'] = $day;
        }

        return $input;
    }

    /** @return array<string,mixed> */
    private function messageInput(ChatState $s): array
    {
        $input = $this->scopeInput($s);
        $draft = (array) ($s->payload['draft'] ?? []);

        foreach (['text', 'attachments', 'media_group_id'] as $key) {
            if (array_key_exists($key, $draft)) {
                $input[$key] = $draft[$key];
            }
        }

        return $input;
    }

    private function audienceLabel(string $audience): string
    {
        return match ($audience) {
            'no_report_today' => Lang::t('bc.audience_no_report'),
            'all_students' => Lang::t('bc.audience_all'),
            default => $audience,
        };
    }

    private function fail(ChatState $s, ApiResult $result): void
    {
        $this->student->fail($s, $result);
    }
}
