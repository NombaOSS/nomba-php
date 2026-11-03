<?php

declare(strict_types=1);

namespace Nomba\Internal;

use Nomba\Exception\ValidationException;

/**
 * Argument checks shared by the resources. Failures throw before any request is sent.
 *
 * @internal
 */
final class Params
{
    public const MAX_PAGE_LIMIT = 50;

    /**
     * Validate and URL-encode one path segment. Rejects values that could
     * escape the intended endpoint ("", ".", "..", or anything with a slash).
     *
     * @throws ValidationException
     */
    public static function segment(mixed $value, string $name): string
    {
        if (!\is_string($value) && !\is_int($value)) {
            throw new ValidationException(\sprintf('%s must be a non-empty string.', $name));
        }
        $value = (string) $value;
        if (
            trim($value) === ''
            || $value === '.'
            || $value === '..'
            || str_contains($value, '/')
            || str_contains($value, '\\')
        ) {
            throw new ValidationException(\sprintf(
                '%s must be a non-empty identifier without "/" or "\\" and not "." or "..".',
                $name,
            ));
        }

        return rawurlencode($value);
    }

    /**
     * Read the optional sub-account from an options array and return the path
     * suffix ("" or "/{encoded id}").
     *
     * @param array<string, mixed> $options
     *
     * @throws ValidationException
     */
    public static function subAccountSuffix(array $options, string $key = 'subAccountId'): string
    {
        if (!isset($options[$key])) {
            return '';
        }

        return '/' . self::segment($options[$key], $key);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws ValidationException
     */
    public static function requireString(array $params, string $key): string
    {
        $value = $params[$key] ?? null;
        if (!\is_string($value) || trim($value) === '') {
            throw new ValidationException(\sprintf('%s is required and must be a non-empty string.', $key));
        }

        return $value;
    }

    /**
     * Returns [key => value] for the single key that is set.
     *
     * @param array<string, mixed> $params
     * @param list<string>         $keys
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function exactlyOne(array $params, array $keys): array
    {
        $present = array_values(array_filter($keys, static fn(string $k): bool => isset($params[$k]) && $params[$k] !== ''));
        if (\count($present) !== 1) {
            throw new ValidationException(\sprintf('Pass exactly one of: %s.', implode(', ', $keys)));
        }

        return [$present[0] => $params[$present[0]]];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws ValidationException
     */
    public static function checkLimit(array $params): void
    {
        if (!\array_key_exists('limit', $params) || $params['limit'] === null) {
            return;
        }
        $limit = $params['limit'];
        if (!\is_int($limit) || $limit < 1 || $limit > self::MAX_PAGE_LIMIT) {
            throw new ValidationException(\sprintf('limit must be an integer from 1 to %d.', self::MAX_PAGE_LIMIT));
        }
    }

    /**
     * Copy only the given keys (those that are set) from $params.
     *
     * @param array<string, mixed> $params
     * @param list<string>         $keys
     *
     * @return array<string, mixed>
     */
    public static function only(array $params, array $keys): array
    {
        return array_intersect_key($params, array_flip($keys));
    }

    /**
     * Ensure $params[$key] holds a reference, generating a UUID v4 if missing.
     *
     * @param array<string, mixed> $params
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    public static function withReference(array $params, string $key = 'merchantTxRef'): array
    {
        $ref = $params[$key] ?? null;
        if (!\is_string($ref) || $ref === '') {
            $ref = Uuid::v4();
            $params[$key] = $ref;
        }

        return [$params, $ref];
    }

    /**
     * Add the reference to the returned data if the API did not echo it.
     *
     * @return array<mixed>
     */
    public static function attachReference(mixed $data, string $ref, string $key = 'merchantTxRef'): array
    {
        if (!\is_array($data)) {
            return [$key => $ref];
        }
        if (!isset($data[$key]) || $data[$key] === '') {
            $data[$key] = $ref;
        }

        return $data;
    }
}
