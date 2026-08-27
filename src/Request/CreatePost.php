<?php

declare(strict_types=1);

namespace Mailtea\Request;

/**
 * The payload for `POST /v1/posts` — a newsletter post or broadcast.
 *
 * Seed the body from a published server template with `$templateId` +
 * `$variables`, or pass inline `$html`. Both together are a 400.
 *
 * A post is a draft unless `$send` is true, in which case it goes out on
 * creation (or at `$scheduledAt`) — and that path needs the `issues:send`
 * scope, not just `issues:write`.
 */
final class CreatePost implements Payload
{
    /**
     * @param array<string, string|int|float>|null $variables Values for the template's placeholders.
     * @param 'newsletter'|'broadcast'|null $kind
     * @param string|null $scheduledAt ISO 8601; only read when `$send` is true.
     */
    public function __construct(
        public readonly string $publicationId,
        public readonly string $subject,
        public readonly ?string $html = null,
        public readonly ?string $text = null,
        public readonly ?string $templateId = null,
        public readonly ?array $variables = null,
        public readonly ?string $from = null,
        public readonly ?string $replyTo = null,
        public readonly ?string $name = null,
        public readonly ?string $kind = null,
        public readonly ?bool $send = null,
        public readonly ?string $scheduledAt = null,
    ) {
    }

    public function toArray(): array
    {
        return Payloads::compact([
            'publication_id' => $this->publicationId,
            'subject' => $this->subject,
            'html' => $this->html,
            'text' => $this->text,
            'template_id' => $this->templateId,
            'variables' => $this->variables,
            'from' => $this->from,
            'reply_to' => $this->replyTo,
            'name' => $this->name,
            'kind' => $this->kind,
            'send' => $this->send,
            'scheduled_at' => $this->scheduledAt,
        ]);
    }
}
