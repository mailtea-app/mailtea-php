<?php

declare(strict_types=1);

/**
 * Endpoint parity with the reference SDK.
 *
 * Every official Mailtea SDK has to reach the same API. A resource quietly
 * missing from one of them is invisible until someone needs it, so this asserts
 * the coverage instead of trusting it.
 */

/**
 * The `/v1/...` path prefixes the Python SDK (the reference implementation)
 * reaches, extracted from the Mailtea monorepo with:
 *
 *     grep -ohE '"/v1/[^"]*"' sdks/python/mailtea/*.py | sort -u
 *
 * Hardcoded rather than computed so this repo stays standalone — the mirror has
 * no Python SDK next to it to read.
 *
 * @var list<string> $referencePaths
 */
$referencePaths = [
    '/v1/api-keys',
    '/v1/api-keys/',
    '/v1/assets',
    '/v1/assets/',
    '/v1/automations',
    '/v1/automations/',
    '/v1/automations/validate',
    '/v1/contact-properties',
    '/v1/contact-properties/',
    '/v1/contacts',
    '/v1/contacts/',
    '/v1/domains',
    '/v1/domains/',
    '/v1/domains/claim',
    '/v1/domains/claims/',
    '/v1/emails',
    '/v1/emails/',
    '/v1/emails/analytics',
    '/v1/emails/batch',
    '/v1/emails/inbound',
    '/v1/event-definitions',
    '/v1/event-definitions/',
    '/v1/events',
    '/v1/posts',
    '/v1/posts/',
    '/v1/segments',
    '/v1/segments/',
    '/v1/senders',
    '/v1/senders/',
    '/v1/suppressions',
    '/v1/suppressions/export',
    '/v1/templates',
    '/v1/templates/',
    '/v1/templates/render',
    '/v1/topics',
    '/v1/topics/',
    '/v1/webhooks/endpoints',
];

/**
 * Every `/v1/...` literal in the SDK's own source, which is how it builds every
 * path it can reach.
 *
 * @return list<string>
 */
function sdkPaths(): array
{
    $found = [];
    $directory = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__ . '/../src', FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($directory as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        if (preg_match_all("#'(/v1/[^']*)'#", $source, $matches) > 0) {
            foreach ($matches[1] as $path) {
                $found[$path] = true;
            }
        }
    }

    $paths = array_keys($found);
    sort($paths);

    return $paths;
}

test('the SDK reaches every endpoint the reference SDK reaches', function () use ($referencePaths): void {
    $missing = array_values(array_diff($referencePaths, sdkPaths()));

    assertSame([], $missing, 'endpoints the reference SDK has and this one does not');
});

test('the reference set is the 37 prefixes it is supposed to be', function () use ($referencePaths): void {
    // A guard on the guard: if someone trims the list above to make the test
    // pass, the count says so.
    assertSame(37, count($referencePaths), 'reference endpoint count');
    assertSame($referencePaths, array_values(array_unique($referencePaths)), 'no duplicates');
});

test('every resource on the client is reachable and typed', function () use ($mailtea): void {
    // The resource properties are the SDK's actual surface. Constructing the
    // client wires all of them, so a missing one is a fatal error here rather
    // than an "undefined property" in someone's application.
    $expected = [
        'emails' => Mailtea\Resource\Emails::class,
        'contacts' => Mailtea\Resource\Contacts::class,
        'posts' => Mailtea\Resource\Posts::class,
        'segments' => Mailtea\Resource\Segments::class,
        'senders' => Mailtea\Resource\Senders::class,
        'assets' => Mailtea\Resource\Assets::class,
        'suppressions' => Mailtea\Resource\Suppressions::class,
        'topics' => Mailtea\Resource\Topics::class,
        'templates' => Mailtea\Resource\Templates::class,
        'domains' => Mailtea\Resource\Domains::class,
        'webhooks' => Mailtea\Resource\Webhooks::class,
        'contactProperties' => Mailtea\Resource\ContactProperties::class,
        'apiKeys' => Mailtea\Resource\ApiKeys::class,
        'automations' => Mailtea\Resource\Automations::class,
        'automationRuns' => Mailtea\Resource\AutomationRuns::class,
        'events' => Mailtea\Resource\Events::class,
        'eventDefinitions' => Mailtea\Resource\EventDefinitions::class,
    ];

    foreach ($expected as $property => $class) {
        assertTrue($mailtea->{$property} instanceof $class, "\$mailtea->{$property} is a {$class}");
    }

    assertTrue($mailtea->emails->inbound instanceof Mailtea\Resource\InboundEmails, 'emails->inbound');
    assertTrue(
        $mailtea->emails->inbound->attachments instanceof Mailtea\Resource\InboundAttachments,
        'emails->inbound->attachments'
    );
    assertTrue($mailtea->domains->tracking instanceof Mailtea\Resource\TrackingDomains, 'domains->tracking');
    assertTrue($mailtea->domains->claims instanceof Mailtea\Resource\DomainClaims, 'domains->claims');
});
