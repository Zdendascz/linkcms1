<?php
/*
if(file_exists('system/fns/db_conect.php'))
  $dir = "";
else if(file_exists('../system/fns/db_conect.php'))
  $dir = "../";
else if(file_exists('../../system/fns/db_conect.php'))
  $dir = "../../";
else if(file_exists('../../../system/fns/db_conect.php'))
  $dir = "../../../";
else
  $dir = "../../../../";
*/

  if( isset($_SESSION['page_aktual_url']) && $_SESSION['page_aktual_url'] <> "" && $_SESSION['page_referer'] <> $_SESSION['page_aktual_url'] ) {
    $_SESSION['page_referer'] = $_SESSION['page_aktual_url'];
  }

  if( $_SERVER['SERVER_PORT'] == "443" ) {
    $_SESSION['page_aktual_url'] = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
  }
  else {
    $_SESSION['page_aktual_url'] = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
  }

//** -- new init -- */
// composer - autoload
require_once __DIR__."/../bootstrap.php";
require_once __DIR__."/../generated-conf/config.php";
require_once __DIR__."/../vendor/autoload.php";

date_default_timezone_set("Europe/Prague");


$dir = path_join(dirname(__FILE__), "..");
$_conf = false;
if (file_exists(path_join($dir, "settings_local.ini"))) {
    //echo "loading settings_local.ini\n";
    $_conf = loadIni(path_join($dir, "settings_local.ini"));
} else
    $_conf = loadIni(path_join($dir, "settings.ini"));
if($_conf == false)
  die ("chyba pri nacitani ini");

  $_conf["web"] = $_conf["url"];
  $_conf["dir"] = $dir;

// -- nastavení Monologu ----------------------------------------------------------
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SwiftMailerHandler;
//use Swift_Mailer;
//use Swift_Message;
//use Swift_SmtpTransport;

// Vytvoření loggeru
$logger = new Logger('MedLog');

// Zápis DEBUG, INFO a NOTICE do Rotujícího Souboru
if (array_key_exists('debug', $_conf) && $_conf['debug']) {
  $rotatingHandler = new RotatingFileHandler(__DIR__ . '/logs/info_debug_notice.log', 7, Logger::DEBUG);
  $logger->pushHandler($rotatingHandler);
}
else{
  $rotatingHandler = new RotatingFileHandler(__DIR__ . '/logs/info_debug_notice.log', 7, Logger::INFO);
  $logger->pushHandler($rotatingHandler);
}

// Zápis WARNING do Trvalého Souboru
$warningHandler = new StreamHandler(__DIR__ . '/logs/warning.log', Logger::WARNING);
$logger->pushHandler($warningHandler);

// Konfigurace a vytvoření SwiftMailerHandler
/*
$transport = new Swift_SmtpTransport('smtp.server.com', 25, 'tls');
$transport->setUsername('username')->setPassword('password');

$mailer = new Swift_Mailer($transport);
$message = (new Swift_Message('Kritická chyba v aplikaci'))
    ->setFrom(['from@example.com' => 'Logger'])
    ->setTo(['to@example.com'])
    ->setBody('Zpráva: %message%');

$mailHandler = new SwiftMailerHandler($mailer, $message, Logger::ERROR);
$logger->pushHandler($mailHandler);
*/
if ($logger) {
  $logger->debug('Toto je debugovací zpráva.');
}

// enable tracy
use Tracy\Debugger;
if (array_key_exists('debug', $_conf) && $_conf['debug']) {
    Debugger::enable(Debugger::DEVELOPMENT);
} else {
    Debugger::enable(Debugger::PRODUCTION);
}

  
// -- knihovny funkci swcontrol ------------------------------------------------
require_once(path_join($dir,$_conf["libraryDir"],'system/fns/sys_fns.php'));
require_once(path_join($dir,$_conf["libraryDir"],'system/fns/display.fns.php'));
require_once(path_join($dir,$_conf["libraryDir"],'system/fns/check.fns.php'));
//require_once($dir.$_conf["libraryDir"].'system/fns/catalog.fns.php');
//require_once($dir.$_conf["libraryDir"].'system/fns/articles.fns.php');
require_once(path_join($dir,$_conf["libraryDir"],'system/fns/sql.fns.php'));
require_once(path_join($dir,$_conf["libraryDir"],'system/fns/form.fns.php'));
//require_once($dir.$_conf["libraryDir"].'system/fns/files.fns.php');
//require_once($dir.$_conf["libraryDir"].'system/fns/galerie.fns.php');
require_once(path_join($dir,$_conf["libraryDir"],'system/fns/mailer.fns.php'));
require_once(path_join($dir,$_conf["libraryDir"],'system/fns/calendar.fns.php'));
require_once(path_join($dir,$_conf["libraryDir"],'system/fns/user.fns.php'));
require_once(path_join($dir,$_conf["libraryDir"],'system/fns/swsql.fns.php')); // swcontrol framework
require_once(path_join($dir,$_conf["libraryDir"],'system/fns/search.fns.php')); // vyhledavaci funkce
require_once(path_join($dir,$_conf["libraryDir"],'system/fns/makra.fns.php')); // modul pro praci s zadavanim maker pred vysetrenim
require_once(path_join($dir,$_conf["libraryDir"],'system/fns/statistiky.fns.php')); // modul pro prace se statistikama


// -- interni knihovny systemu -------------------------------------------------
require(path_join($dir,$_conf["libraryDir"],'system/fns/medisoftBase.fns.php'));
require_once(path_join($dir,'system/fns/medisoftExt.fns.php'));
require_once(path_join($dir,'system/fns/medisoftBase.sql.php'));
require_once(path_join($dir,'system/fns/medisoftExt.sql.php'));
require_once(path_join($dir,'system/fns/medisoftVysetreni.fns.php'));
require_once(path_join($dir,'system/fns/dasta3.fns.php'));                      //knihovna pro datovy standard DS3
require_once(path_join($dir,'system/fns/dasta3_vstupni.fns.php'));                      //knihovna pro datovy standard DS3
require_once(path_join($dir,'system/fns/oh.fns.php'));                      //knihovna pro onkologicka hlaseni




// aplikace MySQL/i compatibility packu, jen pokud nejsou vychozi MySQL fce
if(!function_exists('mysql_connect')) {
  require_once(__DIR__.'/fns/mysqli_comp.fns.php');
}  

require_once(path_join($dir,'system/fns/db_conect.php'));
require_once(path_join($dir,'system/sys_config.php'));
$_conf = loadConfig($_conf); 
/*
$adresar = $dir.'_admin_/moduly/';
$otevreni = opendir($adresar);
  IF(@!$otevreni){
    die('Nepodařilo se otevřít adresář: '.$adresar);
  }
  else{
    while ($soubor = readdir($otevreni)){
      if(strlen($soubor) > 3){
        if(file_exists($dir.'_admin_/moduly/'.$soubor))
          require_once($dir.'_admin_/moduly/'.$soubor);
        else
          die('CHYBA: nepodařilo se nahrát soubor: '.$dir.'admin/moduly/'.$soubor);
      }
    }
  }
*/
function loadIni($soubor){
  //$soubor = $dir."settings.ini";
  if(file_exists($soubor))
    return parse_ini_file($soubor);
  else
    return false;
}
?>