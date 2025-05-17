<?php

declare(strict_types=1);

namespace Zaphyr\Cache;

use Closure;
use Predis\Client;
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
    public const ARRAY_CACHE = 'array';

    /**
     * @const string
     */
    public const FILE_CACHE = 'file';

    /**
     * @const string
     */
    public const REDIS_CACHE = 'redis';

    /**
     * @const array<string, string|int|false>
     */
    protected const REDIS_DEFAULT_CONFIG = [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'async' => false,
        'persistent' => false,
        'timeout' => 5.0,
    ];

    /**
     * @const    string[]
     * @formatter:off
     */
    protected const REDIS_ALLOWED_PARAMETERS = [
        'scheme', 'host', 'port', 'async', 'persistent',
        'timeout', 'path', 'database', 'password', 'username',
        'read_write_timeout', 'alias', 'weight', 'client_info',
    ]; // @formatter:on

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
        $cacheTypes = [self::ARRAY_CACHE, self::FILE_CACHE, self::REDIS_CACHE];

        if ((!$force && isset($this->customCaches[$name])) || in_array($name, $cacheTypes)) {
            throw new CacheException('Cache with name "' . $name . '" already exists.');
        }

        $this->customCaches[$name] = $callback;

        return $this;
    }

    /**
     * @param string $cache
     *
     * @throws CacheException if the cache name does not exist
     * @return CacheInterface
     */
    protected function createCache(string $cache): CacheInterface
    {
        return match ($cache) {
            self::ARRAY_CACHE => new ArrayCache(),
            self::FILE_CACHE => $this->createFileCache(),
            self::REDIS_CACHE => $this->createRedisCache(),
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
     * @return CacheInterface
     */
    protected function createRedisCache(): CacheInterface
    {
        $config = $this->cacheConfig[self::REDIS_CACHE] ?? [];

        $parameters = array_merge(
            self::REDIS_DEFAULT_CONFIG,
            array_intersect_key(
                $config,
                array_flip(self::REDIS_ALLOWED_PARAMETERS)
            )
        );

        $prefix = $config['prefix'] ?? 'zaphyr_';

        return new RedisCache(new Client($parameters), $prefix);
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
