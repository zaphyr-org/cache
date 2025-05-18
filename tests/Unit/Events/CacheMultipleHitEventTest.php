<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit\Events;

use PHPUnit\Framework\TestCase;
use Zaphyr\Cache\Events\CacheMultipleHitEvent;

class CacheMultipleHitEventTest extends TestCase
{
    /**
     * @var CacheMultipleHitEvent
     */
    protected CacheMultipleHitEvent $event;

    /**
     * @var string
     */
    protected string $storeName = 'testStore';

    /**
     * @var iterable<string>
     */
    protected iterable $values = ['test.key1' => 'test_value1', 'test.key2' => 'test_value2'];

    protected function setUp(): void
    {
        $this->event = new CacheMultipleHitEvent($this->storeName, $this->values);
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

    public function testGetValues(): void
    {
        $this->assertSame($this->values, $this->event->getValues());
    }
}
