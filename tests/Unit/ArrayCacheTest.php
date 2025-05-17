<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit;

use ArrayIterator;
use DateInterval;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;
use Zaphyr\Cache\ArrayCache;

class ArrayCacheTest extends TestCase
{
    /**
     * @var ArrayCache
     */
    protected ArrayCache $arrayCache;

    protected function setUp(): void
    {
        $this->arrayCache = new class extends ArrayCache {
            public function getStorage(string $key): array
            {
                return $this->storage[$key];
            }
        };
    }

    /* -------------------------------------------------
     * GET | SET
     * -------------------------------------------------
     */

    public function testGetAndSet(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        $this->arrayCache->set($key, $value);

        self::assertEquals($value, $this->arrayCache->get($key));
    }

    /* -------------------------------------------------
     * GET
     * -------------------------------------------------
     */

    public function testGetDefaultValueIsNull(): void
    {
        self::assertNull($this->arrayCache->get('non_existent_key'));
    }

    public function testGetWithDefaultValue(): void
    {
        $key = 'non_existent_key';
        $defaultValue = 'default_value';

        self::assertEquals($defaultValue, $this->arrayCache->get($key, $defaultValue));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testGetThrowsExceptionOnInvalidKey(string $illegalKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arrayCache->get($illegalKey);
    }

    /* -------------------------------------------------
     * SET
     * -------------------------------------------------
     */

    public function testSetWithTtlDateInterval(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $expiry = new DateInterval('PT1S');

        self::assertTrue($this->arrayCache->set($key, $value, $expiry));
        self::assertEquals($value, $this->arrayCache->get($key));

        $this->arrayCache->delete($key);

        self::assertNull($this->arrayCache->get($key));
    }

    public function testSetWithTtlInteger(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $expiry = 1;

        self::assertTrue($this->arrayCache->set($key, $value, $expiry));
        self::assertEquals($value, $this->arrayCache->get($key));

        $this->arrayCache->delete($key);

        self::assertNull($this->arrayCache->get($key));
    }

    public function testSetWithNullTtlLastsForever(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->arrayCache->set($key, $value));

        $storage = $this->arrayCache->getStorage($key);

        self::assertEquals(9999999999, $storage['expiry']);
    }

    public function testSetWithToHighTTlReturnsMaxTtl(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->arrayCache->set($key, $value, 999999999999999999));

        $storage = $this->arrayCache->getStorage($key);

        self::assertEquals(9999999999, $storage['expiry']);
    }

    public function testSetWithZeroTtlRemovesValue(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->arrayCache->set($key, $value, 0));
        self::assertNull($this->arrayCache->get($key));
    }

    public function testSetWithNegativeTtlRemovesValue(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->arrayCache->set($key, $value, -1));
        self::assertNull($this->arrayCache->get($key));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testSetThrowsExceptionOnInvalidKey(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arrayCache->set($invalidKey, 'value');
    }

    /* -------------------------------------------------
     * DELETE
     * -------------------------------------------------
     */

    public function testDelete(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $this->arrayCache->set($key, $value);

        self::assertEquals($value, $this->arrayCache->get($key));
        self::assertTrue($this->arrayCache->delete($key));
        self::assertNull($this->arrayCache->get($key));
    }

    public function testDeleteReturnsFalseOnFailure(): void
    {
        $key = 'non_existent_key';

        self::assertFalse($this->arrayCache->delete($key));
    }

    /* -------------------------------------------------
     * CLEAR
     * -------------------------------------------------
     */

    public function testClear(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $this->arrayCache->set($key, $value);

        self::assertEquals($value, $this->arrayCache->get($key));
        self::assertTrue($this->arrayCache->clear());
        self::assertNull($this->arrayCache->get($key));
    }

    /* -------------------------------------------------
     * GET MULTIPLE
     * -------------------------------------------------
     */

    public function testGetMultiple(): void
    {
        $items = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
            'test.key3' => 'test_value3',
        ];

        foreach ($items as $key => $value) {
            $this->arrayCache->set($key, $value);
        }

        $keys = array_keys($items);
        $result = $this->arrayCache->getMultiple($keys);

        self::assertIsIterable($result);
        self::assertEquals($items, $result);
    }

    public function testGetMultipleWithDefaultValue(): void
    {
        $keys = ['non_existent_key1', 'non_existent_key2'];
        $default = 'default_value';

        $result = $this->arrayCache->getMultiple($keys, $default);

        $expected = [
            'non_existent_key1' => $default,
            'non_existent_key2' => $default,
        ];

        self::assertIsIterable($result);
        self::assertEquals($expected, $result);
    }

    public function testGetMultipleWithMixedExistingAndNonExistingKeys(): void
    {
        $this->arrayCache->set('existing_key', 'existing_value');

        $keys = ['existing_key', 'non_existent_key'];
        $default = 'default_value';

        $result = $this->arrayCache->getMultiple($keys, $default);

        $expected = [
            'existing_key' => 'existing_value',
            'non_existent_key' => $default,
        ];

        self::assertIsIterable($result);
        self::assertEquals($expected, $result);
    }

    public function testGetMultipleWithTraversable(): void
    {
        $keys = new ArrayIterator(['test.key1', 'test.key2']);

        $this->arrayCache->set('test.key1', 'test_value1');
        $this->arrayCache->set('test.key2', 'test_value2');

        $result = $this->arrayCache->getMultiple($keys);

        $expected = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertEquals($expected, $result);
    }

    public function testGetMultipleWithValidAndInvalidKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arrayCache->getMultiple(['valid_key', '']);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testGetMultipleWithInvalidKeys(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arrayCache->getMultiple([$invalidKey]);
    }

    /* -------------------------------------------------
     * SET MULTIPLE
     * -------------------------------------------------
     */

    public function testSetMultiple(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
            'test.key3' => 'test_value3',
        ];

        self::assertTrue($this->arrayCache->setMultiple($values));

        foreach ($values as $key => $value) {
            self::assertEquals($value, $this->arrayCache->get($key));
        }
    }

    public function testSetMultipleWithTtl(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        $expiry = time() + $ttl = 1;

        self::assertTrue($this->arrayCache->setMultiple($values, $ttl));

        foreach ($values as $key => $value) {
            self::assertEquals($expiry, $this->arrayCache->getStorage($key)['expiry']);
        }
    }

    public function testSetMultipleWithNullTtlLastsForever(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];


        self::assertTrue($this->arrayCache->setMultiple($values));

        foreach ($values as $key => $value) {
            self::assertEquals(9999999999, $this->arrayCache->getStorage($key)['expiry']);
        }
    }

    public function testSetMultipleWithToHighTTlReturnsMaxTtl(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertTrue($this->arrayCache->setMultiple($values, 999999999999999999));

        foreach ($values as $key => $value) {
            self::assertEquals(9999999999, $this->arrayCache->getStorage($key)['expiry']);
        }
    }

    public function testSetMultipleWithZeroTtlRemovesValues(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertTrue($this->arrayCache->setMultiple($values, 0));

        foreach ($values as $key => $value) {
            self::assertNull($this->arrayCache->get($key));
        }
    }

    public function testSetMultipleWithNegativeTtlRemovesValues(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertTrue($this->arrayCache->setMultiple($values, -1));

        foreach ($values as $key => $value) {
            self::assertNull($this->arrayCache->get($key));
        }
    }

    public function testSetMultipleWithTraversable(): void
    {
        $values = new ArrayIterator([
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ]);

        self::assertTrue($this->arrayCache->setMultiple($values));

        foreach ($values as $key => $value) {
            self::assertEquals($value, $this->arrayCache->get($key));
        }
    }

    public function testSetMultipleWithMixedValidAndInvalidKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $values = [
            'valid.key' => 'test_value1',
            '' => 'test_value2',
        ];

        $this->arrayCache->setMultiple($values);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testSetMultipleWithInvalidKeys(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);

        $values = [
            $invalidKey => 'value',
        ];

        $this->arrayCache->setMultiple($values);
    }

    /* -------------------------------------------------
     * DELETE MULTIPLE
     * -------------------------------------------------
     */

    public function testDeleteMultiple(): void
    {
        $items = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
            'test.key3' => 'test_value3',
        ];

        foreach ($items as $key => $value) {
            $this->arrayCache->set($key, $value);
        }

        $keys = array_keys($items);

        self::assertTrue($this->arrayCache->deleteMultiple($keys));

        foreach ($keys as $key) {
            self::assertNull($this->arrayCache->get($key));
        }
    }

    public function testDeleteMultipleWithNonExistentKeys(): void
    {
        $keys = ['non_existent_key1', 'non_existent_key2'];

        self::assertFalse($this->arrayCache->deleteMultiple($keys));
    }

    public function testDeleteMultipleWithMixedExistingAndNonExistingKeys(): void
    {
        $this->arrayCache->set('existing_key', 'value');

        $keys = ['non_existent_key', 'existing_key'];

        self::assertFalse($this->arrayCache->deleteMultiple($keys));
        self::assertNull($this->arrayCache->get('existing_key'));
    }

    public function testDeleteMultipleWithTraversable(): void
    {
        $items = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        foreach ($items as $key => $value) {
            $this->arrayCache->set($key, $value);
        }

        $keys = new ArrayIterator(array_keys($items));

        self::assertTrue($this->arrayCache->deleteMultiple($keys));

        foreach ($keys as $key) {
            self::assertNull($this->arrayCache->get($key));
        }
    }

    public function testDeleteMultipleWithMixedValidAndInvalidKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arrayCache->deleteMultiple(['valid.key', '']);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testDeleteMultipleWithInvalidKeys(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arrayCache->deleteMultiple([$invalidKey]);
    }

    /* -------------------------------------------------
     * HAS
     * -------------------------------------------------
     */

    public function testHas(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        $this->arrayCache->set($key, $value);

        self::assertTrue($this->arrayCache->has($key));

        $this->arrayCache->delete($key);

        self::assertFalse($this->arrayCache->has($key));
    }

    public function testHasWithNullValue(): void
    {
        $key = 'test.key';
        $value = null;

        $this->arrayCache->set($key, $value);

        self::assertTrue($this->arrayCache->has($key));
    }

    public function testHasWithExpiredItem(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        $this->arrayCache->set($key, $value, 1);

        self::assertTrue($this->arrayCache->has($key));

        $this->arrayCache->delete($key);

        self::assertFalse($this->arrayCache->has($key));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testHasThrowsExceptionOnInvalidKey(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arrayCache->has($invalidKey);
    }
}
