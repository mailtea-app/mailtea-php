<?php

declare(strict_types=1);

namespace Mailtea;

/**
 * How the SDK reaches the network. {@see CurlTransport} is the default.
 *
 * Implement this to run the SDK's calls through your own HTTP client — a PSR-18
 * client, a Guzzle instance with your retry middleware, or a fake in a test —
 * and pass it to `new Mailtea(transport: $yours)`. Nothing else in the SDK
 * touches the network, so a fake transport makes a whole suite hermetic.
 */
interface Transport
{
    /**
     * @param array<string, string> $headers Header name => value.
     * @param string|null           $body    The already-encoded request body,
     *                                       or null for a request without one.
     *
     * @throws MailteaException on a transport failure (DNS, TLS, timeout).
     *                          A non-2xx is NOT a failure here — return it.
     */
    public function send(string $method, string $url, array $headers, ?string $body): HttpResponse;
}
