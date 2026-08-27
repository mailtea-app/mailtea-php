<?php

declare(strict_types=1);

namespace Mailtea\Request;

/**
 * The payload for `PATCH /v1/contacts/{id_or_email}`.
 *
 * `publicationId` is required and travels in BOTH the query string and the body
 * — the SDK puts it in both for you, because the route reads it from the query
 * and the schema requires it in the body.
 */
final class UpdateContact implements Payload
{
    /** @param 'active'|'unsubscribed'|'suppressed'|null $status */
    public function __construct(
        public readonly string $publicationId,
        public readonly ?string $status = null,
    ) {
    }

    public function toArray(): array
    {
        return Payloads::compact([
            'publication_id' => $this->publicationId,
            'status' => $this->status,
        ]);
    }
}
