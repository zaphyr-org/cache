<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use Zaphyr\Cache\CacheManager;
use Zaphyr\Cache\Stores\ArrayStore;
use Zaphyr\Cache\Stores\FileStore;
use Zaphyr\Cache\Stores\RedisStore;

class CacheManagerTest extends TestCase
{
    /**
     * @var CacheManager
     */
    protected CacheManager $cacheManager;

    protected function setUp(): void
    {
        $this->cacheManager = new CacheManager([
            CacheManager::FILE_STORE => [
                'path' => __DIR__,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        unset($this->cacheManager);
    }

    /* -------------------------------------------------
     * CACHE
     * -------------------------------------------------
     */

    public function testCacheIsPsrInstance(): void
    {
        self::assertInstanceOf(CacheInterface::class, $this->cacheManager->cache());
    }

    public function testCacheReturnsDefaultStore(): void
    {
        self::assertInstanceOf(FileStore::class, $this->cacheManager->cache()->getStore());
    }

    public function testCacheWithChangedDefaultStore(): void
    {
        $this->cacheManager = new CacheManager([], CacheManager::ARRAY_STORE);

        self::assertInstanceOf(ArrayStore::class, $this->cacheManager->cache()->getStore());
    }

    public function testCacheReturnsSameInstance(): void
    {
        $cache1 = $this->cacheManager->cache();
        $cache2 = $this->cacheManager->cache();

        self::assertSame($cache1, $cache2);
    }

    public function testCacheReturnsArrayStore(): void
    {
        self::assertInstanceOf(
            ArrayStore::class,
            $this->cacheManager->cache(CacheManager::ARRAY_STORE)->getStore()
        );
    }

    public function testCacheReturnsFileStore(): void
    {
        self::assertInstanceOf(
            FileStore::class,
            $this->cacheManager->cache(CacheManager::FILE_STORE)->getStore()
        );
    }

    public function testCacheReturnsRedisStore(): void
    {
        self::assertInstanceOf(
            RedisStore::class,
            $this->cacheManager->cache(CacheManager::REDIS_STORE)->getStore()
        );
    }

    public function testCacheThrowsExceptionIfStoreDoesNotExist(): void
    {
        $this->expectException(CacheException::class);

        $this->cacheManager->cache('nonexistent');
    }

    /* -------------------------------------------------
     * ADD STORE
     * -------------------------------------------------
     */

    public function testAddStore(): void
    {
        $this->cacheManager->addStore('custom', fn() => new FileStore(__DIR__));

        self::assertInstanceOf(FileStore::class, $this->cacheManager->cache('custom')->getStore());
    }

    public function testAddStoreThrowsExceptionIfStoreAlreadyExists(): void
    {
        $this->expectException(CacheException::class);

        $this->cacheManager->addStore('custom', fn() => new FileStore(__DIR__));
        $this->cacheManager->addStore('custom', fn() => new FileStore(__DIR__));
    }

    public function testAddStoreThrowsExceptionIfManagedStoreNameIsUsed(): void
    {
        $this->expectException(CacheException::class);

        $this->cacheManager->addStore(CacheManager::FILE_STORE, fn() => new FileStore(__DIR__), true);
    }

    public function testAddStoreWithForceFlag(): void
    {
        $store1 = new FileStore(__DIR__);
        $store2 = new FileStore(__DIR__);

        $this->cacheManager->addStore('custom', fn() => $store1);
        $this->cacheManager->addStore('custom', fn() => $store2, true);

        $store = $this->cacheManager->cache('custom')->getStore();

        self::assertNotSame($store1, $store);
        self::assertSame($store2, $store);
    }
}
