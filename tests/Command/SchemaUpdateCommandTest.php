<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\Command;

use Chubbyphp\Model\Doctrine\DBAL\Command\SchemaUpdateCommand;
use Chubbyphp\Tests\Model\Doctrine\DBAL\TestHelperTraits\GetConnectionTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @covers \Chubbyphp\Model\Doctrine\DBAL\Command\SchemaUpdateCommand
 */
final class SchemaUpdateCommandTest extends \PHPUnit_Framework_TestCase
{
    use GetConnectionTrait;

    public function testNoChangesNeeded()
    {
        $schemaPath = __DIR__.'/../Resources/schema.php';

        $schema = require $schemaPath;

        $platform = $this->getPlatform();

        $connection = $this->getConnection([
            'schemaManager' => $this->getSchemaManager([
                'createSchema' => [
                    $this->getSchema([
                        'migrateToSql' => [
                            [
                                'arguments' => [
                                    $schema,
                                    $platform,
                                ],
                                'return' => [],
                            ],
                        ],
                    ]),
                ],
            ]),
            'databasePlatform' => $platform,
        ]);

        $inputDefinition = new InputDefinition([
            new InputOption('dump', null, InputOption::VALUE_NONE, 'Dumps the generated SQL statements'),
            new InputOption('force', 'f', InputOption::VALUE_NONE, 'Executes the generated SQL statements.'),
        ]);

        $input = new ArrayInput([
            '--dump' => false,
            '--force' => false,
        ], $inputDefinition);

        $output = new BufferedOutput();

        $command = new SchemaUpdateCommand($connection, $schemaPath);
        $command($input, $output);

        $outputBuffer = $output->fetch();

        self::assertContains('No schema changes required', $outputBuffer);
    }

    public function testMissingOptions()
    {
        $schemaPath = __DIR__.'/../Resources/schema.php';

        $schema = require $schemaPath;

        $platform = $this->getPlatform();

        $connection = $this->getConnection([
            'schemaManager' => $this->getSchemaManager([
                'createSchema' => [
                    $this->getSchema([
                        'migrateToSql' => [
                            [
                                'arguments' => [
                                    $schema,
                                    $platform,
                                ],
                                'return' => [
                                    <<<'EOT'
CREATE TABLE `models` (
  `id` char(36) COLLATE utf8_unicode_ci NOT NULL COMMENT '(DC2Type:guid)',
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8_unicode_ci NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOT
                                ],
                            ],
                        ],
                    ]),
                ],
            ]),
            'databasePlatform' => $platform,
        ]);

        $inputDefinition = new InputDefinition([
            new InputOption('dump', null, InputOption::VALUE_NONE, 'Dumps the generated SQL statements'),
            new InputOption('force', 'f', InputOption::VALUE_NONE, 'Executes the generated SQL statements.'),
        ]);

        $input = new ArrayInput([
            '--dump' => false,
            '--force' => false,
        ], $inputDefinition);

        $output = new BufferedOutput();

        $command = new SchemaUpdateCommand($connection, $schemaPath);
        $command($input, $output);

        $outputBuffer = $output->fetch();

        self::assertContains(
            'Please run the operation by passing one - or both - of the following options:',
            $outputBuffer
        );
    }

    public function testDumpAndForce()
    {
        $schemaPath = __DIR__.'/../Resources/schema.php';

        $schema = require $schemaPath;

        $platform = $this->getPlatform();

        $sql = <<<'EOT'
CREATE TABLE `models` (
  `id` char(36) COLLATE utf8_unicode_ci NOT NULL COMMENT '(DC2Type:guid)',
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8_unicode_ci NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOT;

        $connection = $this->getConnection([
            'beginTransaction' => 1,
            'commit' => 1,
            'exec' => [
                [
                    'arguments' => [
                        $sql,
                    ],
                    'return' => 1,
                ],
            ],
            'schemaManager' => $this->getSchemaManager([
                'createSchema' => [
                    $this->getSchema([
                        'migrateToSql' => [
                            [
                                'arguments' => [
                                    $schema,
                                    $platform,
                                ],
                                'return' => [
                                    $sql,
                                ],
                            ],
                        ],
                    ]),
                ],
            ]),
            'databasePlatform' => $platform,
        ]);

        $inputDefinition = new InputDefinition([
            new InputOption('dump', null, InputOption::VALUE_NONE, 'Dumps the generated SQL statements'),
            new InputOption('force', 'f', InputOption::VALUE_NONE, 'Executes the generated SQL statements.'),
        ]);

        $input = new ArrayInput([
            '--dump' => true,
            '--force' => true,
        ], $inputDefinition);

        $output = new BufferedOutput();

        $command = new SchemaUpdateCommand($connection, $schemaPath);
        $command($input, $output);

        $outputBuffer = $output->fetch();

        self::assertContains('Begin transaction', $outputBuffer);
        self::assertContains('CREATE TABLE `models`', $outputBuffer);
        self::assertContains('Commit', $outputBuffer);
    }

    public function testDump()
    {
        $schemaPath = __DIR__.'/../Resources/schema.php';

        $schema = require $schemaPath;

        $platform = $this->getPlatform();

        $connection = $this->getConnection([
            'schemaManager' => $this->getSchemaManager([
                'createSchema' => [
                    $this->getSchema([
                        'migrateToSql' => [
                            [
                                'arguments' => [
                                    $schema,
                                    $platform,
                                ],
                                'return' => [
                                    <<<'EOT'
CREATE TABLE `models` (
  `id` char(36) COLLATE utf8_unicode_ci NOT NULL COMMENT '(DC2Type:guid)',
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8_unicode_ci NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOT
                                ],
                            ],
                        ],
                    ]),
                ],
            ]),
            'databasePlatform' => $platform,
        ]);

        $inputDefinition = new InputDefinition([
            new InputOption('dump', null, InputOption::VALUE_NONE, 'Dumps the generated SQL statements'),
            new InputOption('force', 'f', InputOption::VALUE_NONE, 'Executes the generated SQL statements.'),
        ]);

        $input = new ArrayInput([
            '--dump' => true,
            '--force' => false,
        ], $inputDefinition);

        $output = new BufferedOutput();

        $command = new SchemaUpdateCommand($connection, $schemaPath);
        $command($input, $output);

        $outputBuffer = $output->fetch();

        self::assertContains('Begin transaction', $outputBuffer);
        self::assertContains('CREATE TABLE `models`', $outputBuffer);
        self::assertContains('Commit', $outputBuffer);
    }

    public function testForce()
    {
        $schemaPath = __DIR__.'/../Resources/schema.php';

        $schema = require $schemaPath;

        $platform = $this->getPlatform();

        $sql = <<<'EOT'
CREATE TABLE `models` (
  `id` char(36) COLLATE utf8_unicode_ci NOT NULL COMMENT '(DC2Type:guid)',
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8_unicode_ci NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOT;

        $connection = $this->getConnection([
            'beginTransaction' => 1,
            'commit' => 1,
            'exec' => [
                [
                    'arguments' => [
                        $sql,
                    ],
                    'return' => 1,
                ],
            ],
            'schemaManager' => $this->getSchemaManager([
                'createSchema' => [
                    $this->getSchema([
                        'migrateToSql' => [
                            [
                                'arguments' => [
                                    $schema,
                                    $platform,
                                ],
                                'return' => [
                                    $sql,
                                ],
                            ],
                        ],
                    ]),
                ],
            ]),
            'databasePlatform' => $platform,
        ]);

        $inputDefinition = new InputDefinition([
            new InputOption('dump', null, InputOption::VALUE_NONE, 'Dumps the generated SQL statements'),
            new InputOption('force', 'f', InputOption::VALUE_NONE, 'Executes the generated SQL statements.'),
        ]);

        $input = new ArrayInput([
            '--dump' => false,
            '--force' => true,
        ], $inputDefinition);

        $output = new BufferedOutput();

        $command = new SchemaUpdateCommand($connection, $schemaPath);
        $command($input, $output);

        $outputBuffer = $output->fetch();

        self::assertNotContains('Begin transaction', $outputBuffer);
        self::assertNotContains('CREATE TABLE `models`', $outputBuffer);
        self::assertNotContains('Commit', $outputBuffer);
    }
}
