<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\Repository;

use Chubbyphp\Model\ModelInterface;
use Chubbyphp\Model\StorageCache\StorageCacheInterface;
use Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository;
use Chubbyphp\Model\ResolverInterface;
use Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits\GetConnectionTrait;
use Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits\GetLoggerTrait;
use Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits\GetResolverTrait;
use Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits\GetStorageCacheTrait;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class DoctrineRepositoryTest extends \PHPUnit_Framework_TestCase
{
    use GetConnectionTrait;
    use GetLoggerTrait;
    use GetResolverTrait;
    use GetStorageCacheTrait;

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::find
     */
    public function testFindFromStorageCache()
    {
        $modelEntry = [
            'id' => 'id1',
            'name' => 'name3',
            'category' => 'category1',
        ];

        $queryBuilder = $this->getQueryBuilder();
        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache([$modelEntry]);
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger
        );

        $model = $repository->find('id1');

        self::assertInstanceOf(ModelInterface::class, $model);

        self::assertSame($modelEntry['id'], $model->getId());
        self::assertSame($modelEntry['name'], $model->getName());
        self::assertSame($modelEntry['category'], $model->getCategory());

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::find
     */
    public function testFindFound()
    {
        $modelEntry = [
            'id' => 'id1',
            'name' => 'name3',
            'category' => 'category1',
        ];

        $queryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $modelEntry)]);

        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger
        );

        $model = $repository->find('id1');

        self::assertInstanceOf(ModelInterface::class, $model);

        self::assertSame($modelEntry['id'], $model->getId());
        self::assertSame($modelEntry['name'], $model->getName());
        self::assertSame($modelEntry['category'], $model->getCategory());

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'models',
                        null,
                    ],
                ],
                'where' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'id',
                                ':id',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'id',
                        'id1',
                        null,
                    ],
                ],
            ],
            $queryBuilder->__calls
        );

        self::assertCount(1, $storageCache->__data);
        self::assertArrayHasKey('id1', $storageCache->__data);
        self::assertEquals($modelEntry, $storageCache->__data['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::find
     */
    public function testFindNotFound()
    {
        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, false),
        ]);

        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger
        );

        self::assertNull($repository->find('id1'));

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'models',
                        null,
                    ],
                ],
                'where' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'id',
                                ':id',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'id',
                        'id1',
                        null,
                    ],
                ],
            ],
            $queryBuilder->__calls
        );

        self::assertCount(0, $storageCache->__data);

        self::assertCount(2, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[0]['context']);
        self::assertSame(LogLevel::NOTICE, $logger->__logs[1]['level']);
        self::assertSame('model: row within table {table} with id {id} not found', $logger->__logs[1]['message']);
        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[1]['context']);
    }

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::findOneBy
     */
    public function testFindOneByFound()
    {
        $modelEntries = [
            [
                'id' => 'id1',
                'name' => 'name3',
                'category' => 'category1',
            ],
        ];

        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, $modelEntries),
        ]);

        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger
        );

        $model = $repository->findOneBy(['category' => 'category1'], ['name' => 'ASC']);

        self::assertInstanceOf(ModelInterface::class, $model);

        self::assertSame($modelEntries[0]['id'], $model->getId());
        self::assertSame($modelEntries[0]['name'], $model->getName());
        self::assertSame($modelEntries[0]['category'], $model->getCategory());

        self::assertEquals(
            array(
                'select' => array(
                        0 => array(
                                0 => '*',
                            ),
                    ),
                'from' => array(
                        0 => array(
                                0 => 'models',
                                1 => null,
                            ),
                    ),
                'setFirstResult' => array(
                        0 => array(
                                0 => 0,
                            ),
                    ),
                'setMaxResults' => array(
                        0 => array(
                                0 => 1,
                            ),
                    ),
                'andWhere' => array(
                        0 => array(
                                0 => array(
                                        'method' => 'eq',
                                        'arguments' => array(
                                                0 => 'category',
                                                1 => ':category',
                                            ),
                                    ),
                            ),
                    ),
                'setParameter' => array(
                        0 => array(
                                0 => 'category',
                                1 => 'category1',
                                2 => null,
                            ),
                    ),
                'addOrderBy' => array(
                        0 => array(
                                0 => 'name',
                                1 => 'ASC',
                            ),
                    ),
            ),
            $queryBuilder->__calls
        );

        self::assertCount(1, $storageCache->__data);
        self::assertArrayHasKey('id1', $storageCache->__data);
        self::assertEquals($modelEntries[0], $storageCache->__data['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame([
            'table' => 'models',
            'criteria' => ['category' => 'category1'],
            'orderBy' => ['name' => 'ASC'],
            'limit' => 1,
            'offset' => 0,
        ], $logger->__logs[0]['context']);
    }

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::findOneBy
     */
    public function testFindOneByNotFound()
    {
        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, []),
        ]);

        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger
        );

        self::assertNull($repository->findOneBy(['category' => 'category1'], ['name' => 'ASC']));

        self::assertEquals(
            array(
                'select' => array(
                        0 => array(
                                0 => '*',
                            ),
                    ),
                'from' => array(
                        0 => array(
                                0 => 'models',
                                1 => null,
                            ),
                    ),
                'setFirstResult' => array(
                        0 => array(
                                0 => 0,
                            ),
                    ),
                'setMaxResults' => array(
                        0 => array(
                                0 => 1,
                            ),
                    ),
                'andWhere' => array(
                        0 => array(
                                0 => array(
                                        'method' => 'eq',
                                        'arguments' => array(
                                                0 => 'category',
                                                1 => ':category',
                                            ),
                                    ),
                            ),
                    ),
                'setParameter' => array(
                        0 => array(
                                0 => 'category',
                                1 => 'category1',
                                2 => null,
                            ),
                    ),
                'addOrderBy' => array(
                        0 => array(
                                0 => 'name',
                                1 => 'ASC',
                            ),
                    ),
            ),
            $queryBuilder->__calls
        );

        self::assertCount(0, $storageCache->__data);

        self::assertCount(2, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame([
            'table' => 'models',
            'criteria' => ['category' => 'category1'],
            'orderBy' => ['name' => 'ASC'],
            'limit' => 1,
            'offset' => 0,
        ], $logger->__logs[0]['context']);
        self::assertSame(LogLevel::NOTICE, $logger->__logs[1]['level']);
        self::assertSame('model: row within table {table} with criteria {criteria} not found', $logger->__logs[1]['message']);
        self::assertSame([
            'table' => 'models',
            'criteria' => ['category' => 'category1'],
            'orderBy' => ['name' => 'ASC'],
            'limit' => 1,
            'offset' => 0,
        ], $logger->__logs[1]['context']);
    }

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::findBy
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::addCriteriaToQueryBuilder
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::addOrderByToQueryBuilder
     */
    public function testFindByFound()
    {
        $modelEntries = [
            [
                'id' => 'id1',
                'name' => 'name3',
                'category' => 'category1',
            ],
        ];

        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, $modelEntries),
        ]);

        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger
        );

        $models = $repository->findBy(['category' => 'category1'], ['name' => 'ASC']);

        /** @var ModelInterface $model */
        $model = reset($models);

        self::assertInstanceOf(ModelInterface::class, $model);

        self::assertSame($modelEntries[0]['id'], $model->getId());
        self::assertSame($modelEntries[0]['name'], $model->getName());
        self::assertSame($modelEntries[0]['category'], $model->getCategory());

        self::assertEquals(
            array(
                'select' => array(
                        0 => array(
                                0 => '*',
                            ),
                    ),
                'from' => array(
                        0 => array(
                                0 => 'models',
                                1 => null,
                            ),
                    ),
                'setFirstResult' => array(
                        0 => array(
                                0 => null,
                            ),
                    ),
                'setMaxResults' => array(
                        0 => array(
                                0 => null,
                            ),
                    ),
                'andWhere' => array(
                        0 => array(
                                0 => array(
                                        'method' => 'eq',
                                        'arguments' => array(
                                                0 => 'category',
                                                1 => ':category',
                                            ),
                                    ),
                            ),
                    ),
                'setParameter' => array(
                        0 => array(
                                0 => 'category',
                                1 => 'category1',
                                2 => null,
                            ),
                    ),
                'addOrderBy' => array(
                        0 => array(
                                0 => 'name',
                                1 => 'ASC',
                            ),
                    ),
            ),
            $queryBuilder->__calls
        );

        self::assertCount(1, $storageCache->__data);
        self::assertArrayHasKey('id1', $storageCache->__data);
        self::assertEquals($modelEntries[0], $storageCache->__data['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame([
            'table' => 'models',
            'criteria' => ['category' => 'category1'],
            'orderBy' => ['name' => 'ASC'],
            'limit' => null,
            'offset' => null,
        ], $logger->__logs[0]['context']);
    }

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::findBy
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::addCriteriaToQueryBuilder
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::addOrderByToQueryBuilder
     */
    public function testFindByNotFound()
    {
        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, []),
        ]);

        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger
        );

        self::assertSame([], $repository->findBy(['category' => 'category1']));

        self::assertEquals(
            array(
                'select' => array(
                        0 => array(
                                0 => '*',
                            ),
                    ),
                'from' => array(
                        0 => array(
                                0 => 'models',
                                1 => null,
                            ),
                    ),
                'setFirstResult' => array(
                        0 => array(
                                0 => null,
                            ),
                    ),
                'setMaxResults' => array(
                        0 => array(
                                0 => null,
                            ),
                    ),
                'andWhere' => array(
                        0 => array(
                                0 => array(
                                        'method' => 'eq',
                                        'arguments' => array(
                                                0 => 'category',
                                                1 => ':category',
                                            ),
                                    ),
                            ),
                    ),
                'setParameter' => array(
                        0 => array(
                                0 => 'category',
                                1 => 'category1',
                                2 => null,
                            ),
                    ),
            ),
            $queryBuilder->__calls
        );

        self::assertCount(0, $storageCache->__data);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame([
            'table' => 'models',
            'criteria' => ['category' => 'category1'],
            'orderBy' => null,
            'limit' => null,
            'offset' => null,
        ], $logger->__logs[0]['context']);
    }

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::persist
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::insert
     */
    public function testPersistWithNewModel()
    {
        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $queryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, false)]);

        $repository = $this->getDoctrineRepository(
            $this->getConnection(
                [
                    'queryBuilder' => [$queryBuilder],
                    'insert' => [
                        [
                            'arguments' => [
                                'models',
                                [
                                    'id' => 'id1',
                                    'name' => 'name1',
                                    'category' => 'category1',
                                ],
                                [],
                            ],
                            'return' => 1,
                        ],
                    ],
                ]
            ),
            $resolver,
            $storageCache,
            $logger
        );

        $model = $this->getModel('id1')->setName('name1')->setCategory('category1');

        $repository->persist($model);

        self::assertCount(1, $storageCache->__data);
        self::assertArrayHasKey('id1', $storageCache->__data);
        self::assertEquals([
            'id' => 'id1',
            'name' => 'name1',
            'category' => 'category1',
        ], $storageCache->__data['id1']);

        self::assertCount(3, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[0]['context']);
        self::assertSame(LogLevel::NOTICE, $logger->__logs[1]['level']);
        self::assertSame('model: row within table {table} with id {id} not found', $logger->__logs[1]['message']);
        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[1]['context']);
        self::assertSame(LogLevel::INFO, $logger->__logs[2]['level']);
        self::assertSame('model: insert row into table {table} with id {id}', $logger->__logs[2]['message']);
        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[2]['context']);
    }

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::persist
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::update
     */
    public function testPersistWithExistingModel()
    {
        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $modelEntry = [
            'id' => 'id1',
            'name' => 'name3',
            'category' => 'category1',
        ];

        $queryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $modelEntry)]);

        $repository = $this->getDoctrineRepository(
            $this->getConnection(
                [
                    'queryBuilder' => [$queryBuilder],
                    'update' => [
                        [
                            'arguments' => [
                                'models',
                                [
                                    'id' => 'id1',
                                    'name' => 'name1',
                                    'category' => 'category1',
                                ],
                                [
                                    'id' => 'id1',
                                ],
                                [],
                            ],
                            'return' => 1,
                        ],
                    ],
                ]
            ),
            $resolver,
            $storageCache,
            $logger
        );

        $model = $this->getModel('id1')->setName('name1')->setCategory('category1');

        $repository->persist($model);

        self::assertCount(1, $storageCache->__data);
        self::assertArrayHasKey('id1', $storageCache->__data);
        self::assertEquals([
            'id' => 'id1',
            'name' => 'name1',
            'category' => 'category1',
        ], $storageCache->__data['id1']);

        self::assertCount(2, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[0]['context']);
        self::assertSame(LogLevel::INFO, $logger->__logs[1]['level']);
        self::assertSame('model: update row into table {table} with id {id}', $logger->__logs[1]['message']);
        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[1]['context']);
    }

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::remove
     */
    public function testRemoveModel()
    {
        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $modelEntry = [
            'id' => 'id1',
            'name' => 'name3',
            'category' => 'category1',
        ];

        $queryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $modelEntry)]);

        $repository = $this->getDoctrineRepository(
            $this->getConnection(
                [
                    'queryBuilder' => [$queryBuilder],
                    'delete' => [
                        [
                            'arguments' => [
                                'models',
                                [
                                    'id' => 'id1',
                                ],
                                [],
                            ],
                            'return' => 1,
                        ],
                    ],
                ]
            ),
            $resolver,
            $storageCache,
            $logger
        );

        $model = $this->getModel('id1')->setName('name1')->setCategory('category1');

        $repository->remove($model);

        self::assertCount(0, $storageCache->__data);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: remove row from table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::clear()
     */
    public function testClear()
    {
        $modelEntry = [
            'id' => 'id1',
            'name' => 'name3',
            'category' => 'category1',
        ];

        $queryBuilder = $this->getQueryBuilder();
        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache([$modelEntry]);
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger
        );

        self::assertCount(1, $storageCache->__data);

        $repository->clear();

        self::assertCount(0, $storageCache->__data);
    }

    /**
     * @param Connection            $connection
     * @param ResolverInterface     $resolver
     * @param StorageCacheInterface $storageCache
     * @param LoggerInterface       $logger
     *
     * @return AbstractDoctrineRepository
     */
    private function getDoctrineRepository(
        Connection $connection,
        ResolverInterface $resolver,
        StorageCacheInterface $storageCache,
        LoggerInterface $logger
    ): AbstractDoctrineRepository {
        /** @var AbstractDoctrineRepository|\PHPUnit_Framework_MockObject_MockObject $repository */
        $repository = $this
            ->getMockBuilder(AbstractDoctrineRepository::class)
            ->setConstructorArgs([$connection, $resolver, $storageCache, $logger])
            ->setMethods(['fromPersistence', 'getTable'])
            ->getMockForAbstractClass();

        $repository->expects(self::any())->method('fromPersistence')->willReturnCallback(function (array $data) {
            return $this->getModel($data['id'])->setName($data['name'])->setCategory($data['category']);
        });

        $repository->expects(self::any())->method('getTable')->willReturn('models');

        return $repository;
    }
}
