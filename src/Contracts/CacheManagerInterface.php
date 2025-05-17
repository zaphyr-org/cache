<?php

declare(strict_types=1);

namespace Zaphyr\Cache\Contracts;

use Closure;
use Zaphyr\Cache\Exceptions\CacheException;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
interface CacheManagerInterface
{
    /**
     * @param string|null $store
     *
     * @throws CacheException if the cache store name does not exist
     * @return CacheInterface
     */
    public function cache(?string $store = null): CacheInterface;

    /**
     * @param string  $name
     * @param Closure $callback
     * @param bool    $force
     *
     * @throws CacheException if the cache name already exists
     * @return $this
     */
    public function addStore(string $name, Closure $callback, bool $force = false): static;
}
