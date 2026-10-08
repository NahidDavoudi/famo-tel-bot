<?php
declare(strict_types=1);

namespace App\Telegram;

use App\Logger;
use Throwable;

final class TelegramApi
{
    public function __construct(private readonly \Telegram\Bot\Api $api) {}

    public static function fromToken(string $token): self
    {
        return new self(new \Telegram\Bot\Api($token));
    }

    public function update(): \Telegram\Bot\Objects\Update
    {
        return $this->api->getWebhookUpdate(false);
    }

    public function sendMessage(int $chatId, string $text, ?array $keyboard = null): int
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($keyboard !== null) {
            $params['reply_markup'] = self::inlineMarkup($keyboard);
        }

        try {
            $message = $this->api->sendMessage($params);
        } catch (\Telegram\Bot\Exceptions\TelegramSDKException $e) {
            if (! self::isParseError($e)) {
                throw $e;
            }

            $params['text'] = htmlspecialchars(strip_tags($text), ENT_QUOTES, 'UTF-8');
            $message = $this->api->sendMessage($params);
        }

        return (int) $message->get('message_id');
    }

    private static function isParseError(\Telegram\Bot\Exceptions\TelegramSDKException $e): bool
    {
        return str_contains(strtolower($e->getMessage()), 'parse');
    }

    public function editMessageText(int $chatId, int $messageId, string $text, ?array $keyboard = null): void
    {
        $this->api->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => self::inlineMarkup($keyboard),
        ]);
    }

    public function editMessageReplyMarkup(int $chatId, int $messageId, ?array $keyboard = null): void
    {
        $this->api->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => self::inlineMarkup($keyboard),
        ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): void
    {
        try {
            $params = ['callback_query_id' => $callbackQueryId];
            if ($text !== '') {
                $params['text'] = $text;
            }

            $this->api->answerCallbackQuery($params);
        } catch (Throwable $e) {
            Logger::warning('answerCallbackQuery failed', ['error' => $e->getMessage()]);
        }
    }

    public function sendChatAction(int $chatId, string $action = 'typing'): void
    {
        try {
            $this->api->sendChatAction([
                'chat_id' => $chatId,
                'action'  => $action,
            ]);
        } catch (\Throwable) {
            // silent
        }
    }

    public function deleteMessage(int $chatId, int $messageId): void
    {
        try {
            $this->api->deleteMessage(['chat_id' => $chatId, 'message_id' => $messageId]);
        } catch (Throwable $e) {
            Logger::warning('deleteMessage failed', ['error' => $e->getMessage()]);
        }
    }

    public function react(int $chatId, int $messageId, string $emoji = '👍'): void
    {
        try {
            $this->api->setMessageReaction([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reaction' => [['type' => 'emoji', 'emoji' => $emoji]],
            ]);
        } catch (Throwable $e) {
            Logger::warning('setMessageReaction failed', ['error' => $e->getMessage()]);
        }
    }

    public function setReplyKeyboard(int $chatId, array $keyboard, string $text = '✔'): int
    {
        $message = $this->api->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(
                ['keyboard' => $keyboard, 'resize_keyboard' => true, 'is_persistent' => true],
                JSON_UNESCAPED_UNICODE
            ),
        ]);

        return (int) $message->get('message_id');
    }

    public function removeReplyKeyboard(int $chatId, string $text = '✔'): int
    {
        $message = $this->api->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['remove_keyboard' => true]),
        ]);

        return (int) $message->get('message_id');
    }

    public function sendAttachment(int $chatId, string $kind, string $fileId, string $caption = ''): void
    {
        $method = match ($kind) {
            'photo' => 'sendPhoto',
            'document' => 'sendDocument',
            'voice' => 'sendVoice',
            'video' => 'sendVideo',
            'audio' => 'sendAudio',
            default => null,
        };

        if ($method === null) {
            return;
        }

        $field = $kind === 'photo' ? 'photo' : $kind;
        $params = ['chat_id' => $chatId, $field => $fileId];
        if ($caption !== '') {
            $params['caption'] = $caption;
            $params['parse_mode'] = 'HTML';
        }

        try {
            $this->api->{$method}($params);
        } catch (Throwable $e) {
            Logger::warning('sendAttachment failed', ['method' => $method, 'error' => $e->getMessage()]);
        }
    }

    private static function inlineMarkup(?array $keyboard): string
    {
        return json_encode(
            ['inline_keyboard' => $keyboard ?? []],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '{"inline_keyboard":[]}';
    }
}
