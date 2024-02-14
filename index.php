<?php
session_start();
require 'vendor/autoload.php';
require 'class/control.php';
require 'class/admin.php';

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
use linkcms1\Models\Article;
use linkcms1\Models\SiteConfiguration;
use PHPAuth\Config as PHPAuthConfig;
use PHPAuth\Auth as PHPAuth;
use linkcms1\adminControl;
use linkcms1\Models\UserDetails;



//******************** Vytvoření loggeru
$logger = new Logger('linkcms');
// Nastavení rotačního handleru pro logování úrovní NOTICE a INFO
$debugHandler = new RotatingFileHandler(__DIR__.'/logs/info.log', 0, Logger::INFO);
$logger->pushHandler($debugHandler);

// nastavení rotačního handleru pro debug
$debugHandler = new RotatingFileHandler(__DIR__.'/logs/debug.log', 0, Logger::DEBUG);
$logger->pushHandler($debugHandler);

// Nastavení handleru pro logování úrovně WARNING do jednoho souboru
$warningHandler = new StreamHandler(__DIR__.'/logs/warning.log', Logger::WARNING);
$warningHandler->setFormatter(new LineFormatter(null, null, true, true));
$logger->pushHandler($warningHandler);

// Nastavení handleru pro logování úrovně ERROR do nerotujícího souboru
$errorHandler = new StreamHandler(__DIR__.'/logs/error.log', Logger::ERROR);
$logger->pushHandler($errorHandler);

//******************** Funkce pro načtení konfiguračních souborů
function loadConfiguration($logger) {
    $envPath = __DIR__; // Nastavte cestu ke složce, kde se nachází .env soubory
    $dotenv = Dotenv::createImmutable($envPath, ['.env_local', '.env']);

    try {
        $dotenv->load();
        // Zkontrolujte, zda existuje .env_local, a nastavte režim debuggeru
        if (file_exists($envPath . '/.env_local')) {
            // Vývojové prostředí
            Tracy\Debugger::enable(Tracy\Debugger::DEVELOPMENT);
        } else {
            // Produkční prostředí
            Tracy\Debugger::enable(Tracy\Debugger::PRODUCTION);
        }
        return $_ENV; // nebo getenv() pro přístup k proměnným prostředí
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

// Nastavení Eloquentu pro globální použití (volitelné)
$capsule->setAsGlobal();
// Spuštění Eloquentu
$capsule->bootEloquent();

//******************** Vytvoření nové PDO instance pro PHPAuth
$dbh = new PDO(
    'mysql:host=' . $_SERVER['DB_HOST'] . ';dbname=' . $_SERVER['DB_NAME'],
    $_SERVER['DB_USER'],
    $_SERVER['DB_PASSWORD'],
    array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'") // Nastavení kódování, pokud je potřeba
);
// Vytvoření objektů pro PHPAuth
$config = new PHPAuthConfig($dbh);
$auth = new PHPAuth($dbh, $config);

//******************** volání informací o doméně
$domainInfo = new \linkcms1\domainControl($capsule, $logger);
$domainInfo->loadDomain();
$domainConf = new SiteConfiguration();
$domainConfData = $domainConf->getAllConfigurationsBySiteId($_SERVER["SITE_ID"]);
foreach($domainConfData as $key =>$value){
    $_SERVER["domain"][$key] = $value;
}
Tracy\Debugger::barDump($domainConfData, 'Domain Conf');

//******************** routování cesty
$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) use ($capsule) {    
    $r->addRoute('GET', '', function($string = "") {
    });
    $r->addRoute('GET', '/', function($string = "") {
    });
    $r->addRoute('POST', '/doLogin', 'loginHandler');
    $r->addRoute('GET', '/{string:.+}', function($string) {});
    $r->addRoute('POST', '/{string:.+}', function($string) {});
});

//******************** Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
Tracy\Debugger::barDump($_SERVER, 'SEVER data');

//******************** Získání URI a odstranění base path
$uri = $_SERVER['REQUEST_URI'];
if (substr($uri, 0, strlen($_SERVER['BASE_PATH'])) == $_SERVER['BASE_PATH']) {
    $uri = substr($uri, strlen($_SERVER['BASE_PATH']));
}
//******************** Strip query string (?foo=bar) and decode URI
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);
$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

$admin = new linkcms1\adminControl($capsule, $logger, $auth);
if($auth->isLogged()){
    $userId = $auth->getCurrentUID();

    // Kontrola, zda je uživatel přihlášen
    if ($userId) {
        $rolesCollection = $admin->getUserRoles($userId);
        $userRoles = $rolesCollection ? $rolesCollection->toArray() : [];
        Tracy\Debugger::barDump('Přihlášený uživatel');
    }
} else {
    $userRoles = [];
    $userId = null;
    Tracy\Debugger::barDump('Uživatel není přihlášen');
}

Tracy\Debugger::barDump($userRoles, 'Role uživatele');

foreach($_SERVER as $key => $value){
    if(strpos($key, "SITE_") !== false){
        $domainData[$key] = $value;
    } elseif(strpos($key, "domain") !== false){
        $domainData[$key] = $value;
    }
}

//******************** Handlery pro routování, pole pro zpracování db
$handlers = [
    "articles" => "linkcms1\Models\Article",
    "getActiveArticlesByCategoryWithUrlAndAuthor" => "linkcms1\Models\Article",
    "get_all_users" => "linkcms1\Models\User",
    "handleSaveOrUpdateSite" => "linkcms1\Models\Site",
    "categories" => "linkcms1\Models\Category",
    "handleSaveOrUpdateArticle" => "linkcms1\Models\Article",
    "getAllArticlesWithCategories" => "linkcms1\Models\Article",
    "getArticleDetails" => "linkcms1\Models\Article",
    "handleSaveOrUpdateCategory" => "linkcms1\Models\Category",
    "updateCategoryOrder" => "linkcms1\Models\Category",
    "getAllDefinitions" => "linkcms1\Models\ConfigurationDefinition",
    "handleSaveOrUpdateConfigurationDefinition" => "linkcms1\Models\ConfigurationDefinition",
    "loadDomain" => array("\linkcms1\domainControl",array($capsule,$logger)),
    "handleCreateUrlRequest" => array("\linkcms1\domainControl",array($capsule,$logger)),
    "roleFormHandler" => array("\linkcms1\adminControl",array($capsule,$logger,$auth)),
    "permissionFormHandler" => array("\linkcms1\adminControl",array($capsule,$logger,$auth)),
    "isUserLoggedIn" => array("linkcms1\adminControl",array($capsule,$logger,$auth)),
    "getAdministration" => array("linkcms1\adminControl",array($capsule,$logger,$auth)),
    "loginHandler" => array("linkcms1\adminControl", array($capsule, $logger, $auth)),
    "logoutUser" => array("linkcms1\adminControl", array($capsule, $logger, $auth)),
    "registerUser" => array("linkcms1\adminControl", array($capsule, $logger, $auth))];

//******************** zpracování routeru    
switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        // ... 404 Not Found
        Tracy\Debugger::barDump($routeInfo[0], 'Stránka nebyla nalezena');        
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
            $urlInfo = $domainInfo->loadSite(); 
            
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domainData["SITE_WEB"] = $_SERVER["SITE_WEB"] = $protocol.$_SERVER["SITE_DOMAIN"].$_SERVER["BASE_PATH"];
            
            // ošetření problému s home
            if(empty($vars["string"])){
                $vars["string"] = "/";
            }

            $vars = array_merge($vars, $urlInfo);
            $vars = array_values($vars);
            Tracy\Debugger::barDump($vars, 'Vars');
            $methodName = $vars[4];

            // Kontrola existence handleru v poli $handlers
            if (array_key_exists($methodName, $handlers)) {
                if (is_array($handlers[$methodName])) {
                    // Vytvoření instance třídy s argumenty
                    $className = $handlers[$methodName][0];
                    $args = $handlers[$methodName][1];
                    $reflectionClass = new ReflectionClass($className);
                    $instance = $reflectionClass->newInstanceArgs($args);
                } else {
                    // Vytvoření instance třídy bez argumentů
                    $className = $handlers[$methodName];
                    $instance = new $className();
                }
            } else {
                // Handler není definován v $handlers
                Tracy\Debugger::barDump("Handler '" . $methodName . "' je databázi, ale není v poli hadlerů");
                // Zde můžete přidat další kód pro zpracování této situace
            }

            Tracy\Debugger::barDump([$instance, $methodName], 'Instance');
            $displayData = call_user_func_array([$instance, $methodName], array($vars[6]));
            

            
            // Převod výsledků na pole, pokud jsou vráceny jako Eloquent Collection
            if ($displayData instanceof Illuminate\Database\Eloquent\Collection) {
                $displayData = $displayData->toArray();
            }

            Tracy\Debugger::barDump($displayData, 'Display data');
            Tracy\Debugger::barDump($vars[5], 'Hodnota pro switch šablon');
            
            $templateDir = $_SERVER["SITE_TEMPLATE_DIR"];

            switch($vars[5]){
                case 'categories' : {
                    $catData = new linkcms1\Models\Category();
                    $pageData = $catData->getCategoryInfo($vars[6]);
                    $renderPage = "category.twig";
                    break;
                }
                case 'admin' : {
                    if(!$admin->hasPermission($userId,"administration",$domainData["SITE_ID"])){
                        header('Location: ' . $domainData["SITE_WEB"].'/admin/login/');
                    }
                    //$pageData = $instance->getCategoryInfo($vars[6]);
                    $templateDir = "templates/admin/";
                    $renderPage = "admin.twig";
                    break;
                }
                case 'adminCategories' : {
                    if(!$admin->hasPermission($userId,"administration",$domainData["SITE_ID"])){
                        header('Location: ' . $domainData["SITE_WEB"].'/admin/login/');
                    }
                    $pageData["allCats"] = $instance->getAllCategoriesTree($domainData["SITE_ID"]);
                    $pageData["urlListToAdd"] = $instance->getUrlsWithTitle($domainData["SITE_DOMAIN"]);
                    $pageData["allUrls"] = $domainInfo->getAllUrlsForDomain($vars[2]);
                    $templateDir = "templates/admin/";
                    $renderPage = "adminCategories.twig";
                    break;
                }
                case 'adminAddArticles' : {
                    if(!$admin->hasPermission($userId,"administration",$domainData["SITE_ID"])){
                        header('Location: ' . $domainData["SITE_WEB"].'/admin/login/');
                    }
                    if(isset($_GET["id"]) and is_numeric($_GET["id"])){
                        $pageData["article"] = Article::getArticleDetails($_GET["id"]);
                    }
                    $catData = new linkcms1\Models\Category();
                    $pageData["allCats"] = $catData->getAllCategoriesTree($domainData["SITE_ID"]);
                    $pageData["urlListToAdd"] = $catData->getUrlsWithTitle($domainData["SITE_DOMAIN"]);
                    $pageData["allUrls"] = $domainInfo->getAllUrlsForDomain($vars[2]);
                    $templateDir = "templates/admin/";
                    $renderPage = "addArticle.twig";
                    break;
                }
                case 'adminAddPages' : {
                    if(!$admin->hasPermission($userId,"administration",$domainData["SITE_ID"])){
                        header('Location: ' . $domainData["SITE_WEB"].'/admin/login/');
                    }
                    if(isset($_GET["id"]) and is_numeric($_GET["id"])){
                        $pageData["article"] = Article::getArticleDetails($_GET["id"]);
                    }
                    $catData = new linkcms1\Models\Category();
                    $pageData["allCats"] = $catData->getAllCategoriesTree($domainData["SITE_ID"]);
                    $pageData["urlListToAdd"] = $catData->getUrlsWithTitle($domainData["SITE_DOMAIN"]);
                    $pageData["allUrls"] = $domainInfo->getAllUrlsForDomain($vars[2]);
                    $templateDir = "templates/admin/";
                    $renderPage = "addPage.twig";
                    break;
                }
                case 'adminAllArticles' : {
                    if(!$admin->hasPermission($userId,"administration",$domainData["SITE_ID"])){
                        header('Location: ' . $domainData["SITE_WEB"].'/admin/login/');
                    }
                    $catData = new linkcms1\Models\Category();
                    $pageData["allCats"] = $catData->getAllCategoriesTree($domainData["SITE_ID"]);
                    $pageData["urlListToAdd"] = $catData->getUrlsWithTitle($domainData["SITE_DOMAIN"]);
                    $pageData["allUrls"] = $domainInfo->getAllUrlsForDomain($vars[2]);
                    $templateDir = "templates/admin/";
                    $renderPage = "allArticles.twig";
                    break;
                }
                case 'superOpravneni' : {
                    if(!$admin->hasPermission($userId,"administration",$domainData["SITE_ID"])){
                        header('Location: ' . $domainData["SITE_WEB"].'/admin/login/');
                    }
                    $pageData["allRolesWithPremissions"] = $admin->getAllRolesWithPermissionsSorted();
                    $pageData["allPermissions"] = $admin->getAllPermissions();
                    $templateDir = "templates/admin/";
                    $renderPage = "superOpravneni.twig";
                    break;
                }
                case 'superAdminUrlAdd' : {
                    if(!$admin->hasPermission($userId,"administration",$domainData["SITE_ID"])){
                        header('Location: ' . $domainData["SITE_WEB"].'/admin/login/');
                    }
                    $pageData["allUrls"] = $domainInfo->getAllUrlsForDomain("*");
                    $pageData["allDomains"] = $domainInfo->getAllDomainsWithInfo();
                    $sitesData = new linkcms1\Models\Site();
                    $pageData["allHandlers"] = $sitesData->getAllUniqueHandlers();
                    $pageData["allModels"] = $sitesData->getAllUniqueModels(); 
                    $templateDir = "templates/admin/";
                    $renderPage = "urls.twig";
                    break;
                }
                case 'adminConfDef' : {
                    if(!$admin->hasPermission($userId,"administration",$domainData["SITE_ID"])){
                        header('Location: ' . $domainData["SITE_WEB"].'/admin/login/');
                    }
                    $pageData["allUrls"] = $domainInfo->getAllUrlsForDomain("*");
                    $pageData["allDomains"] = $domainInfo->getAllDomainsWithInfo();
                    $sitesData = new linkcms1\Models\Site();
                    $pageData["allHandlers"] = $sitesData->getAllUniqueHandlers();
                    $pageData["allModels"] = $sitesData->getAllUniqueModels(); 
                    $templateDir = "templates/admin/";
                    $renderPage = "confDef.twig";
                    break;
                }
                case 'adminConf' : {
                    if(!$admin->hasPermission($userId,"administration",$domainData["SITE_ID"])){
                        header('Location: ' . $domainData["SITE_WEB"].'/admin/login/');
                    }
                    $pageData["allUrls"] = $domainInfo->getAllUrlsForDomain("*");
                    $pageData["allDomains"] = $domainInfo->getAllDomainsWithInfo();
                    $sitesData = new linkcms1\Models\Site();
                    $pageData["allHandlers"] = $sitesData->getAllUniqueHandlers();
                    $pageData["allModels"] = $sitesData->getAllUniqueModels(); 
                    $templateDir = "templates/admin/";
                    $renderPage = "config.twig";
                    break;
                }
                case 'myweb' : {
                    if(!$admin->hasPermission($userId,"administration",$domainData["SITE_ID"])){
                        header('Location: ' . $domainData["SITE_WEB"].'/admin/login/');
                    }
                    $pageData["allUrls"] = $domainInfo->getAllUrlsForDomain("*");
                    $pageData["allDomains"] = $domainInfo->getAllDomainsWithInfo();
                    $sitesData = new linkcms1\Models\Site();
                    $pageData["allHandlers"] = $sitesData->getAllUniqueHandlers();
                    $pageData["allModels"] = $sitesData->getAllUniqueModels(); 
                    $templateDir = "templates/admin/";
                    $renderPage = "myweb.twig";
                    break;
                }
                case 'adminLogin' : {
                    if($admin->hasPermission($userId,"administration",$domainData["SITE_ID"])){
                        header('Location: ' . $domainData["SITE_WEB"].'/admin/');
                    }
                    $templateDir = "templates/admin/";
                    $renderPage = "adminLogin.twig";
                    break;
                }
                case 'adminReg' : {
                    if($admin->hasPermission($userId,"administration",$domainData["SITE_ID"])){
                        header('Location: ' . $domainData["SITE_WEB"].'/admin/');
                    }
                    $templateDir = "templates/admin/";
                    $renderPage = "adminReg.twig";
                    break;
                }
                case 'adminRegSuccess' : {
                    if($admin->hasPermission($userId,"administration",$domainData["SITE_ID"])){
                        header('Location: ' . $domainData["SITE_WEB"].'/admin/');
                    }
                    $templateDir = "templates/admin/";
                    $renderPage = "adminRegSuccess.twig";
                    break;
                }
                case 'adminError' : {
                    if(!$admin->hasPermission($userId,"administration",$domainData["SITE_ID"])){
                        header('Location: ' . $domainData["SITE_WEB"].'/admin/login/');
                    }
                    $templateDir = "templates/admin/";
                    $renderPage = "error.twig";
                    break;
                }
                case 'articles' : {
                    $catData = new linkcms1\Models\Category();
                    $pageData = $catData->getCategoryInfo($vars[6]);

                    $renderPage = "article.twig";
                    break;
                }
                case 'articles' : {
                    $catData = new linkcms1\Models\Category();
                    $pageData = $catData->getCategoryInfo($vars[6]);

                    $renderPage = "home.twig";
                    break;
                }
                default : {
                    $renderPage = $vars[5].".twig";
                    $category = new linkcms1\Models\Category;
                    $pageData = $category->getCategoryInfo($vars[6]);
                    break;
                }
            }
            break;
}

//definice obecných oprávnění
$premissions = [
    'adminPanel' => $admin->hasPermission($userId,"administration",$domainData["SITE_ID"])

];
Tracy\Debugger::barDump($premissions, 'Oprávnění data');
if(!isset($pageData)){$pageData = "";}

$variables = [
    'navigation' => Category::generateNavigation( $_SERVER["SITE_ID"], null,"navigation clearfix"), // zobrazení navigace
    'displayData' => $displayData, // data obsahu stránky
    'pageData' => $pageData, // informace o konkrétní stránce
    'domainData' => $domainData, //data o doméně
    'userData' => $admin->getUserData(),
    'premissions' => $premissions,
    'templateDir' => $templateDir
];
Tracy\Debugger::barDump($domainData, 'Domain data');
Tracy\Debugger::barDump($pageData, 'Page data');
Tracy\Debugger::barDump($admin->getUserData(), 'User data');

Tracy\Debugger::barDump($urlInfo, 'Url info');

Tracy\Debugger::barDump($templateDir.$renderPage, 'Template');
$loader = new \Twig\Loader\FilesystemLoader($templateDir);
$twig = new \Twig\Environment($loader, [
    //'cache' => '/templates/cache',
     'cache' => false, // Vypnout cache pro vývoj
]);

echo $twig->render($renderPage, $variables);
?>