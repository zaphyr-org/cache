<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit\Stores;

use Psr\SimpleCache\CacheInterface;
use Zaphyr\Cache\Stores\ArrayStore;

class ArrayStoreTest extends AbstractStoreTestCase
{
    public function createStore(): CacheInterface
    {
        return new class extends ArrayStore {
            public function getStorage(string $key): array
            {
                return $this->storage[$key];
            }
        };
    }

    /* -------------------------------------------------
     * SET
     * -------------------------------------------------
     */

    public function testSetWithNullTtlLastsForever(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->store->set($key, $value));

        $storage = $this->store->getStorage($key);

        self::assertEquals(9999999999, $storage['expiry']);
    }

    public function testSetWithToHighTTlReturnsMaxTtl(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->store->set($key, $value, 999999999999999999));

        $storage = $this->store->getStorage($key);

        self::assertEquals(9999999999, $storage['expiry']);
    }

    /* -------------------------------------------------
     * CLEAR
     * -------------------------------------------------
     */

    public function testClear(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $this->store->set($key, $value);

        self::assertEquals($value, $this->store->get($key));
        self::assertTrue($this->store->clear());
        self::assertNull($this->store->get($key));
    }

    /* -------------------------------------------------
     * SET MULTIPLE
     * -------------------------------------------------
     */

    public function testSetMultipleWithTtl(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        $expiry = time() + $ttl = 1;

        self::assertTrue($this->store->setMultiple($values, $ttl));

        foreach ($values as $key => $value) {
            self::assertEquals($expiry, $this->store->getStorage($key)['expiry']);
        }
    }

    public function testSetMultipleWithNullTtlLastsForever(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];


        self::assertTrue($this->store->setMultiple($values));

        foreach ($values as $key => $value) {
            self::assertEquals(9999999999, $this->store->getStorage($key)['expiry']);
        }
    }

    public function testSetMultipleWithToHighTTlReturnsMaxTtl(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertTrue($this->store->setMultiple($values, 999999999999999999));

        foreach ($values as $key => $value) {
            self::assertEquals(9999999999, $this->store->getStorage($key)['expiry']);
        }
    }
}
