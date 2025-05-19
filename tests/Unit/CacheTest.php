<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\CacheInterface;
use Zaphyr\Cache\Cache;
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
use Zaphyr\Cache\Events\CacheWriteMissedEvent;
use Zaphyr\Cache\Events\CacheMultipleWriteMissedEvent;
use Zaphyr\Cache\Events\CacheWrittenEvent;
use Zaphyr\Cache\Events\CacheMultipleWrittenEvent;
use Zaphyr\Cache\Stores\ArrayStore;

class CacheTest extends TestCase
{
    /**
     * @var CacheInterface&MockObject
     */
    protected CacheInterface&MockObject $storeMock;

    /**
     * @var EventDispatcherInterface&MockObject
     */
    protected EventDispatcherInterface&MockObject $eventDispatcherMock;

    /**
     * @var Cache
     */
    protected Cache $cache;

    /**
     * @var Cache
     */
    protected Cache $cacheWithEvents;

    protected function setUp(): void
    {
        $this->storeMock = $this->createMock(ArrayStore::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->cache = new Cache('testStore', $this->storeMock);
        $this->cacheWithEvents = new Cache('testStoreWithCache', $this->storeMock, $this->eventDispatcherMock);
    }

    protected function tearDown(): void
    {
        unset(
            $this->storeName,
            $this->storeMock,
            $this->eventDispatcherMock,
            $this->cache,
            $this->cacheWithEvents
        );
    }

    /* -------------------------------------------------
     * REMENBER
     * -------------------------------------------------
     */

    public function testRemember(): void
    {
        $expected = 'test_value';

        $this->storeMock
            ->expects(self::once())
            ->method('get')
            ->with('test.key', null)
            ->willReturn(null);

        $this->storeMock
            ->expects(self::once())
            ->method('set')
            ->with('test.key', $expected, null)
            ->willReturn(true);

        $result = $this->cache->remember('test.key', fn() => $expected);

        self::assertEquals($expected, $result);
    }

    public function testRememberWithDefaultValue(): void
    {
        $expected = 'test_value';

        $this->storeMock
            ->expects(self::once())
            ->method('get')
            ->with('test.key', null)
            ->willReturn($expected);

        $this->storeMock
            ->expects(self::never())
            ->method('set');

        $result = $this->cache->remember('test.key', fn() => 'other_value');

        self::assertEquals($expected, $result);
    }

    public function testRememberWithCacheHitEvent(): void
    {
        $expected = 'test_value';

        $this->storeMock
            ->expects(self::once())
            ->method('get')
            ->with('test.key', null)
            ->willReturn($expected);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheHitEvent::class));

        $result = $this->cacheWithEvents->remember('test.key', fn() => 'other_value');

        self::assertEquals($expected, $result);
    }

    public function testRememberWithCacheMissedEvent(): void
    {
        $expected = 'test_value';
        $ttl = 3600;

        $this->storeMock
            ->expects(self::once())
            ->method('get')
            ->with('test.key', null)
            ->willReturn(null);

        $this->storeMock
            ->expects(self::once())
            ->method('set')
            ->with('test.key', $expected, $ttl)
            ->willReturn(true);

        $this->eventDispatcherMock
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($expected, $ttl) {
                if ($event instanceof CacheMissedEvent) {
                    self::assertEquals('testStoreWithCache', $event->getStoreName());
                    self::assertEquals('test.key', $event->getKey());
                } elseif ($event instanceof CacheWrittenEvent) {
                    self::assertEquals('testStoreWithCache', $event->getStoreName());
                    self::assertEquals('test.key', $event->getKey());
                    self::assertEquals($expected, $event->getValue());
                    self::assertEquals($ttl, $event->getTtl());
                }
            });

        $result = $this->cacheWithEvents->remember('test.key', fn() => $expected, $ttl);

        self::assertEquals($expected, $result);
    }

    /* -------------------------------------------------
     * GET
     * -------------------------------------------------
     */

    public function testGet(): void
    {
        $expected = 'test_value';

        $this->storeMock
            ->expects(self::once())
            ->method('get')
            ->with('test.key', null)
            ->willReturn($expected);

        self::assertEquals($expected, $this->cache->get('test.key'));
    }

    public function testGetWithCacheHitEvent(): void
    {
        $expected = 'test_value';

        $this->storeMock
            ->expects(self::once())
            ->method('get')
            ->with('test.key', null)
            ->willReturn($expected);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheHitEvent::class));

        self::assertEquals($expected, $this->cacheWithEvents->get('test.key'));
    }

    public function testGetWithCacheMissedEvent(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('get')
            ->with('test.key', null)
            ->willReturn(null);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheMissedEvent::class));

        self::assertNull($this->cacheWithEvents->get('test.key'));
    }

    /* -------------------------------------------------
     * SET
     * -------------------------------------------------
     */

    public function testSet(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('set')
            ->with('test.key', 'test_value', null)
            ->willReturn(true);

        self::assertTrue($this->cache->set('test.key', 'test_value'));
    }

    public function testSetWithCacheWrittenEvent(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('set')
            ->with(
                'test.key',
                'test_value',
                null
            )
            ->willReturn(true);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheWrittenEvent::class));

        self::assertTrue($this->cacheWithEvents->set('test.key', 'test_value'));
    }

    public function testSetWithCacheWriteMissedEvent(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('set')
            ->with(
                'test.key',
                'test_value',
                null
            )
            ->willReturn(false);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheWriteMissedEvent::class));

        self::assertFalse($this->cacheWithEvents->set('test.key', 'test_value'));
    }

    /* -------------------------------------------------
     * DELETE
     * -------------------------------------------------
     */

    public function testDelete(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('delete')
            ->with('test.key')
            ->willReturn(true);

        self::assertTrue($this->cache->delete('test.key'));
    }

    public function testDeleteWithCacheDeletedEvent(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('delete')
            ->with('test.key')
            ->willReturn(true);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheDeletedEvent::class));

        self::assertTrue($this->cacheWithEvents->delete('test.key'));
    }

    public function testDeleteWithCacheDeleteMissedEvent(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('delete')
            ->with('test.key')
            ->willReturn(false);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheDeleteMissedEvent::class));

        self::assertFalse($this->cacheWithEvents->delete('test.key'));
    }

    /* -------------------------------------------------
     * CLEAR
     * -------------------------------------------------
     */

    public function testClear(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('clear')
            ->willReturn(true);

        self::assertTrue($this->cache->clear());
    }

    public function testClearWithCacheClearedEvent(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('clear')
            ->willReturn(true);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheClearedEvent::class));

        self::assertTrue($this->cacheWithEvents->clear());
    }

    public function testClearWithCacheClearMissedEvent(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('clear')
            ->willReturn(false);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheClearMissedEvent::class));

        self::assertFalse($this->cacheWithEvents->clear());
    }

    /* -------------------------------------------------
     * GET MULTIPLE
     * -------------------------------------------------
     */

    public function testGetMultiple(): void
    {
        $expected = ['test.key' => 'test_value'];

        $this->storeMock
            ->expects(self::once())
            ->method('getMultiple')
            ->with(['test.key'], null)
            ->willReturn($expected);

        self::assertEquals($expected, $this->cache->getMultiple(['test.key']));
    }

    public function testGetMultipleWithCacheMultipleHitEvent(): void
    {
        $expected = ['test.key' => 'test_value'];

        $this->storeMock
            ->expects(self::once())
            ->method('getMultiple')
            ->with(['test.key'], null)
            ->willReturn($expected);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheMultipleHitEvent::class));

        self::assertEquals($expected, $this->cacheWithEvents->getMultiple(['test.key']));
    }

    public function testGetMultipleWithCacheMultipleMissedEvent(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('getMultiple')
            ->with(['test.key'], null)
            ->willReturn(['test.key' => null]);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheMultipleMissedEvent::class));

        self::assertEquals(['test.key' => null], $this->cacheWithEvents->getMultiple(['test.key']));
    }

    /* -------------------------------------------------
     * SET MULTIPLE
     * -------------------------------------------------
     */

    public function testSetMultiple(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('setMultiple')
            ->with(['test.key' => 'test_value'], null)
            ->willReturn(true);

        self::assertTrue($this->cache->setMultiple(['test.key' => 'test_value']));
    }

    public function testSetMultipleWithCacheWrittenMultipleEvent(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('setMultiple')
            ->with(['test.key' => 'test_value'], null)
            ->willReturn(true);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheMultipleWrittenEvent::class));

        self::assertTrue($this->cacheWithEvents->setMultiple(['test.key' => 'test_value']));
    }

    public function testSetMultipleWithCacheWriteMultipleMissedEvent(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('setMultiple')
            ->with(['test.key' => 'test_value'], null)
            ->willReturn(false);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheMultipleWriteMissedEvent::class));

        self::assertFalse($this->cacheWithEvents->setMultiple(['test.key' => 'test_value']));
    }

    /* -------------------------------------------------
     * DELETE MULTIPLE
     * -------------------------------------------------
     */

    public function testDeleteMultiple(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('deleteMultiple')
            ->with(['test.key'])
            ->willReturn(true);

        self::assertTrue($this->cache->deleteMultiple(['test.key']));
    }

    public function testDeleteMultipleWithCacheMultipleDeletedEvent(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('deleteMultiple')
            ->with(['test.key'])
            ->willReturn(true);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheMultipleDeletedEvent::class));

        self::assertTrue($this->cacheWithEvents->deleteMultiple(['test.key']));
    }

    public function testDeleteMultipleWithCacheMultipleDeleteMissedEvent(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('deleteMultiple')
            ->with(['test.key'])
            ->willReturn(false);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheMultipleDeleteMissedEvent::class));

        self::assertFalse($this->cacheWithEvents->deleteMultiple(['test.key']));
    }

    /* -------------------------------------------------
     * HAS
     * -------------------------------------------------
     */

    public function testHas(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('has')
            ->with('test.key')
            ->willReturn(true);

        self::assertTrue($this->cache->has('test.key'));
    }

    public function testHasWithCacheHasEvent(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('has')
            ->with('test.key')
            ->willReturn(true);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheHasEvent::class));

        self::assertTrue($this->cacheWithEvents->has('test.key'));
    }

    public function testHasWithCacheHasMissedEvent(): void
    {
        $this->storeMock
            ->expects(self::once())
            ->method('has')
            ->with('test.key')
            ->willReturn(false);

        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CacheHasMissedEvent::class));

        self::assertFalse($this->cacheWithEvents->has('test.key'));
    }
}
