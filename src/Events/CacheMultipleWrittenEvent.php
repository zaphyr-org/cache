<?php

declare(strict_types=1);

namespace Zaphyr\Cache\Events;

use DateInterval;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class CacheMultipleWrittenEvent
{
    /**
     * @param string                  $storeName
     * @param iterable<string, mixed> $values
     * @param DateInterval|int|null   $ttl
     */
    public function __construct(
        protected string $storeName,
        protected iterable $values,
        protected DateInterval|int|null $ttl = null
    ) {
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

    /**
     * @return DateInterval|int|null
     */
    public function getTtl(): DateInterval|int|null
    {
        return $this->ttl;
    }
}
