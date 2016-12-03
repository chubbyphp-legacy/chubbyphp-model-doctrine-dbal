<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\Repository;

use Chubbyphp\Model\ModelInterface;
use Chubbyphp\Model\ResolverInterface;
use Chubbyphp\Tests\Model\Resources\User;

trait GetResolverTrait
{
    private function getResolver(array $data = []): ResolverInterface
    {
        /** @var ResolverInterface|\PHPUnit_Framework_MockObject_MockObject $resolver */
        $resolver = $this
            ->getMockBuilder(ResolverInterface::class)
            ->setMethods([
                'find',
                'findOneBy',
                'findBy',
                'lazyFind',
                'lazyFindOneBy',
                'lazyFindBy',
                'persist',
                'remove',
            ])
            ->getMockForAbstractClass()
        ;

        $resolver->__data = $data;

        $resolver
            ->expects(self::any())
            ->method('find')
            ->willReturnCallback(function (string $modelClass, string $id) use ($resolver) {
                return $resolver->__data[$modelClass][$id] ?? null;
            })
        ;

        $resolver
            ->expects(self::any())
            ->method('findOneBy')
            ->willReturnCallback(function (string $modelClass, array $criteria, array $orderBy = null) use ($resolver) {
                return $resolver->findBy($modelClass, $criteria, $orderBy, 1, 0);
            })
        ;

        $resolver
            ->expects(self::any())
            ->method('findBy')
            ->willReturnCallback(
                function (
                    string $modelClass,
                    array $criteria,
                    array $orderBy = null,
                    int $limit = null,
                    int $offset = null
                ) use ($resolver) {
                    if (!isset($resolver->__data[$modelClass])) {
                        return [];
                    }

                    $models = [];
                    foreach ($resolver->__data[$modelClass] as $modelEntry) {
                        foreach ($criteria as $key => $value) {
                            if ($modelEntry[$key] !== $value) {
                                continue 2;
                            }
                        }

                        $models[] = User::fromPersistence($modelEntry);
                    }

                    if (null !== $orderBy) {
                        usort($models, function (ModelInterface $a, ModelInterface $b) use ($orderBy) {
                            foreach ($orderBy as $key => $value) {
                                $propertyReflection = new \ReflectionProperty(get_class($a), $key);
                                $propertyReflection->setAccessible(true);
                                $sorting = strcmp($propertyReflection->getValue($a), $propertyReflection->getValue($b));
                                if ($value === 'DESC') {
                                    $sorting = $sorting * -1;
                                }

                                if (0 !== $sorting) {
                                    return $sorting;
                                }
                            }

                            return 0;
                        });
                    }

                    if (null !== $limit && null !== $offset) {
                        return array_slice($models, $offset, $limit);
                    }

                    if (null !== $limit) {
                        return array_slice($models, 0, $limit);
                    }

                    return $models;
                }
            )
        ;

        $resolver
            ->expects(self::any())
            ->method('lazyFind')
            ->willReturnCallback(function (string $modelClass, string $id) use ($resolver) {
                return function () use ($resolver, $modelClass, $id) {
                    return $resolver->find($modelClass, $id);
                };
            })
        ;

        $resolver
            ->expects(self::any())
            ->method('lazyFindOneBy')
            ->willReturnCallback(function (string $modelClass, array $criteria, array $orderBy = null) use ($resolver) {
                return function () use ($resolver, $modelClass, $criteria, $orderBy) {
                    return $resolver->findOneBy($modelClass, $criteria, $orderBy);
                };
            })
        ;

        $resolver
            ->expects(self::any())
            ->method('lazyFindBy')
            ->willReturnCallback(
                function (
                    string $modelClass,
                    array $criteria,
                    array $orderBy = null,
                    int $limit = null,
                    int $offset = null
                ) use ($resolver) {
                    return function () use ($resolver, $modelClass, $criteria, $orderBy, $limit, $offset) {
                        return $resolver->findBy($modelClass, $criteria, $orderBy, $limit, $offset);
                    };
                }
            )
        ;

        $resolver
            ->expects(self::any())
            ->method('persist')
            ->willReturnCallback(
                function (ModelInterface $model) {
                }
            )
        ;

        $resolver
            ->expects(self::any())
            ->method('remove')
            ->willReturnCallback(
                function (ModelInterface $model) {
                }
            )
        ;

        return $resolver;
    }
}
