<?php

declare(strict_types=1);

namespace Mailtea\Request;

/**
 * The payload for `POST /v1/contacts`.
 *
 * The endpoint upserts: an email already in the publication is updated in place
 * rather than duplicated, which is why `Contacts::upsert()` is the same call
 * under a name that says so.
 */
final class CreateContact implements Payload
{
    /** @param 'active'|'unsubscribed'|'suppressed'|null $status Defaults to active. */
    public function __construct(
        public readonly string $publicationId,
        public readonly string $email,
        public readonly ?string $status = null,
    ) {
    }

    public function toArray(): array
    {
        return Payloads::compact([
            'publication_id' => $this->publicationId,
            'email' => $this->email,
            'status' => $this->status,
        ]);
    }
}
