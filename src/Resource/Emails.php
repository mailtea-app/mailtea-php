<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;
use Mailtea\MailteaException;
use Mailtea\Request\BatchEmail;
use Mailtea\Request\Payload;
use Mailtea\Request\Payloads;
use Mailtea\Request\SendEmail;
use Mailtea\Request\UpdateEmail;

/**
 * Transactional email. Reach it at `$mailtea->emails`.
 *
 * Every method takes either a typed payload from `Mailtea\Request` or a plain
 * wire-format array — use the array whenever you already hold the JSON, or need
 * a field the SDK does not model yet.
 */
final class Emails
{
    /** Inbound (received) email: list, get, reply, and attachments. */
    public readonly InboundEmails $inbound;

    public function __construct(private readonly Requester $api)
    {
        $this->inbound = new InboundEmails($api);
    }

    /**
     * Send one transactional email.
     *
     * ```php
     * $sent = $mailtea->emails->send(new SendEmail(
     *     from: 'Acme <hello@acme.com>',
     *     to: 'reader@example.com',
     *     subject: 'Hello',
     *     html: '<p>Sent with Mailtea.</p>',
     * ));
     * echo $sent['id'];
     * ```
     *
     * @param SendEmail|array<string, mixed> $email
     *
     * @return array<string, mixed> `['id' => 'txemail_…']`
     */
    public function send(SendEmail|array $email): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/emails', Payloads::toArray($email));
    }

    /**
     * Send up to 100 emails in one request.
     *
     * @param list<BatchEmail|array<string, mixed>> $emails
     *
     * @return array<string, mixed> `['data' => [['id' => …], …]]`
     *
     * @throws MailteaException when the list is empty.
     */
    public function batch(array $emails): array
    {
        if ($emails === []) {
            // PHP cannot tell an empty list from an empty map, so an empty batch
            // would go out as `{}` and come back as a confusing schema error.
            // The API takes 1 to 100 either way, so say so without the round trip.
            throw new MailteaException(
                'emails->batch() needs at least one email; the API takes 1 to 100.',
                0,
                'empty_batch'
            );
        }

        $items = array_map(
            static fn (BatchEmail|array $email): array => $email instanceof Payload ? $email->toArray() : $email,
            $emails
        );

        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/emails/batch', array_values($items));
    }

    /**
     * Retrieve an email with its delivery status and tracking counters.
     *
     * Adds a friendly `status` alias of the raw `last_event` wire field, so the
     * same key names the state here and everywhere else in the SDK.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        /** @var array<string, mixed> $email */
        $email = $this->api->request('GET', '/v1/emails/' . Params::segment($id));
        if (!isset($email['status'])) {
            $email['status'] = $email['last_event'] ?? null;
        }

        return $email;
    }

    /**
     * List emails, most recent first.
     *
     * Filters: `status`, `tag_name`, `tag_value`, `search` (substring match on
     * recipient/sender/subject), `from_date`, `to_date`, `limit`, `offset`.
     *
     * `from_date` is clamped to the plan's analytics retention window — 30 days
     * on most plans, 90 on Scale and Enterprise. A value reaching further back
     * returns data from the start of that window rather than an error, and
     * omitting it returns the window rather than all time.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> `['object' => 'list', 'data' => …, 'total' => …, 'limit' => …, 'offset' => …, 'has_more' => …]`
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/emails' . Params::query($params));
    }

    /**
     * Aggregate transactional metrics over an optional date window: totals,
     * delivered/bounced/open/click counts, per-status counts, and rates.
     * Optional filters: `from_date`, `to_date` (ISO 8601).
     *
     * `from_date` is clamped to the plan's retention window the same way
     * {@see self::list()} is, and the response reports the window actually used.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function analytics(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/emails/analytics' . Params::query($params));
    }

    /**
     * Update a scheduled email. `scheduled_at` is the only field it takes.
     *
     * @param UpdateEmail|array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function update(string $id, UpdateEmail|array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'PATCH',
            '/v1/emails/' . Params::segment($id),
            Payloads::toArray($params)
        );
    }

    /**
     * Move a queued send to a new time — {@see self::update()} for the one case
     * anyone uses it for.
     *
     * @return array<string, mixed>
     */
    public function reschedule(string $id, string $scheduledAt): array
    {
        return $this->update($id, new UpdateEmail($scheduledAt));
    }

    /**
     * Cancel a scheduled email before it sends.
     *
     * Only a queued send can be cancelled; anything already handed to the
     * provider answers 422. Note the verb: this is a POST to `/cancel`, not a
     * DELETE — the API has no DELETE on emails.
     *
     * @return array<string, mixed> `['object' => 'email', 'id' => …]`
     */
    public function cancel(string $id): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/emails/' . Params::segment($id) . '/cancel');
    }
}
