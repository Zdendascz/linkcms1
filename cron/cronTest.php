<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../cron/bootstrap.php';

use linkcms1\Models\Article;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

// Nastavení loggeru
$logger = new Logger('updateArticleStatus');
$logHandler = new RotatingFileHandler(__DIR__ . '/../logs/updateArticleStatus.log', 0, Logger::INFO);
$logger->pushHandler($logHandler);

// info o startu cronu
$logger->info('Testovací cron proběhl', ['time' => $currentDateTime]);
