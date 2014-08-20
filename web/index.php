<?php

ini_set('display_errors', 0);

require_once __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../src/app.php';
require __DIR__.'/../config/prod.php';
require __DIR__.'/../src/controllers.php';
$app->run();

/*
require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Debug\Debug;

$env = getenv('APP_ENV') ?: 'dev';
if ($env == 'dev') {
    // Dev environment configuration
    $config_file = __DIR__ . '/../config/dev.php';
    Debug::enable();
} else {
    // Default environment configuration
    ini_set('display_errors', 0);
    $config_file = __DIR__ . '/../config/prod.php';
}

$app = require __DIR__.'/../src/app.php';
require $config_file;
require __DIR__.'/../src/controllers.php';

// echo '<pre>'; var_dump($app);
$app->run();
*/
