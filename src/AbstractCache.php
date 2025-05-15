<?php

declare(strict_types=1);

namespace Zaphyr\Cache;

use Psr\SimpleCache\CacheInterface;
use Zaphyr\Cache\Exceptions\InvalidArgumentException;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
abstract class AbstractCache implements CacheInterface
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
}
