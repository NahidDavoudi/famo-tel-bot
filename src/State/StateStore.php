<?php
declare(strict_types=1);

namespace App\State;

use PDO;
use PDOException;

final class StateStore
{
    private const IDLE_EXPIRY_SECONDS = 1800;

    private PDO $pdo;

    public function __construct(string $path)
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');

        $this->migrate();
    }

    public function seen(int $updateId): bool
    {
        $this->begin();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT OR IGNORE INTO processed_update (update_id, created_at) VALUES (:update_id, :created_at)'
            );
            $stmt->execute([':update_id' => $updateId, ':created_at' => time()]);
            $duplicate = $stmt->rowCount() === 0;
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        if (random_int(1, 100) === 1) {
            $this->cleanup();
        }

        return $duplicate;
    }

    public function load(int $chatId): ChatState
    {
        $state = ChatState::create($chatId);

        $stmt = $this->pdo->prepare('SELECT * FROM chat_state WHERE chat_id = :chat_id');
        $stmt->execute([':chat_id' => $chatId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            $now = time();

            $this->begin();
            try {
                $insert = $this->pdo->prepare(
                    'INSERT OR IGNORE INTO chat_state (chat_id, mode, payload, updated_at) VALUES (:chat_id, :mode, :payload, :updated_at)'
                );
                $insert->execute([
                    ':chat_id' => $chatId,
                    ':mode' => $state->mode,
                    ':payload' => self::encodePayload([]),
                    ':updated_at' => $now,
                ]);
                $this->pdo->commit();
            } catch (PDOException $e) {
                $this->pdo->rollBack();
                throw $e;
            }

            if ($insert->rowCount() !== 0) {
                $state->updatedAt = $now;

                return $state;
            }

            // Lost the insert race: another concurrent request created the
            // row first. Re-read it and hydrate below instead of failing.
            $stmt = $this->pdo->prepare('SELECT * FROM chat_state WHERE chat_id = :chat_id');
            $stmt->execute([':chat_id' => $chatId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                $state->updatedAt = $now;

                return $state;
            }
        }

        $state->telegramUserId = $row['telegram_user_id'] !== null ? (int) $row['telegram_user_id'] : null;
        $state->role = $row['role'] !== null ? (string) $row['role'] : null;
        $state->mode = (string) $row['mode'];
        $state->payload = self::decodePayload($row['payload'] ?? null);
        $state->activeScreenMessageId = $row['active_screen_message_id'] !== null
            ? (int) $row['active_screen_message_id']
            : null;
        $state->updatedAt = (int) $row['updated_at'];

        if ($state->updatedAt > 0 && (time() - $state->updatedAt) > self::IDLE_EXPIRY_SECONDS) {
            $state->mode = 'idle';
            $state->payload = [];
            $state->updatedAt = time();

            $this->begin();
            try {
                $reset = $this->pdo->prepare(
                    'UPDATE chat_state SET mode = :mode, payload = :payload, updated_at = :updated_at WHERE chat_id = :chat_id'
                );
                $reset->execute([
                    ':mode' => $state->mode,
                    ':payload' => self::encodePayload([]),
                    ':updated_at' => $state->updatedAt,
                    ':chat_id' => $chatId,
                ]);
                $this->pdo->commit();
            } catch (PDOException $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        return $state;
    }

    public function save(ChatState $state): void
    {
        $state->updatedAt = time();

        $this->begin();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO chat_state (chat_id, telegram_user_id, role, mode, payload, active_screen_message_id, updated_at)
                 VALUES (:chat_id, :telegram_user_id, :role, :mode, :payload, :active_screen_message_id, :updated_at)
                 ON CONFLICT(chat_id) DO UPDATE SET
                    telegram_user_id = excluded.telegram_user_id,
                    role = excluded.role,
                    mode = excluded.mode,
                    payload = excluded.payload,
                    active_screen_message_id = excluded.active_screen_message_id,
                    updated_at = excluded.updated_at'
            );
            $stmt->execute([
                ':chat_id' => $state->chatId,
                ':telegram_user_id' => $state->telegramUserId,
                ':role' => $state->role,
                ':mode' => $state->mode,
                ':payload' => self::encodePayload($state->payload),
                ':active_screen_message_id' => $state->activeScreenMessageId,
                ':updated_at' => $state->updatedAt,
            ]);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function cleanup(int $olderThanSeconds = 86400): void
    {
        $cutoff = time() - $olderThanSeconds;

        $this->begin();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM processed_update WHERE created_at < :cutoff');
            $stmt->execute([':cutoff' => $cutoff]);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function migrate(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS chat_state (
                chat_id INTEGER PRIMARY KEY,
                telegram_user_id INTEGER,
                role TEXT,
                mode TEXT NOT NULL DEFAULT \'idle\',
                payload TEXT,
                active_screen_message_id INTEGER,
                updated_at INTEGER NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS processed_update (
                update_id INTEGER PRIMARY KEY,
                created_at INTEGER NOT NULL
            )'
        );
    }

    private function begin(): void
    {
        $this->pdo->exec('BEGIN IMMEDIATE');
    }

    /** @param array<string,mixed> $payload */
    private static function encodePayload(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**  array<string,mixed> */
    private static function decodePayload(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
