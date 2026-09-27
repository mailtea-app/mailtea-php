<?php

declare(strict_types=1);

namespace Mailtea\Request;

/**
 * The payload for `POST /v1/posts` — a newsletter post or broadcast.
 *
 * Seed the body from a published server template with `$templateId` +
 * `$variables`, using the template's PUBLISHED version and not any unpublished
 * edits saved since, or pass inline `$html`. Both together are a 400. The
 * `$variables` you pass are filled in, in both the `{{key}}` and Visual Email
 * Designer `{key}` forms, and HTML-escaped (use `{{{key}}}` in the template
 * for raw HTML). Everything else is left for the broadcast to fill per
 * recipient: a declared variable you do not pass keeps its `fallback_value`
 * for recipients with no value, and undeclared tokens like
 * `{{contact.first_name}}` stay as they are. The post keeps the template's
 * published page style.
 *
 * A post is a draft unless `$send` is true, in which case it goes out on
 * creation (or at `$scheduledAt`) — and that path needs the `issues:send`
 * scope, not just `issues:write`.
 */
final class CreatePost implements Payload
{
    /**
     * @param array<string, string|int|float>|null $variables Values substituted into the template's placeholders (both `{{key}}` and Visual Email Designer `{key}` forms).
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
