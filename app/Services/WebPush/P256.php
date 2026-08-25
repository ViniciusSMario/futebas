<?php

namespace App\Services\WebPush;

use RuntimeException;

/**
 * Minimal NIST P-256 (prime256v1) helpers built on ext-openssl.
 *
 * Web Push identifies keys by their *raw* bytes — a 65-byte uncompressed
 * point for public keys, 32 big-endian bytes for private keys — while
 * OpenSSL only speaks PEM. Everything in this class exists to bridge those
 * two representations; the DER prefixes below are the fixed ASN.1 wrappers
 * for a prime256v1 key, so a raw point can be re-hydrated into a PEM that
 * `openssl_pkey_get_*` accepts.
 */
final class P256
{
    /** Uncompressed point: 0x04 || X(32) || Y(32). */
    public const PUBLIC_KEY_LENGTH = 65;

    public const PRIVATE_KEY_LENGTH = 32;

    /** SubjectPublicKeyInfo header for an uncompressed prime256v1 point. */
    private const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** OID 1.2.840.10045.3.1.7 (prime256v1), DER-encoded. */
    private const CURVE_OID = '06082a8648ce3d030107';

    /**
     * Create a fresh ephemeral key pair, returned as raw bytes.
     *
     * The scalar is drawn directly from the CSPRNG rather than through
     * `openssl_pkey_new()`, which needs an openssl.cnf that plenty of PHP
     * builds (Windows especially) do not ship. A random 256-bit value is
     * outside the curve order only with negligible probability; when it
     * is, OpenSSL rejects it and we simply draw again.
     *
     * @return array{public: string, private: string}
     */
    public static function generateKeyPair(): array
    {
        foreach (range(1, 4) as $ignored) {
            $private = random_bytes(self::PRIVATE_KEY_LENGTH);

            try {
                return ['public' => self::publicKeyFromPrivate($private), 'private' => $private];
            } catch (RuntimeException) {
                continue;
            }
        }

        throw new RuntimeException('Não foi possível gerar um par de chaves P-256.');
    }

    /**
     * Derive the public point belonging to a raw private key, by letting
     * OpenSSL recompute it from a PEM that carries only the scalar.
     */
    public static function publicKeyFromPrivate(string $privateKey): string
    {
        $key = openssl_pkey_get_private(self::privateKeyPem($privateKey, null));

        if ($key === false) {
            throw new RuntimeException('Chave privada P-256 inválida: '.openssl_error_string());
        }

        $details = openssl_pkey_get_details($key);

        return "\x04".self::pad($details['ec']['x']).self::pad($details['ec']['y']);
    }

    /**
     * ECDH: the 32-byte shared secret between our private key and the
     * subscriber's public point.
     */
    public static function sharedSecret(string $privateKey, string $peerPublicKey): string
    {
        $private = openssl_pkey_get_private(self::privateKeyPem($privateKey, null));
        $peer = openssl_pkey_get_public(self::publicKeyPem($peerPublicKey));

        if ($private === false || $peer === false) {
            throw new RuntimeException('Chave P-256 inválida na troca ECDH: '.openssl_error_string());
        }

        $secret = openssl_pkey_derive($peer, $private, self::PRIVATE_KEY_LENGTH);

        if ($secret === false) {
            throw new RuntimeException('Falha ao derivar o segredo compartilhado: '.openssl_error_string());
        }

        return str_pad($secret, self::PRIVATE_KEY_LENGTH, "\0", STR_PAD_LEFT);
    }

    /**
     * Sign with ES256, returning the raw R||S form that JWS requires
     * instead of OpenSSL's ASN.1 SEQUENCE.
     */
    public static function signEs256(string $message, string $privateKey): string
    {
        $key = openssl_pkey_get_private(self::privateKeyPem($privateKey, null));

        if ($key === false || ! openssl_sign($message, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Falha ao assinar o token VAPID: '.openssl_error_string());
        }

        return self::rawSignature($der);
    }

    public static function publicKeyPem(string $rawPublicKey): string
    {
        if (strlen($rawPublicKey) !== self::PUBLIC_KEY_LENGTH || $rawPublicKey[0] !== "\x04") {
            throw new RuntimeException('A chave pública deve ser um ponto P-256 não comprimido de 65 bytes.');
        }

        return self::pem('PUBLIC KEY', hex2bin(self::SPKI_PREFIX).$rawPublicKey);
    }

    /**
     * SEC1 "EC PRIVATE KEY" PEM. The public point is optional: OpenSSL
     * recomputes it when absent, which is what lets us store only the
     * 32-byte scalar in the environment.
     */
    public static function privateKeyPem(string $rawPrivateKey, ?string $rawPublicKey = null): string
    {
        $scalar = self::pad($rawPrivateKey);

        $body = "\x02\x01\x01\x04\x20".$scalar."\xa0\x0a".hex2bin(self::CURVE_OID);

        if ($rawPublicKey !== null) {
            $body .= "\xa1\x44\x03\x42\x00".$rawPublicKey;
        }

        return self::pem('EC PRIVATE KEY', "\x30".self::derLength(strlen($body)).$body);
    }

    /** URL-safe base64 without padding, as every Web Push spec expects. */
    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Valor base64url inválido.');
        }

        return $decoded;
    }

    /** Left-pad a big-endian scalar/coordinate to its full 32 bytes. */
    private static function pad(string $value): string
    {
        return str_pad(ltrim($value, "\0"), self::PRIVATE_KEY_LENGTH, "\0", STR_PAD_LEFT);
    }

    private static function pem(string $label, string $der): string
    {
        return "-----BEGIN {$label}-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END {$label}-----\n";
    }

    private static function derLength(int $length): string
    {
        return $length < 128 ? chr($length) : "\x81".chr($length);
    }

    /**
     * Unwrap `SEQUENCE { INTEGER r, INTEGER s }` into two fixed-width
     * 32-byte halves.
     */
    private static function rawSignature(string $der): string
    {
        $offset = 2 + (ord($der[1]) < 128 ? 0 : 1);

        $read = function (int &$offset) use ($der): string {
            $length = ord($der[$offset + 1]);
            $value = substr($der, $offset + 2, $length);
            $offset += 2 + $length;

            return self::pad($value);
        };

        $r = $read($offset);
        $s = $read($offset);

        return $r.$s;
    }
}
