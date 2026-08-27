<?php

declare(strict_types=1);

namespace Mailtea\Request;

/**
 * The payload for `POST /v1/posts/{id}/send`.
 *
 * Omit `$scheduledAt` to send now. The SDK sends no body at all in that case,
 * which is what the endpoint expects.
 */
final class SendPost implements Payload
{
    /** @param string|null $scheduledAt ISO 8601. */
    public function __construct(public readonly ?string $scheduledAt = null)
    {
    }

    public function toArray(): array
    {
        return Payloads::compact(['scheduled_at' => $this->scheduledAt]);
    }
}
