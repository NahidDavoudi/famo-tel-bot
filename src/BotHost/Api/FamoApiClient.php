<?php
declare(strict_types=1);

namespace BotHost\Api;

class FamoApiClient
{
    public function __construct(
        private string $serviceKey,
        private string $baseUrl,
        private int $timeout = 15,
    ) {}

    /**
     * @param array<string,string> $headers
     * @param array<string,mixed>|null $json
     * @param array<string,string> $query
     */
    public function request(string $method, string $path, array $headers = [], ?array $json = null, array $query = []): ApiResult
    {
        $url = $this->baseUrl . '/api/v1' . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $allHeaders = ['X-Bot-Key: ' . $this->serviceKey, 'Accept: application/json'];
        foreach ($headers as $name => $value) {
            $allHeaders[] = $name . ': ' . $value;
        }
        $body = null;
        if ($json !== null) {
            $body = json_encode($json, JSON_UNESCAPED_UNICODE);
            $allHeaders[] = 'Content-Type: application/json';
        }

        $response = $this->http($method, $url, $allHeaders, $body);

        if (($response['transport'] ?? null) !== null) {
            return new ApiResult(0, null, null, (string) $response['transport']);
        }
        $status = (int) $response['status'];
        $decoded = $response['body'];
        $decoded = is_array($decoded) ? $decoded : null;
        $code = $decoded['error']['code'] ?? null;
        return new ApiResult($status, $decoded, is_string($code) ? $code : null);
    }

    public function ping(): ApiResult
    {
        return $this->request('GET', '/bot/ping');
    }

    /**
     * @param list<string> $headers
     * @return array{status:int, body:array<string,mixed>|null, transport:?string}
     */
    protected function http(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $errno !== 0) {
            return ['status' => 0, 'body' => null, 'transport' => $error !== '' ? $error : (string) $errno];
        }
        $decoded = json_decode((string) $raw, true);
        return ['status' => $status, 'body' => is_array($decoded) ? $decoded : null, 'transport' => null];
    }
}
