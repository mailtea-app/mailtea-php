<?php

declare(strict_types=1);

namespace Mailtea;

use RuntimeException;

/**
 * Every failure from the SDK arrives as one of these — a non-2xx API response
 * and a client-side fault alike — so callers need a single catch.
 *
 * Branch on {@see self::$status} and {@see self::$errorCode}, never on the
 * message: the message is human copy and changes without notice.
 */
final class MailteaException extends RuntimeException
{
    /**
     * @param int         $status    HTTP status, or 0 when the request never got
     *                               a response — a missing API key, a DNS or TLS
     *                               failure, a timeout, an unencodable body.
     * @param string|null $errorCode Machine-readable code when one is available:
     *                               the API's `code` (e.g. `marketing_plan_required`
     *                               on a 402) or a client-side code such as
     *                               `missing_api_key`. Named around
     *                               `Exception::$code`, which is an int and
     *                               cannot be redeclared as a string.
     * @param mixed       $details   The API's `details` — on a 400 this is the
     *                               list of validation issues.
     * @param string|null $requestId The API's `x-request-id` header. Quote it in
     *                               a support request and the exact call can be
     *                               found in the logs.
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $errorCode = null,
        public readonly mixed $details = null,
        public readonly ?string $requestId = null,
    ) {
        parent::__construct($message);
    }
}
