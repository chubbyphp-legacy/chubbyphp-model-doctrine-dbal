<?php

declare(strict_types=1);

namespace Chubbyphp\Model\Doctrine\DBAL;

use Chubbyphp\Model\Cache\ModelCache;
use Chubbyphp\Model\Cache\ModelCacheInterface;
use Chubbyphp\Model\Collection\ModelCollectionInterface;
use Chubbyphp\Model\ModelInterface;
use Chubbyphp\Model\RepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract class AbstractDoctrineRepository implements RepositoryInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ModelCacheInterface
     */
    private $cache;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Connection               $connection
     * @param ModelCacheInterface|null $cache
     * @param LoggerInterface|null     $logger
     */
    public function __construct(
        Connection $connection,
        ModelCacheInterface $cache = null,
        LoggerInterface $logger = null
    ) {
        $this->connection = $connection;
        $this->cache = $cache ?? new ModelCache();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param string $id
     *
     * @return ModelInterface|null
     */
    public function find(string $id)
    {
        $modelClass = $this->getModelClass();

        $this->logger->info('model: find model {model} with id {id}', ['model' => $modelClass, 'id' => $id]);

        if ($this->cache->has($id)) {
            return $this->fromRow($this->cache->get($id));
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')->from($this->getTable())->where($qb->expr()->eq('id', ':id'))->setParameter('id', $id);

        $row = $qb->execute()->fetch(\PDO::FETCH_ASSOC);
        if (false === $row) {
            $this->logger->warning(
                'model: model {model} with id {id} not found',
                ['model' => $modelClass, 'id' => $id]
            );

            return null;
        }

        $this->cache->set($row['id'], $row);

        return $this->fromRow($row);
    }

    /**
     * @param array $criteria
     *
     * @return null|ModelInterface
     */
    public function findOneBy(array $criteria)
    {
        $modelClass = $this->getModelClass();

        $this->logger->info(
            'model: find model {model} with criteria {criteria}',
            ['model' => $modelClass, 'criteria' => $criteria]
        );

        $qb = $this->getFindByQueryBuilder($criteria)->setMaxResults(1);

        $row = $qb->execute()->fetch(\PDO::FETCH_ASSOC);
        if (false === $row) {
            $this->logger->warning(
                'model: model {model} with criteria {criteria} not found',
                ['model' => $modelClass, 'criteria' => $criteria]
            );

            return null;
        }

        $this->cache->set($row['id'], $row);

        return $this->fromRow($row);
    }

    /**
     * @param array $criteria
     *
     * @return ModelInterface[]|array
     */
    public function findBy(array $criteria, array $orderBy = null, int $limit = null, int $offset = null): array
    {
        $modelClass = $this->getModelClass();

        $this->logger->info(
            'model: find model {model} with criteria {criteria}',
            ['model' => $modelClass, 'criteria' => $criteria]
        );

        $qb = $this
            ->getFindByQueryBuilder($criteria)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
        ;

        if (null !== $orderBy) {
            foreach ($orderBy as $field => $direction) {
                $qb->addOrderBy($field, $direction);
            }
        }

        $rows = $qb->execute()->fetchAll(\PDO::FETCH_ASSOC);

        if ([] === $rows) {
            return [];
        }

        $models = [];
        foreach ($rows as $row) {
            $this->cache->set($row['id'], $row);
            $models[] = $this->fromRow($row);
        }

        return $models;
    }

    /**
     * @param array $criteria
     *
     * @return QueryBuilder
     */
    private function getFindByQueryBuilder(array $criteria = []): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')->from($this->getTable());

        foreach ($criteria as $field => $value) {
            $qb->andWhere($qb->expr()->eq($field, ':'.$field));
            $qb->setParameter($field, $value);
        }

        return $qb;
    }

    /**
     * @param ModelInterface $model
     */
    public function persist(ModelInterface $model)
    {
        $this->logger->info(
            'model: persist model {model} with id {id}',
            ['model' => get_class($model), 'id' => $model->getId()]
        );

        $row = $model->toRow();
        foreach ($row as $key => $value) {
            if ($value instanceof ModelCollectionInterface) {
                unset($row[$key]);
            }
        }

        if (null === $this->find($model->getId())) {
            $this->connection->insert($this->getTable(), $row);
        } else {
            $this->connection->update($this->getTable(), $row, ['id' => $model->getId()]);
        }

        $this->cache->set($model->getId(), $row);
    }

    /**
     * @param ModelInterface $model
     */
    public function remove(ModelInterface $model)
    {
        $this->logger->info(
            'model: remove model {model} with id {id}',
            ['model' => get_class($model), 'id' => $model->getId()]
        );

        $this->connection->delete($this->getTable(), ['id' => $model->getId()]);

        $this->cache->remove($model->getId());
    }

    /**
     * @param array $row
     * @return ModelInterface
     */
    protected function fromRow(array $row): ModelInterface
    {
        /** @var ModelInterface $modelClass */
        $modelClass = $this->getModelClass();

        return $modelClass::fromRow($row);
    }

    /**
     * @return string
     */
    abstract protected function getTable(): string;
}
