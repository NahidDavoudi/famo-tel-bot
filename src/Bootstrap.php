<?php
declare(strict_types=1);

namespace BotHost;

use Api\FamoApiClient;
use Logging\RuntimeLogger;
use Outbox\OutboxDrainer;
use Storage\LocalStore;
use Storage\RuntimeStats;
use Telegram\TelegramClient;

final class Bootstrap
{
    private function __construct(
        private Config $config,
        private LocalStore $store,
        private RuntimeStats $stats,
        private RuntimeLogger $logger,
        private OutboxDrainer $drainer,
        private ?TelegramClient $telegram = null,
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

        $store = new LocalStore($storageDir);
        $stats = new RuntimeStats($runtimeFile);
        $logger = new RuntimeLogger($logFile);
        $drainer = new OutboxDrainer(rtrim($storageDir, "/\\") . DIRECTORY_SEPARATOR . 'drain.lock', $stats);

        return new self($config, $store, $stats, $logger, $drainer);
    }

    public function config(): Config { return $this->config; }
    public function store(): LocalStore { return $this->store; }
    public function stats(): RuntimeStats { return $this->stats; }
    public function logger(): RuntimeLogger { return $this->logger; }
    public function drainer(): OutboxDrainer { return $this->drainer; }

    public function telegram(): TelegramClient
    {
        return $this->telegram ??= new TelegramClient($this->config->require('TELEGRAM_BOT_TOKEN'), $this->config->telegramBaseUrl());
    }

    public function api(): FamoApiClient
    {
        return $this->api ??= new FamoApiClient($this->config->require('BOT_SERVICE_KEY'), $this->config->apiBaseUrl());
    }

    public function webhookHandler(): WebhookHandler
    {
        return new WebhookHandler($this->config, $this->store, $this->stats, $this->logger, $this->drainer);
    }
}
