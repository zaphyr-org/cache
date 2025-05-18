<?php

declare(strict_types=1);

namespace Zaphyr\Cache\Events;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class CacheMultipleDeleteMissedEvent
{
    /**
     * @param string           $storeName
     * @param iterable<string> $keys
     */
    public function __construct(protected string $storeName, protected iterable $keys)
    {
    }

    /**
     * @return string
     */
    public function getStoreName(): string
    {
        return $this->storeName;
    }

    /**
     * @return iterable<string>
     */
    public function getKeys(): iterable
    {
        return $this->keys;
    }
}
