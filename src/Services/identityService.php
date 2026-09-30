<?php

namespace App\Services;

use App\Api\FamoApiClient;

class IdentityService
{
    public function __construct(private FamoApiClient $api) {}

    public function isLinked($chatId): ?bool
    {
        // GET /api/v1/bot/identity/resolve?chat_id=...
        $res = $this->api->request('GET', '/bot/identity/resolve', [], null, [
            'chat_id' => (string) $chatId,
        ]);

        if (!$res->ok()) {
        throw new \Exception('Failed to resolve identity: ' . $res->status() . ' ' . $res->body());
        }
        return $res->data()['links']['name'] ?? null !== null;
    }
    
}