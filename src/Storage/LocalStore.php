<?php
declare(strict_types=1);

namespace Storage;

final class LocalStore
{
    private \PDO $pdo;

    public function __construct(string $storageDir)
    {
        if (!is_dir($storageDir) && !mkdir($storageDir, 0770, true) && !is_dir($storageDir)) {
            throw new \RuntimeException("Cannot create storage dir: {$storageDir}");
        }
        $dsn = 'sqlite:' . rtrim($storageDir, "/\\") . DIRECTORY_SEPARATOR . 'bot.sqlite';
        $this->pdo = new \PDO($dsn, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS processed_updates (update_id INTEGER PRIMARY KEY, processed_at TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS update_queue (update_id INTEGER PRIMARY KEY, payload TEXT NOT NULL, created_at TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS state (chat_id TEXT PRIMARY KEY, data TEXT NOT NULL, expires_at INTEGER NOT NULL)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
    }

    /**
     * Atomically gate on processed_updates and enqueue a new update.
     * Returns true only when the update was newly accepted (and therefore queued).
     *
     * @param array<string,mixed> $update
     */
    public function acceptUpdate(int $updateId, array $update): bool
    {
        $this->pdo->beginTransaction();
        try {
            $inserted = $this->pdo->prepare('INSERT OR IGNORE INTO processed_updates (update_id, processed_at) VALUES (?, ?)');
            $inserted->execute([$updateId, date('c')]);
            $isNew = $inserted->rowCount() > 0;
            if ($isNew) {
                $this->pdo->prepare('INSERT OR IGNORE INTO update_queue (update_id, payload, created_at) VALUES (?, ?, ?)')
                    ->execute([$updateId, json_encode($update, JSON_UNESCAPED_UNICODE), date('c')]);
            }
            $this->pdo->commit();
            return $isNew;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function markProcessed(int $updateId): bool
    {
        try {
            $this->pdo->prepare('INSERT INTO processed_updates (update_id, processed_at) VALUES (?, ?)')
                ->execute([$updateId, date('c')]);
            return true;
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    /** @param array<string,mixed> $update */
    public function enqueueUpdate(int $updateId, array $update): void
    {
        $this->pdo->prepare('INSERT OR IGNORE INTO update_queue (update_id, payload, created_at) VALUES (?, ?, ?)')
            ->execute([$updateId, json_encode($update, JSON_UNESCAPED_UNICODE), date('c')]);
    }

    /** @return list<array{update_id:int,payload:array<string,mixed>}> */
    public function dequeueUpdates(int $limit): array
    {
        $stmt = $this->pdo->prepare('SELECT update_id, payload FROM update_queue ORDER BY update_id ASC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn ($r) => (int) $r['update_id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->pdo->prepare("DELETE FROM update_queue WHERE update_id IN ({$placeholders})")->execute($ids);

        return array_map(static fn ($r) => [
            'update_id' => (int) $r['update_id'],
            'payload' => json_decode((string) $r['payload'], true) ?: [],
        ], $rows);
    }

    /** @param array<string,mixed> $data */
    public function setState(string $chatId, array $data, int $ttlSeconds): void
    {
        $this->pdo->prepare(
            'INSERT INTO state (chat_id, data, expires_at) VALUES (?, ?, ?)
             ON CONFLICT(chat_id) DO UPDATE SET data = excluded.data, expires_at = excluded.expires_at'
        )->execute([$chatId, json_encode($data, JSON_UNESCAPED_UNICODE), time() + $ttlSeconds]);
    }

    /** @return array<string,mixed>|null */
    public function getState(string $chatId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT data, expires_at FROM state WHERE chat_id = ?');
        $stmt->execute([$chatId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        if ((int) $row['expires_at'] < time()) {
            $this->clearState($chatId);
            return null;
        }
        $data = json_decode((string) $row['data'], true);
        return is_array($data) ? $data : null;
    }

    public function clearState(string $chatId): void
    {
        $this->pdo->prepare('DELETE FROM state WHERE chat_id = ?')->execute([$chatId]);
    }

    public function setMeta(string $key, string $value): void
    {
        $this->pdo->prepare(
            'INSERT INTO meta (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        )->execute([$key, $value]);
    }

    public function getMeta(string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM meta WHERE key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string) $value;
    }
}
