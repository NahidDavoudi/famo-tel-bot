<?php
declare(strict_types=1);

namespace BotHost\Telegram;

class TelegramClient
{
    public function __construct(
        private string $token,
        private string $baseUrl = 'https://api.telegram.org',
        private int $timeout = 15,
    ) {}

    /** @param array<string,mixed> $params @return array<string,mixed> */
    public function call(string $method, array $params = []): array
    {
        $url = $this->baseUrl . '/bot' . $this->token . '/' . $method;
        $decoded = $this->transport($url, $params);

        if (($decoded['ok'] ?? false) !== true) {
            $desc = (string) ($decoded['description'] ?? 'unknown error');
            $code = (int) ($decoded['error_code'] ?? 0);
            throw new \RuntimeException("Telegram API error {$code}: {$desc}");
        }
        return $decoded;
    }

    /** @param array<string,mixed> $params @return array<string,mixed> */
    protected function transport(string $url, array $params): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Telegram transport error: curl_init failed');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $errno !== 0) {
            throw new \RuntimeException('Telegram transport error: ' . ($error !== '' ? $error : (string) $errno));
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Telegram returned invalid JSON');
        }
        return $decoded;
    }

    /** @return array<string,mixed> */
    public function getMe(): array
    {
        return $this->call('getMe');
    }

    /** @param list<string> $allowedUpdates @return array<string,mixed> */
    public function setWebhook(string $url, string $secret, array $allowedUpdates = []): array
    {
        $params = ['url' => $url, 'drop_pending_updates' => false];
        if ($secret !== '') {
            $params['secret_token'] = $secret;
        }
        if ($allowedUpdates !== []) {
            $params['allowed_updates'] = json_encode(array_values($allowedUpdates));
        }
        return $this->call('setWebhook', $params);
    }

    /** @return array<string,mixed> */
    public function deleteWebhook(bool $dropPending = false): array
    {
        return $this->call('deleteWebhook', ['drop_pending_updates' => $dropPending]);
    }

    /** @return array<string,mixed> */
    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo');
    }
}
