<?php

declare(strict_types=1);

namespace Zaphyr\Cache\Stores;

use DateInterval;
use Predis\ClientInterface;
use Zaphyr\Cache\Exceptions\InvalidArgumentException;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class RedisStore extends AbstractStore
{
    /**
     * @param ClientInterface $redis
     * @param string          $prefix
     */
    public function __construct(protected ClientInterface $redis, protected string $prefix = '')
    {
    }

    /**
     * @param string $key
     *
     * @throws InvalidArgumentException if the $key string is not a legal value
     *
     * @return string
     */
    protected function getKey(string $key): string
    {
        $key = $this->prefix . $key;

        $this->validateKey($key);

        return $key;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->getKey($key));

        return !is_null($value) ? unserialize($value) : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $key = $this->getKey($key);
        $value = serialize($value);

        if ($ttl === null) {
            return (bool)$this->redis->set($key, $value);
        }

        if ($ttl instanceof DateInterval) {
            $ttl = $this->convertDateIntervalToTimestamp($ttl) - time();
        }

        if ($ttl <= 0) {
            $this->delete($key);

            return false;
        }

        return (bool)$this->redis->setex($key, $ttl, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return (bool)$this->redis->del($this->getKey($key));
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->redis->flushdb();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $keys = $this->prepareIterable($keys);
        $this->validateMultipleKeys($keys);

        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
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
        $values = $this->prepareIterable($values);

        foreach ($values as $key => $value) {
            $this->validateKey($key);
        }

        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $keys = $this->prepareIterable($keys);
        $this->validateMultipleKeys($keys);

        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return (bool)$this->redis->exists($this->getKey($key));
    }
}
