<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit\Events;

use PHPUnit\Framework\TestCase;
use Zaphyr\Cache\Events\CacheWrittenEvent;

class CacheWrittenEventTest extends TestCase
{
    /**
     * @var CacheWrittenEvent
     */
    protected CacheWrittenEvent $event;

    /**
     * @var string
     */
    protected string $storeName = 'testStore';

    /**
     * @var string
     */
    protected string $key = 'test.key1';

    /**
     * @var string
     */
    protected string $value = 'test_value';

    /**
     * @var int
     */
    protected int $ttl = 3600;

    protected function setUp(): void
    {
        $this->event = new CacheWrittenEvent($this->storeName, $this->key, $this->value, $this->ttl);
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
     * GET KEY
     * -------------------------------------------------
     */

    public function testGetKey(): void
    {
        $this->assertSame($this->key, $this->event->getKey());
    }

    /* -------------------------------------------------
     * GET VALUE
     * -------------------------------------------------
     */

    public function testGetValue(): void
    {
        $this->assertSame($this->value, $this->event->getValue());
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
