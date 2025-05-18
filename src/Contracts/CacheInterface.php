<?php

namespace Zaphyr\Cache\Contracts;

use DateInterval;
use Psr\SimpleCache\CacheInterface as PsrCacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
interface CacheInterface extends PsrCacheInterface
{
    /**
     * @return PsrCacheInterface
     */
    public function getStore(): PsrCacheInterface;

    /**
     * @param string                $key
     * @param callable              $value
     * @param DateInterval|int|null $ttl
     *
     * @throws InvalidArgumentException if the $key string is not a legal value
     * @return mixed
     */
    public function remember(string $key, callable $value, DateInterval|int|null $ttl = null): mixed;
}
