<?php

declare(strict_types=1);

namespace Mailtea;

/**
 * One HTTP response, as a {@see Transport} hands it back.
 *
 * A transport reports a non-2xx by RETURNING it, never by throwing: the client
 * needs the body to read the API's `error`, `code` and `details` out of it.
 */
final class HttpResponse
{
    /**
     * @param array<string, string> $headers Header names lowercased, so the
     *                                       client can read `x-request-id`
     *                                       without guessing the server's case.
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }
}
