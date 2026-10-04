<?php
declare(strict_types=1);

namespace App\Famo;

use App\Logger;

final class FamoApi
{
    public function __construct(
        private readonly string $serviceKey,
        private readonly string $baseUrl,
        private readonly int $timeout = 15,
    ) {}

    public function ping(): ApiResult
    {
        return $this->request('GET', '/bot/ping');
    }

    public function resolveByChat(int $chatId): ApiResult
    {
        return $this->normalizeUserResult($this->request('POST', '/bot/resolve', [], ['chat_id' => (string) $chatId]));
    }

    public function linkPhone(int $chatId, string $phone): ApiResult
    {
        return $this->normalizeUserResult($this->request('POST', '/bot/link-phone', [], [
            'chat_id' => (string) $chatId,
            'phone' => $phone,
        ]));
    }

    public function registerStudent(int $chatId, string $phone, string $fullName, string $nationalId, int $grade, string $major): ApiResult
    {
        return $this->normalizeUserResult($this->request('POST', '/bot/register', [], [
            'chat_id'     => (string) $chatId,
            'phone'       => $phone,
            'full_name'   => $fullName,
            'national_id' => $nationalId,
            'grade'       => $grade,
            'major'       => $major,
        ]));
    }

    public function resolveByUser(int $telegramUserId): ApiResult
    {
        return $this->request('GET', '/bot/identity/resolve', ['telegram_user_id' => $telegramUserId]);
    }

    public function unlink(int $telegramUserId, ?string $role = null): ApiResult
    {
        $json = ['telegram_user_id' => $telegramUserId];
        if ($role !== null) {
            $json['role'] = $role;
        }

        return $this->request('POST', '/bot/identity/unlink', [], $json);
    }

    /** @param array<string,mixed> $input */
    public function sendStudentMessage(int $chatId, int $telegramUserId, array $input): ApiResult
    {
        return $this->request(
            'POST',
            '/bot/threads/messages',
            [],
            $input,
            self::identity('student', $telegramUserId, $chatId)
        );
    }

    public function day(
        int $chatId,
        int $telegramUserId,
        string $role,
        ?string $day,
        ?int $studentId,
        int $page = 1,
        int $perPage = 10,
    ): ApiResult {
        $query = ['page' => $page, 'perPage' => $perPage];
        if ($day !== null) {
            $query['day'] = $day;
        }
        if ($studentId !== null) {
            $query['student_id'] = $studentId;
        }

        return $this->request(
            'GET',
            '/bot/threads/day',
            $query,
            null,
            self::identity($role, $telegramUserId, $chatId)
        );
    }

    public function weekly(
        int $chatId,
        int $telegramUserId,
        string $role,
        ?string $weekStart = null,
        ?int $studentId = null,
    ): ApiResult {
        $query = [];
        if ($weekStart !== null) {
            $query['week_start'] = $weekStart;
        }
        if ($studentId !== null) {
            $query['student_id'] = $studentId;
        }

        return $this->request(
            'GET',
            '/bot/threads/weekly',
            $query,
            null,
            self::identity($role, $telegramUserId, $chatId)
        );
    }

    public function markRead(
        int $chatId,
        int $telegramUserId,
        string $role,
        ?int $studentId = null,
        ?string $day = null,
    ): ApiResult {
        $json = [];
        if ($studentId !== null) {
            $json['student_id'] = $studentId;
        }
        if ($day !== null) {
            $json['day'] = $day;
        }

        return $this->request(
            'POST',
            '/bot/threads/read',
            [],
            $json,
            self::identity($role, $telegramUserId, $chatId)
        );
    }

    public function supporterInbox(
        int $chatId,
        int $telegramUserId,
        string $role,
        int $page = 1,
        int $perPage = 10,
    ): ApiResult {
        return $this->request(
            'GET',
            '/bot/supporter/inbox',
            ['page' => $page, 'perPage' => $perPage],
            null,
            self::identity($role, $telegramUserId, $chatId)
        );
    }

    public function supporterStudents(int $chatId, int $telegramUserId, string $role): ApiResult
    {
        return $this->request(
            'GET',
            '/bot/supporter/students',
            [],
            null,
            self::identity($role, $telegramUserId, $chatId)
        );
    }

    public function supporterStudentUnread(
        int $chatId,
        int $telegramUserId,
        string $role,
        int $studentId,
        int $limit = 200,
    ): ApiResult {
        return $this->request(
            'GET',
            '/bot/supporter/students/' . $studentId . '/unread',
            ['limit' => $limit],
            null,
            self::identity($role, $telegramUserId, $chatId)
        );
    }

    /** @param array<string,mixed> $input */
    public function supporterReply(int $chatId, int $telegramUserId, array $input): ApiResult
    {
        return $this->request(
            'POST',
            '/bot/supporter/reply',
            [],
            $input,
            self::identity('supporter', $telegramUserId, $chatId)
        );
    }

    /** @param array<string,mixed> $input */
    public function broadcastPreview(int $chatId, int $telegramUserId, string $role, array $input): ApiResult
    {
        return $this->request(
            'POST',
            '/bot/broadcasts/preview',
            [],
            $input,
            self::identity($role, $telegramUserId, $chatId)
        );
    }

    /** @param array<string,mixed> $input */
    public function broadcastConfirm(int $chatId, int $telegramUserId, string $role, array $input): ApiResult
    {
        return $this->request(
            'POST',
            '/bot/broadcasts/confirm',
            [],
            $input,
            self::identity($role, $telegramUserId, $chatId)
        );
    }

    public function broadcastList(int $chatId, int $telegramUserId, string $role, int $page = 1, int $perPage = 10): ApiResult
    {
        return $this->request(
            'GET',
            '/bot/broadcasts',
            ['page' => $page, 'perPage' => $perPage],
            null,
            self::identity($role, $telegramUserId, $chatId)
        );
    }

    public function broadcastGet(int $chatId, int $telegramUserId, string $role, int $id): ApiResult
    {
        return $this->request(
            'GET',
            '/bot/broadcasts/' . $id,
            [],
            null,
            self::identity($role, $telegramUserId, $chatId)
        );
    }

    /**
     * @param array<string,int|string> $query
     * @param array<string,mixed>|null $json
     * @param array{role:string,userId:int,chatId:int}|null $identity
     */
    private function request(string $method, string $path, array $query = [], ?array $json = null, ?array $identity = null): ApiResult
    {
        $url = $this->baseUrl . '/api/v1' . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = [
            'X-Bot-Key: ' . $this->serviceKey,
            'Accept: application/json',
        ];

        if ($identity !== null) {
            $headers[] = 'X-Bot-Role: ' . $identity['role'];
            $headers[] = 'X-Telegram-User-Id: ' . $identity['userId'];
            $headers[] = 'X-Telegram-Chat-Id: ' . $identity['chatId'];
        }

        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $transportError = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $transportError !== '') {
            Logger::warning('Famo API transport error', [
                'method' => $method,
                'path' => $path,
                'error' => $transportError,
            ]);

            return new ApiResult(0, null, null, $transportError !== '' ? $transportError : 'transport error');
        }

        return $this->responseResult($method, $path, $status, (string) $raw);
    }

    private function responseResult(string $method, string $path, int $status, string $raw): ApiResult
    {
        $body = json_decode($raw, true);
        $body = is_array($body) ? $body : null;
        $errorCode = $body['error']['code'] ?? null;
        $result = new ApiResult($status, $body, is_string($errorCode) ? $errorCode : null);

        if (!$result->ok()) {
            Logger::warning('Famo API request failed', [
                'method' => $method,
                'path' => $path,
                'status' => $status,
                'error_code' => $result->errorCode,
            ]);
        }

        return $result;
    }

    private function normalizeUserResult(ApiResult $result): ApiResult
    {
        if (!is_array($result->data())) {
            return $result;
        }

        $data = $result->data();
        $user = $data['user'] ?? null;
        $data['links'] = is_array($user) ? [self::userAsLink($user)] : [];
        unset($data['user']);
        $body = $result->body ?? [];
        $body['data'] = $data;

        return new ApiResult($result->status, $body, $result->errorCode, $result->transportError);
    }

    /** @param array<string,mixed> $user
     *  @return array<string,mixed>
     */
    private static function userAsLink(array $user): array
    {
        return [
            'role' => (string) ($user['role'] ?? ''),
            'account_id' => (int) ($user['linked_id'] ?? $user['id'] ?? 0),
            'name' => (string) ($user['full_name'] ?? ''),
            'is_active' => true,
            'is_blocked' => false,
            'telegram_user_id' => 0,
            'chat_id' => (int) ($user['chat_id'] ?? 0),
        ];
    }

    /**  array{role:string,userId:int,chatId:int} */
    private static function identity(string $role, int $telegramUserId, int $chatId): array
    {
        return ['role' => $role, 'userId' => $telegramUserId, 'chatId' => $chatId];
    }
}
