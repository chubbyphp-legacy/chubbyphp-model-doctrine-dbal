<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\Repository;

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

        $storageCache->__data = $data;

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
            ->willReturnCallback(function (ModelInterface $model) use ($storageCache) {
                $storageCache->__data[$model->getId()] = $model;

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
