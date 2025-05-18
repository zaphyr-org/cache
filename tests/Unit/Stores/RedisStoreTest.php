<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit\Stores;

use ArrayIterator;
use DateInterval;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Predis\ClientInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Zaphyr\Cache\Stores\RedisStore;
use Zaphyr\CacheTests\Unit\TestDataProvider;

class RedisStoreTest extends TestCase
{
    /**
     * @var Client&MockObject
     */
    protected ClientInterface&MockObject $clientMock;

    /**
     * @var RedisStore
     */
    protected RedisStore $redisStore;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(ClientInterface::class);

        $this->redisStore = new RedisStore($this->clientMock);
    }

    protected function tearDown(): void
    {
        unset($this->clientMock, $this->redisStore);
    }

    /* -------------------------------------------------
     * GET | SET
     * -------------------------------------------------
     */

    public function testGetAndSet(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $serializedValue = serialize($value);

        $this->clientMock
            ->expects(self::exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) use ($key, $serializedValue) {
                return match ($command) {
                    'set' => $arguments === [$key, $serializedValue],
                    'get' => $arguments === [$key] ? $serializedValue : null,
                    default => null,
                };
            });

        self::assertTrue($this->redisStore->set($key, $value));
        self::assertEquals($value, $this->redisStore->get($key));
    }

    public function testGetSetWithPrefix(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $prefix = 'prefix_';
        $serializedValue = serialize($value);

        $this->clientMock
            ->expects(self::exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) use ($key, $serializedValue, $prefix) {
                return match ($command) {
                    'set' => $arguments === [$prefix . $key, $serializedValue],
                    'get' => $arguments === [$prefix . $key] ? $serializedValue : null,
                    default => null,
                };
            });

        $redisStore = new RedisStore($this->clientMock, $prefix);

        self::assertTrue($redisStore->set($key, $value));
        self::assertEquals($value, $redisStore->get($key));
    }

    /* -------------------------------------------------
     * GET
     * -------------------------------------------------
     */

    public function testGetDefaultValueIsNull(): void
    {
        $key = 'test_key';
        $defaultValue = null;

        $this->clientMock
            ->expects(self::once())
            ->method('__call')
            ->with('get', [$key])
            ->willReturn(null);

        self::assertNull($this->redisStore->get($key, $defaultValue));
    }

    public function testGetWithDefaultValue(): void
    {
        $key = 'non_existent_key';
        $defaultValue = 'default_value';

        $this->clientMock
            ->expects(self::once())
            ->method('__call')
            ->with('get', [$key])
            ->willReturn(null);

        self::assertEquals($defaultValue, $this->redisStore->get($key, $defaultValue));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testGetThrowsExceptionOnInvalidKey(string $illegalKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->redisStore->get($illegalKey);
    }

    /* -------------------------------------------------
     * SET
     * -------------------------------------------------
     */

    public function testSetWithTtlDateInterval(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $ttl = new DateInterval('PT1H');
        $serializedValue = serialize($value);

        $this->clientMock
            ->expects(self::once())
            ->method('__call')
            ->with('setex', [$key, 3600, $serializedValue])
            ->willReturn(true);

        self::assertTrue($this->redisStore->set($key, $value, $ttl));
    }

    public function testSetWIthTtlInteger(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $ttl = 3600;
        $serializedValue = serialize($value);

        $this->clientMock
            ->expects(self::once())
            ->method('__call')
            ->with('setex', [$key, $ttl, $serializedValue])
            ->willReturn(true);

        self::assertTrue($this->redisStore->set($key, $value, $ttl));
    }

    public function testSetWithZeroTtlRemovesKey(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $ttl = 0;

        $this->clientMock
            ->expects(self::once())
            ->method('__call')
            ->with('del', [$key])
            ->willReturn(true);

        self::assertFalse($this->redisStore->set($key, $value, $ttl));
    }

    public function testSetWithNegativeTtlRemovesKey(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $ttl = -3600;

        $this->clientMock
            ->expects(self::once())
            ->method('__call')
            ->with('del', [$key])
            ->willReturn(true);

        self::assertFalse($this->redisStore->set($key, $value, $ttl));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testSetThrowsExceptionOnInvalidKey(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->redisStore->set($invalidKey, 'value');
    }

    /* -------------------------------------------------
     * DELETE
     * -------------------------------------------------
     */

    public function testDelete(): void
    {
        $key = 'test_key';

        $this->clientMock
            ->expects(self::once())
            ->method('__call')
            ->with('del', [$key])
            ->willReturn(true);

        self::assertTrue($this->redisStore->delete($key));
    }

    public function testDeleteReturnsFalseOnFailure(): void
    {
        $key = 'test_key';

        $this->clientMock
            ->expects(self::once())
            ->method('__call')
            ->with('del', [$key])
            ->willReturn(false);

        self::assertFalse($this->redisStore->delete($key));
    }

    /* -------------------------------------------------
     * CLEAR
     * -------------------------------------------------
     */

    public function testClear(): void
    {
        $this->clientMock
            ->expects(self::once())
            ->method('__call')
            ->with('flushdb')
            ->willReturn(true);

        self::assertTrue($this->redisStore->clear());
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

        $this->clientMock
            ->expects(self::exactly(3))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) use ($items) {
                if ($command === 'get') {
                    $key = $arguments[0];

                    return isset($items[$key]) ? serialize($items[$key]) : null;
                }

                return null;
            });

        $keys = array_keys($items);

        self::assertEquals($items, $this->redisStore->getMultiple($keys));
    }

    public function testGetMultipleWithDefaultValue(): void
    {
        $keys = ['non_existent_key1', 'non_existent_key2'];
        $default = 'default_value';

        $items = [
            'non_existent_key1' => $default,
            'non_existent_key2' => $default,
        ];

        $this->clientMock
            ->expects(self::exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) use ($items) {
                if ($command === 'get') {
                    $key = $arguments[0];

                    return isset($items[$key]) ? serialize($items[$key]) : null;
                }

                return null;
            });

        self::assertEquals($items, $this->redisStore->getMultiple($keys, $default));
    }

    public function testGetMultipleWithMixedExistingAndNonExistingKeys(): void
    {
        $keys = ['existing_key', 'non_existent_key'];
        $default = 'default_value';

        $items = [
            'existing_key' => 'existing_value',
            'non_existent_key' => $default,
        ];

        $this->clientMock
            ->expects(self::exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) use ($items) {
                if ($command === 'get') {
                    $key = $arguments[0];

                    return isset($items[$key]) ? serialize($items[$key]) : null;
                }

                return null;
            });

        self::assertEquals($items, $this->redisStore->getMultiple($keys, $default));
    }

    public function testGetMultipleWithTraversable(): void
    {
        $keys = new ArrayIterator(['test.key1', 'test.key2']);
        $items = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        $this->clientMock
            ->expects(self::exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) use ($items) {
                if ($command === 'get') {
                    $key = $arguments[0];

                    return isset($items[$key]) ? serialize($items[$key]) : null;
                }

                return null;
            });

        self::assertEquals($items, $this->redisStore->getMultiple($keys));
    }

    public function testGetMultipleWithValidAndInvalidKeysThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->redisStore->getMultiple(['valid_key', '']);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testGetMultipleWithInvalidKeysThrowsException(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->redisStore->getMultiple([$invalidKey]);
    }

    /* -------------------------------------------------
     * SET MULTIPLE
     * -------------------------------------------------
     */

    public function testSetMultiple(): void
    {
        $items = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
            'test.key3' => 'test_value3',
        ];

        $this->clientMock
            ->expects(self::exactly(3))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) use ($items) {
                if ($command === 'set') {
                    $key = $arguments[0];
                    $value = unserialize($arguments[1]);

                    return isset($items[$key]) && $value === $items[$key];
                }

                return null;
            });

        self::assertTrue($this->redisStore->setMultiple($items));
    }

    public function testSetMultipleWithTtl(): void
    {
        $items = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];
        $ttl = 3600;

        $this->clientMock
            ->expects(self::exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) use ($items) {
                if ($command === 'setex') {
                    $key = $arguments[0];
                    $value = unserialize($arguments[2]);

                    return isset($items[$key]) && $value === $items[$key];
                }

                return null;
            });

        self::assertTrue($this->redisStore->setMultiple($items, $ttl));
    }

    public function testSetMultipleWithZeroTtlRemovesValues(): void
    {
        $items = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];
        $ttl = 0;

        $this->clientMock
            ->expects(self::exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) use ($items) {
                if ($command === 'del') {
                    $key = $arguments[0];

                    return isset($items[$key]);
                }

                return null;
            });

        self::assertFalse($this->redisStore->setMultiple($items, $ttl));
    }

    public function testSetMultipleWithNegativeTtlRemovesValues(): void
    {
        $items = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];
        $ttl = -3600;

        $this->clientMock
            ->expects(self::exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) use ($items) {
                if ($command === 'del') {
                    $key = $arguments[0];

                    return isset($items[$key]);
                }

                return null;
            });

        self::assertFalse($this->redisStore->setMultiple($items, $ttl));
    }

    public function testSetMultipleWithTraversable(): void
    {
        $items = new ArrayIterator([
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ]);

        $this->clientMock
            ->expects(self::exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) use ($items) {
                if ($command === 'set') {
                    $key = $arguments[0];
                    $value = unserialize($arguments[1]);

                    return isset($items[$key]) && $value === $items[$key];
                }

                return null;
            });

        self::assertTrue($this->redisStore->setMultiple($items));
    }

    public function testSetMultipleWithMixedValidAndInvalidKeysThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->redisStore->setMultiple(['valid_key' => 'value', '' => 'value']);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testSetMultipleWithInvalidKeysThrowsException(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);

        $values = [
            $invalidKey => 'value',
        ];

        $this->redisStore->setMultiple($values);
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

        $this->clientMock
            ->expects(self::exactly(3))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) use ($items) {
                if ($command === 'del') {
                    $key = $arguments[0];

                    return isset($items[$key]);
                }

                return null;
            });

        $keys = array_keys($items);

        self::assertTrue($this->redisStore->deleteMultiple($keys));
    }

    public function testDeleteMultipleWithNonExistentKeys(): void
    {
        $keys = ['non_existent_key1', 'non_existent_key2'];

        $this->clientMock
            ->expects(self::exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($command) {
                if ($command === 'del') {
                    return false;
                }

                return null;
            });

        self::assertFalse($this->redisStore->deleteMultiple($keys));
    }

    public function testDeleteMultipleWithMixedExistingAndNonExistingKeys(): void
    {
        $keys = ['existing_key', 'non_existent_key'];

        $this->clientMock
            ->expects(self::exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) {
                if ($command === 'del') {
                    $key = $arguments[0];

                    return $key === 'existing_key';
                }

                return null;
            });

        self::assertFalse($this->redisStore->deleteMultiple($keys));
    }

    public function testDeleteMultipleWithTraversable(): void
    {
        $keys = new ArrayIterator(['test.key1', 'test.key2']);

        $this->clientMock
            ->expects(self::exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) {
                $items = [
                    'test.key1' => 'test_value1',
                    'test.key2' => 'test_value2',
                ];
                if ($command === 'del') {
                    $key = $arguments[0];

                    return isset($items[$key]);
                }

                return null;
            });

        self::assertTrue($this->redisStore->deleteMultiple($keys));
    }

    public function testDeleteMultipleWithValidAndInvalidKeysThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->redisStore->deleteMultiple(['valid_key', '']);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testDeleteMultipleWithInvalidKeysThrowsException(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->redisStore->deleteMultiple([$invalidKey]);
    }

    /* -------------------------------------------------
     * HAS
     * -------------------------------------------------
     */

    public function testHas(): void
    {
        $key = 'test_key';

        $this->clientMock
            ->method('__call')
            ->with('exists', [$key])
            ->willReturn(true);

        self::assertTrue($this->redisStore->has($key));
    }

    public function testHasReturnsFalseOnNonExistentKey(): void
    {
        $key = 'non_existent_key';

        $this->clientMock
            ->method('__call')
            ->with('exists', [$key])
            ->willReturn(false);

        self::assertFalse($this->redisStore->has($key));
    }

    public function testHasWithNullValue(): void
    {
        $key = 'test_key';
        $value = null;
        $serializedValue = serialize($value);

        $this->clientMock
            ->expects(self::exactly(2))
            ->method('__call')
            ->willReturnCallback(function ($command, $arguments) use ($key, $serializedValue) {
                return match ($command) {
                    'set' => $arguments === [$key, $serializedValue],
                    'exists' => $arguments === [$key],
                    default => null,
                };
            });

        $this->redisStore->set($key, $value);

        self::assertTrue($this->redisStore->has($key));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testHasThrowsExceptionOnInvalidKey(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->redisStore->has($invalidKey);
    }
}
