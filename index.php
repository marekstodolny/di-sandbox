<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require './DI.php';
require './DIReciever.php';

class ExampleService {
    public function execute()
    {
        echo "Executed! \n";
    }
}

$di = new DI();
$globalTestVar = "Test \n";
$di->register($globalTestVar, 'globalTestVar');

$service = new ExampleService();
$di->register($service, 'ExampleService');

$dir = new DIReciever();

$di->inject('DIReciever->test', [], $dir);
