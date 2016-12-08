<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits;

use Chubbyphp\Model\StorageCache\StorageCacheInterface;

trait GetStorageCacheTrait
{
    /**
     * @param array $data
     *
     * @return StorageCacheInterface
     */
    private function getStorageCache(array $data = []): StorageCacheInterface
    {
        /** @var StorageCacheInterface|\PHPUnit_Framework_MockObject_MockObject $storageCache */
        $storageCache = $this
            ->getMockBuilder(StorageCacheInterface::class)
            ->setMethods(['has', 'get', 'set', 'remove'])
            ->getMockForAbstractClass()
        ;

        $storageCache->__data = [];
        foreach ($data as $element) {
            $storageCache->__data[$element['id']] = $element;
        }

        $storageCache
            ->expects(self::any())
            ->method('has')
            ->willReturnCallback(function (string $id) use ($storageCache) {
                return array_key_exists($id, $storageCache->__data);
            })
        ;

        $storageCache
            ->expects(self::any())
            ->method('get')
            ->willReturnCallback(function (string $id) use ($storageCache) {
                return $storageCache->__data[$id];
            })
        ;

        $storageCache
            ->expects(self::any())
            ->method('set')
            ->willReturnCallback(function (string $id, array $entry) use ($storageCache) {
                $storageCache->__data[$id] = $entry;

                return $storageCache;
            })
        ;

        $storageCache
            ->expects(self::any())
            ->method('remove')
            ->willReturnCallback(function (string $id) use ($storageCache) {
                unset($storageCache->__data[$id]);

                return $storageCache;
            })
        ;

        $storageCache
            ->expects(self::any())
            ->method('clear')
            ->willReturnCallback(function () use ($storageCache) {
                $storageCache->__data = [];

                return $storageCache;
            })
        ;

        return $storageCache;
    }
}
