<?php
require 'vendor/autoload.php';
require 'class/control.php';

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
use linkcms1\Models\Category;

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
loadConfiguration($logger);

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

// Nastavení Eloquentu pro globální použití (volitelné)
$capsule->setAsGlobal();

// Spuštění Eloquentu
$capsule->bootEloquent();

//volání informací o doméně
$domainInfo = new \linkcms1\domainControl($capsule, $logger);
$domainInfo->loadDomain();



$loader = new \Twig\Loader\FilesystemLoader('templates/');
$twig = new \Twig\Environment($loader, [
    //'cache' => '/templates/cache',
     'cache' => false, // Vypnout cache pro vývoj
]);

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) use ($capsule, $domainInfo) {
    
    $r->addRoute('GET', '/users', function() use ($capsule) {
        $user = new User($capsule);
        $user->get_all_users();
    });
    $r->addRoute('GET', '/user/{id:\d+}', function($id) use ($capsule) {
        $user = new User($capsule);
        $user->get_user($id);
    });

    $r->addRoute('GET', '/', [$domainInfo, 'get_home']);


    $r->addGroup('/admin', function (RouteCollector $r) {
        $r->addRoute('GET', '/do-something', 'handler');
        $r->addRoute('GET', '/do-another-thing', 'handler');
        $r->addRoute('GET', '/do-something-else', 'handler');
    });
    $r->addRoute('GET', '/{string}', function($string) {

        // Tady můžete zpracovat $retezec
        echo "Zachycený řetězec: " . $string;
        // Zpracování GET parametrů
        foreach ($_GET as $key => $value) {
            echo "<br>GET parametr: $key, Hodnota: $value";
        }
    });
});

// Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Získání URI a odstranění base path
$uri = $_SERVER['REQUEST_URI'];
if (substr($uri, 0, strlen($_SERVER['BASE_PATH'])) == $_SERVER['BASE_PATH']) {
    $uri = substr($uri, strlen($_SERVER['BASE_PATH']));
}

// Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

$handlers = [
    "articles" => "linkcms1\Models\Category",
    "get_all_users" => "linkcms1\Models\User"];

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
            $url = $domainInfo->loadSite();
            $vars = array_merge($vars, $url);
            $vars = array_values($vars);
            $methodName = $vars[4];
            $instance = new $handlers[$methodName]();
            $displayData = call_user_func_array([$instance, $methodName], $vars);
            echo '<pre> Vars:' . print_r($displayData, true) . '</pre>';
            break;
}

$variables = [
    'title' => 'Vítejte',
    'greeting' => 'Ahoj, světe!',
    'navigation' => Category::generateNavigation( $_SERVER["SITE_ID"], null),
    'displayData' => $displayData
];

echo $twig->render('head.twig', $variables);


?>