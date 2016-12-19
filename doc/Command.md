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
