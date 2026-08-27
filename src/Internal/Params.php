<?php

declare(strict_types=1);

namespace Mailtea\Internal;

/**
 * Query-string and payload helpers shared by every resource.
 *
 * @internal Not part of the public API; the signatures here may change in a
 *           patch release.
 */
final class Params
{
    /**
     * Render a query string, dropping nulls.
     *
     * Two coercions the wire needs and PHP will not do for you:
     * booleans become `true`/`false` (the API matches those literal strings and
     * 400s on `1`), and a list becomes a comma-joined value (that is how the
     * API reads a repeated filter like `status`, not as `status[]=`).
     *
     * @param array<string, mixed> $params
     */
    public static function query(array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $value = implode(',', array_map(static fn (mixed $item): string => self::scalar($item), $value));
            } else {
                $value = self::scalar($value);
            }
            $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode($value);
        }

        return $pairs === [] ? '' : '?' . implode('&', $pairs);
    }

    /**
     * Take one key out of a payload and hand back both halves.
     *
     * Several endpoints read `publication_id` from the query string and reject
     * it in the body (or ignore it there, which is worse — the call succeeds
     * against the wrong publication). Those methods split the payload with this.
     *
     * @param array<string, mixed> $params
     *
     * @return array{0: mixed, 1: array<string, mixed>} The value, and the rest.
     */
    public static function take(array $params, string $key): array
    {
        $value = $params[$key] ?? null;
        unset($params[$key]);

        return [$value, $params];
    }

    /** A path segment that cannot break out of its position in the URL. */
    public static function segment(string $value): string
    {
        return rawurlencode($value);
    }

    private static function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
