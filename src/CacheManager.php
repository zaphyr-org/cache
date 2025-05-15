<?php

declare(strict_types=1);

namespace Zaphyr\Cache;

use Closure;
use Psr\SimpleCache\CacheInterface;
use Zaphyr\Cache\Contracts\CacheManagerInterface;
use Zaphyr\Cache\Exceptions\CacheException;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class CacheManager implements CacheManagerInterface
{
    /**
     * @const string
     */
    public const FILE_CACHE = 'file';

    /**
     * @var CacheInterface[]
     */
    protected array $caches = [];

    /**
     * @var array<string, Closure>
     */
    protected array $customCaches = [];

    /**
     * @param array<string, mixed> $cacheConfig
     * @param string               $defaultCache
     */
    public function __construct(protected array $cacheConfig, protected string $defaultCache = self::FILE_CACHE)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function cache(?string $cache = null): CacheInterface
    {
        $cache ??= $this->defaultCache;

        if (!isset($this->caches[$cache])) {
            $this->caches[$cache] = $this->createCache($cache);
        }

        return $this->caches[$cache];
    }

    /**
     * {@inheritdoc}
     */
    public function addCache(string $name, Closure $callback, bool $force = false): static
    {
        if ((!$force && isset($this->customCaches[$name])) || in_array($name, [self::FILE_CACHE])) {
            throw new CacheException('Cache with name "' . $name . '" already exists.');
        }

        $this->customCaches[$name] = $callback;

        return $this;
    }

    /**
     * @param string $cache
     *
     * @return CacheInterface
     */
    protected function createCache(string $cache): CacheInterface
    {
        return match ($cache) {
            self::FILE_CACHE => $this->createFileCache(),
            default => $this->createCustomCache($cache),
        };
    }

    /**
     * @return CacheInterface
     */
    protected function createFileCache(): CacheInterface
    {
        $path = $this->cacheConfig[self::FILE_CACHE]['path'] ?? sys_get_temp_dir();
        $permissions = $this->cacheConfig[self::FILE_CACHE]['permissions'] ?? null;

        return new FileCache($path, $permissions);
    }

    /**
     * @param string $cache
     *
     * @throws CacheException if the cache name does not exist
     * @return CacheInterface
     */
    protected function createCustomCache(string $cache): CacheInterface
    {
        if (!isset($this->customCaches[$cache])) {
            throw new CacheException('Cache with name "' . $cache . '" does not exist.');
        }

        return $this->customCaches[$cache]();
    }
}
