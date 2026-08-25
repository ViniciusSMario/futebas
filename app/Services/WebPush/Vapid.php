<?php

namespace App\Services\WebPush;

use RuntimeException;

/**
 * Voluntary Application Server Identification (RFC 8292).
 *
 * Every push service requires the application server to sign a short-lived
 * ES256 JWT scoped to the push endpoint's origin. Browsers use the public
 * half of the same key pair when subscribing, which is why it is exposed
 * to the frontend via a meta tag.
 */
final class Vapid
{
    /** Push services reject tokens valid for more than 24h; stay well under. */
    private const TOKEN_LIFETIME = 12 * 3600;

    public function __construct(
        private readonly ?string $publicKey,
        private readonly ?string $privateKey,
        private readonly string $subject,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->publicKey) && filled($this->privateKey);
    }

    public function publicKey(): ?string
    {
        return $this->publicKey;
    }

    /**
     * The `Authorization` header for a request to the given push endpoint.
     */
    public function authorizationHeader(string $endpoint): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('As chaves VAPID não estão configuradas.');
        }

        $token = $this->token($this->audienceFor($endpoint));

        return "vapid t={$token}, k={$this->publicKey}";
    }

    /**
     * Generate a brand new VAPID key pair, base64url-encoded and ready to
     * be pasted into the .env file.
     *
     * @return array{public: string, private: string}
     */
    public static function generateKeys(): array
    {
        $pair = P256::generateKeyPair();

        return [
            'public' => P256::base64UrlEncode($pair['public']),
            'private' => P256::base64UrlEncode($pair['private']),
        ];
    }

    /**
     * The `aud` claim is the scheme + host of the endpoint, never the full
     * subscription URL — push services reject the latter.
     */
    private function audienceFor(string $endpoint): string
    {
        $parts = parse_url($endpoint);

        if (! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException("Endpoint de push inválido: {$endpoint}");
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$parts['scheme']}://{$parts['host']}{$port}";
    }

    private function token(string $audience): string
    {
        $header = $this->encodeSegment(['typ' => 'JWT', 'alg' => 'ES256']);
        $claims = $this->encodeSegment([
            'aud' => $audience,
            'exp' => time() + self::TOKEN_LIFETIME,
            'sub' => $this->subject,
        ]);

        $signature = P256::signEs256(
            "{$header}.{$claims}",
            P256::base64UrlDecode($this->privateKey),
        );

        return "{$header}.{$claims}.".P256::base64UrlEncode($signature);
    }

    private function encodeSegment(array $data): string
    {
        return P256::base64UrlEncode(json_encode($data, JSON_UNESCAPED_SLASHES));
    }
}
