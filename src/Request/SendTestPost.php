<?php

declare(strict_types=1);

namespace Mailtea\Request;

/**
 * The payload for `POST /v1/posts/{id}/test`.
 *
 * This sends a one-shot `[TEST]` copy to the addresses you name — it does NOT
 * touch the audience. Up to 10 recipients, and `$from` must use a verified
 * domain.
 */
final class SendTestPost implements Payload
{
    /** @param list<string> $recipients Up to 10. */
    public function __construct(
        public readonly array $recipients,
        public readonly string $from,
        public readonly ?string $replyTo = null,
    ) {
    }

    public function toArray(): array
    {
        return Payloads::compact([
            'recipients' => $this->recipients,
            'from' => $this->from,
            'reply_to' => $this->replyTo,
        ]);
    }
}
