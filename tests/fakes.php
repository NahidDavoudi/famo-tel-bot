<?php
declare(strict_types=1);

use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Message as TgMessage;

final class FakeTelegramApi extends Api
{
    /** @var list<array<string,mixed>> */
    public array $sends = [];

    /** @var list<array<string,mixed>> */
    public array $edits = [];

    /** @var list<array<string,mixed>> */
    public array $markupEdits = [];

    public int $nextMessageId = 1000;

    public bool $failNextSendWithParseError = false;

    public function __construct()
    {
        parent::__construct('123456:FAKE_TOKEN_FOR_TESTS');
    }

    public function sendMessage(array $params): TgMessage
    {
        if ($this->failNextSendWithParseError) {
            $this->failNextSendWithParseError = false;

            throw new TelegramSDKException("Bad Request: can't parse entities: Unexpected end tag");
        }

        $this->sends[] = $params;

        return new TgMessage(['message_id' => $this->nextMessageId++]);
    }

    public function editMessageText(array $params): TgMessage
    {
        $this->edits[] = $params;

        return new TgMessage(['message_id' => (int) ($params['message_id'] ?? 0)]);
    }

    public function editMessageReplyMarkup(array $params): TgMessage
    {
        $this->markupEdits[] = $params;

        return new TgMessage(['message_id' => (int) ($params['message_id'] ?? 0)]);
    }

    public function deleteMessage(array $params)
    {
        return true;
    }

    public function answerCallbackQuery(array $params): bool
    {
        return true;
    }
}
