<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\Repository;

use Chubbyphp\Model\ModelInterface;
use Chubbyphp\Model\StorageCache\StorageCacheInterface;
use Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository;
use Chubbyphp\Model\ResolverInterface;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository
 */
final class DoctrineRepositoryTest extends \PHPUnit_Framework_TestCase
{
    use GetConnectionTrait;
    use GetLoggerTrait;
    use GetResolverTrait;
    use GetStorageCacheTrait;

    public function testFindFromStorageCache()
    {
        $modelEntries = [
            [
                'id' => 'id1',
                'name' => 'name3',
                'category' => 'category1',
            ],
            [
                'id' => 'id2',
                'name' => 'name2',
                'category' => 'category2',
            ],
            [
                'id' => 'id3',
                'name' => 'name1',
                'category' => 'category1',
            ],
        ];

        $queryBuilder = $this->getQueryBuilder();
        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache($modelEntries);
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger,
            ModelInterface::class,
            'models'
        );

        $model = $repository->find('id1');

        self::assertInstanceOf(ModelInterface::class, $model);

        self::assertSame('id1', $model->getId());
        self::assertSame('name1', $model->getName());
        self::assertSame('category1', $model->getCategory());

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    public function testFindFound()
    {
        $entry = [
            'id' => 'id1',
            'modelname' => 'modelname',
            'password' => 'password',
            'active' => true,
        ];

        $queryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $entry)]);

        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger,
            ModelInterface::class,
            'models'
        );

        $model = $repository->find('id1');

        self::assertInstanceOf(ModelInterface::class, $model);

        self::assertSame('id1', $model->getId());
        self::assertSame('modelname', $model->getUsername());
        self::assertSame('password', $model->getPassword());
        self::assertTrue($model->isActive());

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
        self::assertSame($entry, $storageCache->__data['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[0]['context']);
    }

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
            $logger,
            ModelInterface::class,
            'models'
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
        self::assertSame(LogLevel::WARNING, $logger->__logs[1]['level']);
        self::assertSame('model: row within table {table} with id {id} not found', $logger->__logs[1]['message']);
        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[1]['context']);
    }

    public function testFindOneByFound()
    {
        $entry = [
            'id' => 'id1',
            'modelname' => 'model1',
            'password' => 'password',
            'active' => true,
        ];

        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, [$entry]),
        ]);

        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger,
            ModelInterface::class,
            'models'
        );

        $model = $repository->findOneBy(['modelname' => 'model1']);

        self::assertInstanceOf(ModelInterface::class, $model);

        self::assertSame('id1', $model->getId());
        self::assertSame('model1', $model->getUsername());
        self::assertSame('password', $model->getPassword());
        self::assertTrue($model->isActive());

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
                'setFirstResult' => [
                    [
                        0,
                    ],
                ],
                'setMaxResults' => [
                    [
                        1,
                    ],
                ],
                'andWhere' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'modelname',
                                ':modelname',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'modelname',
                        'model1',
                        null,
                    ],
                ],
            ],
            $queryBuilder->__calls
        );

        self::assertCount(1, $storageCache->__data);
        self::assertArrayHasKey('id1', $storageCache->__data);
        self::assertSame($entry, $storageCache->__data['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame([
            'table' => 'models',
            'criteria' => ['modelname' => 'model1'],
            'orderBy' => null,
            'limit' => 1,
            'offset' => 0,
        ], $logger->__logs[0]['context']);
    }

//    public function testFindOneByNotFound()
//    {
//        $queryBuilder = $this->getQueryBuilder([
//            $this->getStatement(\PDO::FETCH_ASSOC, false),
//        ]);

//        $resolver = $this->getResolver();
//        $storageCache = $this->getStorageCache();
//        $logger = $this->getLogger();

//        $repository = $this->getDoctrineRepository(
//            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
//            $resolver,
//            $storageCache,
//            $logger,
//            ModelInterface::class,
//            'models'
//        );

//        self::assertNull($repository->findOneBy(['modelname' => 'model1']));

//        self::assertEquals(
//            [
//                'select' => [
//                    [
//                        '*',
//                    ],
//                ],
//                'from' => [
//                    [
//                        'models',
//                        null,
//                    ],
//                ],
//                'andWhere' => [
//                    [
//                        [
//                            'method' => 'eq',
//                            'arguments' => [
//                                'modelname',
//                                ':modelname',
//                            ],
//                        ],
//                    ],
//                ],
//                'setParameter' => [
//                    [
//                        'modelname',
//                        'model1',
//                        null,
//                    ],
//                ],
//                'setMaxResults' => [
//                    [
//                        1,
//                    ],
//                ],
//            ],
//            $queryBuilder->__calls
//        );

//        self::assertCount(0, $storageCache->__data);

//        self::assertCount(2, $logger->__logs);
//        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
//        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
//        self::assertSame(['table' => 'models', 'criteria' => ['modelname' => 'model1']], $logger->__logs[0]['context']);
//        self::assertSame(LogLevel::WARNING, $logger->__logs[1]['level']);
//        self::assertSame('model: model {model} with criteria {criteria} not found', $logger->__logs[1]['message']);
//        self::assertSame(['table' => 'models', 'criteria' => ['modelname' => 'model1']], $logger->__logs[1]['context']);
//    }

//    public function testFindByNotFound()
//    {
//        $queryBuilder = $this->getQueryBuilder([
//            $this->getStatement(\PDO::FETCH_ASSOC, []),
//        ]);

//        $resolver = $this->getResolver();
//        $storageCache = $this->getStorageCache();
//        $logger = $this->getLogger();

//        $repository = $this->getDoctrineRepository(
//            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
//            $resolver,
//            $storageCache,
//            $logger,
//            ModelInterface::class,
//            'models'
//        );

//        self::assertSame([], $repository->findBy(['active' => true]));

//        self::assertEquals(
//            [
//                'select' => [
//                    [
//                        '*',
//                    ],
//                ],
//                'from' => [
//                    [
//                        'models',
//                        null,
//                    ],
//                ],
//                'setFirstResult' => [[null]],
//                'setMaxResults' => [[null]],
//                'andWhere' => [
//                    [
//                        [
//                            'method' => 'eq',
//                            'arguments' => [
//                                'active',
//                                ':active',
//                            ],
//                        ],
//                    ],
//                ],
//                'setParameter' => [
//                    [
//                        'active',
//                        true,
//                        null,
//                    ],
//                ],
//            ],
//            $queryBuilder->__calls
//        );

//        self::assertCount(0, $storageCache->__data);

//        self::assertCount(1, $logger->__logs);
//        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
//        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
//        self::assertSame([
//            'table' => 'models',
//            'criteria' => ['active' => true],
//            'orderBy' => null,
//            'limit' => null,
//            'offset' => null
//        ], $logger->__logs[0]['context']);
//    }

//    public function testFindByFound()
//    {
//        $queryBuilder = $this->getQueryBuilder([
//            $this->getStatement(\PDO::FETCH_ASSOC, [
//                [
//                    'id' => 'id1',
//                    'modelname' => 'model1',
//                    'password' => 'password',
//                    'active' => true,
//                ],
//                [
//                    'id' => 'id2',
//                    'modelname' => 'model2',
//                    'password' => 'password',
//                    'active' => true,
//                ],
//            ]),
//        ]);

//        $resolver = $this->getResolver();
//        $storageCache = $this->getStorageCache();
//        $logger = $this->getLogger();

//        $repository = $this->getDoctrineRepository(
//            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
//            $resolver,
//            $storageCache,
//            $logger,
//            ModelInterface::class,
//            'models'
//        );

//        $models = $repository->findBy(['active' => true]);

//        self::assertCount(2, $models);

//        self::assertInstanceOf(ModelInterface::class, $models[0]);

//        self::assertSame('id1', $models[0]->getId());
//        self::assertSame('model1', $models[0]->getUsername());
//        self::assertSame('password', $models[0]->getPassword());
//        self::assertTrue($models[0]->isActive());

//        self::assertInstanceOf(ModelInterface::class, $models[1]);

//        self::assertSame('id2', $models[1]->getId());
//        self::assertSame('model2', $models[1]->getUsername());
//        self::assertSame('password', $models[1]->getPassword());
//        self::assertTrue($models[1]->isActive());

//        self::assertEquals(
//            [
//                'select' => [
//                    [
//                        '*',
//                    ],
//                ],
//                'from' => [
//                    [
//                        'models',
//                        null,
//                    ],
//                ],
//                'andWhere' => [
//                    [
//                        [
//                            'method' => 'eq',
//                            'arguments' => [
//                                'active',
//                                ':active',
//                            ],
//                        ],
//                    ],
//                ],
//                'setParameter' => [
//                    [
//                        'active',
//                        true,
//                        null,
//                    ],
//                ],
//            ],
//            $queryBuilder->__calls
//        );

//        self::assertCount(0, $storageCache->__data);

//        self::assertCount(1, $logger->__logs);
//        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
//        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
//        self::assertSame(['table' => 'models', 'criteria' => ['active' => true]], $logger->__logs[0]['context']);
//    }

//    public function testPersistWithNewUser()
//    {
//        $resolver = $this->getResolver();
//        $storageCache = $this->getStorageCache();
//        $logger = $this->getLogger();

//        $repository = $this->getDoctrineRepository(
//            $this->getConnection(
//                [
//                    'insert' => [
//                        [
//                            'arguments' => [
//                                'models',
//                                [
//                                    'id' => 'id1',
//                                    'modelname' => 'model1',
//                                    'password' => 'password',
//                                    'active' => true,
//                                ],
//                                [],
//                            ],
//                            'return' => 1,
//                        ],
//                    ],
//                ]
//            ),
//            $resolver,
//            $storageCache,
//            $logger,
//            ModelInterface::class,
//            'models'
//        );

//        $model = new User('id1');
//        $model->setUsername('model1');
//        $model->setPassword('password');
//        $model->setActive(true);

//        $repository->persist($model);

//        self::assertCount(1, $storageCache->__data);
//        self::assertArrayHasKey('id1', $storageCache->__data);
//        self::assertInstanceOf(ModelInterface::class, $storageCache->__data['id1']);

//        self::assertCount(1, $logger->__logs);
//        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
//        self::assertSame('model: insert model {model} with id {id}', $logger->__logs[0]['message']);
//        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[0]['context']);
//    }

//    public function testPersistWithExistingUser()
//    {
//        $resolver = $this->getResolver();
//        $storageCache = $this->getStorageCache();
//        $logger = $this->getLogger();

//        $repository = $this->getDoctrineRepository(
//            $this->getConnection(
//                [
//                    'select' => []
//                ],
//                [
//                    'update' => [
//                        [
//                            'arguments' => [
//                                'models',
//                                [
//                                    'id' => 'id1',
//                                    'modelname' => 'model1',
//                                    'password' => 'password',
//                                    'active' => true,
//                                ],
//                                [
//                                    'id' => 'id1',
//                                ],
//                                [],
//                            ],
//                            'return' => 1,
//                        ],
//                    ],
//                ]
//            ),
//            $resolver,
//            $storageCache,
//            $logger,
//            ModelInterface::class,
//            'models'
//        );

//        $model = new User('id1');
//        $model->setUsername('model1');
//        $model->setPassword('password');
//        $model->setActive(true);

//        $repository->persist($model);

//        self::assertCount(1, $storageCache->__data);
//        self::assertArrayHasKey('id1', $storageCache->__data);
//        self::assertInstanceOf(ModelInterface::class, $storageCache->__data['id1']);

//        self::assertCount(1, $logger->__logs);
//        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
//        self::assertSame('model: update model {model} with id {id}', $logger->__logs[0]['message']);
//        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[0]['context']);
//    }

//    public function testRemove()
//    {
//        $resolver = $this->getResolver();
//        $storageCache = $this->getStorageCache();
//        $logger = $this->getLogger();

//        $repository = $this->getDoctrineRepository(
//            $this->getConnection(
//                [
//                    'delete' => [
//                        [
//                            'arguments' => [
//                                'models',
//                                [
//                                    'id' => 'id1',
//                                ],
//                                [],
//                            ],
//                            'return' => 1,
//                        ],
//                    ],
//                ]
//            ),
//            $resolver,
//            $storageCache,
//            $logger,
//            ModelInterface::class,
//            'models'
//        );

//        $model = new User('id1');
//        $model->setUsername('model1');
//        $model->setPassword('password');
//        $model->setActive(true);

//        $repository->remove($model);

//        self::assertCount(0, $storageCache->__data);

//        self::assertCount(1, $logger->__logs);
//        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
//        self::assertSame('model: remove row from table {table} with id {id}', $logger->__logs[0]['message']);
//        self::assertSame(['table' => 'models', 'id' => 'id1'], $logger->__logs[0]['context']);
//    }

    /**
     * @param Connection            $connection
     * @param ResolverInterface     $resolver
     * @param StorageCacheInterface $storageCache
     * @param LoggerInterface       $logger
     * @param string                $modelClass
     * @param string                $table
     *
     * @return AbstractDoctrineRepository
     */
    private function getDoctrineRepository(
        Connection $connection,
        ResolverInterface $resolver,
        StorageCacheInterface $storageCache,
        LoggerInterface $logger,
        string $modelClass,
        string $table
    ): AbstractDoctrineRepository {
        /** @var AbstractDoctrineRepository|\PHPUnit_Framework_MockObject_MockObject $repository */
        $repository = $this
            ->getMockBuilder(AbstractDoctrineRepository::class)
            ->setConstructorArgs([$connection, $resolver, $storageCache, $logger])
            ->setMethods(['getModelClass', 'getTable'])
            ->getMockForAbstractClass();

        $repository->expects(self::any())->method('fromPersistence')->willReturnCallback(function (array $data) {
            return ModelInterface::fromPersistence($data);
        });

        $repository->expects(self::any())->method('getTable')->willReturn($table);

        return $repository;
    }
}
