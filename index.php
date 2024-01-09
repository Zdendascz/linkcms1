<?php
require 'vendor/autoload.php';

use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;
use Tracy\Debugger;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;
use Illuminate\Database\Capsule\Manager as Capsule;
use LinkCmsLib\User;

Debugger::enable(Debugger::DEVELOPMENT);

// Vytvoření loggeru
$logger = new Logger('linkcms');
// Nastavení rotačního handleru pro logování úrovní DEBUG, NOTICE a INFO
$debugHandler = new RotatingFileHandler(__DIR__.'/logs/debug_info.log', 0, Logger::INFO);
$logger->pushHandler($debugHandler);

// Nastavení rotačního handleru pro logování úrovně WARNING
$warningHandler = new RotatingFileHandler(__DIR__.'/logs/warning.log', 0, Logger::WARNING, false, true);
$warningHandler->setFormatter(new LineFormatter(null, null, true, true));
$logger->pushHandler($warningHandler);

// Nastavení handleru pro logování úrovně ERROR do nerotujícího souboru
$errorHandler = new StreamHandler(__DIR__.'/logs/error.log', Logger::ERROR);
$logger->pushHandler($errorHandler);

// Funkce pro načtení konfiguračních souborů
function loadConfiguration($logger) {
    $envPath = __DIR__; // Nastavte cestu ke složce, kde se nachází .env soubory
    $dotenv = Dotenv::createImmutable($envPath, ['.env_local', '.env']);

    try {
        $dotenv->load();
        return $_ENV; // nebo getenv() pro přístup k proměnným prostředí
    } catch (InvalidPathException $e) {
        $logger->error("Konfigurační soubor .env nebyl nalezen.");
        throw $e;
    }
}

// Načtení konfigurace
$config = loadConfiguration($logger);

$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => $config['DB_DRIVER'],
    'host'      => $config['DB_HOST'],
    'database'  => $config['DB_NAME'],
    'username'  => $config['DB_USER'],
    'password'  => $config['DB_PASSWORD'],
    'charset'   => $config['DB_CHARSET'],
    'collation' => $config['DB_COLLATION'],
    'prefix'    => $config['DB_PREFIX'],
]);

// Nastavení Eloquentu pro globální použití (volitelné)
$capsule->setAsGlobal();

// Spuštění Eloquentu
$capsule->bootEloquent();

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) use ($capsule) {
    // $r->addRoute('GET', '/users', 'get_all_users_handler');
    $r->addRoute('GET', '/users', function() use ($capsule) {
        $user = new User($capsule);
        $user->get_all_users();
    });
    $r->addRoute('GET', '/user/{id:\d+}', function($id) {
        $user = new User();
        $user->get_user($id);
    });

    $r->addRoute('GET', '/', 'get_home');
    // {id} must be a number (\d+)
    //$r->addRoute('GET', '/user/{id:\d+}', 'get_user_handler');
    // The /{title} suffix is optional
    $r->addRoute('GET', '/articles/{id:\d+}[/{title}]', 'get_article_handler');

    $r->addGroup('/admin', function (RouteCollector $r) {
        $r->addRoute('GET', '/do-something', 'handler');
        $r->addRoute('GET', '/do-another-thing', 'handler');
        $r->addRoute('GET', '/do-something-else', 'handler');
    });
});


// Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Nastavení base path
$basePath = $config['BASE_PATH'];

// Získání URI a odstranění base path
$uri = $_SERVER['REQUEST_URI'];
if (substr($uri, 0, strlen($basePath)) == $basePath) {
    $uri = substr($uri, strlen($basePath));
}

// Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        // ... 404 Not Found
        $logger->warning('Požadovaná stránka '.$uri." nebyla nalezena s metodou ".$httpMethod);
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        $logger->error('Přístup ke stránce '.$uri." nebyl povolen s metodou".$httpMethod);
        // ... 405 Method Not Allowed
        break;
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        // ... call $handler with $vars
        call_user_func_array($handler, $vars);
        break;
}

// function get_all_users_handler() {
//     // Logika pro načtení dat uživatelů
//     // Například získání dat z databáze a jejich zobrazení
//     echo "jeden to!<br />vars: ".$vars;
// }

// class user {
    
//     public function get_user_handler($id){
//         echo "toto jede taky a je tu ".$id;
//     }

// }
?>