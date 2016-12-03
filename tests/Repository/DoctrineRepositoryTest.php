<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\Repository;

use Chubbyphp\Model\StorageCache\StorageCacheInterface;
use Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository;
use Chubbyphp\Model\ModelInterface;
use Chubbyphp\Model\ResolverInterface;
use Chubbyphp\Tests\Model\Resources\User;
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
            User::class,
            'users'
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
                        'users',
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
        self::assertSame(['table' => 'users', 'id' => 'id1'], $logger->__logs[0]['context']);
        self::assertSame(LogLevel::WARNING, $logger->__logs[1]['level']);
        self::assertSame('model: row within table {table} with id {id} not found', $logger->__logs[1]['message']);
        self::assertSame(['table' => 'users', 'id' => 'id1'], $logger->__logs[1]['context']);
    }

    public function testFindFound()
    {
        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, [
                'id' => 'id1',
                'username' => 'username',
                'password' => 'password',
                'active' => true,
            ]),
        ]);

        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger,
            User::class,
            'users'
        );

        /** @var User $user */
        $user = $repository->find('id1');

        self::assertInstanceOf(User::class, $user);

        self::assertSame('id1', $user->getId());
        self::assertSame('username', $user->getUsername());
        self::assertSame('password', $user->getPassword());
        self::assertTrue($user->isActive());

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'users',
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
        self::assertInstanceOf(ModelInterface::class, $storageCache->__data['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'users', 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    public function testFindFoundWithinStorageCache()
    {
        $data = [
            'id' => 'id1',
            'username' => 'username',
            'password' => 'password',
            'active' => true,
        ];

        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, $data),
        ]);

        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache(['id1' => $data]);
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger,
            User::class,
            'users'
        );

        /** @var User $user */
        $user = $repository->find('id1');

        self::assertInstanceOf(User::class, $user);

        self::assertSame('id1', $user->getId());
        self::assertSame('username', $user->getUsername());
        self::assertSame('password', $user->getPassword());
        self::assertTrue($user->isActive());

        self::assertCount(0, $queryBuilder->__calls);

        self::assertCount(1, $storageCache->__data);
        self::assertInstanceOf(ModelInterface::class, $storageCache->__data['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find row within table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'users', 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    public function testFindOneByNotFound()
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
            User::class,
            'users'
        );

        self::assertNull($repository->findOneBy(['username' => 'user1']));

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'users',
                        null,
                    ],
                ],
                'andWhere' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'username',
                                ':username',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'username',
                        'user1',
                        null,
                    ],
                ],
                'setMaxResults' => [
                    [
                        1,
                    ],
                ],
            ],
            $queryBuilder->__calls
        );

        self::assertCount(0, $storageCache->__data);

        self::assertCount(2, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'users', 'criteria' => ['username' => 'user1']], $logger->__logs[0]['context']);
        self::assertSame(LogLevel::WARNING, $logger->__logs[1]['level']);
        self::assertSame('model: model {model} with criteria {criteria} not found', $logger->__logs[1]['message']);
        self::assertSame(['table' => 'users', 'criteria' => ['username' => 'user1']], $logger->__logs[1]['context']);
    }

    public function testFindOneByFound()
    {
        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, [
                'id' => 'id1',
                'username' => 'user1',
                'password' => 'password',
                'active' => true,
            ]),
        ]);

        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger,
            User::class,
            'users'
        );

        /** @var User $user */
        $user = $repository->findOneBy(['username' => 'user1']);

        self::assertInstanceOf(User::class, $user);

        self::assertSame('id1', $user->getId());
        self::assertSame('user1', $user->getUsername());
        self::assertSame('password', $user->getPassword());
        self::assertTrue($user->isActive());

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'users',
                        null,
                    ],
                ],
                'andWhere' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'username',
                                ':username',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'username',
                        'user1',
                        null,
                    ],
                ],
                'setMaxResults' => [
                    [
                        1,
                    ],
                ],
            ],
            $queryBuilder->__calls
        );

        self::assertCount(0, $storageCache->__data);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'users', 'criteria' => ['username' => 'user1']], $logger->__logs[0]['context']);
    }

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
            $logger,
            User::class,
            'users'
        );

        self::assertSame([], $repository->findBy(['active' => true]));

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'users',
                        null,
                    ],
                ],
                'setFirstResult' => [[null]],
                'setMaxResults' => [[null]],
                'andWhere' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'active',
                                ':active',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'active',
                        true,
                        null,
                    ],
                ],
            ],
            $queryBuilder->__calls
        );

        self::assertCount(0, $storageCache->__data);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame([
            'table' => 'users',
            'criteria' => ['active' => true],
            'orderBy' => null,
            'limit' => null,
            'offset' => null
        ], $logger->__logs[0]['context']);
    }

    public function testFindByFound()
    {
        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, [
                [
                    'id' => 'id1',
                    'username' => 'user1',
                    'password' => 'password',
                    'active' => true,
                ],
                [
                    'id' => 'id2',
                    'username' => 'user2',
                    'password' => 'password',
                    'active' => true,
                ],
            ]),
        ]);

        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $resolver,
            $storageCache,
            $logger,
            User::class,
            'users'
        );

        $users = $repository->findBy(['active' => true]);

        self::assertCount(2, $users);

        self::assertInstanceOf(User::class, $users[0]);

        self::assertSame('id1', $users[0]->getId());
        self::assertSame('user1', $users[0]->getUsername());
        self::assertSame('password', $users[0]->getPassword());
        self::assertTrue($users[0]->isActive());

        self::assertInstanceOf(User::class, $users[1]);

        self::assertSame('id2', $users[1]->getId());
        self::assertSame('user2', $users[1]->getUsername());
        self::assertSame('password', $users[1]->getPassword());
        self::assertTrue($users[1]->isActive());

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'users',
                        null,
                    ],
                ],
                'andWhere' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'active',
                                ':active',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'active',
                        true,
                        null,
                    ],
                ],
            ],
            $queryBuilder->__calls
        );

        self::assertCount(0, $storageCache->__data);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find rows within table {table} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'users', 'criteria' => ['active' => true]], $logger->__logs[0]['context']);
    }

    public function testPersistWithNewUser()
    {
        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(
                [
                    'insert' => [
                        [
                            'arguments' => [
                                'users',
                                [
                                    'id' => 'id1',
                                    'username' => 'user1',
                                    'password' => 'password',
                                    'active' => true,
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
            $logger,
            User::class,
            'users'
        );

        $user = new User('id1');
        $user->setUsername('user1');
        $user->setPassword('password');
        $user->setActive(true);

        $repository->persist($user);

        self::assertCount(1, $storageCache->__data);
        self::assertArrayHasKey('id1', $storageCache->__data);
        self::assertInstanceOf(ModelInterface::class, $storageCache->__data['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: insert model {model} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'users', 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    public function testPersistWithExistingUser()
    {
        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(
                [
                    'select' => []
                ],
                [
                    'update' => [
                        [
                            'arguments' => [
                                'users',
                                [
                                    'id' => 'id1',
                                    'username' => 'user1',
                                    'password' => 'password',
                                    'active' => true,
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
            $logger,
            User::class,
            'users'
        );

        $user = new User('id1');
        $user->setUsername('user1');
        $user->setPassword('password');
        $user->setActive(true);

        $repository->persist($user);

        self::assertCount(1, $storageCache->__data);
        self::assertArrayHasKey('id1', $storageCache->__data);
        self::assertInstanceOf(ModelInterface::class, $storageCache->__data['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: update model {model} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'users', 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    public function testRemove()
    {
        $resolver = $this->getResolver();
        $storageCache = $this->getStorageCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(
                [
                    'delete' => [
                        [
                            'arguments' => [
                                'users',
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
            $logger,
            User::class,
            'users'
        );

        $user = new User('id1');
        $user->setUsername('user1');
        $user->setPassword('password');
        $user->setActive(true);

        $repository->remove($user);

        self::assertCount(0, $storageCache->__data);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: remove row from table {table} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['table' => 'users', 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    /**
     * @param Connection          $connection
     * @param ResolverInterface   $resolver
     * @param StorageCacheInterface $storageCache
     * @param LoggerInterface     $logger
     * @param string              $modelClass
     * @param string              $table
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

        $repository->expects(self::any())->method('getModelClass')->willReturn($modelClass);
        $repository->expects(self::any())->method('getTable')->willReturn($table);

        return $repository;
    }
}
