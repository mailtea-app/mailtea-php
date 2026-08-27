<?php

declare(strict_types=1);

namespace Mailtea\Request;

/**
 * The payload for `PATCH /v1/emails/{id}` — the only field a queued email
 * accepts. Once a send leaves the queue there is nothing left to change; the
 * API answers 422 rather than pretending.
 */
final class UpdateEmail implements Payload
{
    /** @param string $scheduledAt ISO 8601. */
    public function __construct(public readonly string $scheduledAt)
    {
    }

    public function toArray(): array
    {
        return ['scheduled_at' => $this->scheduledAt];
    }
}
