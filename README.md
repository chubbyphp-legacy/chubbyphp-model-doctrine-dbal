# chubbyphp-model-doctrine-dbal

[![Build Status](https://api.travis-ci.org/chubbyphp/chubbyphp-model-doctrine-dbal.png?branch=master)](https://travis-ci.org/chubbyphp/chubbyphp-model-doctrine-dbal)
[![Total Downloads](https://poser.pugx.org/chubbyphp/chubbyphp-model-doctrine-dbal/downloads.png)](https://packagist.org/packages/chubbyphp/chubbyphp-model-doctrine-dbal)
[![Latest Stable Version](https://poser.pugx.org/chubbyphp/chubbyphp-model-doctrine-dbal/v/stable.png)](https://packagist.org/packages/chubbyphp/chubbyphp-model-doctrine-dbal)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/chubbyphp/chubbyphp-model-doctrine-dbal/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/chubbyphp/chubbyphp-model-doctrine-dbal/?branch=master)

## Description

## Requirements

 * php: ~7.0
 * chubbyphp/chubbyphp-model: ~2.0
 * container-interop/container-interop: ~1.1
 * doctrine/common: ~2.5
 * doctrine/dbal: ~2.5

## Suggests

 * chubbyphp/lazy: ~1.0,
 * symfony/console: ~2.7|~3.0

## Installation

Through [Composer](http://getcomposer.org) as [chubbyphp/chubbyphp-model-doctrine-dbal][1].

## Usage

### Commands

#### CreateDatabaseCommand

```{.php}
<?php

use Chubbyphp\Lazy\LazyCommand;
use Chubbyphp\Model\Doctrine\DBAL\Command\CreateDatabaseCommand;
use Pimple\Container;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;

$application = new Application();

$input = new ArgvInput();

$container[CreateDatabaseCommand::class] = function () use ($container) {
    return new CreateDatabaseCommand($connection);
};

$console->add(
    new LazyCommand(
        $container,
        CreateDatabaseCommand::class,
        'chubbyphp:model:dbal:database:create'
    )
);

$console->run($input);
```

#### RunSqlCommand

```{.php}
<?php

use Chubbyphp\Lazy\LazyCommand;
use Chubbyphp\Model\Doctrine\DBAL\Command\RunSqlCommand;
use Interop\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

$application = new Application();

$input = new ArgvInput();

$container[RunSqlCommand::class] = function () use ($container) {
    return new RunSqlCommand($connection);
};

$console->add(
    new LazyCommand(
        $container,
        RunSqlCommand::class,
        'chubbyphp:model:dbal:database:run:sql',
        [
            new InputArgument('sql', InputArgument::REQUIRED, 'The SQL statement to execute.'),
            new InputOption('depth', null, InputOption::VALUE_REQUIRED, 'Dumping depth of result set.', 7),
        ]
    )
);

$console->run($input);
```

#### SchemaUpdateCommand

```{.php}
<?php

use Chubbyphp\Lazy\LazyCommand;
use Chubbyphp\Model\Doctrine\DBAL\Command\SchemaUpdateCommand;
use Interop\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

$application = new Application();

$input = new ArgvInput();

$container[SchemaUpdateCommand::class] = function () use ($container) {
    return new SchemaUpdateCommand($connection, '/path/to/schema/file');
};

$console->add(
    new LazyCommand(
        $container,
        SchemaUpdateCommand::class,
        'chubbyphp:model:dbal:database:schema:update',
        [
            new InputOption('dump', null, InputOption::VALUE_NONE, 'Dumps the generated SQL statements'),
            new InputOption('force', 'f', InputOption::VALUE_NONE, 'Executes the generated SQL statements.'),
        ]
    )
);

$console->run($input);
```

### Repository

#### MyModelRepository

```{.php}
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
            $this->resolver->lazyFind(MyEmbeddedModel::class, $row['oneToOneId'])
        );

        $row['oneToMany'] = new LazyModelCollection(
            $this->resolver->lazyFindBy(MyEmbeddedModel::class, ['modelId' => $row['id']])
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
```

#### MyEmbeddedRepository

```{.php}
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
     * @return bool
     */
    public function isResponsible(string $modelClass): bool
    {
        return MyEmbeddedModel::class === $modelClass;
    }

    /**
     * @param array $row
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
```

[1]: https://packagist.org/packages/chubbyphp/chubbyphp-model-doctrine-dbal

## Copyright

Dominik Zogg 2016
