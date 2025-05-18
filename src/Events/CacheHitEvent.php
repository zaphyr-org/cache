<?php

declare(strict_types=1);

namespace Zaphyr\Cache\Events;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class CacheHitEvent extends AbstractEvent
{
    /**
     * @param string $storeName
     * @param string $key
     * @param mixed  $value
     */
    public function __construct(string $storeName, string $key, protected mixed $value)
    {
        parent::__construct($storeName, $key);
    }

    /**
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
