<?php

declare(strict_types=1);

namespace Mailtea;

/**
 * Standard Webhooks (standardwebhooks.com) signature verification.
 *
 * A dependency-free mirror of the Mailtea signer, kept in exact parity with the
 * platform's own implementation so a signature produced there verifies here
 * byte for byte.
 *
 * The stored signing secret is `whsec_<base64>`; the HMAC key is that base64
 * remainder decoded to bytes. The signed content is
 * `{msg_id}.{timestamp}.{payload}` where `timestamp` is Unix SECONDS, matching
 * the `webhook-timestamp` header. The `webhook-signature` header is
 * `v1,<base64 HMAC-SHA256>`; during key rotation it may carry several
 * space-delimited `v1,<sig>` tokens, and a match against any one of them passes.
 */
final class WebhookSigning
{
    private const SECRET_PREFIX = 'whsec_';
    private const SIGNATURE_VERSION = 'v1';

    /** Allowed clock skew each way, in seconds. Five minutes. */
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * Sign a webhook payload.
     *
     * Returns the `webhook-signature` header value in Standard Webhooks form,
     * `v1,<base64 HMAC-SHA256>`. Useful for faking Mailtea deliveries in tests.
     *
     * @param string    $msgId     The `webhook-id` value.
     * @param int|float $timestamp Unix SECONDS — the same value sent in
     *                             `webhook-timestamp`.
     * @param string    $payload   The raw body, exactly as it will be sent.
     *
     * @throws MailteaException when the secret is not valid base64.
     */
    public static function sign(string $secret, string $msgId, int|float $timestamp, string $payload): string
    {
        $key = self::decodeSigningKey($secret);
        if ($key === false) {
            throw new MailteaException(
                'The webhook signing secret is not valid base64.',
                0,
                'invalid_signing_secret'
            );
        }

        return self::SIGNATURE_VERSION . ',' . self::compute($key, $msgId, (int) floor($timestamp), $payload);
    }

    /**
     * Verify a `webhook-signature` header against the expected HMAC.
     *
     * Returns false rather than throwing for every rejection there is — a bad
     * signature, a timestamp outside the tolerance (replay protection), a
     * malformed header, an unreadable secret. A handler's job is to answer 401,
     * not to decide which flavour of forgery it was looking at.
     *
     * The comparison is constant-time, so a caller cannot learn the expected
     * signature one byte at a time by measuring how long a rejection takes.
     *
     * @param string           $secret          The endpoint's signing secret (`whsec_…`).
     * @param string           $msgId           The `webhook-id` header value.
     * @param int|float|string $timestamp       The `webhook-timestamp` header value
     *                                          (Unix seconds; the string PHP hands
     *                                          you from the header is fine).
     * @param string           $payload         The RAW request body, exactly as
     *                                          received — not re-encoded JSON,
     *                                          which reorders keys and breaks the
     *                                          signature.
     * @param string           $signatureHeader The `webhook-signature` header value.
     * @param int              $toleranceSeconds
     * @param int|float|null   $now             Injectable current time (Unix
     *                                          seconds) for tests.
     */
    public static function verify(
        string $secret,
        string $msgId,
        int|float|string $timestamp,
        string $payload,
        string $signatureHeader,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        int|float|null $now = null,
    ): bool {
        if (!is_numeric($timestamp)) {
            return false;
        }
        $timestampValue = (float) $timestamp;
        if (!is_finite($timestampValue)) {
            return false;
        }
        $timestampSeconds = (int) floor($timestampValue);

        $nowSeconds = $now === null ? time() : (int) floor($now);
        if (abs($nowSeconds - $timestampSeconds) > $toleranceSeconds) {
            return false;
        }

        $key = self::decodeSigningKey($secret);
        if ($key === false) {
            return false;
        }

        $expected = self::compute($key, $msgId, $timestampSeconds, $payload);

        foreach (explode(' ', $signatureHeader) as $token) {
            if ($token === '') {
                continue;
            }
            $separator = strpos($token, ',');
            if ($separator === false) {
                continue;
            }
            $version = substr($token, 0, $separator);
            $signature = substr($token, $separator + 1);
            if ($version === self::SIGNATURE_VERSION && hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private static function compute(string $key, string $msgId, int $timestamp, string $payload): string
    {
        return base64_encode(
            hash_hmac('sha256', $msgId . '.' . $timestamp . '.' . $payload, $key, true)
        );
    }

    /**
     * Decode the HMAC key from a `whsec_`-prefixed secret.
     *
     * Matches the platform's lenient decoder: it accepts the base64url alphabet
     * and tolerates missing padding, so a secret minted with either alphabet
     * decodes to the same bytes.
     *
     * @return string|false The raw key, or false when the secret is not base64.
     */
    private static function decodeSigningKey(string $secret): string|false
    {
        $raw = str_starts_with($secret, self::SECRET_PREFIX)
            ? substr($secret, strlen(self::SECRET_PREFIX))
            : $secret;
        $raw = strtr(trim($raw), '-_', '+/');
        $padding = (4 - (strlen($raw) % 4)) % 4;

        return base64_decode($raw . str_repeat('=', $padding), true);
    }
}
