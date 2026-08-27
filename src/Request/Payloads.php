<?php

declare(strict_types=1);

namespace Mailtea\Request;

/**
 * Shared payload plumbing.
 *
 * @internal
 */
final class Payloads
{
    /**
     * Drop the fields the caller never set.
     *
     * A null here always means "not set": the typed payloads model creation and
     * sending, where no field is nullable on the wire. Clearing a nullable field
     * on an update is done with a plain array (`['reply_to' => null]`), which
     * the SDK passes through untouched.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public static function compact(array $body): array
    {
        return array_filter($body, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Normalise whatever a method was handed into a wire-format array.
     *
     * @param Payload|array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public static function toArray(Payload|array $payload): array
    {
        return $payload instanceof Payload ? $payload->toArray() : $payload;
    }
}
