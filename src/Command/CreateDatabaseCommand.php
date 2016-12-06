<?php

declare(strict_types=1);

namespace Chubbyphp\Model\Doctrine\DBAL\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This file is part of the Doctrine Bundle.
 *
 * The code was originally distributed inside the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 * (c) Doctrine Project, Benjamin Eberlei <kontakt@beberlei.de>
 */
final class CreateDatabaseCommand
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null
     */
    public function __invoke(InputInterface $input, OutputInterface $output)
    {
        $parameters = $this->getParameters();

        $name = $this->getName($parameters);

        // Need to get rid of _every_ occurrence of dbname from connection configuration
        unset($parameters['dbname'], $parameters['path'], $parameters['url']);

        $tmpConnection = DriverManager::getConnection($parameters);
        $shouldNotCreateDatabase = in_array($name, $tmpConnection->getSchemaManager()->listDatabases());

        // Only quote if we don't have a path
        if (!isset($parameters['path'])) {
            $name = $tmpConnection->getDatabasePlatform()->quoteSingleIdentifier($name);
        }

        return $this->createDatabase($output, $tmpConnection, $name, $shouldNotCreateDatabase);
    }

    /**
     * @return array
     */
    private function getParameters(): array
    {
        $params = $this->connection->getParams();

        if (isset($params['master'])) {
            $params = $params['master'];
        }

        return $params;
    }

    /**
     * @return string
     */
    private function getName(array $parameters): string
    {
        if (isset($parameters['path'])) {
            return $parameters['path'];
        }

        if (isset($parameters['dbname'])) {
            return $parameters['dbname'];
        }

        throw new \InvalidArgumentException("Connection does not contain a 'path' or 'dbname' parameter.");
    }

    /**
     * @param OutputInterface $output
     * @param Connection      $tmpConnection
     * @param string          $name
     * @param bool            $shouldNotCreateDatabase
     *
     * @return int
     */
    private function createDatabase(
        OutputInterface $output,
        Connection $tmpConnection,
        string $name,
        bool $shouldNotCreateDatabase
    ): int {
        try {
            if ($shouldNotCreateDatabase) {
                $output->writeln(sprintf('<info>Database <comment>%s</comment> already exists.</info>', $name));
            } else {
                $tmpConnection->getSchemaManager()->createDatabase($name);
                $output->writeln(sprintf('<info>Created database <comment>%s</comment>', $name));
            }

            return 0;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Could not create database <comment>%s</comment></error>', $name));
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return 1;
        }
    }
}
