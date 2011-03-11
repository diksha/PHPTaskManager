#!/usr/bin/env php
<?php
error_reporting(E_ALL | E_NOTICE);
ini_set('display_errors', 1);
require_once('Manager.php');
try {
    if(!array_key_exists(1, $argv))
        throw new RuntimeException(sprintf('No argument given. Usage: php %s crontabfile', __FILE__));

    $cron_file = $argv[1];

    $manager = new Manager($cron_file);
    $manager->executeAll();
}
catch(Exception $e) {
    echo $e->getMessage();
    echo "\n------------------------------------\n";
    echo $e->getTraceAsString();
    echo "\n";
}
