<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit\Events;

use PHPUnit\Framework\TestCase;
use Zaphyr\Cache\Events\CacheWrittenMultipleEvent;

class CacheWrittenMultipleEventTest extends TestCase
{
    /**
     * @var CacheWrittenMultipleEvent
     */
    protected CacheWrittenMultipleEvent $event;

    /**
     * @var string
     */
    protected string $storeName = 'testStore';

    /**
     * @var iterable<string, mixed>
     */
    protected iterable $values = ['test.key1' => 'test_value1', 'test.key2' => 'test_value2'];

    /**
     * @var int
     */
    protected int $ttl = 3600;

    protected function setUp(): void
    {
        $this->event = new CacheWrittenMultipleEvent($this->storeName, $this->values, $this->ttl);
    }

    protected function tearDown(): void
    {
        unset($this->event);
    }

    /* -------------------------------------------------
     * GET STORE NAME
     * -------------------------------------------------
     */

    public function testGetStoreName(): void
    {
        $this->assertSame($this->storeName, $this->event->getStoreName());
    }

    /* -------------------------------------------------
     * GET VALUES
     * -------------------------------------------------
     */

    public function testGetKeys(): void
    {
        $this->assertSame($this->values, $this->event->getValues());
    }

    /* -------------------------------------------------
     * GET TTL
     * -------------------------------------------------
     */

    public function testGetTtl(): void
    {
        $this->assertSame($this->ttl, $this->event->getTtl());
    }
}
