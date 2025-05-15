<?php

declare(strict_types=1);

namespace Zaphyr\Cache\Contracts;

use Closure;
use Psr\SimpleCache\CacheInterface;
use Zaphyr\Cache\Exceptions\CacheException;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
interface CacheManagerInterface
{
    /**
     * @param string|null $cache
     *
     * @throws CacheException if the cache name does not exist
     * @return CacheInterface
     */
    public function cache(?string $cache = null): CacheInterface;

    /**
     * @param string  $name
     * @param Closure $callback
     * @param bool    $force
     *
     * @throws CacheException if the cache name already exists
     * @return $this
     */
    public function addCache(string $name, Closure $callback, bool $force = false): static;
}
