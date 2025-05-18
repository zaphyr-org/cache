<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit\Stores;

use ArrayIterator;
use DateInterval;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;
use Zaphyr\Cache\Stores\FileStore;
use Zaphyr\CacheTests\Unit\TestDataProvider;
use Zaphyr\Utils\File;

class FileStoreTest extends TestCase
{
    /**
     * @var FileStore
     */
    protected FileStore $fileStore;

    /**
     * @var string
     */
    protected string $path = __DIR__ . '/cache';

    protected function setUp(): void
    {
        $this->fileStore = new FileStore($this->path);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        unset($this->fileStore);
    }

    protected function getPath(string $key): string
    {
        $hash = sha1($key);
        $parts = array_slice(str_split($hash, 2), 0, 2);

        return $this->path . '/' . implode('/', $parts) . '/' . $hash;
    }

    /**
     * @param string $key
     *
     * @return array<string, mixed>
     */
    protected function getContentsByKey(string $key): array
    {
        return unserialize(file_get_contents($this->getPath($key)));
    }

    /* -------------------------------------------------
     * GET | SET
     * -------------------------------------------------
     */

    public function testGetAndSet(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        $this->fileStore->set($key, $value);

        self::assertEquals($value, $this->fileStore->get($key));
    }

    /* -------------------------------------------------
     * GET
     * -------------------------------------------------
     */

    public function testGetDefaultValueIsNull(): void
    {
        self::assertNull($this->fileStore->get('non_existent_key'));
    }

    public function testGetWithDefaultValue(): void
    {
        $key = 'non_existent_key';
        $defaultValue = 'default_value';

        self::assertEquals($defaultValue, $this->fileStore->get($key, $defaultValue));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testGetThrowsExceptionOnInvalidKey(string $illegalKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fileStore->get($illegalKey);
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

        self::assertTrue($this->fileStore->set($key, $value, $expiry));
        self::assertEquals($value, $this->fileStore->get($key));

        $this->fileStore->delete($key);

        self::assertNull($this->fileStore->get($key));
    }

    public function testSetWithTtlInteger(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $expiry = 1;

        self::assertTrue($this->fileStore->set($key, $value, $expiry));
        self::assertEquals($value, $this->fileStore->get($key));

        $this->fileStore->delete($key);

        self::assertNull($this->fileStore->get($key));
    }

    public function testSetWithNullTtlLastsForever(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->fileStore->set($key, $value));

        $contents = $this->getContentsByKey($key);

        self::assertEquals(9999999999, $contents['expiry']);
    }

    public function testSetWithToHighTTlReturnsMaxTtl(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->fileStore->set($key, $value, 999999999999999999));

        $contents = $this->getContentsByKey($key);

        self::assertEquals(9999999999, $contents['expiry']);
    }

    public function testSetWithZeroTtlRemovesValue(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->fileStore->set($key, $value, 0));
        self::assertNull($this->fileStore->get($key));
    }

    public function testSetWithNegativeTtlRemovesValue(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->fileStore->set($key, $value, -1));
        self::assertNull($this->fileStore->get($key));
    }

    public function testSetFilePermissions(): void
    {
        $permissions = 0777;
        $key = 'test.key';
        $value = 'test_value';

        $fileStore = new FileStore($this->path, $permissions);
        $fileStore->set($key, $value);

        $path = $this->getPath($key);

        self::assertEquals($permissions, intval(File::chmod(dirname($path)), 8));
        self::assertEquals($permissions, intval(File::chmod(dirname($path, 2)), 8));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testSetThrowsExceptionOnInvalidKey(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fileStore->set($invalidKey, 'value');
    }

    /* -------------------------------------------------
     * DELETE
     * -------------------------------------------------
     */

    public function testDelete(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $this->fileStore->set($key, $value);

        self::assertEquals($value, $this->fileStore->get($key));
        self::assertTrue($this->fileStore->delete($key));
        self::assertNull($this->fileStore->get($key));
    }

    public function testDeleteReturnsFalseOnFailure(): void
    {
        $key = 'non_existent_key';

        self::assertFalse($this->fileStore->delete($key));
    }

    /* -------------------------------------------------
     * CLEAR
     * -------------------------------------------------
     */

    public function testClear(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $this->fileStore->set($key, $value);

        self::assertEquals($value, $this->fileStore->get($key));
        self::assertTrue($this->fileStore->clear());
        self::assertDirectoryDoesNotExist(dirname($this->getPath($key), 2));
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
            $this->fileStore->set($key, $value);
        }

        $keys = array_keys($items);
        $result = $this->fileStore->getMultiple($keys);

        self::assertIsIterable($result);
        self::assertEquals($items, $result);
    }

    public function testGetMultipleWithDefaultValue(): void
    {
        $keys = ['non_existent_key1', 'non_existent_key2'];
        $default = 'default_value';

        $result = $this->fileStore->getMultiple($keys, $default);

        $expected = [
            'non_existent_key1' => $default,
            'non_existent_key2' => $default,
        ];

        self::assertIsIterable($result);
        self::assertEquals($expected, $result);
    }

    public function testGetMultipleWithMixedExistingAndNonExistingKeys(): void
    {
        $this->fileStore->set('existing_key', 'existing_value');

        $keys = ['existing_key', 'non_existent_key'];
        $default = 'default_value';

        $result = $this->fileStore->getMultiple($keys, $default);

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

        $this->fileStore->set('test.key1', 'test_value1');
        $this->fileStore->set('test.key2', 'test_value2');

        $result = $this->fileStore->getMultiple($keys);

        $expected = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertEquals($expected, $result);
    }

    public function testGetMultipleWithValidAndInvalidKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fileStore->getMultiple(['valid_key', '']);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testGetMultipleWithInvalidKeys(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fileStore->getMultiple([$invalidKey]);
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

        self::assertTrue($this->fileStore->setMultiple($values));

        foreach ($values as $key => $value) {
            self::assertEquals($value, $this->fileStore->get($key));
        }
    }

    public function testSetMultipleWithTtl(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        $expiry = time() + $ttl = 1;

        self::assertTrue($this->fileStore->setMultiple($values, $ttl));

        foreach ($values as $key => $value) {
            self::assertEquals($expiry, $this->getContentsByKey($key)['expiry']);
        }
    }

    public function testSetMultipleWithNullTtlLastsForever(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];


        self::assertTrue($this->fileStore->setMultiple($values));

        foreach ($values as $key => $value) {
            self::assertEquals(9999999999, $this->getContentsByKey($key)['expiry']);
        }
    }

    public function testSetMultipleWithToHighTTlReturnsMaxTtl(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertTrue($this->fileStore->setMultiple($values, 999999999999999999));

        foreach ($values as $key => $value) {
            self::assertEquals(9999999999, $this->getContentsByKey($key)['expiry']);
        }
    }

    public function testSetMultipleWithZeroTtlRemovesValues(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertTrue($this->fileStore->setMultiple($values, 0));

        foreach ($values as $key => $value) {
            self::assertNull($this->fileStore->get($key));
        }
    }

    public function testSetMultipleWithNegativeTtlRemovesValues(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertTrue($this->fileStore->setMultiple($values, -1));

        foreach ($values as $key => $value) {
            self::assertNull($this->fileStore->get($key));
        }
    }

    public function testSetMultipleWithTraversable(): void
    {
        $values = new ArrayIterator([
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ]);

        self::assertTrue($this->fileStore->setMultiple($values));

        foreach ($values as $key => $value) {
            self::assertEquals($value, $this->fileStore->get($key));
        }
    }

    public function testSetMultipleWithMixedValidAndInvalidKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $values = [
            'valid.key' => 'test_value1',
            '' => 'test_value2',
        ];

        $this->fileStore->setMultiple($values);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testSetMultipleWithInvalidKeys(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);

        $values = [
            $invalidKey => 'value',
        ];

        $this->fileStore->setMultiple($values);
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
            $this->fileStore->set($key, $value);
        }

        $keys = array_keys($items);

        self::assertTrue($this->fileStore->deleteMultiple($keys));

        foreach ($keys as $key) {
            self::assertNull($this->fileStore->get($key));
        }
    }

    public function testDeleteMultipleWithNonExistentKeys(): void
    {
        $keys = ['non_existent_key1', 'non_existent_key2'];

        self::assertFalse($this->fileStore->deleteMultiple($keys));
    }

    public function testDeleteMultipleWithMixedExistingAndNonExistingKeys(): void
    {
        $this->fileStore->set('existing_key', 'value');

        $keys = ['non_existent_key', 'existing_key'];

        self::assertFalse($this->fileStore->deleteMultiple($keys));
        self::assertNull($this->fileStore->get('existing_key'));
    }

    public function testDeleteMultipleWithTraversable(): void
    {
        $items = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        foreach ($items as $key => $value) {
            $this->fileStore->set($key, $value);
        }

        $keys = new ArrayIterator(array_keys($items));

        self::assertTrue($this->fileStore->deleteMultiple($keys));

        foreach ($keys as $key) {
            self::assertNull($this->fileStore->get($key));
        }
    }

    public function testDeleteMultipleWithMixedValidAndInvalidKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fileStore->deleteMultiple(['valid.key', '']);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testDeleteMultipleWithInvalidKeys(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fileStore->deleteMultiple([$invalidKey]);
    }

    /* -------------------------------------------------
     * HAS
     * -------------------------------------------------
     */

    public function testHas(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        $this->fileStore->set($key, $value);

        self::assertTrue($this->fileStore->has($key));

        $this->fileStore->delete($key);

        self::assertFalse($this->fileStore->has($key));
    }

    public function testHasWithNullValue(): void
    {
        $key = 'test.key';
        $value = null;

        $this->fileStore->set($key, $value);

        self::assertTrue($this->fileStore->has($key));
    }

    public function testHasWithExpiredItem(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        $this->fileStore->set($key, $value, 1);

        self::assertTrue($this->fileStore->has($key));

        $this->fileStore->delete($key);

        self::assertFalse($this->fileStore->has($key));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testHasThrowsExceptionOnInvalidKey(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fileStore->has($invalidKey);
    }
}
