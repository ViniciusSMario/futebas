<?php

namespace App\Services\WebPush;

/**
 * Message Encryption for Web Push (RFC 8291) using the `aes128gcm`
 * content coding (RFC 8188).
 *
 * The browser hands us two values when it subscribes — a P-256 public key
 * (`p256dh`) and a 16-byte `auth` secret — and only the browser can undo
 * what happens here, so the push service never sees the payload.
 */
final class PushEncryption
{
    private const RECORD_SIZE = 4096;

    /** Marks the last (and here, only) record of the payload. */
    private const PADDING_DELIMITER = "\x02";

    /**
     * Encrypt a payload for one subscription, returning the raw HTTP body:
     * `salt(16) || record size(4) || key id length(1) || sender public key(65) || ciphertext`.
     *
     * The salt and sender key pair are parameters purely so the RFC 8291
     * test vectors can be reproduced; in production both are random.
     *
     * @param  array{public: string, private: string}|null  $senderKeyPair
     */
    public static function encrypt(
        string $payload,
        string $receiverPublicKey,
        string $authSecret,
        ?string $salt = null,
        ?array $senderKeyPair = null,
    ): string {
        $salt ??= random_bytes(16);
        $sender = $senderKeyPair ?? P256::generateKeyPair();

        $sharedSecret = P256::sharedSecret($sender['private'], $receiverPublicKey);

        // Combine the ECDH secret with the subscription's auth secret, binding
        // the result to both public keys so it can't be replayed elsewhere.
        $keyInfo = "WebPush: info\x00".$receiverPublicKey.$sender['public'];
        $ikm = hash_hkdf('sha256', $sharedSecret, 32, $keyInfo, $authSecret);

        $contentEncryptionKey = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        $ciphertext = openssl_encrypt(
            $payload.self::PADDING_DELIMITER,
            'aes-128-gcm',
            $contentEncryptionKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16,
        );

        return $salt
            .pack('N', self::RECORD_SIZE)
            .chr(strlen($sender['public']))
            .$sender['public']
            .$ciphertext
            .$tag;
    }
}
