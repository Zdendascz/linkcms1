<?php
session_start();
// -- nastaveni preautorizace --------------------------------------------------
if(!isset($_SESSION["auth"])){
  $_SESSION["auth"]["jmeno"] = "host";
  $_SESSION["auth"]["lng"] = "cz";
  $_SESSION["auth"]["prava"] = 0;
  $_SESSION["auth"]["log"] = 0;
}
require_once('engine.php');


// -- nacteni smarty -----------------------------------------------------------
require 'smarty/smarty/Smarty.class.php';
class MojeSmarty extends Smarty {
  public function __construct(){
    $this->Smarty();
    $this->template_dir = 'templates/';
    $this->config_dir = 'smarty/config/';
    $this->compile_dir = 'smarty/templates_c/';
    $this->cache_dir = 'smarty/cache/';
  }
}

// -- definice udaju pro smarty ------------------------------------------------
$smarty = new MojeSmarty;
$smarty->assign('url',$_conf["web"]);
$smarty->assign('meta_author',$_conf["meta_author"]);
$smarty->assign('meta_copyright',$_conf["meta_copyright"]);
$smarty->assign('meta_country',$_conf["meta_country"]);
$smarty->assign('meta_language',$_conf["meta_language"]);


// -- vyplneni vyhledavani -----------------------------------------------------
if(isset($_GET["slovo"]))
  $smarty->assign('SEARCHvalue',$_GET["slovo"]);
else
  $smarty->assign('SEARCHvalue',"hledaný výraz");
// -- nacteni obsahu webu ------------------------------------------------------
if(!isset($_GET['str'])){
  require_once("str/uvod.php");
  $smartyPage = "uvod";
}
else if(!file_exists("str/".$_GET['str'].".php")){
  require_once("str/sys/404.php");
  $smartyPage = "404";
}
else{
  require_once("str/".$_GET['str'].".php");
  $smartyPage = $_GET['str'];
}
$smarty->assign('currentPage',$smartyPage);
$smarty->display('layout/hlavicka.tpl');
$smarty->display($smartyPage.'.tpl');
$smarty->display('layout/paticka.tpl');
?>
