<?php

declare(strict_types=1);

namespace MyProject\Repository;

use Chubbyphp\Model\Collection\LazyModelCollection;
use Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository;
use Chubbyphp\Model\ModelInterface;
use Chubbyphp\Model\Reference\LazyModelReference;
use MyProject\Model\MyEmbeddedModel;
use MyProject\Model\MyModel;

final class MyModelRepository extends AbstractDoctrineRepository
{
    /**
     * @param string $modelClass
     * @return bool
     */
    public function isResponsible(string $modelClass): bool
    {
        return MyModel::class === $modelClass;
    }

    /**
     * @param array $row
     * @return MyModel|ModelInterface
     */
    protected function fromPersistence(array $row): ModelInterface
    {
        $row['oneToOne'] = new LazyModelReference(
            $this->resolver, MyEmbeddedModel::class, $row['oneToOneId']
        );

        $row['oneToMany'] = new LazyModelCollection(
            $this->resolver, MyEmbeddedModel::class, 'modelId', $row['id'], ['name' => 'ASC']
        );

        return MyModel::fromPersistence($row);
    }

    /**
     * @return string
     */
    protected function getTable(): string
    {
        return 'mymodels';
    }
}
