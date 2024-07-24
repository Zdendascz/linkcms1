<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../class/control.php';
require __DIR__ . '/../class/admin.php';

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;
use Illuminate\Database\Capsule\Manager as Capsule;

//******************** Vytvoření loggeru
$logger = new Logger('linkcms');
$debugHandler = new RotatingFileHandler(__DIR__ . '/../logs/info.log', 0, Logger::INFO);
$logger->pushHandler($debugHandler);
$debugHandler = new RotatingFileHandler(__DIR__ . '/../logs/debug.log', 0, Logger::DEBUG);
$logger->pushHandler($debugHandler);
$warningHandler = new StreamHandler(__DIR__ . '/../logs/warning.log', Logger::WARNING);
$warningHandler->setFormatter(new LineFormatter(null, null, true, true));
$logger->pushHandler($warningHandler);
$errorHandler = new StreamHandler(__DIR__ . '/../logs/error.log', Logger::ERROR);
$logger->pushHandler($errorHandler);

//******************** Funkce pro načtení konfiguračních souborů
function loadConfiguration($logger) {
    $envPath = __DIR__ . '/..';
    $dotenv = Dotenv::createImmutable($envPath, ['.env_local', '.env']);
    try {
        $dotenv->load();
        return $_ENV;
    } catch (InvalidPathException $e) {
        $logger->error("Konfigurační soubor .env nebyl nalezen.");
        throw $e;
    }
}

//******************** Načtení konfigurace
loadConfiguration($logger);

//******************** připojení k db
$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => $_SERVER['DB_DRIVER'],
    'host'      => $_SERVER['DB_HOST'],
    'database'  => $_SERVER['DB_NAME'],
    'username'  => $_SERVER['DB_USER'],
    'password'  => $_SERVER['DB_PASSWORD'],
    'charset'   => $_SERVER['DB_CHARSET'],
    'collation' => $_SERVER['DB_COLLATION'],
    'prefix'    => $_SERVER['DB_PREFIX'],
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
