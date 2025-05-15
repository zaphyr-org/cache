<?php

declare(strict_types=1);

namespace Zaphyr\Cache;

use DateInterval;
use Throwable;
use Zaphyr\Utils\File;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class FileCache extends AbstractCache
{
    /**
     * @param string   $path
     * @param int|null $permissions
     */
    public function __construct(protected string $path, protected ?int $permissions = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);

        try {
            $contents = File::unserialize($this->getPath($key), true);

            if ($contents === null || !$this->isValidCacheData($contents)) {
                return $default;
            }

            if (time() > $contents['expiry']) {
                $this->delete($key);

                return $default;
            }

            return $contents['value'];
        } catch (Throwable) {
            return $default;
        }
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function getPath(string $key): string
    {
        $hash = sha1($key);
        $hashSegments = array_slice(str_split($hash, 2), 0, 2);

        return $this->path . '/' . implode('/', $hashSegments) . '/' . $hash;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->validateKey($key);

        $path = $this->getPath($key);
        $this->createMissingCacheDirectory($path);

        $result = File::serialize($path, $this->createCacheItem($value, $ttl), true);

        if ($result !== false && $result > 0) {
            $this->setPermissions($path);

            return true;
        }

        return false;
    }

    /**
     * @param string $path
     *
     * @return void
     */
    protected function createMissingCacheDirectory(string $path): void
    {
        $directory = dirname($path);

        if (!file_exists($directory)) {
            File::createDirectory($directory, 0777, true, true);

            $this->setPermissions($directory);
            $this->setPermissions(dirname($directory));
        }
    }

    /**
     * @param string $directory
     *
     * @return void
     */
    protected function setPermissions(string $directory): void
    {
        if ($this->permissions === null || intval(File::chmod($directory), 8) === $this->permissions) {
            return;
        }

        File::chmod($directory, $this->permissions);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);

        return File::delete($this->getPath($key));
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        return File::cleanDirectory($this->path);
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

        try {
            $contents = File::unserialize($this->getPath($key), true);

            if ($contents === null) {
                return false;
            }

            return time() <= $contents['expiry'];
        } catch (Throwable) {
            return false;
        }
    }
}
