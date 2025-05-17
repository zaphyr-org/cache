<?php

namespace Zaphyr\Cache\Contracts;

use Psr\SimpleCache\CacheInterface as PsrCacheInterface;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
interface CacheInterface extends PsrCacheInterface
{
    /**
     * @return PsrCacheInterface
     */
    public function getStore(): PsrCacheInterface;
}
