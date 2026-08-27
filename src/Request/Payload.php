<?php

declare(strict_types=1);

namespace Mailtea\Request;

/**
 * A typed request payload.
 *
 * The SDK models the payloads you reach for every day — sending an email,
 * creating a contact, publishing a post — so your editor can complete them and
 * a typo is a static error rather than a 400 from the server. Everything else
 * takes a plain wire-format array, and every method that takes one of these
 * takes an array too, so a field the SDK does not model yet never blocks you.
 */
interface Payload
{
    /**
     * The wire-format body: snake_case keys, unset fields omitted entirely
     * (an omitted field and a null one mean different things to the API).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
