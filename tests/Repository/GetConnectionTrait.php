<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;

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
        $repository = $this
            ->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->setMethods(['createQueryBuilder', 'insert', 'update', 'delete'])
            ->getMockForAbstractClass();

        $queryBuilderStack = $stacks['queryBuilder'] ?? [];
        $insertStack = $stacks['insert'] ?? [];
        $updateStack = $stacks['update'] ?? [];
        $deleteStack = $stacks['delete'] ?? [];

        $queryBuilderCounter = 0;

        $repository
            ->expects(self::any())
            ->method('createQueryBuilder')
            ->willReturnCallback(function () use (&$queryBuilderStack, &$queryBuilderCounter) {
                ++$queryBuilderCounter;

                $queryBuilder = array_shift($queryBuilderStack);

                self::assertNotNull($queryBuilder,
                    sprintf(
                        'createQueryBuilder failed, cause there was no data within $queryBuilderStack at call %d',
                        $queryBuilderCounter
                    )
                );

                return $queryBuilder;
            });

        $insertStackCounter = 0;

        $repository
            ->expects(self::any())
            ->method('insert')
            ->willReturnCallback(
                function (
                    $tableExpression,
                    array $data,
                    array $types = []
                ) use (&$insertStack, &$insertStackCounter) {
                    ++$insertStackCounter;

                    $insert = array_shift($insertStack);

                    self::assertNotNull($insert,
                        sprintf(
                            'insert failed, cause there was no data within $insertStack at call %d',
                            $insertStack
                        )
                    );

                    self::assertSame($insert['arguments'][0], $tableExpression);
                    self::assertSame($insert['arguments'][1], $data);
                    self::assertSame($insert['arguments'][2], $types);

                    return $insert['return'];
                }
            );

        $updateStackCounter = 0;

        $repository
            ->expects(self::any())
            ->method('update')
            ->willReturnCallback(
                function (
                    $tableExpression,
                    array $data,
                    array $identifier,
                    array $types = []
                ) use (&$updateStack, &$updateStackCounter) {
                    ++$updateStackCounter;

                    $update = array_shift($updateStack);

                    self::assertNotNull($update,
                        sprintf(
                            'update failed, cause there was no data within $updateStack at call %d',
                            $updateStack
                        )
                    );

                    self::assertSame($update['arguments'][0], $tableExpression);
                    self::assertSame($update['arguments'][1], $data);
                    self::assertSame($update['arguments'][2], $identifier);
                    self::assertSame($update['arguments'][3], $types);

                    return $update['return'];
                }
            );

        $deleteStackCounter = 0;

        $repository
            ->expects(self::any())
            ->method('delete')
            ->willReturnCallback(
                function (
                    $tableExpression,
                    array $identifier,
                    array $types = []
                ) use (&$deleteStack, &$deleteStackCounter) {
                    ++$deleteStackCounter;

                    $delete = array_shift($deleteStack);

                    self::assertNotNull($delete,
                        sprintf(
                            'delete failed, cause there was no data within $deleteStack at call %d',
                            $deleteStack
                        )
                    );

                    self::assertSame($delete['arguments'][0], $tableExpression);
                    self::assertSame($delete['arguments'][1], $identifier);
                    self::assertSame($delete['arguments'][2], $types);

                    return $delete['return'];
                }
            );

        return $repository;
    }

    /**
     * @param array $executeStack
     *
     * @return QueryBuilder
     */
    private function getQueryBuilder(array $executeStack): QueryBuilder
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

        $executeStackCounter = 0;

        $queryBuilder
            ->expects(self::any())
            ->method('execute')
            ->willReturnCallback(function () use ($queryBuilder, &$executeStack, &$executeStackCounter) {
                ++$executeStackCounter;

                $execute = array_shift($executeStack);

                self::assertNotNull($execute,
                    sprintf(
                        'execute failed, cause there was no data within $executeStack at call %d',
                        $executeStackCounter
                    )
                );

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
            ->getMockForAbstractClass();

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
}
