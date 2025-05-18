<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit\Events;

use PHPUnit\Framework\TestCase;
use Zaphyr\Cache\Events\CacheHitEvent;

class CacheHitEventTest extends TestCase
{
    /**
     * @var CacheHitEvent
     */
    protected CacheHitEvent $event;

    /**
     * @var string
     */
    protected string $storeName = 'testStore';

    /**
     * @var string
     */
    protected string $key = 'test.key';

    /**
     * @var string
     */
    protected string $value = 'test_value';

    protected function setUp(): void
    {
        $this->event = new CacheHitEvent($this->storeName, $this->key, $this->value);
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
}
