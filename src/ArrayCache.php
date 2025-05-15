<?php

declare(strict_types=1);

namespace Zaphyr\Cache;

use DateInterval;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class ArrayCache extends AbstractCache
{
    /**
     * @var array<string, mixed>
     */
    protected array $storage = [];

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);

        if (!isset($this->storage[$key])) {
            return $default;
        }

        $item = $this->storage[$key];

        if (!$this->isValidCacheData($item)) {
            return $default;
        }

        if (time() > $item['expiry']) {
            $this->delete($key);

            return $default;
        }

        return $item['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->validateKey($key);

        $this->storage[$key] = $this->createCacheItem($value, $ttl);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);

        if (!isset($this->storage[$key])) {
            return false;
        }

        unset($this->storage[$key]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->storage = [];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $this->validateMultipleKeys($keys);

        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        $this->validateMultipleKeys($values);

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
        $this->validateKey($key);

        return isset($this->storage[$key]) && time() <= $this->storage[$key]['expiry'];
    }
}
