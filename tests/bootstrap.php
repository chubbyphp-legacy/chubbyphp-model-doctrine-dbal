<?php

$loader = require __DIR__.'/../vendor/autoload.php';
$loader->setPsr4('Chubbyphp\Tests\Model\\', __DIR__.'/../vendor/chubbyphp/chubbyphp-model/tests');
$loader->setPsr4('Chubbyphp\Tests\Model\Doctrine\DBAL\\', __DIR__);
