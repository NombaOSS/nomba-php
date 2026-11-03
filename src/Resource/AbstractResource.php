<?php

declare(strict_types=1);

namespace Nomba\Resource;

use Nomba\Exception\NombaException;
use Nomba\Exception\Reference;
use Nomba\Http\ApiClient;
use Nomba\Internal\Uuid;

/**
 * @internal
 */
abstract class AbstractResource
{
    public function __construct(protected readonly ApiClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [];
    }

    /**
     * A request whose data is an object or list. A null/absent `data` becomes [].
     *
     * @param array<string, mixed> $query
     * @param array<mixed>|null    $body
     *
     * @return array<mixed>
     *
     * @throws \Nomba\Exception\ApiException
     * @throws \Nomba\Exception\ConnectionException
     */
    protected function requestArray(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $data = $this->client->request($method, $path, $query, $body);

        return \is_array($data) ? $data : [];
    }

    /**
     * POST a money-moving request with an idempotency reference.
     *
     * Uses $params[$key] (or $params[$within][$key]) when set, otherwise generates a
     * UUID v4 there. The reference is added to the returned data under $key when the
     * API does not echo it, and attached to any {@see NombaException} the call throws.
     * When $within is given but $params[$within] is not an array, no reference is used.
     *
     * @param array<string, mixed> $params
     *
     * @return array<mixed>
     *
     * @throws \Nomba\Exception\ApiException
     * @throws \Nomba\Exception\ConnectionException
     */
    protected function postWithReference(string $path, array $params, string $key = 'merchantTxRef', ?string $within = null): array
    {
        $holder = $within === null ? $params : ($params[$within] ?? null);
        if (!\is_array($holder)) {
            return $this->requestArray('POST', $path, [], $params);
        }

        $value = $holder[$key] ?? null;
        if (!\is_string($value) || $value === '') {
            $value = Uuid::v4();
            $holder[$key] = $value;
        }
        if ($within === null) {
            $params = $holder;
        } else {
            $params[$within] = $holder;
        }

        try {
            $data = $this->client->request('POST', $path, [], $params);
        } catch (NombaException $e) {
            throw $e->withReference(new Reference($within === null ? $key : $within . '.' . $key, $value));
        }

        $data = \is_array($data) ? $data : [];
        if (!isset($data[$key]) || $data[$key] === '') {
            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Iterate every item across cursor pages.
     *
     * @param callable(?string): mixed $fetchPage returns the page `data` for a cursor
     *
     * @return \Generator<int, mixed, mixed, void>
     */
    protected static function paginate(callable $fetchPage, ?string $cursor = null): \Generator
    {
        $seen = [];
        do {
            $page = $fetchPage($cursor);
            $results = \is_array($page) && \is_array($page['results'] ?? null) ? $page['results'] : [];
            foreach ($results as $item) {
                yield $item;
            }
            $next = \is_array($page) && \is_string($page['cursor'] ?? null) ? $page['cursor'] : '';
            if ($next === '' || $results === [] || isset($seen[$next])) {
                return;
            }
            $seen[$next] = true;
            $cursor = $next;
        } while (true);
    }
}
