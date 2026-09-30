<?php
declare(strict_types=1);

namespace App;

use App\Api\FamoApiClient;
use App\Logging\RuntimeLogger;
use App\Outbox\OutboxDrainer;

final class Bootstrap
{
    private function __construct(
        private Config $config,
        private OutboxDrainer $drainer,
        private ?FamoApiClient $api = null,
    ) {}

    public static function create(?string $root = null, ?Config $config = null): self
    {
        $root ??= dirname(__DIR__, 2);
        if ($config === null) {
            if (is_file($root . '/.env')) {
                \Dotenv\Dotenv::createImmutable($root)->safeLoad();
            }
            $config = Config::fromEnv();
        }

        $storageDir = $config->get('BOT_STORAGE_DIR') ?? $root . '/storage';
        $runtimeFile = $config->get('BOT_RUNTIME_FILE') ?? $root . '/admin/data/bot-runtime.json';
        $logFile = $config->get('BOT_LOG_FILE') ?? $root . '/admin/data/logs.json';

        $drainer = new OutboxDrainer(rtrim($storageDir, "/\\") . DIRECTORY_SEPARATOR . 'drain.lock');

        return new self($config, $drainer);
    }

    public function config(): Config { return $this->config; }
    public function drainer(): OutboxDrainer { return $this->drainer; }

    public function api(): FamoApiClient
    {
        return $this->api ??= new FamoApiClient($this->config->require('BOT_SERVICE_KEY'), $this->config->apiBaseUrl());
    }

    public function webhookHandler(): WebhookHandler
    {
        return new WebhookHandler($this->config,$this->logger, $this->drainer);
    }
}
