<?php

declare(strict_types=1);

namespace Zaphyr\Cache\Events;

use DateInterval;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class CacheWriteMissedEvent extends AbstractEvent
{
    /**
     * @param string                $storeName
     * @param string                $key
     * @param mixed                 $value
     * @param DateInterval|int|null $ttl
     */
    public function __construct(
        string $storeName,
        string $key,
        protected mixed $value,
        protected DateInterval|int|null $ttl = null
    ) {
        parent::__construct($storeName, $key);
    }

    /**
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * @return DateInterval|int|null
     */
    public function getTtl(): DateInterval|int|null
    {
        return $this->ttl;
    }
}
