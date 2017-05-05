<?php

$loader = require __DIR__.'/../vendor/autoload.php';
$loader->setPsr4('Chubbyphp\Tests\Model\Doctrine\DBAL\\', __DIR__);

$chubbyphpModelResourceDir = __DIR__.'/../vendor/chubbyphp/chubbyphp-model/tests/Resources';
$chubbyphpModelDoctrineResourceDir = __DIR__.'/Resources';

$loader->addClassMap([
    \MyProject\Model\MyModel::class => $chubbyphpModelResourceDir.'/Model/MyModel.php',
    \MyProject\Model\MyEmbeddedModel::class => $chubbyphpModelResourceDir.'/Model/MyEmbeddedModel.php',
    \MyProject\Repository\MyEmbeddedRepository::class => $chubbyphpModelDoctrineResourceDir.'/Repository/MyEmbeddedRepository.php',
    \MyProject\Repository\MyModelRepository::class => $chubbyphpModelDoctrineResourceDir.'/Repository/MyModelRepository.php',
]);
