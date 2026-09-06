<?php

declare(strict_types=1);

namespace Mailtea;

use Mailtea\Internal\Requester;
use Mailtea\Resource\ApiKeys;
use Mailtea\Resource\Assets;
use Mailtea\Resource\AutomationRuns;
use Mailtea\Resource\Automations;
use Mailtea\Resource\ContactProperties;
use Mailtea\Resource\Contacts;
use Mailtea\Resource\Domains;
use Mailtea\Resource\Emails;
use Mailtea\Resource\EventDefinitions;
use Mailtea\Resource\Events;
use Mailtea\Resource\Posts;
use Mailtea\Resource\Segments;
use Mailtea\Resource\Senders;
use Mailtea\Resource\Suppressions;
use Mailtea\Resource\Templates;
use Mailtea\Resource\Topics;
use Mailtea\Resource\Webhooks;

/**
 * The Mailtea client.
 *
 * ```php
 * use Mailtea\Mailtea;
 * use Mailtea\Request\SendEmail;
 *
 * $mailtea = new Mailtea();                 // reads MAILTEA_API_KEY
 *
 * $sent = $mailtea->emails->send(new SendEmail(
 *     from: 'you@yourdomain.com',
 *     to: 'recipient@example.com',
 *     subject: 'Hello',
 *     html: '<p>Sent with Mailtea.</p>',
 * ));
 *
 * echo $sent['id'];
 * ```
 *
 * The API key is passed to the constructor or read from `MAILTEA_API_KEY`.
 * Self-hosting or local dev: pass `$baseUrl` or set `MAILTEA_API_BASE_URL`.
 */
final class Mailtea
{
    public const VERSION = '0.2.0';

    public const DEFAULT_BASE_URL = 'https://api.mailtea.app';

    public readonly Emails $emails;
    public readonly Contacts $contacts;
    public readonly Posts $posts;
    public readonly Segments $segments;
    public readonly Senders $senders;
    public readonly Assets $assets;
    public readonly Suppressions $suppressions;
    public readonly Topics $topics;
    public readonly Templates $templates;
    public readonly Domains $domains;
    public readonly Webhooks $webhooks;
    public readonly ContactProperties $contactProperties;
    public readonly ApiKeys $apiKeys;
    public readonly Automations $automations;
    public readonly AutomationRuns $automationRuns;
    public readonly Events $events;
    public readonly EventDefinitions $eventDefinitions;

    private readonly string $baseUrl;

    /**
     * @param string|null    $apiKey    Falls back to `MAILTEA_API_KEY`.
     * @param string|null    $baseUrl   Falls back to `MAILTEA_API_BASE_URL`, then
     *                                  to the hosted API. Only needed for local
     *                                  dev or a self-hosted Mailtea.
     * @param Transport|null $transport Your own HTTP client, or a fake in a test.
     *                                  Defaults to {@see CurlTransport}.
     *
     * @throws MailteaException when no API key is available. Failing here beats
     *                          sending an unauthenticated request and reporting
     *                          the 401 that comes back, which reads like a bad
     *                          key rather than a missing one.
     */
    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?Transport $transport = null,
    ) {
        $key = self::firstNonEmpty($apiKey, self::env('MAILTEA_API_KEY'));
        if ($key === null) {
            throw new MailteaException(
                'Missing Mailtea API key. Pass it to new Mailtea($apiKey) or set the '
                    . 'MAILTEA_API_KEY environment variable.',
                0,
                'missing_api_key'
            );
        }

        $this->baseUrl = rtrim(
            self::firstNonEmpty($baseUrl, self::env('MAILTEA_API_BASE_URL')) ?? self::DEFAULT_BASE_URL,
            '/'
        );

        $api = new Requester(
            $key,
            $this->baseUrl,
            $transport ?? new CurlTransport(),
            'mailtea-php/' . self::VERSION
        );

        $this->emails = new Emails($api);
        $this->contacts = new Contacts($api);
        $this->posts = new Posts($api);
        $this->segments = new Segments($api);
        $this->senders = new Senders($api);
        $this->assets = new Assets($api);
        $this->suppressions = new Suppressions($api);
        $this->topics = new Topics($api);
        $this->templates = new Templates($api);
        $this->domains = new Domains($api);
        $this->webhooks = new Webhooks($api);
        $this->contactProperties = new ContactProperties($api);
        $this->apiKeys = new ApiKeys($api);
        $this->automations = new Automations($api);
        $this->automationRuns = new AutomationRuns($api);
        $this->events = new Events($api);
        $this->eventDefinitions = new EventDefinitions($api);
    }

    /** The base URL this client is talking to, trailing slash stripped. */
    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * `getenv()` returns `false` for an unset variable and `''` for one set to
     * nothing; neither is a usable value, and under `strict_types` the `false`
     * will not even pass as a string.
     */
    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? null : $value;
    }

    private static function firstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
