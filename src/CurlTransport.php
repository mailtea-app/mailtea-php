<?php

declare(strict_types=1);

namespace Mailtea;

/**
 * The default transport: ext-curl and nothing else.
 *
 * PHP ships no HTTP client in its standard library, and requiring Guzzle for
 * five requests a day is a dependency the caller pays for in every lockfile
 * they own. ext-curl is compiled into essentially every PHP install.
 */
final class CurlTransport implements Transport
{
    /**
     * @param int $timeout       Seconds to wait for the whole request.
     * @param int $connectTimeout Seconds to wait for the connection alone. Kept
     *                            separate so a dead host fails fast even when
     *                            the overall budget is generous.
     */
    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
    ) {
    }

    public function send(string $method, string $url, array $headers, ?string $body): HttpResponse
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new MailteaException('Could not initialise a cURL handle.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        // curl adds `Expect: 100-continue` to bodies over 1KB and then waits a
        // full second when the server never answers it. A single attachment
        // crosses that line, so turn it off.
        $headerLines[] = 'Expect:';

        $responseHeaders = [];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            // Read response headers through a callback rather than CURLOPT_HEADER,
            // which would prepend them to the body and leave the caller to split
            // the two — badly, on any response carrying a redirect's headers too.
            CURLOPT_HEADERFUNCTION => static function ($_curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $separator = strpos($line, ':');
                if ($separator !== false) {
                    $name = strtolower(trim(substr($line, 0, $separator)));
                    $responseHeaders[$name] = trim(substr($line, $separator + 1));
                }

                return $length;
            },
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($curl, $options);
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($raw === false) {
            // No response at all: DNS, TLS, connection refused, timeout. Status
            // 0 is how the caller tells this apart from an API rejection.
            throw new MailteaException('Request to Mailtea failed: ' . $error);
        }

        return new HttpResponse($status, $responseHeaders, (string) $raw);
    }
}
