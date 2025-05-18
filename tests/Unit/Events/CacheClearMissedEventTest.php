<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit\Events;

use PHPUnit\Framework\TestCase;
use Zaphyr\Cache\Events\CacheClearMissedEvent;

class CacheClearMissedEventTest extends TestCase
{
    /**
     * @var CacheClearMissedEvent
     */
    protected CacheClearMissedEvent $event;

    /**
     * @var string
     */
    protected string $storeName = 'testStore';

    protected function setUp(): void
    {
        $this->event = new CacheClearMissedEvent($this->storeName);
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
