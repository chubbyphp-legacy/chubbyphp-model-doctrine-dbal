<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\Repository;

use Chubbyphp\Model\ModelInterface;
use Chubbyphp\Model\Resolver;
use Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits\GetConnectionTrait;
use Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits\GetLoggerTrait;
use Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits\GetStorageCacheTrait;
use Interop\Container\ContainerInterface;
use MyProject\Model\MyEmbeddedModel;
use MyProject\Model\MyModel;
use MyProject\Repository\MyEmbeddedRepository;
use MyProject\Repository\MyModelRepository;
use Pimple\Container;
use Psr\Log\LogLevel;

final class DoctrineRepositoryTest extends \PHPUnit_Framework_TestCase
{
    use GetConnectionTrait;
    use GetLoggerTrait;
    use GetStorageCacheTrait;

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::find
     */
    public function testFindWithNullId()
    {
        $connection = $this->getConnection();

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $logger = $this->getLogger();

        $container = $this->getContainer(
            function (Resolver $resolver) use ($connection, $storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($connection, $storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyEmbeddedModel,
                    $logger
                );
            }
        );

        /** @var MyModelRepository $repository */
        $repository = $container[MyModelRepository::class];

        self::assertNull($repository->find(null));
    }

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
            'oneToOneId' => null
        ];

        $connection = $this->getConnection();

        $storageCacheMyModel = $this->getStorageCache([$modelEntry]);
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $logger = $this->getLogger();

        $container = $this->getContainer(
            function (Resolver $resolver) use ($connection, $storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($connection, $storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyEmbeddedModel,
                    $logger
                );
            }
        );

        /** @var MyModelRepository $repository */
        $repository = $container[MyModelRepository::class];

        /** @var MyModel $model */
        $model = $repository->find('id1');

        self::assertInstanceOf(MyModel::class, $model);

        self::assertSame($modelEntry['id'], $model->getId());
        self::assertSame($modelEntry['name'], $model->getName());
        self::assertSame($modelEntry['category'], $model->getCategory());

        self::assertCount(2, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[0]['context']);
        self::assertSame(LogLevel::INFO, $logger->__logs[1]['level']);
        self::assertSame('model: found row within cache for table {table} with id {id}', $logger->__logs[1]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[1]['context']);
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
            'oneToOneId' => null
        ];

        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $modelEntry)]);

        $connection = $this->getConnection(['queryBuilder' => [$myModelQueryBuilder]]);

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $logger = $this->getLogger();

        $container = $this->getContainer(
            function (Resolver $resolver) use ($connection, $storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($connection, $storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyEmbeddedModel,
                    $logger
                );
            }
        );

        /** @var MyModelRepository $repository */
        $repository = $container[MyModelRepository::class];

        /** @var MyModel $model */
        $model = $repository->find('id1');

        self::assertInstanceOf(MyModel::class, $model);

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
                        'mymodels',
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
            $myModelQueryBuilder->__calls
        );

        self::assertCount(1, $storageCacheMyModel->__data);
        self::assertArrayHasKey('id1', $storageCacheMyModel->__data);
        self::assertEquals($modelEntry, $storageCacheMyModel->__data['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::find
     */
    public function testFindNotFound()
    {
        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, false)]);

        $connection = $this->getConnection(['queryBuilder' => [$myModelQueryBuilder]]);

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $logger = $this->getLogger();

        $container = $this->getContainer(
            function (Resolver $resolver) use ($connection, $storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($connection, $storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyEmbeddedModel,
                    $logger
                );
            }
        );

        /** @var MyModelRepository $repository */
        $repository = $container[MyModelRepository::class];

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
                        'mymodels',
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
            $myModelQueryBuilder->__calls
        );

        self::assertCount(0, $storageCacheMyModel->__data);

        self::assertCount(2, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[0]['context']);
        self::assertSame(LogLevel::NOTICE, $logger->__logs[1]['level']);
        self::assertSame('model: row within table {table} with id {id} not found', $logger->__logs[1]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[1]['context']);
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
                'oneToOneId' => null
            ],
        ];

        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $modelEntries)]);

        $connection = $this->getConnection(['queryBuilder' => [$myModelQueryBuilder]]);

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $logger = $this->getLogger();

        $container = $this->getContainer(
            function (Resolver $resolver) use ($connection, $storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($connection, $storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyEmbeddedModel,
                    $logger
                );
            }
        );

        /** @var MyModelRepository $repository */
        $repository = $container[MyModelRepository::class];

        /** @var MyModel $model */
        $model = $repository->findOneBy(['category' => 'category1'], ['name' => 'ASC']);

        self::assertInstanceOf(ModelInterface::class, $model);

        self::assertSame($modelEntries[0]['id'], $model->getId());
        self::assertSame($modelEntries[0]['name'], $model->getName());
        self::assertSame($modelEntries[0]['category'], $model->getCategory());

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'mymodels',
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
                                'category',
                                ':category',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'category',
                        'category1',
                        null,
                    ],
                ],
                'addOrderBy' => [
                    [
                        'name',
                        'ASC',
                    ],
                ],
            ],
            $myModelQueryBuilder->__calls
        );

        self::assertCount(1, $storageCacheMyModel->__data);
        self::assertArrayHasKey('id1', $storageCacheMyModel->__data);
        self::assertEquals($modelEntries[0], $storageCacheMyModel->__data['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame([
            'table' => 'mymodels',
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
        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, [])]);

        $connection = $this->getConnection(['queryBuilder' => [$myModelQueryBuilder]]);

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $logger = $this->getLogger();

        $container = $this->getContainer(
            function (Resolver $resolver) use ($connection, $storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($connection, $storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyEmbeddedModel,
                    $logger
                );
            }
        );

        /** @var MyModelRepository $repository */
        $repository = $container[MyModelRepository::class];

        self::assertNull($repository->findOneBy(['category' => 'category1'], ['name' => 'ASC']));

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'mymodels',
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
                                'category',
                                ':category',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'category',
                        'category1',
                        null,
                    ],
                ],
                'addOrderBy' => [
                    [
                        'name',
                        'ASC',
                    ],
                ],
            ],
            $myModelQueryBuilder->__calls
        );

        self::assertCount(0, $storageCacheMyModel->__data);

        self::assertCount(2, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame([
            'table' => 'mymodels',
            'criteria' => ['category' => 'category1'],
            'orderBy' => ['name' => 'ASC'],
            'limit' => 1,
            'offset' => 0,
        ], $logger->__logs[0]['context']);
        self::assertSame(LogLevel::NOTICE, $logger->__logs[1]['level']);
        self::assertSame('model: row within table {table} with criteria {criteria} not found', $logger->__logs[1]['message']);
        self::assertSame([
            'table' => 'mymodels',
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
                'oneToOneId' => null
            ],
        ];

        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $modelEntries)]);

        $connection = $this->getConnection(['queryBuilder' => [$myModelQueryBuilder]]);

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $logger = $this->getLogger();

        $container = $this->getContainer(
            function (Resolver $resolver) use ($connection, $storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($connection, $storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyEmbeddedModel,
                    $logger
                );
            }
        );

        /** @var MyModelRepository $repository */
        $repository = $container[MyModelRepository::class];

        $models = $repository->findBy(['category' => 'category1'], ['name' => 'ASC']);

        /** @var MyModel $model */
        $model = reset($models);

        self::assertInstanceOf(ModelInterface::class, $model);

        self::assertSame($modelEntries[0]['id'], $model->getId());
        self::assertSame($modelEntries[0]['name'], $model->getName());
        self::assertSame($modelEntries[0]['category'], $model->getCategory());

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'mymodels',
                        null,
                    ],
                ],
                'setFirstResult' => [
                    [
                        null,
                    ],
                ],
                'setMaxResults' => [
                    [
                        null,
                    ],
                ],
                'andWhere' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'category',
                                ':category',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'category',
                        'category1',
                        null,
                    ],
                ],
                'addOrderBy' => [
                    [
                        'name',
                        'ASC',
                    ],
                ],
            ],
            $myModelQueryBuilder->__calls
        );

        self::assertCount(1, $storageCacheMyModel->__data);
        self::assertArrayHasKey('id1', $storageCacheMyModel->__data);
        self::assertEquals($modelEntries[0], $storageCacheMyModel->__data['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame([
            'table' => 'mymodels',
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
        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, [])]);

        $connection = $this->getConnection(['queryBuilder' => [$myModelQueryBuilder]]);

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $logger = $this->getLogger();

        $container = $this->getContainer(
            function (Resolver $resolver) use ($connection, $storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($connection, $storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyEmbeddedModel,
                    $logger
                );
            }
        );

        /** @var MyModelRepository $repository */
        $repository = $container[MyModelRepository::class];

        self::assertSame([], $repository->findBy(['category' => 'category1']));

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'mymodels',
                        null,
                    ],
                ],
                'setFirstResult' => [
                    [
                        null,
                    ],
                ],
                'setMaxResults' => [
                    [
                        null,
                    ],
                ],
                'andWhere' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'category',
                                ':category',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'category',
                        'category1',
                        null,
                    ],
                ],
            ],
            $myModelQueryBuilder->__calls
        );

        self::assertCount(0, $storageCacheMyModel->__data);
        self::assertCount(0, $storageCacheMyEmbeddedModel->__data);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame([
            'table' => 'mymodels',
            'criteria' => ['category' => 'category1'],
            'orderBy' => null,
            'limit' => null,
            'offset' => null,
        ], $logger->__logs[0]['context']);
    }

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::persist
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::persistReference
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::insert
     */
    public function testPersistWithNewModel()
    {
        $logger = $this->getLogger();

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $myEmbeddedModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, false)]);
        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, false)]);

        $connection = $this->getConnection([
            'queryBuilder' => [$myEmbeddedModelQueryBuilder, $myModelQueryBuilder],
            'beginTransaction' => 2,
            'commit' => 2,
            'insert' => [
                [
                    'arguments' => [
                        'tableExpression' => 'myembeddedmodels',
                        'data' => [
                            'id' => 'id1',
                            'modelId' => 'id1',
                            'name' => 'name1'
                        ],
                        'types' => [],
                    ],
                    'return' => 1,
                ],
                [
                    'arguments' => [
                        'tableExpression' => 'mymodels',
                        'data' => [
                            'id' => 'id1',
                            'name' => 'name1',
                            'category' => 'category1',
                            'oneToOneId' => 'id1'
                        ],
                        'types' => [],
                    ],
                    'return' => 1,
                ],
            ],
        ]);

        $container = $this->getContainer(
            function (Resolver $resolver) use ($connection, $storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($connection, $storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyEmbeddedModel,
                    $logger
                );
            }
        );

        /** @var MyModelRepository $repository */
        $repository = $container[MyModelRepository::class];

        $model = MyModel::create('id1')->setName('name1')->setCategory('category1')->setOneToOne(
            MyEmbeddedModel::create('id1', 'id1')->setName('name1')
        );

        $repository->persist($model);

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'myembeddedmodels',
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
            $myEmbeddedModelQueryBuilder->__calls
        );

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'mymodels',
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
            $myModelQueryBuilder->__calls
        );

        self::assertCount(1, $storageCacheMyModel->__data);
        self::assertArrayHasKey('id1', $storageCacheMyModel->__data);
        self::assertEquals([
            'id' => 'id1',
            'name' => 'name1',
            'category' => 'category1',
            'oneToOneId' => 'id1',
        ], $storageCacheMyModel->__data['id1']);

        self::assertCount(6, $logger->__logs);

        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id1'], $logger->__logs[0]['context']);

        self::assertSame(LogLevel::NOTICE, $logger->__logs[1]['level']);
        self::assertSame('model: row within table {table} with id {id} not found', $logger->__logs[1]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id1'], $logger->__logs[1]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[2]['level']);
        self::assertSame('model: insert row into table {table} with id {id}', $logger->__logs[2]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id1'], $logger->__logs[2]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[3]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[3]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[3]['context']);

        self::assertSame(LogLevel::NOTICE, $logger->__logs[4]['level']);
        self::assertSame('model: row within table {table} with id {id} not found', $logger->__logs[4]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[4]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[5]['level']);
        self::assertSame('model: insert row into table {table} with id {id}', $logger->__logs[5]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[5]['context']);
    }

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::persist
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::persistReference
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::persistCollection
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::persistRelatedModel
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::removeRelatedModel
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::update
     */
    public function testPersistWithExistingModel()
    {
        $modelEntry = [
            'id' => 'id1',
            'name' => 'name3',
            'category' => 'category1',
            'oneToOneId' => 'id3'
        ];

        $embeddedModelEntries = [
            [
                'id' => 'id1',
                'modelId' => 'id1',
                'name' => 'name3'
            ],
            [
                'id' => 'id2',
                'modelId' => 'id1',
                'name' => 'name2'
            ],
            [
                'id' => 'id3',
                'modelId' => 'id1',
                'name' => 'name1'
            ],
        ];

        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $modelEntry)]);

        $myEmbeddedModelQueryBuilder1 = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $embeddedModelEntries[2])]);
        $myEmbeddedModelQueryBuilder2 = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $embeddedModelEntries)]);
        $myEmbeddedModelQueryBuilder3 = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, false)]);

        $connection = $this->getConnection(
            [
                'queryBuilder' => [
                    $myModelQueryBuilder,
                    $myEmbeddedModelQueryBuilder1,
                    $myEmbeddedModelQueryBuilder2,
                    $myEmbeddedModelQueryBuilder3
                ],
                'beginTransaction' => 5,
                'commit' => 5,
                'delete' => [
                    [
                        'arguments' => [
                            'tableExpression' => 'myembeddedmodels',
                            'identifier' => [
                                'id' => 'id3',
                            ],
                            'types' => [],
                        ],
                        'return' => 1,
                    ],
                    [
                        'arguments' => [
                            'tableExpression' => 'myembeddedmodels',
                            'identifier' => [
                                'id' => 'id2',
                            ],
                            'types' => [],
                        ],
                        'return' => 1,
                    ],
                ],
                'insert' => [
                    [
                        'arguments' => [
                            'tableExpression' => 'myembeddedmodels',
                            'data' => [
                                'id' => 'id3',
                                'modelId' => 'id1',
                                'name' => 'name1'
                            ],
                            'types' => [],
                        ],
                        'return' => 1,
                    ],
                ],
                'update' => [
                    [
                        'arguments' => [
                            'tableExpression' => 'mymodels',
                            'data' => [
                                'id' => 'id1',
                                'name' => 'name1',
                                'category' => 'category1',
                                'oneToOneId' => null
                            ],
                            'identifier' => [
                                'id' => 'id1',
                            ],
                            'types' => [],
                        ],
                        'return' => 1,
                    ],
                    [
                        'arguments' => [
                            'tableExpression' => 'myembeddedmodels',
                            'data' => [
                                'id' => 'id1',
                                'modelId' => 'id1',
                                'name' => 'name3',
                            ],
                            'identifier' => [
                                'id' => 'id1',
                            ],
                            'types' => [],
                        ],
                        'return' => 1,
                    ],
                ],
            ]
        );

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $logger = $this->getLogger();

        $container = $this->getContainer(
            function (Resolver $resolver) use ($connection, $storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($connection, $storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyEmbeddedModel,
                    $logger
                );
            }
        );

        /** @var MyModelRepository $repository */
        $repository = $container[MyModelRepository::class];

        /** @var MyModel $model */
        $model = $repository->find('id1');

        $model->setName('name1');
        $model->setOneToOne(null);
        $model->setOneToMany([$model->getOneToMany()[0], $model->getOneToMany()[2]]);

        $repository->persist($model);

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'mymodels',
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
            $myModelQueryBuilder->__calls
        );

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'myembeddedmodels',
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
                        'id3',
                        null,
                    ],
                ],
            ],
            $myEmbeddedModelQueryBuilder1->__calls
        );

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'myembeddedmodels',
                        null,
                    ],
                ],
                'setFirstResult' => [
                    [
                        null,
                    ],
                ],
                'setMaxResults' => [
                    [
                        null,
                    ],
                ],
                'andWhere' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'modelId',
                                ':modelId',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'modelId',
                        'id1',
                        null,
                    ],
                ]
            ],
            $myEmbeddedModelQueryBuilder2->__calls
        );

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'myembeddedmodels',
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
                        'id3',
                        null,
                    ],
                ],
            ],
            $myEmbeddedModelQueryBuilder3->__calls
        );

        self::assertCount(1, $storageCacheMyModel->__data);

        self::assertArrayHasKey('id1', $storageCacheMyModel->__data);
        self::assertEquals([
            'id' => 'id1',
            'name' => 'name1',
            'category' => 'category1',
            'oneToOneId' => null,
        ], $storageCacheMyModel->__data['id1']);

        self::assertCount(2, $storageCacheMyEmbeddedModel->__data);

        self::assertArrayHasKey('id1', $storageCacheMyEmbeddedModel->__data);
        self::assertEquals([
            'id' => 'id1',
            'modelId' => 'id1',
            'name' => 'name3'
        ], $storageCacheMyEmbeddedModel->__data['id1']);

        self::assertArrayHasKey('id3', $storageCacheMyEmbeddedModel->__data);
        self::assertEquals([
            'id' => 'id3',
            'modelId' => 'id1',
            'name' => 'name1'
        ], $storageCacheMyEmbeddedModel->__data['id3']);

        self::assertCount(18, $logger->__logs);

        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[0]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[1]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[1]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id3'], $logger->__logs[1]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[2]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[2]['message']);
        self::assertSame([
            'table' => 'myembeddedmodels',
            'criteria' => ['modelId' => 'id1'],
            'orderBy' => null,
            'limit' => null,
            'offset' => null,
        ], $logger->__logs[2]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[3]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[3]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id3'], $logger->__logs[3]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[4]['level']);
        self::assertSame('model: found row within cache for table {table} with id {id}', $logger->__logs[4]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id3'], $logger->__logs[4]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[5]['level']);
        self::assertSame('model: remove row from table {table} with id {id}', $logger->__logs[5]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id3'], $logger->__logs[5]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[6]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[6]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[6]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[7]['level']);
        self::assertSame('model: found row within cache for table {table} with id {id}', $logger->__logs[7]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[7]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[8]['level']);
        self::assertSame('model: update row into table {table} with id {id}', $logger->__logs[8]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[8]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[9]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[9]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id1'], $logger->__logs[9]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[10]['level']);
        self::assertSame('model: found row within cache for table {table} with id {id}', $logger->__logs[10]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id1'], $logger->__logs[10]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[11]['level']);
        self::assertSame('model: update row into table {table} with id {id}', $logger->__logs[11]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id1'], $logger->__logs[11]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[12]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[12]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id3'], $logger->__logs[12]['context']);

        self::assertSame(LogLevel::NOTICE, $logger->__logs[13]['level']);
        self::assertSame('model: row within table {table} with id {id} not found', $logger->__logs[13]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id3'], $logger->__logs[13]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[14]['level']);
        self::assertSame('model: insert row into table {table} with id {id}', $logger->__logs[14]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id3'], $logger->__logs[14]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[15]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[15]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id2'], $logger->__logs[15]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[16]['level']);
        self::assertSame('model: found row within cache for table {table} with id {id}', $logger->__logs[16]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id2'], $logger->__logs[16]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[17]['level']);
        self::assertSame('model: remove row from table {table} with id {id}', $logger->__logs[17]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id2'], $logger->__logs[17]['context']);
    }

    /**
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::__construct
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::remove
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::removeRelatedModels
     * @covers \Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository::removeRelatedModel
     */
    public function testRemoveModel()
    {
        $modelEntry = [
            'id' => 'id1',
            'name' => 'name3',
            'category' => 'category1',
            'oneToOneId' => null
        ];

        $embeddedModelEntries = [
            [
                'id' => 'id1',
                'modelId' => 'id1',
                'name' => 'name3'
            ],
            [
                'id' => 'id2',
                'modelId' => 'id1',
                'name' => 'name2'
            ],
            [
                'id' => 'id3',
                'modelId' => 'id1',
                'name' => 'name1'
            ],
        ];

        $connection = $this->getConnection(
            [
                'queryBuilder' => [
                    $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $modelEntry)]),
                    $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $embeddedModelEntries)]),
                    $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, false)]),
                ],
                'beginTransaction' => 4,
                'commit' => 4,
                'delete' => [
                    [
                        'arguments' => [
                            'tableExpression' => 'myembeddedmodels',
                            'identifier' => [
                                'id' => 'id1',
                            ],
                            'types' => [],
                        ],
                        'return' => 1,
                    ],
                    [
                        'arguments' => [
                            'tableExpression' => 'myembeddedmodels',
                            'identifier' => [
                                'id' => 'id2',
                            ],
                            'types' => [],
                        ],
                        'return' => 1,
                    ],
                    [
                        'arguments' => [
                            'tableExpression' => 'myembeddedmodels',
                            'identifier' => [
                                'id' => 'id3',
                            ],
                            'types' => [],
                        ],
                        'return' => 1,
                    ],
                    [
                        'arguments' => [
                            'tableExpression' => 'mymodels',
                            'identifier' => [
                                'id' => 'id1',
                            ],
                            'types' => [],
                        ],
                        'return' => 1,
                    ],
                ],
            ]
        );

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $logger = $this->getLogger();

        $container = $this->getContainer(
            function (Resolver $resolver) use ($connection, $storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($connection, $storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $connection,
                    $resolver,
                    $storageCacheMyEmbeddedModel,
                    $logger
                );
            }
        );

        /** @var MyModelRepository $repository */
        $repository = $container[MyModelRepository::class];

        /** @var MyModel $model */
        $model = $repository->find('id1');

        $repository->remove($model);

        self::assertCount(0, $storageCacheMyModel->__data);
        self::assertCount(0, $storageCacheMyEmbeddedModel->__data);

        self::assertCount(14, $logger->__logs);

        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[0]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[1]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[1]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[1]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[2]['level']);
        self::assertSame('model: found row within cache for table {table} with id {id}', $logger->__logs[2]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[2]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[3]['level']);
        self::assertSame('model: remove row from table {table} with id {id}', $logger->__logs[3]['message']);
        self::assertSame(['table' => 'mymodels', 'id' => 'id1'], $logger->__logs[3]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[4]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[4]['message']);
        self::assertSame([
            'table' => 'myembeddedmodels',
            'criteria' => ['modelId' => 'id1'],
            'orderBy' => null,
            'limit' => null,
            'offset' => null,
        ], $logger->__logs[4]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[5]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[5]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id1'], $logger->__logs[5]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[6]['level']);
        self::assertSame('model: found row within cache for table {table} with id {id}', $logger->__logs[6]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id1'], $logger->__logs[6]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[7]['level']);
        self::assertSame('model: remove row from table {table} with id {id}', $logger->__logs[7]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id1'], $logger->__logs[7]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[8]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[8]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id2'], $logger->__logs[8]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[9]['level']);
        self::assertSame('model: found row within cache for table {table} with id {id}', $logger->__logs[9]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id2'], $logger->__logs[9]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[10]['level']);
        self::assertSame('model: remove row from table {table} with id {id}', $logger->__logs[10]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id2'], $logger->__logs[10]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[11]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[11]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id3'], $logger->__logs[11]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[12]['level']);
        self::assertSame('model: found row within cache for table {table} with id {id}', $logger->__logs[12]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id3'], $logger->__logs[12]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[13]['level']);
        self::assertSame('model: remove row from table {table} with id {id}', $logger->__logs[13]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id3'], $logger->__logs[13]['context']);

        $repository->remove($model);
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
            'oneToOneId' => null
        ];

        $connnection = $this->getConnection();

        $storageCacheMyModel = $this->getStorageCache([$modelEntry]);
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $logger = $this->getLogger();

        $container = $this->getContainer(
            function (Resolver $resolver) use ($connnection, $storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $connnection,
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($connnection, $storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $connnection,
                    $resolver,
                    $storageCacheMyEmbeddedModel,
                    $logger
                );
            }
        );

        /** @var MyModelRepository $repository */
        $repository = $container[MyModelRepository::class];

        self::assertCount(1, $storageCacheMyModel->__data);

        $repository->clear();

        self::assertCount(0, $storageCacheMyModel->__data);
    }

    /**
     * @param \Closure $myRepositoryService
     * @param \Closure $myEmbeddedRepositoryService
     * @return Container
     */
    private function getContainer(\Closure $myRepositoryService, \Closure $myEmbeddedRepositoryService): Container
    {
        $container = new Container();

        $container['resolver'] = function () use ($container) {
            return new Resolver($this->getInteropContainer($container), [
                MyModelRepository::class,
                MyEmbeddedRepository::class
            ]);
        };

        $container[MyModelRepository::class] = function () use ($container, $myRepositoryService) {
            return $myRepositoryService($container['resolver']);
        };

        $container[MyEmbeddedRepository::class] = function () use ($container, $myEmbeddedRepositoryService) {
            return $myEmbeddedRepositoryService($container['resolver']);
        };

        return $container;
    }

    /**
     * @param Container $container
     * @return ContainerInterface
     */
    private function getInteropContainer(Container $container): ContainerInterface
    {
        /** @var ContainerInterface|\PHPUnit_Framework_MockObject_MockObject $interopContainer */
        $interopContainer = $this->getMockBuilder(ContainerInterface::class)
            ->setMethods(['get'])
            ->getMockForAbstractClass();

        $interopContainer->__container = $container;

        $interopContainer
            ->expects(self::any())
            ->method('get')
            ->willReturnCallback(function (string $key) use ($interopContainer) {
                return $interopContainer->__container[$key];
            });

        return $interopContainer;
    }
}
