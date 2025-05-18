<?php

declare(strict_types=1);

namespace Zaphyr\Cache\Events;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class CacheClearMissedEvent
{
    /**
     * @param string $storeName
     */
    public function __construct(protected string $storeName)
    {
    }

    /**
     * @return string
     */
    public function getStoreName(): string
    {
        return $this->storeName;
    }
}
