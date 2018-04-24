<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;

trait GetConnectionTrait
{
    /**
     * @param array $stacks
     *
     * @return Connection
     */
    private function getConnection(array $stacks = []): Connection
    {
        /* @var Connection|\PHPUnit_Framework_MockObject_MockObject $connection */
        $connection = $this
            ->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'createQueryBuilder',
                'beginTransaction',
                'commit',
                'rollBack',
                'insert',
                'update',
                'delete',
                'fetchAll',
                'executeUpdate',
                'exec',
                'getSchemaManager',
                'getDatabasePlatform',
                'getParams',
            ])
            ->getMockForAbstractClass();

        $queryBuilderStack = $stacks['queryBuilder'] ?? [];
        $beginTransaction = $stacks['beginTransaction'] ?? 0;
        $commit = $stacks['commit'] ?? 0;
        $rollBack = $stacks['rollBack'] ?? 0;
        $insertStack = $stacks['insert'] ?? [];
        $updateStack = $stacks['update'] ?? [];
        $deleteStack = $stacks['delete'] ?? [];
        $fetchAllStack = $stacks['fetchAll'] ?? [];
        $executeUpdateStack = $stacks['executeUpdate'] ?? [];
        $execStack = $stacks['exec'] ?? [];
        $schemaManager = $stacks['schemaManager'] ?? null;
        $databasePlatform = $stacks['databasePlatform'] ?? null;
        $params = $stacks['params'] ?? [];

        $connection
            ->expects(self::exactly(count($queryBuilderStack)))
            ->method('createQueryBuilder')
            ->willReturnCallback(function () use (&$queryBuilderStack) {
                return array_shift($queryBuilderStack);
            });

        $connection
            ->expects(self::exactly($beginTransaction))
            ->method('beginTransaction');

        $connection
            ->expects(self::exactly($commit))
            ->method('commit');

        $connection
            ->expects(self::exactly($rollBack))
            ->method('rollBack');

        $connection
            ->expects(self::exactly(count($insertStack)))
            ->method('insert')
            ->willReturnCallback(
                function (
                    $tableExpression,
                    array $data,
                    array $types = []
                ) use (&$insertStack) {
                    $insertKey = key($insertStack);
                    $insert = array_shift($insertStack);

                    self::assertSame($insert['arguments']['tableExpression'], $tableExpression, sprintf('$insertStack at call %d, argument: tableExpression', $insertKey));
                    self::assertSame($insert['arguments']['data'], $data, sprintf('$insertStack at call %d, argument: data', $insertKey));
                    self::assertSame($insert['arguments']['types'], $types, sprintf('$insertStack at call %d, argument: types', $insertKey));

                    if (isset($insert['exception'])) {
                        throw $insert['exception'];
                    }

                    return $insert['return'];
                }
            );

        $connection
            ->expects(self::exactly(count($updateStack)))
            ->method('update')
            ->willReturnCallback(
                function (
                    $tableExpression,
                    array $data,
                    array $identifier,
                    array $types = []
                ) use (&$updateStack) {
                    $updateKey = key($updateStack);
                    $update = array_shift($updateStack);

                    self::assertSame($update['arguments']['tableExpression'], $tableExpression, sprintf('$updateStack at call %d, argument: tableExpression', $updateKey));
                    self::assertSame($update['arguments']['data'], $data, sprintf('$updateStack at call %d, argument: data', $updateKey));
                    self::assertSame($update['arguments']['identifier'], $identifier, sprintf('$updateStack at call %d, argument: identifier', $updateKey));
                    self::assertSame($update['arguments']['types'], $types, sprintf('$updateStack at call %d, argument: types', $updateKey));

                    return $update['return'];
                }
            );

        $connection
            ->expects(self::exactly(count($deleteStack)))
            ->method('delete')
            ->willReturnCallback(
                function (
                    $tableExpression,
                    array $identifier,
                    array $types = []
                ) use (&$deleteStack) {
                    $deleteKey = key($deleteStack);
                    $delete = array_shift($deleteStack);

                    self::assertSame($delete['arguments']['tableExpression'], $tableExpression, sprintf('$deleteStack at call %d, argument: tableExpression', $deleteKey));
                    self::assertSame($delete['arguments']['identifier'], $identifier, sprintf('$deleteStack at call %d, argument: identifier', $deleteKey));
                    self::assertSame($delete['arguments']['types'], $types, sprintf('$deleteStack at call %d, argument: types', $deleteKey));

                    return $delete['return'];
                }
            );

        $connection
            ->expects(self::exactly(count($fetchAllStack)))
            ->method('fetchAll')
            ->willReturnCallback(function ($sql, array $params = array(), $types = array()) use (&$fetchAllStack) {
                $fetchAllKey = key($fetchAllStack);
                $fetchAll = array_shift($fetchAllStack);

                self::assertSame($fetchAll['arguments']['sql'], $sql, sprintf('$fetchAllStack at call %d, argument: sql', $fetchAllKey));
                self::assertSame($fetchAll['arguments']['params'], $params, sprintf('$fetchAllStack at call %d, argument: params', $fetchAllKey));
                self::assertSame($fetchAll['arguments']['types'], $types, sprintf('$fetchAllStack at call %d, argument: types', $fetchAllKey));

                return $fetchAll['return'];
            });

        $connection
            ->expects(self::any())
            ->method('executeUpdate')
            ->willReturnCallback(function ($sql, array $params = array(), $types = array()) use (&$executeUpdateStack) {
                $executeUpdateKey = key($executeUpdateStack);
                $executeUpdate = array_shift($executeUpdateStack);

                self::assertSame($executeUpdate['arguments']['sql'], $sql, sprintf('$executeUpdateStack at call %d, argument: sql', $executeUpdateKey));
                self::assertSame($executeUpdate['arguments']['params'], $params, sprintf('$executeUpdateStack at call %d, argument: params', $executeUpdateKey));
                self::assertSame($executeUpdate['arguments']['types'], $types, sprintf('$executeUpdateStack at call %d, argument: types', $executeUpdateKey));

                return $executeUpdate['return'];
            });

        $connection
            ->expects(self::any())
            ->method('exec')
            ->willReturnCallback(function ($sql) use (&$execStack) {
                $exec = array_shift($execStack);

                self::assertSame($exec['arguments'][0], $sql);

                return $exec['return'];
            });

        $connection
            ->expects(self::any())
            ->method('getSchemaManager')
            ->willReturnCallback(function () use ($schemaManager) {
                return $schemaManager;
            });

        $connection
            ->expects(self::any())
            ->method('getDatabasePlatform')
            ->willReturnCallback(function () use ($databasePlatform) {
                return $databasePlatform;
            });

        $connection
            ->expects(self::any())
            ->method('getParams')
            ->willReturnCallback(function () use ($params) {
                return $params;
            });

        return $connection;
    }

    /**
     * @param array $executeStack
     *
     * @return QueryBuilder
     */
    private function getQueryBuilder(array $executeStack = []): QueryBuilder
    {
        $modifiers = [
            'setParameter',
            'setParameters',
            'setFirstResult',
            'setMaxResults',
            'add',
            'select',
            'addSelect',
            'delete',
            'update',
            'insert',
            'from',
            'innerJoin',
            'leftJoin',
            'rightJoin',
            'set',
            'where',
            'andWhere',
            'orWhere',
            'groupBy',
            'addGroupBy',
            'setValue',
            'values',
            'having',
            'andHaving',
            'orHaving',
            'orderBy',
            'addOrderBy',
            'resetQueryParts',
            'resetQueryPart',
        ];

        /** @var QueryBuilder|\PHPUnit_Framework_MockObject_MockObject $queryBuilder */
        $queryBuilder = $this
            ->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods(array_merge($modifiers, ['expr', 'execute']))
            ->getMockForAbstractClass();

        $queryBuilder->__calls = [];

        foreach ($modifiers as $modifier) {
            $queryBuilder
                ->expects(self::any())
                ->method($modifier)
                ->willReturnCallback(function () use ($queryBuilder, $modifier) {
                    if (!isset($queryBuilder->__calls[$modifier])) {
                        $queryBuilder->__calls[$modifier] = [];
                    }

                    $queryBuilder->__calls[$modifier][] = func_get_args();

                    return $queryBuilder;
                });
        }

        $queryBuilder
            ->expects(self::any())
            ->method('expr')
            ->willReturnCallback(function () {
                return $this->getExpressionBuilder();
            });

        $queryBuilder
            ->expects(self::exactly(count($executeStack)))
            ->method('execute')
            ->willReturnCallback(function () use (&$executeStack) {
                $execute = array_shift($executeStack);

                return $execute;
            });

        return $queryBuilder;
    }

    /**
     * @return ExpressionBuilder
     */
    private function getExpressionBuilder(): ExpressionBuilder
    {
        $comparsions = [
            'andX',
            'orX',
            'comparison',
            'eq',
            'neq',
            'lt',
            'lte',
            'gt',
            'gte',
            'isNull',
            'isNotNull',
            'like',
            'notLike',
            'in',
            'notIn',
            'literal',
        ];

        /** @var ExpressionBuilder|\PHPUnit_Framework_MockObject_MockObject $expr */
        $expr = $this
            ->getMockBuilder(ExpressionBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods($comparsions)
            ->getMock();

        foreach ($comparsions as $comparsion) {
            $expr
                ->expects(self::any())
                ->method($comparsion)
                ->willReturnCallback(function () use ($comparsion) {
                    return ['method' => $comparsion, 'arguments' => func_get_args()];
                });
        }

        return $expr;
    }

    /**
     * @param int   $checkType
     * @param mixed $data
     *
     * @return Statement
     */
    private function getStatement(int $checkType, $data): Statement
    {
        /** @var Statement|\PHPUnit_Framework_MockObject_MockObject $stmt */
        $stmt = $this
            ->getMockBuilder(Statement::class)
            ->setMethods(['fetch', 'fetchAll'])
            ->getMockForAbstractClass();

        $stmt
            ->expects(self::any())
            ->method('fetch')
            ->willReturnCallback(function (int $type) use ($checkType, $data) {
                self::assertSame($checkType, $type);

                return $data;
            });

        $stmt
            ->expects(self::any())
            ->method('fetchAll')
            ->willReturnCallback(function (int $type) use ($checkType, $data) {
                self::assertSame($checkType, $type);

                return $data;
            });

        return $stmt;
    }

    /**
     * @param array $stacks
     *
     * @return AbstractSchemaManager
     */
    private function getSchemaManager(array $stacks = []): AbstractSchemaManager
    {
        /** @var AbstractSchemaManager|\PHPUnit_Framework_MockObject_MockObject $schemaManager */
        $schemaManager = $this
            ->getMockBuilder(AbstractSchemaManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['createSchema', 'createDatabase', 'listDatabases'])
            ->getMockForAbstractClass();

        $createSchemaStack = $stacks['createSchema'] ?? [];
        $listDatabases = $stacks['listDatabases'] ?? [];
        $createDatabaseStack = $stacks['createDatabase'] ?? [];

        $createSchemaStackCounter = 0;

        $schemaManager
            ->expects(self::any())
            ->method('createSchema')
            ->willReturnCallback(function () use (&$createSchemaStack, &$createSchemaStackCounter) {
                ++$createSchemaStackCounter;

                $createSchema = array_shift($createSchemaStack);

                self::assertNotNull($createSchema,
                    sprintf(
                        'execute failed, cause there was no data within $createSchemaStack at call %d',
                        $createSchemaStackCounter
                    )
                );

                return $createSchema;
            });

        $createDatabaseStackCounter = 0;

        $schemaManager
            ->expects(self::any())
            ->method('createDatabase')
            ->willReturnCallback(function (string $database) use (&$createDatabaseStack, &$createDatabaseStackCounter) {
                ++$createDatabaseStackCounter;

                $createDatabase = array_shift($createDatabaseStack);

                self::assertNotNull($createDatabase,
                    sprintf(
                        'execute failed, cause there was no data within $createDatabaseStack at call %d',
                        $createDatabaseStackCounter
                    )
                );

                self::assertSame($createDatabase['arguments'][0], $database);

                if (isset($createDatabase['exception'])) {
                    throw $createDatabase['exception'];
                }
            });

        $schemaManager
            ->expects(self::any())
            ->method('listDatabases')
            ->willReturnCallback(function () use ($listDatabases) {
                return $listDatabases;
            });

        return $schemaManager;
    }

    /**
     * @param $stacks
     *
     * @return Schema
     */
    private function getSchema(array $stacks): Schema
    {
        /** @var Schema|\PHPUnit_Framework_MockObject_MockObject $schema */
        $schema = $this
            ->getMockBuilder(Schema::class)
            ->disableOriginalConstructor()
            ->setMethods(['getMigrateToSql'])
            ->getMock();

        $migrateToSqlStack = $stacks['migrateToSql'] ?? [];

        $migrateToSqlStackCounter = 0;

        $schema
            ->expects(self::any())
            ->method('getMigrateToSql')
            ->willReturnCallback(function (Schema $toSchema, AbstractPlatform $platform) use (&$migrateToSqlStack, &$migrateToSqlStackCounter) {
                ++$migrateToSqlStackCounter;

                $migrateToSql = array_shift($migrateToSqlStack);

                self::assertNotNull($migrateToSql,
                    sprintf(
                        'execute failed, cause there was no data within $migrateToSqlStack at call %d',
                        $migrateToSqlStackCounter
                    )
                );

                self::assertEquals($migrateToSql['arguments'][0], $toSchema);
                self::assertSame($migrateToSql['arguments'][1], $platform);

                return $migrateToSql['return'];
            });

        return $schema;
    }

    /**
     * @return AbstractPlatform
     */
    private function getPlatform(): AbstractPlatform
    {
        /** @var AbstractPlatform|\PHPUnit_Framework_MockObject_MockObject $platform */
        $platform = $this
            ->getMockBuilder(AbstractPlatform::class)
            ->disableOriginalConstructor()
            ->setMethods(['quoteSingleIdentifier'])
            ->getMockForAbstractClass();

        $platform
            ->expects(self::any())
            ->method('quoteSingleIdentifier')
            ->willReturnCallback(function ($str) {
                return $str;
            });

        return $platform;
    }

    /**
     * @param MockObject $mock
     * @return array
     */
    private function toMockArray(MockObject $mock)
    {
        return json_decode(json_encode($mock), true);
    }
}
