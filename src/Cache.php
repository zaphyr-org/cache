<?php

declare(strict_types=1);

namespace Zaphyr\Cache;

use DateInterval;
use Zaphyr\Cache\Contracts\CacheInterface;
use Psr\SimpleCache\CacheInterface as PsrCacheInterface;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class Cache implements CacheInterface
{
    /**
     * @param PsrCacheInterface $store
     */
    public function __construct(protected PsrCacheInterface $store)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getStore(): PsrCacheInterface
    {
        return $this->store;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store->get($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        return $this->store->set($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return $this->store->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        return $this->store->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->store->getMultiple($keys, $default);
    }

    /**
     * {@inheritdoc}
     *
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        return $this->store->setMultiple($values, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        return $this->store->deleteMultiple($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->store->has($key);
    }
}
