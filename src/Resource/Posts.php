<?php

declare(strict_types=1);

namespace Mailtea\Resource;

use Mailtea\Internal\Params;
use Mailtea\Internal\Requester;
use Mailtea\Request\CreatePost;
use Mailtea\Request\Payloads;
use Mailtea\Request\SendPost;
use Mailtea\Request\SendTestPost;

/**
 * Newsletter posts and broadcasts. Reach it at `$mailtea->posts`.
 */
final class Posts
{
    public function __construct(private readonly Requester $api)
    {
    }

    /**
     * Create a post — a draft by default.
     *
     * @param CreatePost|array<string, mixed> $params
     *
     * @return array<string, mixed> `['id' => 'post_…']`
     */
    public function create(CreatePost|array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('POST', '/v1/posts', Payloads::toArray($params));
    }

    /**
     * List posts, most recent first, offset-paginated. Takes `publication_id`
     * (required) plus optional `limit`, `offset`, `status`, and `kind`.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> `['data' => …, 'total' => …]`
     */
    public function list(array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('GET', '/v1/posts' . Params::query($params));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function get(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'GET',
            '/v1/posts/' . Params::segment($id) . Params::query($params)
        );
    }

    /**
     * Update a draft post — `subject`, `html`, `text`, `from`, `reply_to`,
     * `name`. A sent post is immutable.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request('PATCH', '/v1/posts/' . Params::segment($id), $params);
    }

    /**
     * Delete a draft post. A sent post cannot be deleted.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function delete(string $id, array $params = []): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'DELETE',
            '/v1/posts/' . Params::segment($id) . Params::query($params)
        );
    }

    /**
     * Send a draft post to the publication's audience — now, or at
     * `scheduled_at`. Requires the `issues:send` scope.
     *
     * @param SendPost|array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function send(string $id, SendPost|array $params = []): array
    {
        $body = Payloads::toArray($params);

        /** @var array<string, mixed> */
        return $this->api->request(
            'POST',
            '/v1/posts/' . Params::segment($id) . '/send',
            // No body at all when nothing was asked for, which is what the
            // endpoint expects for "send it now".
            $body === [] ? null : $body
        );
    }

    /**
     * Send a `[TEST]` copy of a post to specific recipients.
     *
     * It renders the post exactly as a subscriber would receive it and delivers
     * a one-shot message — it does NOT send to the audience.
     *
     * @param SendTestPost|array<string, mixed> $params
     *
     * @return array<string, mixed> `['sent_to' => […], 'failed_to' => […]]`
     */
    public function sendTest(string $id, SendTestPost|array $params): array
    {
        /** @var array<string, mixed> */
        return $this->api->request(
            'POST',
            '/v1/posts/' . Params::segment($id) . '/test',
            Payloads::toArray($params)
        );
    }
}
