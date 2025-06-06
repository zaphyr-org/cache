<?php

declare(strict_types=1);

namespace Zaphyr\Cache\Stores;

use DateInterval;
use DateTime;
use Generator;
use Psr\SimpleCache\CacheInterface;
use Zaphyr\Cache\Exceptions\InvalidArgumentException;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
abstract class AbstractStore implements CacheInterface
{
    /**
     * @param string $key
     *
     * @throws InvalidArgumentException if the $key string is not a legal value
     */
    protected function validateKey(string $key): void
    {
        $key = trim($key);

        if ($key === '') {
            throw new InvalidArgumentException('Cache key cannot be empty');
        }

        if (!mb_check_encoding($key, 'UTF-8')) {
            throw new InvalidArgumentException('Cache key must be UTF-8 encoded');
        }

        if (strpbrk($key, '{}()/\@:')) {
            throw new InvalidArgumentException(
                'Cache key cannot contain the following characters: {}()/\\@:'
            );
        }
    }

    /**
     * @param iterable<string> $keys
     *
     * @throws InvalidArgumentException if any of the $keys strings is not a legal value
     * @return void
     */
    protected function validateMultipleKeys(iterable $keys): void
    {
        foreach ($keys as $key) {
            $this->validateKey($key);
        }
    }

    /**
     * @param array<string, mixed> $contents
     *
     * @return bool
     */
    protected function isValidCacheData(array $contents): bool
    {
        return isset($contents['value'], $contents['expiry']) && is_int($contents['expiry']);
    }

    /**
     * @param mixed                 $value
     * @param DateInterval|int|null $ttl
     *
     * @return array{value: mixed, expiry: int}
     */
    protected function createCacheItem(mixed $value, DateInterval|int|null $ttl = null): array
    {
        return [
            'value' => $value,
            'expiry' => $this->expiry($ttl),
        ];
    }

    /**
     * @param DateInterval|int|null $ttl
     *
     * @return int
     */
    protected function expiry(DateInterval|int|null $ttl): int
    {
        if ($ttl instanceof DateInterval) {
            return $this->convertDateIntervalToTimestamp($ttl);
        }

        if (is_int($ttl)) {
            if ($ttl <= 0) {
                return 0;
            }

            if ($ttl > 9999999999) {
                return 9999999999;
            }

            return time() + $ttl;
        }

        return 9999999999;
    }

    /**
     * @param DateInterval $interval
     *
     * @return int
     */
    protected function convertDateIntervalToTimestamp(DateInterval $interval): int
    {
        return (new DateTime())->add($interval)->getTimestamp();
    }

    /**
     * @param iterable<string, mixed> $values
     *
     * @return iterable<string, mixed>
     */
    protected function prepareIterable(iterable $values): iterable
    {
        return $values instanceof Generator ? iterator_to_array($values) : $values;
    }
}
