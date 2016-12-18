<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\Command;

use Chubbyphp\Model\Doctrine\DBAL\Command\RunSqlCommand;
use Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits\GetConnectionTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @covers \Chubbyphp\Model\Doctrine\DBAL\Command\RunSqlCommand
 */
final class RunSqlCommandTest extends \PHPUnit_Framework_TestCase
{
    use GetConnectionTrait;

    public function testSelect()
    {
        $sql = 'SELECT * FROM models';

        $connection = $this->getConnection([
            'fetchAll' => [
                [
                    'arguments' => [
                        'sql' => $sql,
                        'params' => [],
                        'types' => [],
                    ],
                    'return' => [
                        [
                            'id' => 'id1',
                            'name' => 'name1',
                            'category' => 'category1',
                        ],
                        [
                            'id' => 'id2',
                            'name' => 'name2',
                            'category' => 'category2',
                        ],
                    ],
                ],
            ],
        ]);

        $inputDefinition = new InputDefinition([
            new InputArgument('sql', InputArgument::REQUIRED, 'The SQL statement to execute.'),
            new InputOption('depth', null, InputOption::VALUE_REQUIRED, 'Dumping depth of result set.', 7),
        ]);

        $input = new ArrayInput([
            'sql' => $sql,
            '--depth' => 5,
        ], $inputDefinition);

        $output = new BufferedOutput();

        $command = new RunSqlCommand($connection);
        $command($input, $output);

        $outputBuffer = $output->fetch();

        self::assertContains("'id' => string 'id1'", $outputBuffer);
        self::assertContains("'name' => string 'name1'", $outputBuffer);
        self::assertContains("'category' => string 'category1", $outputBuffer);

        self::assertContains("'id' => string 'id2'", $outputBuffer);
        self::assertContains("'name' => string 'name2'", $outputBuffer);
        self::assertContains("'category' => string 'category2", $outputBuffer);
    }

    public function testInsert()
    {
        $sql = "INSERT INTO models (id, name, category) VALUES ('id1', 'name1', 'category1')";

        $connection = $this->getConnection([
            'executeUpdate' => [
                [
                    'arguments' => [
                        'sql' => $sql,
                        'params' => [],
                        'types' => [],
                    ],
                    'return' => 1,
                ],
            ],
        ]);

        $inputDefinition = new InputDefinition([
            new InputArgument('sql', InputArgument::REQUIRED, 'The SQL statement to execute.'),
            new InputOption('depth', null, InputOption::VALUE_REQUIRED, 'Dumping depth of result set.', 7),
        ]);

        $input = new ArrayInput([
            'sql' => $sql,
            '--depth' => 5,
        ], $inputDefinition);

        $output = new BufferedOutput();

        $command = new RunSqlCommand($connection);
        $command($input, $output);

        $outputBuffer = $output->fetch();

        self::assertContains('int 1', $outputBuffer);
    }

    public function testWithNullQuery()
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage("Argument 'SQL' is required in order to execute this command correctly.");

        $connection = $this->getConnection();

        $inputDefinition = new InputDefinition([
            new InputArgument('sql', InputArgument::REQUIRED, 'The SQL statement to execute.'),
            new InputOption('depth', null, InputOption::VALUE_REQUIRED, 'Dumping depth of result set.', 7),
        ]);

        $input = new ArrayInput([
            'sql' => null,
        ], $inputDefinition);

        $output = new BufferedOutput();

        $command = new RunSqlCommand($connection);
        $command($input, $output);
    }

    public function testWithInvalidDepth()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage("Option 'depth' must contains an integer value");

        $connection = $this->getConnection();

        $inputDefinition = new InputDefinition([
            new InputArgument('sql', InputArgument::REQUIRED, 'The SQL statement to execute.'),
            new InputOption('depth', null, InputOption::VALUE_REQUIRED, 'Dumping depth of result set.', 7),
        ]);

        $input = new ArrayInput([
            'sql' => 'SELECT * FROM models',
            '--depth' => 'test',
        ], $inputDefinition);

        $output = new BufferedOutput();

        $command = new RunSqlCommand($connection);
        $command($input, $output);
    }
}
