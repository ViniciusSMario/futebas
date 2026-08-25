<?php

namespace Tests\Unit;

use App\Services\WebPush\P256;
use App\Services\WebPush\PushEncryption;
use App\Services\WebPush\Vapid;
use PHPUnit\Framework\TestCase;

/**
 * Web Push encryption is only correct if a *browser* can decrypt it, which
 * a test suite cannot do. The published RFC test vectors are the next best
 * thing: they pin every step of the derivation to a known-good output.
 *
 * Vectors from RFC 8291 §5 and Appendix A.
 */
class WebPushEncryptionTest extends TestCase
{
    private const UA_PRIVATE = 'q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94';

    private const UA_PUBLIC = 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4';

    private const AS_PRIVATE = 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw';

    private const AS_PUBLIC = 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8';

    private const AUTH_SECRET = 'BTBZMqHH6r4Tts7J_aSIgg';

    private const SALT = 'DGv6ra1nlYgDCS1FRnbzlw';

    private const PLAINTEXT = 'When I grow up, I want to be a watermelon';

    private const EXPECTED_BODY = 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN';

    public function test_it_reproduces_the_rfc_8291_encrypted_body(): void
    {
        $body = PushEncryption::encrypt(
            self::PLAINTEXT,
            P256::base64UrlDecode(self::UA_PUBLIC),
            P256::base64UrlDecode(self::AUTH_SECRET),
            P256::base64UrlDecode(self::SALT),
            [
                'public' => P256::base64UrlDecode(self::AS_PUBLIC),
                'private' => P256::base64UrlDecode(self::AS_PRIVATE),
            ],
        );

        $this->assertSame(self::EXPECTED_BODY, P256::base64UrlEncode($body));
    }

    public function test_the_ecdh_secret_matches_the_rfc_vector(): void
    {
        $secret = P256::sharedSecret(
            P256::base64UrlDecode(self::AS_PRIVATE),
            P256::base64UrlDecode(self::UA_PUBLIC),
        );

        $this->assertSame('kyrL1jIIOHEzg3sM2ZWRHDRB62YACZhhSlknJ672kSs', P256::base64UrlEncode($secret));
    }

    public function test_the_shared_secret_is_the_same_from_either_side(): void
    {
        $fromSender = P256::sharedSecret(
            P256::base64UrlDecode(self::AS_PRIVATE),
            P256::base64UrlDecode(self::UA_PUBLIC),
        );

        $fromReceiver = P256::sharedSecret(
            P256::base64UrlDecode(self::UA_PRIVATE),
            P256::base64UrlDecode(self::AS_PUBLIC),
        );

        $this->assertSame($fromSender, $fromReceiver);
    }

    public function test_it_recomputes_the_public_point_from_a_private_key(): void
    {
        $this->assertSame(
            self::AS_PUBLIC,
            P256::base64UrlEncode(P256::publicKeyFromPrivate(P256::base64UrlDecode(self::AS_PRIVATE))),
        );
    }

    public function test_generated_key_pairs_are_valid_and_self_consistent(): void
    {
        $pair = P256::generateKeyPair();

        $this->assertSame(P256::PUBLIC_KEY_LENGTH, strlen($pair['public']));
        $this->assertSame(P256::PRIVATE_KEY_LENGTH, strlen($pair['private']));
        $this->assertSame("\x04", $pair['public'][0]);
        $this->assertSame($pair['public'], P256::publicKeyFromPrivate($pair['private']));
    }

    public function test_each_encryption_uses_a_fresh_salt_and_ephemeral_key(): void
    {
        $arguments = [self::PLAINTEXT, P256::base64UrlDecode(self::UA_PUBLIC), P256::base64UrlDecode(self::AUTH_SECRET)];

        $this->assertNotSame(
            PushEncryption::encrypt(...$arguments),
            PushEncryption::encrypt(...$arguments),
        );
    }

    public function test_the_body_carries_the_aes128gcm_header(): void
    {
        $body = PushEncryption::encrypt(
            self::PLAINTEXT,
            P256::base64UrlDecode(self::UA_PUBLIC),
            P256::base64UrlDecode(self::AUTH_SECRET),
        );

        // salt(16) || record size(4) || key id length(1) || key(65)
        $this->assertSame(4096, unpack('N', substr($body, 16, 4))[1]);
        $this->assertSame(65, ord($body[20]));
        $this->assertSame("\x04", $body[21]);
        $this->assertSame(86 + strlen(self::PLAINTEXT) + 1 + 16, strlen($body));
    }

    public function test_the_vapid_authorization_header_is_a_signed_es256_token(): void
    {
        $keys = Vapid::generateKeys();
        $vapid = new Vapid($keys['public'], $keys['private'], 'mailto:contato@futebas.test');

        $header = $vapid->authorizationHeader('https://fcm.googleapis.com/fcm/send/abc123?x=1');

        $this->assertStringStartsWith('vapid t=', $header);
        $this->assertStringContainsString(", k={$keys['public']}", $header);

        [$encodedHeader, $encodedClaims, $encodedSignature] = explode('.', substr($header, 8, strpos($header, ',') - 8));

        $this->assertSame(['typ' => 'JWT', 'alg' => 'ES256'], json_decode(P256::base64UrlDecode($encodedHeader), true));

        $claims = json_decode(P256::base64UrlDecode($encodedClaims), true);
        // The audience is the endpoint's origin, never the full URL.
        $this->assertSame('https://fcm.googleapis.com', $claims['aud']);
        $this->assertSame('mailto:contato@futebas.test', $claims['sub']);
        $this->assertGreaterThan(time(), $claims['exp']);
        $this->assertLessThanOrEqual(time() + 24 * 3600, $claims['exp']);

        $this->assertSame(64, strlen(P256::base64UrlDecode($encodedSignature)));
        $this->assertSame(1, $this->verifyEs256(
            "{$encodedHeader}.{$encodedClaims}",
            P256::base64UrlDecode($encodedSignature),
            P256::base64UrlDecode($keys['public']),
        ));
    }

    public function test_it_reports_when_vapid_keys_are_missing(): void
    {
        $this->assertFalse((new Vapid(null, null, 'mailto:a@b.test'))->isConfigured());
        $this->assertTrue((new Vapid('pub', 'priv', 'mailto:a@b.test'))->isConfigured());
    }

    /**
     * Verify a raw R||S signature by rebuilding the ASN.1 form OpenSSL
     * expects — the inverse of what P256 does when signing.
     */
    private function verifyEs256(string $message, string $signature, string $publicKey): int
    {
        $encodeInteger = function (string $value): string {
            $value = ltrim($value, "\0");

            if (ord($value[0]) > 0x7F) {
                $value = "\0".$value;
            }

            return "\x02".chr(strlen($value)).$value;
        };

        $sequence = $encodeInteger(substr($signature, 0, 32)).$encodeInteger(substr($signature, 32));

        return openssl_verify(
            $message,
            "\x30".chr(strlen($sequence)).$sequence,
            P256::publicKeyPem($publicKey),
            OPENSSL_ALGO_SHA256,
        );
    }
}
