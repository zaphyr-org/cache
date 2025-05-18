<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit\Stores;

use ArrayIterator;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use stdClass;
use Zaphyr\CacheTests\Unit\TestDataProvider;

abstract class AbstractStoreTestCase extends TestCase
{
    /**
     * @var CacheInterface
     */
    protected CacheInterface $store;

    /**
     * @return CacheInterface
     */
    abstract public function createStore(): CacheInterface;

    #[Before]
    public function setupService(): void
    {
        $this->store = $this->createStore();
    }

    protected function tearDown(): void
    {
        unset($this->store);
    }

    /* -------------------------------------------------
     * GET | SET
     * -------------------------------------------------
     */

    public function testGetAndSet(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        $this->store->set($key, $value);

        self::assertEquals($value, $this->store->get($key));
    }

    /* -------------------------------------------------
     * GET
     * -------------------------------------------------
     */

    public function testGetDefaultValueIsNull(): void
    {
        self::assertNull($this->store->get('non_existent_key'));
    }

    public function testGetWithDefaultValue(): void
    {
        $key = 'non_existent_key';
        $defaultValue = 'default_value';

        self::assertEquals($defaultValue, $this->store->get($key, $defaultValue));
    }

    public function testGetWithObjectAsDefaultValue(): void
    {
        $key = 'non_existent_key';
        $defaultValue = new stdClass();
        $defaultValue->foo = 'bar';

        self::assertEquals($defaultValue, $this->store->get($key, $defaultValue));
    }

    public function testGetWithObjectDoesNotChangeInCache(): void
    {
        $key = 'test.key';

        $object = new stdClass();
        $object->foo = 'original';

        $this->store->set($key, $object);

        $object->foo = 'changed';
        $cachedObject = $this->store->get($key);

        $this->assertEquals('original', $cachedObject->foo);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testGetOnInvalidKeyThrowsException(string $illegalKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->store->get($illegalKey);
    }

    /* -------------------------------------------------
     * SET
     * -------------------------------------------------
     */

    public function testSetWithTtlDateInterval(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $expiry = new \DateInterval('PT1S');

        self::assertTrue($this->store->set($key, $value, $expiry));
        self::assertEquals($value, $this->store->get($key));

        $this->store->delete($key);

        self::assertNull($this->store->get($key));
    }

    public function testSetWithTtlInteger(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $expiry = 1;

        self::assertTrue($this->store->set($key, $value, $expiry));
        self::assertEquals($value, $this->store->get($key));

        $this->store->delete($key);

        self::assertNull($this->store->get($key));
    }

    public function testSetWithZeroTtlRemovesValue(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->store->set($key, $value, 0));
        self::assertNull($this->store->get($key));
    }

    public function testSetWithNegativeTtlRemovesValue(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->store->set($key, $value, -1));
        self::assertNull($this->store->get($key));
    }

    public function testSetWithNullOverwritesPreviousValue(): void
    {
        $this->store->set('test.key', 'test_value');
        $this->store->set('test.key', null);

        self::assertNull($this->store->get('test.key'));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getValidKeysDataProvider')]
    public function testSetWithValidKey(string $validKey): void
    {
        $this->store->set($validKey, 'test_value');

        self::assertEquals('test_value', $this->store->get($validKey));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getValidValuesDataProvider')]
    public function testSetWithValidValue(mixed $validValue): void
    {
        $this->store->set('test.key', $validValue);

        self::assertEquals($validValue, $this->store->get('test.key'));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testSetOnInvalidKeyThrowsException(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->store->set($invalidKey, 'value');
    }

    /* -------------------------------------------------
     * DELETE
     * -------------------------------------------------
     */

    public function testDelete(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $this->store->set($key, $value);

        self::assertEquals($value, $this->store->get($key));
        self::assertTrue($this->store->delete($key));
        self::assertNull($this->store->get($key));
    }

    public function testDeleteReturnsFalseOnFailure(): void
    {
        $key = 'non_existent_key';

        self::assertFalse($this->store->delete($key));
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
            $this->store->set($key, $value);
        }

        $keys = array_keys($items);
        $result = $this->store->getMultiple($keys);

        self::assertEquals($items, $result);
    }

    public function testGetMultipleWithDefaultValue(): void
    {
        $keys = ['non_existent_key1', 'non_existent_key2'];
        $default = 'default_value';

        $result = $this->store->getMultiple($keys, $default);

        $expected = [
            'non_existent_key1' => $default,
            'non_existent_key2' => $default,
        ];

        self::assertEquals($expected, $result);
    }

    public function testGetMultipleWithMixedExistingAndNonExistingKeys(): void
    {
        $this->store->set('existing_key', 'existing_value');

        $keys = ['existing_key', 'non_existent_key'];
        $default = 'default_value';

        $result = $this->store->getMultiple($keys, $default);

        $expected = [
            'existing_key' => 'existing_value',
            'non_existent_key' => $default,
        ];

        self::assertEquals($expected, $result);
    }

    public function testGetMultipleWithTraversable(): void
    {
        $keys = new ArrayIterator(['test.key1', 'test.key2']);

        $this->store->set('test.key1', 'test_value1');
        $this->store->set('test.key2', 'test_value2');

        $result = $this->store->getMultiple($keys);

        $expected = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertEquals($expected, $result);
    }

    public function testGetMultipleWithObjectAsDefaultValue(): void
    {
        $keys = ['non_existent_key'];
        $default = new stdClass();
        $default->foo = 'bar';

        self::assertEquals(
            ['non_existent_key' => $default],
            $this->store->getMultiple($keys, $default)
        );
    }

    public function testGetMultipleWithObjectDoesNotChangeInCache(): void
    {
        $key = 'test.key';

        $object = new stdClass();
        $object->foo = 'original';

        $this->store->set($key, $object);

        $object->foo = 'changed';
        $cachedObjects = $this->store->getMultiple([$key]);

        $this->assertEquals('original', $cachedObjects[$key]->foo);
    }

    public function testGetMultipleWithGenerator(): void
    {
        $generator = static function () {
            yield 0 => 'test.key0';
            yield 1 => 'test.key1';
        };

        $this->store->set('test.key0', 'test_value0');
        $result = $this->store->getMultiple($generator());

        $keys = [];
        foreach ($result as $key => $value) {
            $keys[] = $key;

            if ($key === 'test.key0') {
                self::assertEquals('test_value0', $value);
            } elseif ($key === 'test.key1') {
                self::assertNull($value);
            }
        }

        sort($keys);

        self::assertSame(['test.key0', 'test.key1'], $keys);
        self::assertEquals('test_value0', $this->store->get('test.key0'));
        self::assertNull($this->store->get('test.key1'));
    }

    public function testGetMultipleWithValidAndInvalidKeysThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->store->getMultiple(['valid_key', '']);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testGetMultipleWithInvalidKeysThrowsException(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->store->getMultiple([$invalidKey]);
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

        self::assertTrue($this->store->setMultiple($values));

        foreach ($values as $key => $value) {
            self::assertEquals($value, $this->store->get($key));
        }
    }

    public function testSetMultipleWithZeroTtlRemovesValues(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertTrue($this->store->setMultiple($values, 0));

        foreach ($values as $key => $value) {
            self::assertNull($this->store->get($key));
        }
    }

    public function testSetMultipleWithNegativeTtlRemovesValues(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertTrue($this->store->setMultiple($values, -1));

        foreach ($values as $key => $value) {
            self::assertNull($this->store->get($key));
        }
    }

    public function testSetMultipleWithTraversable(): void
    {
        $values = new ArrayIterator([
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ]);

        self::assertTrue($this->store->setMultiple($values));

        foreach ($values as $key => $value) {
            self::assertEquals($value, $this->store->get($key));
        }
    }

    public function testSetMultipleWithGenerator(): void
    {
        $values = static function () {
            yield 'test.key1' => 'test_value1';
            yield 'test.key2' => 'test_value2';
        };

        self::assertTrue($this->store->setMultiple($values()));

        foreach ($values() as $key => $value) {
            self::assertEquals($value, $this->store->get($key));
        }
    }

    #[DataProviderExternal(TestDataProvider::class, 'getValidKeysDataProvider')]
    public function testSetMultipleWithValidKey(string $validKey): void
    {
        $this->store->setMultiple([$validKey => 'test_value']);

        $results = $this->store->getMultiple([$validKey]);

        $keys = [];
        foreach ($results as $index => $result) {
            $keys[] = $index;

            self::assertEquals($validKey, $index);
            self::assertEquals('test_value', $result);
        }

        self::assertSame([$validKey], $keys);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getValidValuesDataProvider')]
    public function testSetMultipleWithValidValue(mixed $validValue): void
    {
        $this->store->setMultiple(['test.key' => $validValue]);

        $results = $this->store->getMultiple(['test.key']);

        $keys = [];
        foreach ($results as $index => $result) {
            $keys[] = $index;

            self::assertEquals($validValue, $result);
        }

        self::assertSame(['test.key'], $keys);
    }

    public function testSetMultipleWithMixedValidAndInvalidKeysThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $values = [
            'valid.key' => 'test_value1',
            '' => 'test_value2',
        ];

        $this->store->setMultiple($values);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testSetMultipleWithInvalidKeysThrowsException(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);

        $values = [
            $invalidKey => 'value',
        ];

        $this->store->setMultiple($values);
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
            $this->store->set($key, $value);
        }

        $keys = array_keys($items);

        self::assertTrue($this->store->deleteMultiple($keys));

        foreach ($keys as $key) {
            self::assertNull($this->store->get($key));
        }
    }

    public function testDeleteMultipleWithNonExistentKeys(): void
    {
        $keys = ['non_existent_key1', 'non_existent_key2'];

        self::assertFalse($this->store->deleteMultiple($keys));
    }

    public function testDeleteMultipleWithMixedExistingAndNonExistingKeys(): void
    {
        $this->store->set('existing_key', 'value');

        $keys = ['non_existent_key', 'existing_key'];

        self::assertFalse($this->store->deleteMultiple($keys));
        self::assertNull($this->store->get('existing_key'));
    }

    public function testDeleteMultipleWithTraversable(): void
    {
        $items = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        foreach ($items as $key => $value) {
            $this->store->set($key, $value);
        }

        $keys = new ArrayIterator(array_keys($items));

        self::assertTrue($this->store->deleteMultiple($keys));

        foreach ($keys as $key) {
            self::assertNull($this->store->get($key));
        }
    }

    public function testDeleteMultipleWithGenerator(): void
    {
        $generator = static function () {
            yield 0 => 'test.key0';
            yield 1 => 'test.key1';
        };

        $this->store->setMultiple([
            'test.key0' => 'test_value0',
            'test.key1' => 'test_value1',
        ]);

        self::assertTrue($this->store->deleteMultiple($generator()));

        foreach ($generator() as $key) {
            self::assertNull($this->store->get($key));
        }
    }

    public function testDeleteMultipleWithMixedValidAndInvalidKeysThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->store->deleteMultiple(['valid.key', '']);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testDeleteMultipleWithInvalidKeysThrowsException(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->store->deleteMultiple([$invalidKey]);
    }

    /* -------------------------------------------------
     * HAS
     * -------------------------------------------------
     */

    public function testHas(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        $this->store->set($key, $value);

        self::assertTrue($this->store->has($key));

        $this->store->delete($key);

        self::assertFalse($this->store->has($key));
    }

    public function testHasWithNullValue(): void
    {
        $key = 'test.key';
        $value = null;

        $this->store->set($key, $value);

        self::assertTrue($this->store->has($key));
    }

    public function testHasWithExpiredItem(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        $this->store->set($key, $value, 1);

        self::assertTrue($this->store->has($key));

        $this->store->delete($key);

        self::assertFalse($this->store->has($key));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testHasOnInvalidKeyThrowsException(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->store->has($invalidKey);
    }
}
