<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit\Stores;

use Psr\SimpleCache\CacheInterface;
use Zaphyr\Cache\Stores\FileStore;
use Zaphyr\Utils\File;

class FileStoreTest extends AbstractStoreTestCase
{
    /**
     * @var string
     */
    protected string $path = __DIR__ . '/cache';

    public function createStore(): CacheInterface
    {
        return new FileStore($this->path);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
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
     * SET
     * -------------------------------------------------
     */

    public function testSetWithNullTtlLastsForever(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->store->set($key, $value));

        $contents = $this->getContentsByKey($key);

        self::assertEquals(9999999999, $contents['expiry']);
    }

    public function testSetWithToHighTTlReturnsMaxTtl(): void
    {
        $key = 'test.key';
        $value = 'test_value';

        self::assertTrue($this->store->set($key, $value, 999999999999999999));

        $contents = $this->getContentsByKey($key);

        self::assertEquals(9999999999, $contents['expiry']);
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
        self::assertDirectoryDoesNotExist(dirname($this->getPath($key), 2));
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
            self::assertEquals($expiry, $this->getContentsByKey($key)['expiry']);
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
            self::assertEquals(9999999999, $this->getContentsByKey($key)['expiry']);
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
            self::assertEquals(9999999999, $this->getContentsByKey($key)['expiry']);
        }
    }
}
