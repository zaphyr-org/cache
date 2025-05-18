<?php

declare(strict_types=1);

namespace Zaphyr\Cache\Events;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class CacheMultipleHitEvent
{
    /**
     * @param string                  $storeName
     * @param iterable<string, mixed> $values
     */
    public function __construct(protected string $storeName, protected iterable $values)
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
     * @return iterable<string, mixed>
     */
    public function getValues(): iterable
    {
        return $this->values;
    }
}
