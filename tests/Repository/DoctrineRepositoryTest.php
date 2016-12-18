<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\Repository;

use Chubbyphp\Model\Collection\ModelCollection;
use Chubbyphp\Model\ModelInterface;
use Chubbyphp\Model\Resolver;
use Chubbyphp\Model\StorageCache\ArrayStorageCache;
use Chubbyphp\Model\StorageCache\NullStorageCache;
use Chubbyphp\Model\StorageCache\StorageCacheInterface;
use Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository;
use Chubbyphp\Model\ResolverInterface;
use Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits\GetConnectionTrait;
use Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits\GetLoggerTrait;
use Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits\GetResolverTrait;
use Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits\GetStorageCacheTrait;
use Doctrine\DBAL\Connection;
use Interop\Container\ContainerInterface;
use MyProject\Model\MyEmbeddedModel;
use MyProject\Model\MyModel;
use MyProject\Repository\MyEmbeddedRepository;
use MyProject\Repository\MyModelRepository;
use Pimple\Container;
use Psr\Log\LoggerInterface;
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
        $logger = $this->getLogger();

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $container = $this->getContainer(
            function (Resolver $resolver) use ($storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $this->getConnection(),
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $this->getConnection(),
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

        $logger = $this->getLogger();

        $storageCacheMyModel = $this->getStorageCache([$modelEntry]);
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $container = $this->getContainer(
            function (Resolver $resolver) use ($storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $this->getConnection(),
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $this->getConnection(),
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

        $logger = $this->getLogger();

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $modelEntry)]);

        $container = $this->getContainer(
            function (Resolver $resolver) use ($storageCacheMyModel, $logger, $myModelQueryBuilder) {
                return new MyModelRepository(
                    $this->getConnection(['queryBuilder' => [$myModelQueryBuilder]]),
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $this->getConnection(),
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
        $logger = $this->getLogger();

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, false),]);

        $container = $this->getContainer(
            function (Resolver $resolver) use ($storageCacheMyModel, $logger, $myModelQueryBuilder) {
                return new MyModelRepository(
                    $this->getConnection(['queryBuilder' => [$myModelQueryBuilder]]),
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $this->getConnection(),
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

        $logger = $this->getLogger();

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $modelEntries)]);

        $container = $this->getContainer(
            function (Resolver $resolver) use ($storageCacheMyModel, $logger, $myModelQueryBuilder) {
                return new MyModelRepository(
                    $this->getConnection(['queryBuilder' => [$myModelQueryBuilder]]),
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $this->getConnection(),
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
            array(
                'select' => array(
                        0 => array(
                                0 => '*',
                            ),
                    ),
                'from' => array(
                        0 => array(
                                0 => 'mymodels',
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
        $logger = $this->getLogger();

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, [])]);

        $container = $this->getContainer(
            function (Resolver $resolver) use ($storageCacheMyModel, $logger, $myModelQueryBuilder) {
                return new MyModelRepository(
                    $this->getConnection(['queryBuilder' => [$myModelQueryBuilder]]),
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $this->getConnection(),
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
            array(
                'select' => array(
                        0 => array(
                                0 => '*',
                            ),
                    ),
                'from' => array(
                        0 => array(
                                0 => 'mymodels',
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

        $logger = $this->getLogger();

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $modelEntries)]);

        $container = $this->getContainer(
            function (Resolver $resolver) use ($storageCacheMyModel, $logger, $myModelQueryBuilder) {
                return new MyModelRepository(
                    $this->getConnection(['queryBuilder' => [$myModelQueryBuilder]]),
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $this->getConnection(),
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
            array(
                'select' => array(
                        0 => array(
                                0 => '*',
                            ),
                    ),
                'from' => array(
                        0 => array(
                                0 => 'mymodels',
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
        $logger = $this->getLogger();

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, [])]);

        $container = $this->getContainer(
            function (Resolver $resolver) use ($storageCacheMyModel, $logger, $myModelQueryBuilder) {
                return new MyModelRepository(
                    $this->getConnection(['queryBuilder' => [$myModelQueryBuilder]]),
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $this->getConnection(),
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
            array(
                'select' => array(
                        0 => array(
                                0 => '*',
                            ),
                    ),
                'from' => array(
                        0 => array(
                                0 => 'mymodels',
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
            $myModelQueryBuilder->__calls
        );

        self::assertCount(0, $storageCacheMyModel->__data);

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

        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, false)]);
        $myEmbeddedModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, false)]);

        $container = $this->getContainer(
            function (Resolver $resolver) use ($storageCacheMyModel, $logger, $myModelQueryBuilder) {
                return new MyModelRepository(
                    $this->getConnection(
                        [
                            'queryBuilder' => [$myModelQueryBuilder],
                            'insert' => [
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
                        ]
                    ),
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($storageCacheMyEmbeddedModel, $logger, $myEmbeddedModelQueryBuilder) {
                return new MyEmbeddedRepository(
                    $this->getConnection(
                        [
                            'queryBuilder' => [$myEmbeddedModelQueryBuilder],
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
                            ],
                        ]
                    ),
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

        $logger = $this->getLogger();

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $myModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $modelEntry)]);

        $myEmbeddedModelQueryBuilder1 = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $embeddedModelEntries[1])]);
        $myEmbeddedModelQueryBuilder2 = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $embeddedModelEntries)]);
        $myEmbeddedModelQueryBuilder3 = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $embeddedModelEntries[2])]);

        $container = $this->getContainer(
            function (Resolver $resolver) use ($storageCacheMyModel, $logger, $myModelQueryBuilder) {
                return new MyModelRepository(
                    $this->getConnection(
                        [
                            'queryBuilder' => [$myModelQueryBuilder],
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
                            ],
                        ]
                    ),
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($storageCacheMyEmbeddedModel, $logger, $myEmbeddedModelQueryBuilder1, $myEmbeddedModelQueryBuilder2, $myEmbeddedModelQueryBuilder3) {
                return new MyEmbeddedRepository(
                    $this->getConnection(
                        [
                            'queryBuilder' => [$myEmbeddedModelQueryBuilder1, $myEmbeddedModelQueryBuilder2, $myEmbeddedModelQueryBuilder3],
                            'delete' => [
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
                            ],
                            'update' => [
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
                                [
                                    'arguments' => [
                                        'tableExpression' => 'myembeddedmodels',
                                        'data' => [
                                            'id' => 'id3',
                                            'modelId' => 'id1',
                                            'name' => 'name1',
                                        ],
                                        'identifier' => [
                                            'id' => 'id3',
                                        ],
                                        'types' => [],
                                    ],
                                    'return' => 1,
                                ],
                            ],
                        ]
                    ),
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
        $model->setOneToMany([$model->getOneToMany()[0]]);

        $repository->persist($model);

        self::assertCount(1, $storageCacheMyModel->__data);
        self::assertArrayHasKey('id1', $storageCacheMyModel->__data);
        self::assertEquals([
            'id' => 'id1',
            'name' => 'name1',
            'category' => 'category1',
            'oneToOneId' => null,
        ], $storageCacheMyModel->__data['id1']);

        self::assertArrayHasKey('id1', $storageCacheMyEmbeddedModel->__data);
        self::assertEquals([
            'id' => 'id1',
            'modelId' => 'id1',
            'name' => 'name3'
        ], $storageCacheMyEmbeddedModel->__data['id1']);

        self::assertCount(17, $logger->__logs);

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
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id2'], $logger->__logs[3]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[4]['level']);
        self::assertSame('model: found row within cache for table {table} with id {id}', $logger->__logs[4]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id2'], $logger->__logs[4]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[5]['level']);
        self::assertSame('model: remove row from table {table} with id {id}', $logger->__logs[5]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id2'], $logger->__logs[5]['context']);

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
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id2'], $logger->__logs[12]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[13]['level']);
        self::assertSame('model: remove row from table {table} with id {id}', $logger->__logs[13]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id2'], $logger->__logs[13]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[14]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[14]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id3'], $logger->__logs[14]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[15]['level']);
        self::assertSame('model: found row within cache for table {table} with id {id}', $logger->__logs[15]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id3'], $logger->__logs[15]['context']);

        self::assertSame(LogLevel::INFO, $logger->__logs[16]['level']);
        self::assertSame('model: remove row from table {table} with id {id}', $logger->__logs[16]['message']);
        self::assertSame(['table' => 'myembeddedmodels', 'id' => 'id3'], $logger->__logs[16]['context']);
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

        $logger = $this->getLogger();

        $storageCacheMyModel = $this->getStorageCache();
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $myModelQueryBuilder1 = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $modelEntry)]);
        $myModelQueryBuilder2 = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, false)]);

        $myEmbeddedModelQueryBuilder = $this->getQueryBuilder([$this->getStatement(\PDO::FETCH_ASSOC, $embeddedModelEntries)]);

        $container = $this->getContainer(
            function (Resolver $resolver) use ($storageCacheMyModel, $logger, $myModelQueryBuilder1, $myModelQueryBuilder2) {
                return new MyModelRepository(
                    $this->getConnection(
                        [
                            'queryBuilder' => [$myModelQueryBuilder1, $myModelQueryBuilder2],
                            'delete' => [
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
                    ),
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($storageCacheMyEmbeddedModel, $logger, $myEmbeddedModelQueryBuilder) {
                return new MyEmbeddedRepository(
                    $this->getConnection(
                        [
                            'queryBuilder' => [$myEmbeddedModelQueryBuilder],
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
                            ],
                        ]
                    ),
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

        $logger = $this->getLogger();

        $storageCacheMyModel = $this->getStorageCache([$modelEntry]);
        $storageCacheMyEmbeddedModel = $this->getStorageCache();

        $container = $this->getContainer(
            function (Resolver $resolver) use ($storageCacheMyModel, $logger) {
                return new MyModelRepository(
                    $this->getConnection(),
                    $resolver,
                    $storageCacheMyModel,
                    $logger
                );
            },
            function (Resolver $resolver) use ($storageCacheMyEmbeddedModel, $logger) {
                return new MyEmbeddedRepository(
                    $this->getConnection(),
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
