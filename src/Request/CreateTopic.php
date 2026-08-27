<?php

declare(strict_types=1);

namespace Mailtea\Request;

/**
 * The payload for `POST /v1/topics` — a topic definition.
 */
final class CreateTopic implements Payload
{
    /**
     * @param 'opt_in'|'opt_out'  $defaultSubscription Whether a new contact is
     *        subscribed to this topic unless they say otherwise.
     * @param 'public'|'private'|null $visibility `public` puts the topic on the
     *        reader preference page as its own subscription. Private by default.
     */
    public function __construct(
        public readonly string $publicationId,
        public readonly string $name,
        public readonly string $defaultSubscription,
        public readonly ?string $description = null,
        public readonly ?string $visibility = null,
    ) {
    }

    public function toArray(): array
    {
        return Payloads::compact([
            'publication_id' => $this->publicationId,
            'name' => $this->name,
            'default_subscription' => $this->defaultSubscription,
            'description' => $this->description,
            'visibility' => $this->visibility,
        ]);
    }
}
