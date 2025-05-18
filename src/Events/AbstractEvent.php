<?php

declare(strict_types=1);

namespace Zaphyr\Cache\Events;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
abstract class AbstractEvent
{
    /**
     * @param string $storeName
     * @param string $key
     */
    public function __construct(protected string $storeName, protected string $key)
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
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }
}
