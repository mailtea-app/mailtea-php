<?php

declare(strict_types=1);

use Mailtea\WebhookSigning;

/**
 * Standard Webhooks signing and verification.
 *
 * The fixtures below are self-describing on purpose — the secret decodes to
 * readable English so nobody has to wonder whether a real key leaked into a test.
 */

const SECRET = 'whsec_' . 'dGVzdHNpZ25pbmdrZXlub3RhcmVhbHNlY3JldA==';  // "testsigningkeynotarealsecret"
const MSG_ID = 'msg_2abc';
const PAYLOAD = '{"type":"email.delivered","data":{"id":"txemail_1"}}';

test('a signature round-trips', function (): void {
    $now = 1_760_000_000;
    $header = WebhookSigning::sign(SECRET, MSG_ID, $now, PAYLOAD);

    assertTrue(str_starts_with($header, 'v1,'), 'the header is v1,<sig>');
    assertTrue(
        WebhookSigning::verify(SECRET, MSG_ID, $now, PAYLOAD, $header, now: $now),
        'a fresh signature verifies'
    );
});

test('a tampered body is rejected', function (): void {
    $now = 1_760_000_000;
    $header = WebhookSigning::sign(SECRET, MSG_ID, $now, PAYLOAD);

    assertTrue(
        !WebhookSigning::verify(SECRET, MSG_ID, $now, PAYLOAD . ' ', $header, now: $now),
        'one extra byte of payload breaks it'
    );
    assertTrue(
        !WebhookSigning::verify(SECRET, 'msg_other', $now, PAYLOAD, $header, now: $now),
        'a different message id breaks it'
    );
    assertTrue(
        !WebhookSigning::verify('whsec_b3RoZXJzZWNyZXQ', MSG_ID, $now, PAYLOAD, $header, now: $now),
        'a different secret breaks it'
    );
});

test('an expired timestamp is rejected even when the signature is right', function (): void {
    $signedAt = 1_760_000_000;
    $header = WebhookSigning::sign(SECRET, MSG_ID, $signedAt, PAYLOAD);

    // Replay protection: the HMAC still matches, and that is the point — an
    // attacker replaying a captured delivery has a valid signature.
    assertTrue(
        !WebhookSigning::verify(SECRET, MSG_ID, $signedAt, PAYLOAD, $header, now: $signedAt + 301),
        'past the 5 minute tolerance'
    );
    assertTrue(
        WebhookSigning::verify(SECRET, MSG_ID, $signedAt, PAYLOAD, $header, now: $signedAt + 299),
        'inside it'
    );
    // Skew runs both ways: a sender's clock ahead of ours is just as normal.
    assertTrue(
        !WebhookSigning::verify(SECRET, MSG_ID, $signedAt, PAYLOAD, $header, now: $signedAt - 301),
        'a timestamp too far in the future'
    );
    assertTrue(
        WebhookSigning::verify(SECRET, MSG_ID, $signedAt, PAYLOAD, $header, toleranceSeconds: 600, now: $signedAt + 400),
        'a wider tolerance can be asked for'
    );
});

test('the timestamp is accepted as the string a header actually gives you', function (): void {
    $now = 1_760_000_000;
    $header = WebhookSigning::sign(SECRET, MSG_ID, $now, PAYLOAD);

    assertTrue(
        WebhookSigning::verify(SECRET, MSG_ID, (string) $now, PAYLOAD, $header, now: $now),
        'a numeric string verifies'
    );
    assertTrue(
        !WebhookSigning::verify(SECRET, MSG_ID, 'not-a-number', PAYLOAD, $header, now: $now),
        'a non-numeric timestamp is a rejection, not a crash'
    );
});

test('a header carrying several tokens passes if any v1 token matches', function (): void {
    $now = 1_760_000_000;
    $good = WebhookSigning::sign(SECRET, MSG_ID, $now, PAYLOAD);

    // Standard Webhooks allows key rotation: a delivery may be signed with both
    // the old and the new secret, space-delimited.
    $rotating = 'v1,AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= ' . $good;
    assertTrue(WebhookSigning::verify(SECRET, MSG_ID, $now, PAYLOAD, $rotating, now: $now), 'rotation');

    // A v2 token is not a v1 token, whatever it contains.
    $wrongVersion = str_replace('v1,', 'v2,', $good);
    assertTrue(!WebhookSigning::verify(SECRET, MSG_ID, $now, PAYLOAD, $wrongVersion, now: $now), 'wrong version');
    assertTrue(!WebhookSigning::verify(SECRET, MSG_ID, $now, PAYLOAD, '', now: $now), 'an empty header');
    assertTrue(!WebhookSigning::verify(SECRET, MSG_ID, $now, PAYLOAD, 'garbage', now: $now), 'a header with no comma');
});

test('the secret decodes from either base64 alphabet, padded or not', function (): void {
    $now = 1_760_000_000;
    // The same 32 bytes written three ways: standard base64 with padding,
    // without padding, and base64url. All three must produce the same key, or a
    // secret minted by a different service refuses to verify.
    $bytes = random_bytes(32);
    $standard = 'whsec_' . base64_encode($bytes);
    $unpadded = 'whsec_' . rtrim(base64_encode($bytes), '=');
    $urlSafe = 'whsec_' . rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

    $header = WebhookSigning::sign($standard, MSG_ID, $now, PAYLOAD);
    assertTrue(WebhookSigning::verify($unpadded, MSG_ID, $now, PAYLOAD, $header, now: $now), 'unpadded');
    assertTrue(WebhookSigning::verify($urlSafe, MSG_ID, $now, PAYLOAD, $header, now: $now), 'base64url');
});

test('the comparison is constant-time', function (): void {
    // Not a timing measurement — those are flaky in CI. This asserts the code
    // uses PHP's constant-time comparison rather than `===`, which returns on the
    // first differing byte and leaks the expected signature one byte at a time.
    $source = (string) file_get_contents(__DIR__ . '/../src/WebhookSigning.php');
    assertContainsString('hash_equals(', $source, 'hash_equals is used');
    assertTrue(
        !preg_match('/\$signature\s*===\s*\$expected|\$expected\s*===\s*\$signature/', $source),
        'no plain === on the signature'
    );
});

test('an unreadable secret is a rejection in verify and a throw in sign', function (): void {
    $now = 1_760_000_000;
    assertTrue(
        !WebhookSigning::verify('whsec_!!!not-base64!!!', MSG_ID, $now, PAYLOAD, 'v1,x', now: $now),
        'verify answers false rather than throwing at a handler'
    );

    $error = assertThrows(static fn () => WebhookSigning::sign('whsec_!!!not-base64!!!', MSG_ID, $now, PAYLOAD));
    assertSame('invalid_signing_secret', $error->errorCode, 'error code');
});

test('the signature matches the platform, byte for byte', function (): void {
    // A golden vector produced by Mailtea's own signer (the same one that signs
    // real deliveries, and the Python SDK's port of it) for exactly these
    // inputs. Parity with the platform is the whole point of this file: a port
    // that is self-consistent but disagrees with the sender verifies nothing.
    assertSame(
        'v1,tdI5D4DaiVRxNgPx51PAbAx2eN1IBleBR7jZA8uCc+w=',
        WebhookSigning::sign(SECRET, MSG_ID, 1_760_000_000, PAYLOAD),
        'signature'
    );
});
