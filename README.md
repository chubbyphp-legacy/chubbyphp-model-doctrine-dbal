# chubbyphp-model-doctrine-dbal

[![Build Status](https://api.travis-ci.org/chubbyphp/chubbyphp-model-doctrine-dbal.png?branch=master)](https://travis-ci.org/chubbyphp/chubbyphp-model-doctrine-dbal)
[![Total Downloads](https://poser.pugx.org/chubbyphp/chubbyphp-model-doctrine-dbal/downloads.png)](https://packagist.org/packages/chubbyphp/chubbyphp-model-doctrine-dbal)
[![Latest Stable Version](https://poser.pugx.org/chubbyphp/chubbyphp-model-doctrine-dbal/v/stable.png)](https://packagist.org/packages/chubbyphp/chubbyphp-model-doctrine-dbal)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/chubbyphp/chubbyphp-model-doctrine-dbal/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/chubbyphp/chubbyphp-model-doctrine-dbal/?branch=master)

## Description

A simple [chubbyphp/chubbyphp-model][2] implementation for relational databases based on [doctrine/dbal][3].

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

### Model

#### MyModel

```{.php}
<?php

declare(strict_types=1);

namespace MyProject\Model;

use Chubbyphp\Model\Collection\ModelCollection;
use Chubbyphp\Model\ModelInterface;
use Chubbyphp\Model\Reference\ModelReference;
use Ramsey\Uuid\Uuid;

final class MyModel implements ModelInterface
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $category;

    /**
     * @var ModelReference
     */
    private $oneToOne;

    /**
     * @var ModelCollection
     */
    private $oneToMany;

    /**
     * @param string|null $id
     * @return MyModel
     */
    public static function create(string $id = null): MyModel
    {
        $myModel = new self;
        $myModel->id = $id ?? (string) Uuid::uuid4();
        $myModel->oneToOne = new ModelReference();
        $myModel->oneToMany = new ModelCollection();

        return $myModel;
    }

    private function __construct() {}

    /**
     * @param array $data
     *
     * @return ModelInterface
     */
    public static function fromPersistence(array $data): ModelInterface
    {
        $model = new self;
        $model->id = $data['id'];
        $model->name = $data['name'];
        $model->category = $data['category'];
        $model->oneToOne = $data['oneToOne'];
        $model->oneToMany = $data['oneToMany'];

        return $model;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName(string $name): MyModel
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $category
     * @return self
     */
    public function setCategory(string $category): MyModel
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * @param MyEmbeddedModel|null $oneToOne
     * @return self
     */
    public function setOneToOne(MyEmbeddedModel $oneToOne = null): MyModel
    {
        $this->oneToOne->setModel($oneToOne);

        return $this;
    }

    /**
     * @return MyEmbeddedModel|ModelInterface|null
     */
    public function getOneToOne()
    {
        return $this->oneToOne->getModel();
    }

    /**
     * @param MyEmbeddedModel[]|array $oneToMany
     * @return $this
     */
    public function setOneToMany(array $oneToMany)
    {
        $this->oneToMany->setModels($oneToMany);

        return $this;
    }

    /**
     * @return MyEmbeddedModel[]|ModelInterface[]|array
     */
    public function getOneToMany()
    {
        return $this->oneToMany->getModels();
    }

    /**
     * @return array
     */
    public function toPersistence(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'oneToOne' => $this->oneToOne,
            'oneToMany' => $this->oneToMany
        ];
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'oneToOne' => $this->oneToOne->jsonSerialize(),
            'oneToMany' => $this->oneToMany->jsonSerialize()
        ];
    }
}
```

#### MyEmbeddedModel

```{.php}
<?php

declare(strict_types=1);

namespace MyProject\Model;

use Chubbyphp\Model\ModelInterface;
use Ramsey\Uuid\Uuid;

final class MyEmbeddedModel implements ModelInterface
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $modelId;

    /**
     * @var string
     */
    private $name;

    /**
     * @param string $modelId
     * @param string|null $id
     * @return MyEmbeddedModel
     */
    public function create(string $modelId, string $id = null): MyEmbeddedModel
    {
        $myEmbeddedModel = new self;
        $myEmbeddedModel->id = $id ?? (string) Uuid::uuid4();
        $myEmbeddedModel->modelId = $modelId;

        return $myEmbeddedModel;
    }

    private function __construct() {}

    /**
     * @param array $data
     *
     * @return MyEmbeddedModel|ModelInterface
     */
    public static function fromPersistence(array $data): ModelInterface
    {
        $model = new self;
        $model->id = $data['id'];
        $model->modelId = $data['modelId'];
        $model->name = $data['name'];

        return $model;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $name
     * @return MyEmbeddedModel
     */
    public function setName(string $name): MyEmbeddedModel
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function toPersistence(): array
    {
        return [
            'id' => $this->id,
            'modelId' => $this->modelId,
            'name' => $this->name
        ];
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'name' => $this->name
        ];
    }
}
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
[2]: https://github.com/chubbyphp/chubbyphp-model
[3]: https://github.com/doctrine/dbal

## Copyright

Dominik Zogg 2016
