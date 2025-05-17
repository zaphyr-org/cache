<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit;

use ArrayIterator;
use DateInterval;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;
use Zaphyr\Cache\Stores\ArrayStore;

class ArrayStoreTest extends TestCase
{
    /**
     * @var ArrayStore
     */
    protected ArrayStore $arrayStore;

    protected function setUp(): void
    {
        $this->arrayStore = new class extends ArrayStore {
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

        $this->arrayStore->set($key, $value);

        self::assertEquals($value, $this->arrayStore->get($key));
    }

    /* -------------------------------------------------
     * GET
     * -------------------------------------------------
     */

    public function testGetDefaultValueIsNull(): void
    {
        self::assertNull($this->arrayStore->get('non_existent_key'));
    }

    public function testGetWithDefaultValue(): void
    {
        $key = 'non_existent_key';
        $defaultValue = 'default_value';

        self::assertEquals($defaultValue, $this->arrayStore->get($key, $defaultValue));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testGetThrowsExceptionOnInvalidKey(string $illegalKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arrayStore->get($illegalKey);
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

        self::assertTrue($this->arrayStore->set($key, $value, $expiry));
        self::assertEquals($value, $this->arrayStore->get($key));

        $this->arrayStore->delete($key);

        self::assertNull($this->arrayStore->get($key));
    }

    public function testSetWithTtlInteger(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $expiry = 1;

        self::assertTrue($this->arrayStore->set($key, $value, $expiry));
        self::assertEquals($value, $this->arrayStore->get($key));

        $this->arrayStore->delete($key);

        self::assertNull($this->arrayStore->get($key));
    }

    public function testSetWithNullTtlLastsForever(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->arrayStore->set($key, $value));

        $storage = $this->arrayStore->getStorage($key);

        self::assertEquals(9999999999, $storage['expiry']);
    }

    public function testSetWithToHighTTlReturnsMaxTtl(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->arrayStore->set($key, $value, 999999999999999999));

        $storage = $this->arrayStore->getStorage($key);

        self::assertEquals(9999999999, $storage['expiry']);
    }

    public function testSetWithZeroTtlRemovesValue(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->arrayStore->set($key, $value, 0));
        self::assertNull($this->arrayStore->get($key));
    }

    public function testSetWithNegativeTtlRemovesValue(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->arrayStore->set($key, $value, -1));
        self::assertNull($this->arrayStore->get($key));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testSetThrowsExceptionOnInvalidKey(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arrayStore->set($invalidKey, 'value');
    }

    /* -------------------------------------------------
     * DELETE
     * -------------------------------------------------
     */

    public function testDelete(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $this->arrayStore->set($key, $value);

        self::assertEquals($value, $this->arrayStore->get($key));
        self::assertTrue($this->arrayStore->delete($key));
        self::assertNull($this->arrayStore->get($key));
    }

    public function testDeleteReturnsFalseOnFailure(): void
    {
        $key = 'non_existent_key';

        self::assertFalse($this->arrayStore->delete($key));
    }

    /* -------------------------------------------------
     * CLEAR
     * -------------------------------------------------
     */

    public function testClear(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $this->arrayStore->set($key, $value);

        self::assertEquals($value, $this->arrayStore->get($key));
        self::assertTrue($this->arrayStore->clear());
        self::assertNull($this->arrayStore->get($key));
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
            $this->arrayStore->set($key, $value);
        }

        $keys = array_keys($items);
        $result = $this->arrayStore->getMultiple($keys);

        self::assertIsIterable($result);
        self::assertEquals($items, $result);
    }

    public function testGetMultipleWithDefaultValue(): void
    {
        $keys = ['non_existent_key1', 'non_existent_key2'];
        $default = 'default_value';

        $result = $this->arrayStore->getMultiple($keys, $default);

        $expected = [
            'non_existent_key1' => $default,
            'non_existent_key2' => $default,
        ];

        self::assertIsIterable($result);
        self::assertEquals($expected, $result);
    }

    public function testGetMultipleWithMixedExistingAndNonExistingKeys(): void
    {
        $this->arrayStore->set('existing_key', 'existing_value');

        $keys = ['existing_key', 'non_existent_key'];
        $default = 'default_value';

        $result = $this->arrayStore->getMultiple($keys, $default);

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

        $this->arrayStore->set('test.key1', 'test_value1');
        $this->arrayStore->set('test.key2', 'test_value2');

        $result = $this->arrayStore->getMultiple($keys);

        $expected = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertEquals($expected, $result);
    }

    public function testGetMultipleWithValidAndInvalidKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arrayStore->getMultiple(['valid_key', '']);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testGetMultipleWithInvalidKeys(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arrayStore->getMultiple([$invalidKey]);
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

        self::assertTrue($this->arrayStore->setMultiple($values));

        foreach ($values as $key => $value) {
            self::assertEquals($value, $this->arrayStore->get($key));
        }
    }

    public function testSetMultipleWithTtl(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        $expiry = time() + $ttl = 1;

        self::assertTrue($this->arrayStore->setMultiple($values, $ttl));

        foreach ($values as $key => $value) {
            self::assertEquals($expiry, $this->arrayStore->getStorage($key)['expiry']);
        }
    }

    public function testSetMultipleWithNullTtlLastsForever(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];


        self::assertTrue($this->arrayStore->setMultiple($values));

        foreach ($values as $key => $value) {
            self::assertEquals(9999999999, $this->arrayStore->getStorage($key)['expiry']);
        }
    }

    public function testSetMultipleWithToHighTTlReturnsMaxTtl(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertTrue($this->arrayStore->setMultiple($values, 999999999999999999));

        foreach ($values as $key => $value) {
            self::assertEquals(9999999999, $this->arrayStore->getStorage($key)['expiry']);
        }
    }

    public function testSetMultipleWithZeroTtlRemovesValues(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertTrue($this->arrayStore->setMultiple($values, 0));

        foreach ($values as $key => $value) {
            self::assertNull($this->arrayStore->get($key));
        }
    }

    public function testSetMultipleWithNegativeTtlRemovesValues(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertTrue($this->arrayStore->setMultiple($values, -1));

        foreach ($values as $key => $value) {
            self::assertNull($this->arrayStore->get($key));
        }
    }

    public function testSetMultipleWithTraversable(): void
    {
        $values = new ArrayIterator([
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ]);

        self::assertTrue($this->arrayStore->setMultiple($values));

        foreach ($values as $key => $value) {
            self::assertEquals($value, $this->arrayStore->get($key));
        }
    }

    public function testSetMultipleWithMixedValidAndInvalidKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $values = [
            'valid.key' => 'test_value1',
            '' => 'test_value2',
        ];

        $this->arrayStore->setMultiple($values);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testSetMultipleWithInvalidKeys(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);

        $values = [
            $invalidKey => 'value',
        ];

        $this->arrayStore->setMultiple($values);
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
            $this->arrayStore->set($key, $value);
        }

        $keys = array_keys($items);

        self::assertTrue($this->arrayStore->deleteMultiple($keys));

        foreach ($keys as $key) {
            self::assertNull($this->arrayStore->get($key));
        }
    }

    public function testDeleteMultipleWithNonExistentKeys(): void
    {
        $keys = ['non_existent_key1', 'non_existent_key2'];

        self::assertFalse($this->arrayStore->deleteMultiple($keys));
    }

    public function testDeleteMultipleWithMixedExistingAndNonExistingKeys(): void
    {
        $this->arrayStore->set('existing_key', 'value');

        $keys = ['non_existent_key', 'existing_key'];

        self::assertFalse($this->arrayStore->deleteMultiple($keys));
        self::assertNull($this->arrayStore->get('existing_key'));
    }

    public function testDeleteMultipleWithTraversable(): void
    {
        $items = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        foreach ($items as $key => $value) {
            $this->arrayStore->set($key, $value);
        }

        $keys = new ArrayIterator(array_keys($items));

        self::assertTrue($this->arrayStore->deleteMultiple($keys));

        foreach ($keys as $key) {
            self::assertNull($this->arrayStore->get($key));
        }
    }

    public function testDeleteMultipleWithMixedValidAndInvalidKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arrayStore->deleteMultiple(['valid.key', '']);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testDeleteMultipleWithInvalidKeys(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arrayStore->deleteMultiple([$invalidKey]);
    }

    /* -------------------------------------------------
     * HAS
     * -------------------------------------------------
     */

    public function testHas(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        $this->arrayStore->set($key, $value);

        self::assertTrue($this->arrayStore->has($key));

        $this->arrayStore->delete($key);

        self::assertFalse($this->arrayStore->has($key));
    }

    public function testHasWithNullValue(): void
    {
        $key = 'test.key';
        $value = null;

        $this->arrayStore->set($key, $value);

        self::assertTrue($this->arrayStore->has($key));
    }

    public function testHasWithExpiredItem(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        $this->arrayStore->set($key, $value, 1);

        self::assertTrue($this->arrayStore->has($key));

        $this->arrayStore->delete($key);

        self::assertFalse($this->arrayStore->has($key));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testHasThrowsExceptionOnInvalidKey(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->arrayStore->has($invalidKey);
    }
}
