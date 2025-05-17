<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheException;
use Zaphyr\Cache\ArrayCache;
use Zaphyr\Cache\CacheManager;
use Zaphyr\Cache\FileCache;
use Zaphyr\Cache\RedisCache;

class CacheManagerTest extends TestCase
{
    /**
     * @var CacheManager
     */
    protected CacheManager $cacheManager;

    protected function setUp(): void
    {
        $this->cacheManager = new CacheManager([
            CacheManager::FILE_CACHE => [
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

    public function testCacheReturnsDefaultCache(): void
    {
        self::assertInstanceOf(FileCache::class, $this->cacheManager->cache());
    }

    public function testCacheWithChangedDefaultCache(): void
    {
        $this->cacheManager = new CacheManager([], CacheManager::ARRAY_CACHE);

        self::assertInstanceOf(ArrayCache::class, $this->cacheManager->cache());
    }

    public function testCacheReturnsSameInstance(): void
    {
        $cache1 = $this->cacheManager->cache();
        $cache2 = $this->cacheManager->cache();

        self::assertSame($cache1, $cache2);
    }

    public function testCacheReturnsArrayCache(): void
    {
        self::assertInstanceOf(
            ArrayCache::class,
            $this->cacheManager->cache(CacheManager::ARRAY_CACHE)
        );
    }

    public function testCacheReturnsFileCache(): void
    {
        self::assertInstanceOf(
            FileCache::class,
            $this->cacheManager->cache(CacheManager::FILE_CACHE)
        );
    }

    public function testCacheReturnsRedisCache(): void
    {
        self::assertInstanceOf(
            RedisCache::class,
            $this->cacheManager->cache(CacheManager::REDIS_CACHE)
        );
    }

    public function testCacheThrowsExceptionIfCacheDoesNotExist(): void
    {
        $this->expectException(CacheException::class);

        $this->cacheManager->cache('nonexistent');
    }

    /* -------------------------------------------------
     * ADD CACHE
     * -------------------------------------------------
     */

    public function testAddCache(): void
    {
        $this->cacheManager->addCache('custom', fn() => new FileCache(__DIR__));

        $cache = $this->cacheManager->cache('custom');

        self::assertInstanceOf(FileCache::class, $cache);
    }

    public function testAddCacheThrowsExceptionIfCacheAlreadyExists(): void
    {
        $this->expectException(CacheException::class);

        $this->cacheManager->addCache('custom', fn() => new FileCache(__DIR__));
        $this->cacheManager->addCache('custom', fn() => new FileCache(__DIR__));
    }

    public function testAddCacheThrowsExceptionIfManagedCacheNameIsUsed(): void
    {
        $this->expectException(CacheException::class);

        $this->cacheManager->addCache(CacheManager::FILE_CACHE, fn() => new FileCache(__DIR__), true);
    }

    public function testAddCacheWithForceFlag(): void
    {
        $cache1 = new FileCache(__DIR__);
        $cache2 = new FileCache(__DIR__);

        $this->cacheManager->addCache('custom', fn() => $cache1);
        $this->cacheManager->addCache('custom', fn() => $cache2, true);

        $cache = $this->cacheManager->cache('custom');

        self::assertNotSame($cache1, $cache);
        self::assertSame($cache2, $cache);
    }
}
