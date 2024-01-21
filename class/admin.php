<?php
namespace linkcms1;
use Illuminate\Database\Capsule\Manager as Capsule;
use linkcms1\Models\User as EloquentUser;
use linkcms1\Models\Site;
use linkcms1\Models\Url;
use linkcms1\Models\Category;
use Monolog\Logger;
use Tracy\Debugger;
use PHPAuth\Auth as PHPAuth;

Debugger::enable(Debugger::DEVELOPMENT);
/**
 * Třída reprezentující web, zpracovává kontrolu domény a informace o ní.
 * také zpracovává informace o konkrétní stránce
 */
class adminControl {

    protected $capsule;
    protected $logger;
    protected $auth;

    public function __construct($capsule, Logger $logger, \PHPAuth\Auth $auth) {
        $this->capsule = $capsule;
        $this->logger = $logger;
        $this->auth = $auth;
    }
    
    
    public function admin(){
        if ($this->auth->isLogged()) {
            echo "Uživatel je přihlášen";
            // Zde můžete přidat další logiku pro přihlášeného uživatele
        } else {
            echo "Uživatel není přihlášen";
            // Zde můžete přidat logiku pro nepřihlášeného uživatele
        }
        return null;
    }

}

?>