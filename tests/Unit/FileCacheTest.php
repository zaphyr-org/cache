<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit;

use ArrayIterator;
use DateInterval;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;
use Zaphyr\Cache\FileCache;
use Zaphyr\Utils\File;

class FileCacheTest extends TestCase
{
    /**
     * @var FileCache
     */
    protected FileCache $fileCache;

    /**
     * @var string
     */
    protected string $path = __DIR__ . '/cache';

    protected function setUp(): void
    {
        $this->fileCache = new FileCache($this->path);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        unset($this->fileCache);
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

        $this->fileCache->set($key, $value);

        self::assertEquals($value, $this->fileCache->get($key));
    }

    /* -------------------------------------------------
     * GET
     * -------------------------------------------------
     */

    public function testGetDefaultValueIsNull(): void
    {
        self::assertNull($this->fileCache->get('non_existent_key'));
    }

    public function testGetWithDefaultValue(): void
    {
        $key = 'non_existent_key';
        $defaultValue = 'default_value';

        self::assertEquals($defaultValue, $this->fileCache->get($key, $defaultValue));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testGetThrowsExceptionOnInvalidKey(string $illegalKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fileCache->get($illegalKey);
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

        self::assertTrue($this->fileCache->set($key, $value, $expiry));
        self::assertEquals($value, $this->fileCache->get($key));

        $this->fileCache->delete($key);

        self::assertNull($this->fileCache->get($key));
    }

    public function testSetWithTtlInteger(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $expiry = 1;

        self::assertTrue($this->fileCache->set($key, $value, $expiry));
        self::assertEquals($value, $this->fileCache->get($key));

        $this->fileCache->delete($key);

        self::assertNull($this->fileCache->get($key));
    }

    public function testSetWithNullTtlLastsForever(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->fileCache->set($key, $value));

        $contents = $this->getContentsByKey($key);

        self::assertEquals(9999999999, $contents['expiry']);
    }

    public function testSetWithToHighTTlReturnsMaxTtl(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->fileCache->set($key, $value, 999999999999999999));

        $contents = $this->getContentsByKey($key);

        self::assertEquals(9999999999, $contents['expiry']);
    }

    public function testSetWithZeroTtlRemovesValue(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->fileCache->set($key, $value, 0));
        self::assertNull($this->fileCache->get($key));
    }

    public function testSetWithNegativeTtlRemovesValue(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->fileCache->set($key, $value, -1));
        self::assertNull($this->fileCache->get($key));
    }

    public function testSetFilePermissions(): void
    {
        $permissions = 0777;
        $key = 'test.key';
        $value = 'test_value';

        $fileCache = new FileCache($this->path, $permissions);
        $fileCache->set($key, $value);

        $path = $this->getPath($key);

        self::assertEquals($permissions, intval(File::chmod(dirname($path)), 8));
        self::assertEquals($permissions, intval(File::chmod(dirname($path, 2)), 8));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testSetThrowsExceptionOnInvalidKey(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fileCache->set($invalidKey, 'value');
    }

    /* -------------------------------------------------
     * DELETE
     * -------------------------------------------------
     */

    public function testDelete(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $this->fileCache->set($key, $value);

        self::assertEquals($value, $this->fileCache->get($key));
        self::assertTrue($this->fileCache->delete($key));
        self::assertNull($this->fileCache->get($key));
    }

    public function testDeleteReturnsFalseOnFailure(): void
    {
        $key = 'non_existent_key';

        self::assertFalse($this->fileCache->delete($key));
    }

    /* -------------------------------------------------
     * CLEAR
     * -------------------------------------------------
     */

    public function testClear(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $this->fileCache->set($key, $value);

        self::assertEquals($value, $this->fileCache->get($key));
        self::assertTrue($this->fileCache->clear());
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
            $this->fileCache->set($key, $value);
        }

        $keys = array_keys($items);
        $result = $this->fileCache->getMultiple($keys);

        self::assertIsIterable($result);
        self::assertEquals($items, $result);
    }

    public function testGetMultipleWithDefaultValue(): void
    {
        $keys = ['non_existent_key1', 'non_existent_key2'];
        $default = 'default_value';

        $result = $this->fileCache->getMultiple($keys, $default);

        $expected = [
            'non_existent_key1' => $default,
            'non_existent_key2' => $default,
        ];

        self::assertIsIterable($result);
        self::assertEquals($expected, $result);
    }

    public function testGetMultipleWithMixedExistingAndNonExistingKeys(): void
    {
        $this->fileCache->set('existing_key', 'existing_value');

        $keys = ['existing_key', 'non_existent_key'];
        $default = 'default_value';

        $result = $this->fileCache->getMultiple($keys, $default);

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

        $this->fileCache->set('test.key1', 'test_value1');
        $this->fileCache->set('test.key2', 'test_value2');

        $result = $this->fileCache->getMultiple($keys);

        $expected = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertEquals($expected, $result);
    }

    public function testGetMultipleWithValidAndInvalidKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fileCache->getMultiple(['valid_key', '']);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testGetMultipleWithInvalidKeys(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fileCache->getMultiple([$invalidKey]);
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

        self::assertTrue($this->fileCache->setMultiple($values));

        foreach ($values as $key => $value) {
            self::assertEquals($value, $this->fileCache->get($key));
        }
    }

    public function testSetMultipleWithTtl(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        $expiry = time() + $ttl = 1;

        self::assertTrue($this->fileCache->setMultiple($values, $ttl));

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


        self::assertTrue($this->fileCache->setMultiple($values));

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

        self::assertTrue($this->fileCache->setMultiple($values, 999999999999999999));

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

        self::assertTrue($this->fileCache->setMultiple($values, 0));

        foreach ($values as $key => $value) {
            self::assertNull($this->fileCache->get($key));
        }
    }

    public function testSetMultipleWithNegativeTtlRemovesValues(): void
    {
        $values = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        self::assertTrue($this->fileCache->setMultiple($values, -1));

        foreach ($values as $key => $value) {
            self::assertNull($this->fileCache->get($key));
        }
    }

    public function testSetMultipleWithTraversable(): void
    {
        $values = new ArrayIterator([
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ]);

        self::assertTrue($this->fileCache->setMultiple($values));

        foreach ($values as $key => $value) {
            self::assertEquals($value, $this->fileCache->get($key));
        }
    }

    public function testSetMultipleWithMixedValidAndInvalidKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $values = [
            'valid.key' => 'test_value1',
            '' => 'test_value2',
        ];

        $this->fileCache->setMultiple($values);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testSetMultipleWithInvalidKeys(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);

        $values = [
            $invalidKey => 'value',
        ];

        $this->fileCache->setMultiple($values);
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
            $this->fileCache->set($key, $value);
        }

        $keys = array_keys($items);

        self::assertTrue($this->fileCache->deleteMultiple($keys));

        foreach ($keys as $key) {
            self::assertNull($this->fileCache->get($key));
        }
    }

    public function testDeleteMultipleWithNonExistentKeys(): void
    {
        $keys = ['non_existent_key1', 'non_existent_key2'];

        self::assertFalse($this->fileCache->deleteMultiple($keys));
    }

    public function testDeleteMultipleWithMixedExistingAndNonExistingKeys(): void
    {
        $this->fileCache->set('existing_key', 'value');

        $keys = ['non_existent_key', 'existing_key'];

        self::assertFalse($this->fileCache->deleteMultiple($keys));
        self::assertNull($this->fileCache->get('existing_key'));
    }

    public function testDeleteMultipleWithTraversable(): void
    {
        $items = [
            'test.key1' => 'test_value1',
            'test.key2' => 'test_value2',
        ];

        foreach ($items as $key => $value) {
            $this->fileCache->set($key, $value);
        }

        $keys = new ArrayIterator(array_keys($items));

        self::assertTrue($this->fileCache->deleteMultiple($keys));

        foreach ($keys as $key) {
            self::assertNull($this->fileCache->get($key));
        }
    }

    public function testDeleteMultipleWithMixedValidAndInvalidKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fileCache->deleteMultiple(['valid.key', '']);
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testDeleteMultipleWithInvalidKeys(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fileCache->deleteMultiple([$invalidKey]);
    }

    /* -------------------------------------------------
     * HAS
     * -------------------------------------------------
     */

    public function testHas(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        $this->fileCache->set($key, $value);

        self::assertTrue($this->fileCache->has($key));

        $this->fileCache->delete($key);

        self::assertFalse($this->fileCache->has($key));
    }

    public function testHasWithNullValue(): void
    {
        $key = 'test.key';
        $value = null;

        $this->fileCache->set($key, $value);

        self::assertTrue($this->fileCache->has($key));
    }

    public function testHasWithExpiredItem(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        $this->fileCache->set($key, $value, 1);

        self::assertTrue($this->fileCache->has($key));

        $this->fileCache->delete($key);

        self::assertFalse($this->fileCache->has($key));
    }

    #[DataProviderExternal(TestDataProvider::class, 'getIllegalCharactersDataProvider')]
    public function testHasThrowsExceptionOnInvalidKey(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fileCache->has($invalidKey);
    }
}
