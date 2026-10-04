<?php

use Telegram\Bot\Api;
use Telegram\Bot\HttpClients\GuzzleHttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use App\Commands\StartCommand;
use App\Commands\HelpCommand;
use App\Commands\ReportCommand;
use App\Handlers\CallBackHandler;
use App\Errors\ErrorHandler;
use App\Logging\Logger;

class Bot
{
    private Api $telegram;
    private CallBackHandler $callbackHandler;

    public function __construct(string $token)
    {
        $this->telegram = $this->buildTelegram($token);
        $this->registerCommands();
        $this->callbackHandler = new CallBackHandler($this->telegram);
    }

    private function buildTelegram(string $token): Api
    {
        $stack = HandlerStack::create();

        $stack->push(Middleware::mapRequest(function ($request) {
            $body = (string) $request->getBody();
            if ($request->getBody()->isSeekable()) {
                $request->getBody()->rewind();
            }
            Logger::debug('telegram.request', [
                'method' => $request->getMethod(),
                'url' => (string) $request->getUri(),
                'body' => $body,
            ]);

            return $request;
        }));

        $stack->push(Middleware::mapResponse(function ($response) {
            $body = (string) $response->getBody();
            if ($response->getBody()->isSeekable()) {
                $response->getBody()->rewind();
            }
            Logger::debug('telegram.response', [
                'status' => $response->getStatusCode(),
                'body' => $body,
            ]);

            return $response;
        }));

        return new Api(
            $token,
            false,
            new GuzzleHttpClient(new Client(['handler' => $stack]))
        );
    }

    private function registerCommands(): void
    {
        $this->telegram->addCommands([
            StartCommand::class,
            HelpCommand::class,
            ReportCommand::class,
        ]);
    }

    public function handle(): void
    {
        $update = $this->telegram->getWebhookUpdate();

        Logger::info('telegram.update', [
            'type' => $update->objectType(),
            'chat_id' => $update->getChat()->get('id'),
            'raw' => $update->getRawResponse(),
        ]);

    }
}
