<?php

declare(strict_types=1);

namespace Chubbyphp\Model\Doctrine\DBAL\Repository;

use Chubbyphp\Model\Collection\ModelCollectionInterface;
use Chubbyphp\Model\ModelInterface;
use Chubbyphp\Model\Reference\ModelReferenceInterface;
use Chubbyphp\Model\RelatedModelManipulationStack;
use Chubbyphp\Model\RepositoryInterface;
use Chubbyphp\Model\ResolverInterface;
use Chubbyphp\Model\StorageCache\NullStorageCache;
use Chubbyphp\Model\StorageCache\StorageCacheInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract class AbstractDoctrineRepository implements RepositoryInterface
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var ResolverInterface
     */
    protected $resolver;

    /**
     * @var StorageCacheInterface
     */
    protected $storageCache;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Connection            $connection
     * @param ResolverInterface     $resolver
     * @param StorageCacheInterface $storageCache
     * @param LoggerInterface|null  $logger
     */
    public function __construct(
        Connection $connection,
        ResolverInterface $resolver,
        StorageCacheInterface $storageCache,
        LoggerInterface $logger = null
    ) {
        $this->connection = $connection;
        $this->resolver = $resolver;
        $this->storageCache = $storageCache ?? new NullStorageCache();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param string $id
     *
     * @return ModelInterface|null
     */
    public function find(string $id = null)
    {
        if (null === $id) {
            return null;
        }

        $table = $this->getTable();

        $this->logger->info('model: find row within table {table} with id {id}', ['table' => $table, 'id' => $id]);

        if ($this->storageCache->has($id)) {
            $this->logger->info(
                'model: found row within cache for table {table} with id {id}',
                ['table' => $table, 'id' => $id]
            );

            return $this->fromPersistence($this->storageCache->get($id));
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')->from($this->getTable())->where($qb->expr()->eq('id', ':id'))->setParameter('id', $id);

        $row = $qb->execute()->fetch(\PDO::FETCH_ASSOC);
        if (false === $row) {
            $this->logger->notice(
                'model: row within table {table} with id {id} not found',
                ['table' => $table, 'id' => $id]
            );

            return null;
        }

        $this->storageCache->set($row['id'], $row);

        return $this->fromPersistence($row);
    }

    /**
     * @param array $criteria
     *
     * @return null|ModelInterface
     */
    public function findOneBy(array $criteria, array $orderBy = null)
    {
        $models = $this->findBy($criteria, $orderBy, 1, 0);

        if ([] === $models) {
            $this->logger->notice(
                'model: row within table {table} with criteria {criteria} not found',
                [
                    'table' => $this->getTable(),
                    'criteria' => $criteria,
                    'orderBy' => $orderBy,
                    'limit' => 1,
                    'offset' => 0,
                ]
            );

            return null;
        }

        return reset($models);
    }

    /**
     * @param array $criteria
     *
     * @return ModelInterface[]|array
     */
    public function findBy(array $criteria, array $orderBy = null, int $limit = null, int $offset = null): array
    {
        $table = $this->getTable();

        $this->logger->info(
            'model: find rows within table {table} with criteria {criteria}',
            ['table' => $table, 'criteria' => $criteria, 'orderBy' => $orderBy, 'limit' => $limit, 'offset' => $offset]
        );

        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($table)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
        ;

        $this->addCriteriaToQueryBuilder($qb, $criteria);
        $this->addOrderByToQueryBuilder($qb, $orderBy);

        $rows = $qb->execute()->fetchAll(\PDO::FETCH_ASSOC);

        if ([] === $rows) {
            return [];
        }

        $models = [];
        foreach ($rows as $row) {
            $this->storageCache->set($row['id'], $row);

            $models[] = $this->fromPersistence($row);
        }

        return $models;
    }

    /**
     * @param QueryBuilder $qb
     * @param array        $criteria
     */
    protected function addCriteriaToQueryBuilder(QueryBuilder $qb, array $criteria)
    {
        foreach ($criteria as $field => $value) {
            $qb->andWhere($qb->expr()->eq($field, ':'.$field));
            $qb->setParameter($field, $value);
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array|null   $orderBy
     */
    protected function addOrderByToQueryBuilder(QueryBuilder $qb, array $orderBy = null)
    {
        if (null === $orderBy) {
            return;
        }

        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy($field, $direction);
        }
    }

    /**
     * @param ModelInterface $model
     *
     * @return RepositoryInterface
     */
    public function persist(ModelInterface $model): RepositoryInterface
    {
        $id = $model->getId();
        $row = $model->toPersistence();

        $this->connection->beginTransaction();

        $alreadyExists = (bool) $this->find($id);

        $stack = new RelatedModelManipulationStack();

        foreach ($row as $field => $value) {
            if ($value instanceof ModelCollectionInterface) {
                $stack->addToRemoveModels($value->getInitialModels());
                $stack->addToPersistModels($value->getModels());
                unset($row[$field]);
            } elseif ($value instanceof ModelReferenceInterface) {
                $row[$field.'Id'] = $this->persistModelReference($value, $stack);

                unset($row[$field]);
            }
        }

        if (!$alreadyExists) {
            $this->insert($id, $row);
        } else {
            $this->update($id, $row);
        }

        $this->removeRelatedModels($stack->getToRemoveModels());
        $this->persistRelatedModels($stack->getToPersistModels());

        $this->storageCache->set($id, $row);

        $this->connection->commit();

        return $this;
    }

    /**
     * @param ModelInterface $model
     *
     * @return RepositoryInterface
     */
    public function remove(ModelInterface $model): RepositoryInterface
    {
        $id = $model->getId();
        $table = $this->getTable();

        if (null === $this->find($id)) {
            return $this;
        }

        $this->connection->beginTransaction();

        $row = $model->toPersistence();

        $this->callbackIfReference($id, $row, function (string $id, array $row) {
            $this->update($id, $row);
        });

        foreach ($row as $field => $value) {
            if ($value instanceof ModelCollectionInterface) {
                $this->removeRelatedModels($value->getInitialModels());
            } elseif ($value instanceof ModelReferenceInterface) {
                if (null !== $initialModel = $value->getInitialModel()) {
                    $this->removeRelatedModel($initialModel);
                }
            }
        }

        $this->logger->info(
            'model: remove row from table {table} with id {id}',
            ['table' => $table, 'id' => $id]
        );

        $this->connection->delete($table, ['id' => $id]);

        $this->storageCache->remove($id);

        $this->connection->commit();

        return $this;
    }

    /**
     * @return RepositoryInterface
     */
    public function clear(): RepositoryInterface
    {
        $this->storageCache->clear();

        return $this;
    }

    /**
     * @param string $id
     * @param array  $row
     */
    protected function insert(string $id, array $row)
    {
        $table = $this->getTable();

        $this->logger->info(
            'model: insert row into table {table} with id {id}',
            ['table' => $table, 'id' => $id]
        );

        $this->connection->insert($table, $row);
    }

    /**
     * @param string $id
     * @param array  $row
     */
    protected function update(string $id, array $row)
    {
        $table = $this->getTable();

        $this->logger->info(
            'model: update row from table {table} with id {id}',
            ['table' => $table, 'id' => $id]
        );

        $this->connection->update($table, $row, ['id' => $id]);
    }

    /**
     * @param string $id
     * @param array  $row
     * @param \Closure $callback
     *
     * @return bool
     */
    private function callbackIfReference(string $id, array $row, \Closure $callback): bool
    {
        $gotReference = false;
        foreach ($row as $field => $value) {
            if ($value instanceof ModelCollectionInterface) {
                unset($row[$field]);
            } elseif ($value instanceof ModelReferenceInterface) {
                $row[$field.'Id'] = null;
                $gotReference = true;
                unset($row[$field]);
            }
        }

        if ($gotReference) {
            $callback($id, $row);

            return true;
        }

        return false;
    }

    /**
     * @param ModelReferenceInterface       $reference
     * @param RelatedModelManipulationStack $stack
     *
     * @return null|string
     */
    private function persistModelReference(ModelReferenceInterface $reference, RelatedModelManipulationStack $stack)
    {
        $initialModel = $reference->getInitialModel();
        $model = $reference->getModel();

        if (null !== $initialModel && (null === $model || $model->getId() !== $initialModel->getId())) {
            $stack->addToRemoveModel($initialModel);
        }

        if (null !== $model) {
            $this->persistRelatedModel($model);

            return $model->getId();
        }

        return null;
    }

    /**
     * @param ModelInterface[]|array $toRemoveModels
     */
    private function persistRelatedModels(array $toRemoveModels)
    {
        foreach ($toRemoveModels as $toRemoveRelatedModel) {
            $this->persistRelatedModel($toRemoveRelatedModel);
        }
    }

    /**
     * @param ModelInterface $model
     */
    private function persistRelatedModel(ModelInterface $model)
    {
        $this->resolver->persist($model);
    }

    /**
     * @param ModelInterface[]|array $toRemoveModels
     */
    private function removeRelatedModels(array $toRemoveModels)
    {
        foreach ($toRemoveModels as $toRemoveRelatedModel) {
            $this->removeRelatedModel($toRemoveRelatedModel);
        }
    }

    /**
     * @param ModelInterface $model
     */
    private function removeRelatedModel(ModelInterface $model)
    {
        $this->resolver->remove($model);
    }

    /**
     * @param array $row
     *
     * @return ModelInterface
     */
    abstract protected function fromPersistence(array $row): ModelInterface;

    /**
     * @return string
     */
    abstract protected function getTable(): string;
}
