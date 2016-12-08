<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits;

use Chubbyphp\Model\ModelInterface;
use Chubbyphp\Model\ResolverInterface;
use Chubbyphp\Tests\Model\GetRepositoryTrait;

trait GetResolverTrait
{
    use GetRepositoryTrait;

    private function getResolver(array $modelEntries = []): ResolverInterface
    {
        /** @var ResolverInterface|\PHPUnit_Framework_MockObject_MockObject $resolver */
        $resolver = $this->getMockBuilder(ResolverInterface::class)->setMethods([
            'find',
            'findOneBy',
            'findBy',
            'lazyFind',
            'lazyFindOneBy',
            'lazyFindBy',
            'persist',
            'remove',
        ])->getMockForAbstractClass();

        $resolver->__repository = $this->getRepository($modelEntries);

        $resolver->expects(self::any())->method('find')->willReturnCallback(
            function (string $modelClass, string $id) use ($resolver) {
                return $resolver->__repository->find($id);
            }
        );

        $resolver->expects(self::any())->method('findOneBy')->willReturnCallback(
            function (string $modelClass, array $criteria, array $orderBy = null) use ($resolver) {
                return $resolver->__repository->findOneBy($criteria, $orderBy);
            }
        );

        $resolver->expects(self::any())->method('findBy')->willReturnCallback(
            function (
                string $modelClass,
                array $criteria,
                array $orderBy = null,
                int $limit = null,
                int $offset = null
            ) use ($resolver) {
                return $resolver->__repository->findBy($criteria, $orderBy, $limit, $offset);
            }
        );

        $resolver->expects(self::any())->method('lazyFind')->willReturnCallback(
            function (string $modelClass, string $id) use ($resolver) {
                return function () use ($resolver, $modelClass, $id) {
                    return $resolver->find($modelClass, $id);
                };
            }
        );

        $resolver->expects(self::any())->method('lazyFindOneBy')->willReturnCallback(
            function (string $modelClass, array $criteria, array $orderBy = null) use ($resolver) {
                return function () use ($resolver, $modelClass, $criteria, $orderBy) {
                    return $resolver->findOneBy($modelClass, $criteria, $orderBy);
                };
            }
        );

        $resolver->expects(self::any())->method('lazyFindBy')->willReturnCallback(
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
        );

        $resolver->expects(self::any())->method('persist')->willReturnCallback(
            function (ModelInterface $model) use ($resolver) {
                return $resolver->__repository->persist($model);
            }
        );

        $resolver->expects(self::any())->method('remove')->willReturnCallback(
            function (ModelInterface $model) use ($resolver) {
                return $resolver->__repository->remove($model);
            }
        );

        return $resolver;
    }
}
