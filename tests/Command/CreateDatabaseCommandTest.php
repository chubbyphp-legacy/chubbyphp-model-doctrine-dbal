<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\Command;

use Chubbyphp\Model\Doctrine\DBAL\Command\CreateDatabaseCommand;
use Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits\GetConnectionTrait;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @covers \Chubbyphp\Model\Doctrine\DBAL\Command\CreateDatabaseCommand
 */
final class CreateDatabaseCommandTest extends \PHPUnit_Framework_TestCase
{
    use GetConnectionTrait;

    public static function setUpBeforeClass()
    {
        $code = <<<'EOT'
namespace Doctrine\DBAL
{
    use Doctrine\DBAL\Connection;
    
    class DriverManager
    {    
        /**
         * @var Connection
         */
        private static $connection;
        
        public static function setConnection(Connection $connection)
        {
            self::$connection = $connection;
        }
    
        public static function getConnection(array $parameters)
        {
            return self::$connection;
        }
    }
}
EOT;

        eval($code);
    }

    public function testWithPath()
    {
        $connection = $this->getConnection([
            'params' => [
                'master' => [
                    'path' => '/path/to/database',
                ],
            ],
        ]);

        $tmpConnection = $this->getConnection([
            'schemaManager' => $this->getSchemaManager([
                'listDatabases' => [],
                'createDatabase' => [
                    [
                        'arguments' => [
                            '/path/to/database',
                        ],
                    ],
                ],
            ]),
        ]);

        DriverManager::setConnection($tmpConnection);

        $input = new ArrayInput([]);

        $output = new BufferedOutput();

        $command = new CreateDatabaseCommand($connection);
        $command($input, $output);

        $outputBuffer = $output->fetch();

        self::assertContains('Created database /path/to/database', $outputBuffer);
    }

    public function testWithDbName()
    {
        $connection = $this->getConnection([
            'params' => [
                'master' => [
                    'dbname' => 'database',
                ],
            ],
        ]);

        $tmpConnection = $this->getConnection([
            'schemaManager' => $this->getSchemaManager([
                'listDatabases' => [],
                'createDatabase' => [
                    [
                        'arguments' => [
                            'database',
                        ],
                    ],
                ],
            ]),
            'databasePlatform' => $this->getPlatform(),
        ]);

        DriverManager::setConnection($tmpConnection);

        $input = new ArrayInput([]);

        $output = new BufferedOutput();

        $command = new CreateDatabaseCommand($connection);
        $command($input, $output);

        $outputBuffer = $output->fetch();

        self::assertContains('Created database database', $outputBuffer);
    }

    public function testWithDbNameAndExceptionOnCreate()
    {
        $connection = $this->getConnection([
            'params' => [
                'master' => [
                    'dbname' => 'database',
                ],
            ],
        ]);

        $tmpConnection = $this->getConnection([
            'schemaManager' => $this->getSchemaManager([
                'listDatabases' => [],
                'createDatabase' => [
                    [
                        'arguments' => [
                            'database',
                        ],
                        'exception' => new \RuntimeException('Failed to create database'),
                    ],
                ],
            ]),
            'databasePlatform' => $this->getPlatform(),
        ]);

        DriverManager::setConnection($tmpConnection);

        $input = new ArrayInput([]);

        $output = new BufferedOutput();

        $command = new CreateDatabaseCommand($connection);
        $command($input, $output);

        $outputBuffer = $output->fetch();

        self::assertContains('Could not create database database', $outputBuffer);
    }

    public function testWithDbNameAlreadyExists()
    {
        $connection = $this->getConnection([
            'params' => [
                'master' => [
                    'dbname' => 'database',
                ],
            ],
        ]);

        $tmpConnection = $this->getConnection([
            'schemaManager' => $this->getSchemaManager([
                'listDatabases' => [
                    'database',
                ],
                'createDatabase' => [
                    [
                        'arguments' => [
                            'database',
                        ],
                    ],
                ],
            ]),
            'databasePlatform' => $this->getPlatform(),
        ]);

        DriverManager::setConnection($tmpConnection);

        $input = new ArrayInput([]);

        $output = new BufferedOutput();

        $command = new CreateDatabaseCommand($connection);
        $command($input, $output);

        $outputBuffer = $output->fetch();

        self::assertContains('Database database already exists', $outputBuffer);
    }

    public function testWithoutNameOrPath()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage("Connection does not contain a 'path' or 'dbname' parameter");

        $connection = $this->getConnection([
            'params' => [
                'master' => [],
            ],
        ]);

        $input = new ArrayInput([]);

        $output = new BufferedOutput();

        $command = new CreateDatabaseCommand($connection);
        $command($input, $output);
    }
}
