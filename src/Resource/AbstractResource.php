<?php

declare(strict_types=1);

namespace Nomba\Resource;

use Nomba\Http\ApiClient;

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
