<?php

declare(strict_types=1);

namespace Zaphyr\Cache;

use DateInterval;
use Psr\EventDispatcher\EventDispatcherInterface;
use Zaphyr\Cache\Contracts\CacheInterface;
use Psr\SimpleCache\CacheInterface as PsrCacheInterface;
use Zaphyr\Cache\Events\CacheClearedEvent;
use Zaphyr\Cache\Events\CacheClearMissedEvent;
use Zaphyr\Cache\Events\CacheDeletedEvent;
use Zaphyr\Cache\Events\CacheDeleteMissedEvent;
use Zaphyr\Cache\Events\CacheHasEvent;
use Zaphyr\Cache\Events\CacheHasMissedEvent;
use Zaphyr\Cache\Events\CacheHitEvent;
use Zaphyr\Cache\Events\CacheMissedEvent;
use Zaphyr\Cache\Events\CacheMultipleDeletedEvent;
use Zaphyr\Cache\Events\CacheMultipleDeleteMissedEvent;
use Zaphyr\Cache\Events\CacheMultipleHitEvent;
use Zaphyr\Cache\Events\CacheMultipleMissedEvent;
use Zaphyr\Cache\Events\CacheWriteMultipleMissedEvent;
use Zaphyr\Cache\Events\CacheWrittenMultipleEvent;
use Zaphyr\Cache\Events\CacheWriteMissedEvent;
use Zaphyr\Cache\Events\CacheWrittenEvent;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class Cache implements CacheInterface
{
    /**
     * @param string                        $storeName
     * @param PsrCacheInterface             $storeInstance
     * @param EventDispatcherInterface|null $eventDispatcher
     */
    public function __construct(
        protected string $storeName,
        protected PsrCacheInterface $storeInstance,
        protected ?EventDispatcherInterface $eventDispatcher = null
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getStore(): PsrCacheInterface
    {
        return $this->storeInstance;
    }

    /**
     * {@inheritdoc}
     */
    public function remember(string $key, callable $value, DateInterval|int|null $ttl = null): mixed
    {
        $result = $this->get($key);

        if ($result !== null) {
            return $result;
        }

        $result = $value();
        $this->set($key, $result, $ttl);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->storeInstance->get($key, $default);

        if ($value === null) {
            $this->dispatchEvent(new CacheMissedEvent($this->storeName, $key));
        } else {
            $this->dispatchEvent(new CacheHitEvent($this->storeName, $key, $value));
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $success = $this->storeInstance->set($key, $value, $ttl);

        if ($success) {
            $this->dispatchEvent(new CacheWrittenEvent($this->storeName, $key, $value, $ttl));
        } else {
            $this->dispatchEvent(new CacheWriteMissedEvent($this->storeName, $key, $value, $ttl));
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $success = $this->storeInstance->delete($key);

        if ($success) {
            $this->dispatchEvent(new CacheDeletedEvent($this->storeName, $key));
        } else {
            $this->dispatchEvent(new CacheDeleteMissedEvent($this->storeName, $key));
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $success = $this->storeInstance->clear();

        if ($success) {
            $this->dispatchEvent(new CacheClearedEvent($this->storeName));
        } else {
            $this->dispatchEvent(new CacheClearMissedEvent($this->storeName));
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = $this->storeInstance->getMultiple($keys, $default);
        $hits = [];
        $misses = [];

        foreach ($results as $key => $value) {
            if ($value === null) {
                $misses[] = $key;
            } else {
                $hits[$key] = $value;
            }
        }

        if (!empty($hits)) {
            $this->dispatchEvent(new CacheMultipleHitEvent($this->storeName, $hits));
        }

        if (!empty($misses)) {
            $this->dispatchEvent(new CacheMultipleMissedEvent($this->storeName, $misses));
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     *
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        $success = $this->storeInstance->setMultiple($values, $ttl);

        if ($success) {
            $this->dispatchEvent(new CacheWrittenMultipleEvent($this->storeName, $values, $ttl));
        } else {
            $this->dispatchEvent(new CacheWriteMultipleMissedEvent($this->storeName, $values, $ttl));
        }

        return $success;
    }


    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = $this->storeInstance->deleteMultiple($keys);

        if ($success) {
            $this->dispatchEvent(new CacheMultipleDeletedEvent($this->storeName, $keys));
        } else {
            $this->dispatchEvent(new CacheMultipleDeleteMissedEvent($this->storeName, $keys));
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        $success = $this->storeInstance->has($key);

        if ($success) {
            $this->dispatchEvent(new CacheHasEvent($this->storeName, $key));
        } else {
            $this->dispatchEvent(new CacheHasMissedEvent($this->storeName, $key));
        }

        return $success;
    }

    /**
     * @param object $event
     *
     * @return void
     */
    protected function dispatchEvent(object $event): void
    {
        $this->eventDispatcher?->dispatch($event);
    }
}
