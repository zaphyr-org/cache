<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit\Events;

use PHPUnit\Framework\TestCase;
use Zaphyr\Cache\Events\CacheClearedEvent;

class CacheClearedEventTest extends TestCase
{
    /**
     * @var CacheClearedEvent
     */
    protected CacheClearedEvent $event;

    /**
     * @var string
     */
    protected string $storeName = 'testStore';

    protected function setUp(): void
    {
        $this->event = new CacheClearedEvent($this->storeName);
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
}
