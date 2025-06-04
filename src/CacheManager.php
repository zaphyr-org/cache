<?php

declare(strict_types=1);

namespace Zaphyr\Cache;

use Closure;
use Predis\Client;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\CacheInterface as PsrCacheInterface;
use Zaphyr\Cache\Contracts\CacheInterface;
use Zaphyr\Cache\Contracts\CacheManagerInterface;
use Zaphyr\Cache\Exceptions\CacheException;
use Zaphyr\Cache\Stores\ArrayStore;
use Zaphyr\Cache\Stores\FileStore;
use Zaphyr\Cache\Stores\RedisStore;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class CacheManager implements CacheManagerInterface
{
    /**
     * @const string
     */
    public const ARRAY_STORE = 'array';

    /**
     * @const string
     */
    public const FILE_STORE = 'file';

    /**
     * @const string
     */
    public const REDIS_STORE = 'redis';

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
    protected array $stores = [];

    /**
     * @var array<string, Closure>
     */
    protected array $customStores = [];

    /**
     * @param array<string, mixed>          $storeConfig
     * @param string                        $defaultStore
     * @param EventDispatcherInterface|null $eventDispatcher
     */
    public function __construct(
        protected array $storeConfig,
        protected string $defaultStore = self::FILE_STORE,
        protected ?EventDispatcherInterface $eventDispatcher = null
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function cache(?string $store = null): CacheInterface
    {
        $store ??= $this->defaultStore;

        if (!isset($this->stores[$store])) {
            $this->stores[$store] = $this->createStore($store);
        }

        return $this->stores[$store];
    }

    /**
     * {@inheritdoc}
     */
    public function addStore(string $name, Closure $callback, bool $force = false): static
    {
        $storeTypes = [self::ARRAY_STORE, self::FILE_STORE, self::REDIS_STORE];

        if ((!$force && isset($this->customStores[$name])) || in_array($name, $storeTypes)) {
            throw new CacheException('Cache store with name "' . $name . '" already exists.');
        }

        $this->customStores[$name] = $callback;

        return $this;
    }

    /**
     * @param string $store
     *
     * @throws CacheException if the cache name does not exist
     * @return CacheInterface
     */
    protected function createStore(string $store): CacheInterface
    {
        return match ($store) {
            self::ARRAY_STORE => $this->createArrayStore(),
            self::FILE_STORE => $this->createFileStore(),
            self::REDIS_STORE => $this->createRedisStore(),
            default => $this->createCustomStore($store),
        };
    }

    /**
     * @return CacheInterface
     */
    protected function createArrayStore(): CacheInterface
    {
        return $this->buildCache(self::ARRAY_STORE, new ArrayStore());
    }

    /**
     * @return CacheInterface
     */
    protected function createFileStore(): CacheInterface
    {
        $path = $this->storeConfig[self::FILE_STORE]['path'] ?? sys_get_temp_dir();
        $permissions = $this->storeConfig[self::FILE_STORE]['permissions'] ?? null;

        return $this->buildCache(self::FILE_STORE, new FileStore($path, $permissions));
    }

    /**
     * @return CacheInterface
     */
    protected function createRedisStore(): CacheInterface
    {
        $config = $this->storeConfig[self::REDIS_STORE] ?? [];

        $parameters = array_merge(
            self::REDIS_DEFAULT_CONFIG,
            array_intersect_key(
                $config,
                array_flip(self::REDIS_ALLOWED_PARAMETERS)
            )
        );

        return $this->buildCache(self::REDIS_STORE, new RedisStore(new Client($parameters), $config['prefix'] ?? ''));
    }

    /**
     * @param string $name
     *
     * @throws CacheException if the cache store name does not exist
     * @return CacheInterface
     */
    protected function createCustomStore(string $name): CacheInterface
    {
        if (!isset($this->customStores[$name])) {
            throw new CacheException('Cache store with name "' . $name . '" does not exist.');
        }

        return $this->buildCache($name, $this->customStores[$name]());
    }

    protected function buildCache(string $storeName, PsrCacheInterface $storeInstance): CacheInterface
    {
        return new Cache($storeName, $storeInstance, $this->eventDispatcher);
    }
}
