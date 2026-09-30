<?php
declare(strict_types=1);

namespace BotHost;

use BotHost\Api\FamoApiClient;
use BotHost\Logging\RuntimeLogger;
use BotHost\Outbox\OutboxDrainer;
use BotHost\Storage\LocalStore;
use BotHost\Storage\RuntimeStats;
use BotHost\Telegram\TelegramClient;

final class Bootstrap
{
    private function __construct(
        private Config $config,
        private LocalStore $store,
        private RuntimeStats $stats,
        private RuntimeLogger $logger,
        private OutboxDrainer $drainer,
        private TelegramClient $telegram,
        private FamoApiClient $api,
    ) {}

    public static function create(?string $root = null): self
    {
        $root ??= dirname(__DIR__, 2);
        if (is_file($root . '/.env')) {
            \Dotenv\Dotenv::createImmutable($root)->safeLoad();
        }

        $config = Config::fromEnv();
        $storageDir = $config->get('BOT_STORAGE_DIR') ?? $root . '/storage';
        $runtimeFile = $config->get('BOT_RUNTIME_FILE') ?? $root . '/admin/data/bot-runtime.json';
        $logFile = $config->get('BOT_LOG_FILE') ?? $root . '/admin/data/logs.json';

        $store = new LocalStore($storageDir);
        $stats = new RuntimeStats($runtimeFile);
        $logger = new RuntimeLogger($logFile);
        $drainer = new OutboxDrainer(rtrim($storageDir, "/\\") . DIRECTORY_SEPARATOR . 'drain.lock', $stats);
        $telegram = new TelegramClient($config->require('TELEGRAM_BOT_TOKEN'), $config->telegramBaseUrl());
        $api = new FamoApiClient($config->require('BOT_SERVICE_KEY'), $config->apiBaseUrl());

        return new self($config, $store, $stats, $logger, $drainer, $telegram, $api);
    }

    public function config(): Config { return $this->config; }
    public function store(): LocalStore { return $this->store; }
    public function stats(): RuntimeStats { return $this->stats; }
    public function logger(): RuntimeLogger { return $this->logger; }
    public function drainer(): OutboxDrainer { return $this->drainer; }
    public function telegram(): TelegramClient { return $this->telegram; }
    public function api(): FamoApiClient { return $this->api; }

    public function webhookHandler(): WebhookHandler
    {
        return new WebhookHandler($this->config, $this->store, $this->stats, $this->logger, $this->drainer);
    }
}
