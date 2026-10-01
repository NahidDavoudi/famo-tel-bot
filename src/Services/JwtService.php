<?php
declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    private static ?string $secret = null;

    private static string $algorithm = 'HS256';

    public static function boot(?string $secret = null, string $algorithm = 'HS256'): void
    {
        self::$secret = ($secret !== null && $secret !== '') ? $secret : self::envSecret();
        self::$algorithm = $algorithm;
    }

    /** @param array<string,mixed> $payload */
    public static function encode(array $payload, int $ttlSeconds = 3600): string
    {
        $now = time();
        $payload += ['iat' => $now, 'exp' => $now + $ttlSeconds];

        return JWT::encode($payload, self::secret(), self::$algorithm);
    }

    /** @return array<string,mixed> */
    public static function decode(string $token): array
    {
        return (array) JWT::decode($token, new Key(self::secret(), self::$algorithm));
    }

    private static function secret(): string
    {
        $secret = self::$secret ?? self::envSecret();
        if ($secret === null || $secret === '') {
            throw new \RuntimeException('JWT secret is not configured (set JWT_SECRET).');
        }

        return $secret;
    }

    private static function envSecret(): ?string
    {
        $value = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');

        return ($value === false || $value === null || $value === '') ? null : (string) $value;
    }
}
