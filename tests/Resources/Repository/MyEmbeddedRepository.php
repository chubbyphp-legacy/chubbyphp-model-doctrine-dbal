<?php

declare(strict_types=1);

namespace MyProject\Repository;

use Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository;
use Chubbyphp\Model\ModelInterface;
use MyProject\Model\MyEmbeddedModel;

final class MyEmbeddedRepository extends AbstractDoctrineRepository
{
    /**
     * @param string $modelClass
     *
     * @return bool
     */
    public function isResponsible(string $modelClass): bool
    {
        return MyEmbeddedModel::class === $modelClass;
    }

    /**
     * @param array $row
     *
     * @return MyEmbeddedModel|ModelInterface
     */
    protected function fromPersistence(array $row): ModelInterface
    {
        return MyEmbeddedModel::fromPersistence($row);
    }

    /**
     * @return string
     */
    protected function getTable(): string
    {
        return 'myembeddedmodels';
    }
}
