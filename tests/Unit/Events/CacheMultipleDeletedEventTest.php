<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit\Events;

use PHPUnit\Framework\TestCase;
use Zaphyr\Cache\Events\CacheMultipleDeletedEvent;

class CacheMultipleDeletedEventTest extends TestCase
{
    /**
     * @var CacheMultipleDeletedEvent
     */
    protected CacheMultipleDeletedEvent $event;

    /**
     * @var string
     */
    protected string $storeName = 'testStore';

    /**
     * @var iterable<string>
     */
    protected iterable $keys = ['test.key1', 'test.key2'];

    protected function setUp(): void
    {
        $this->event = new CacheMultipleDeletedEvent($this->storeName, $this->keys);
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
     * GET KEYS
     * -------------------------------------------------
     */

    public function testGetValue(): void
    {
        $this->assertSame($this->keys, $this->event->getKeys());
    }
}
