<?php

declare(strict_types=1);

namespace Mailtea\Internal;

use JsonException;
use Mailtea\MailteaException;
use Mailtea\Transport;

/**
 * Turns a method + path + payload into an API call, and an API response into
 * either a decoded array or a {@see MailteaException}.
 *
 * Every resource holds one of these. It is the only place in the SDK that knows
 * about the API key, the base URL, or JSON.
 *
 * @internal Not part of the public API; construct a `Mailtea\Mailtea` instead.
 */
final class Requester
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly Transport $transport,
        private readonly string $userAgent,
    ) {
    }

    /**
     * @param array<mixed>|null $body Encoded as JSON when present. A `null` body
     *                                sends no body and no Content-Type, which is
     *                                what the POST-with-no-payload endpoints
     *                                (cancel, publish, activate) expect.
     *
     * @return array<mixed>|string An empty response body (a 204, or a 200 with
     *                             nothing in it) comes back as `[]`, so callers
     *                             never have to null-check a delete. With
     *                             `$raw` the body is returned untouched — the
     *                             CSV endpoints are not JSON.
     *
     * @throws MailteaException on any non-2xx, and on a client-side fault.
     */
    public function request(string $method, string $path, ?array $body = null, bool $raw = false): array|string
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => $raw ? '*/*' : 'application/json',
            'User-Agent' => $this->userAgent,
        ];

        $encoded = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            try {
                $encoded = json_encode(
                    // An empty PHP array encodes as `[]`, which is right for a
                    // batch and wrong for every object payload. Only `emails.batch`
                    // sends a list, and it is never empty, so an empty payload
                    // here always means "an object with no fields".
                    $body === [] ? new \stdClass() : $body,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
            } catch (JsonException $e) {
                // A subject or body that is not valid UTF-8 — a string read from
                // a latin-1 source is the usual one. Caught here so the caller
                // still needs one catch: everything the SDK fails with is a
                // MailteaException.
                throw new MailteaException(
                    'Could not encode the request body as JSON: ' . $e->getMessage(),
                    0,
                    'invalid_request_body'
                );
            }
        }

        $response = $this->transport->send($method, $this->baseUrl . $path, $headers, $encoded);
        $requestId = $response->headers['x-request-id'] ?? null;

        // Anything outside 2xx is a failure, not just 4xx and 5xx: a redirect
        // from a misconfigured proxy carries a body that is not what was asked
        // for, and returning it as success hides that.
        if ($response->status < 200 || $response->status >= 300) {
            throw self::error($response->status, $response->body, $requestId);
        }

        if ($raw) {
            return $response->body;
        }
        if (trim($response->body) === '') {
            return [];
        }

        $decoded = json_decode($response->body, true);
        if (!is_array($decoded)) {
            throw new MailteaException(
                'Mailtea returned a body that is not JSON: ' . substr($response->body, 0, 200),
                $response->status,
                'invalid_response_body',
                null,
                $requestId
            );
        }

        return $decoded;
    }

    private static function error(int $status, string $body, ?string $requestId): MailteaException
    {
        $message = 'Mailtea returned HTTP ' . $status;
        $code = null;
        $details = null;

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (is_string($decoded['error'] ?? null) && $decoded['error'] !== '') {
                $message = $decoded['error'];
            }
            // A machine-readable code, when the API sends one. Branching on it
            // survives a copy change to the message; branching on the message
            // does not.
            if (is_string($decoded['code'] ?? null)) {
                $code = $decoded['code'];
            }
            $details = $decoded['details'] ?? null;
        }

        return new MailteaException($message, $status, $code, $details, $requestId);
    }
}
