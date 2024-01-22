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
    
    /**
     * loginUser funkce zajišťuje přihlášení uživatele
     *
     * @param  mixed $email
     * @param  mixed $password
     * @param  mixed $remember
     * @return void
     */
    public function loginUser($email, $password, $remember = false) {
        // Ověření vstupních dat
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "Neplatný formát e-mailu";
            return false;
        }
    
        // Zde můžete přidat logiku pro kontrolu počtu neúspěšných pokusů
    
        $result = $this->auth->login($email, $password, $remember);
    
        // Záznam pokusu o přihlášení
        $this->logger->info("Pokus o přihlášení pro uživatele: $email");
    
        if ($result['error']) {
            $this->logger->warning("Neúspěšný pokus o přihlášení pro uživatele: $email");
            return ['success' => false, 'message' => "Přihlášení selhalo: " . $result['message']];
        } else {
            return ['success' => true, 'message' => "Uživatel byl úspěšně přihlášen"];
        }
    }
       
        
    /**
     * isUserLoggedIn funkce ověřuje, zda je uživtel přihlášen
     *
     * @return void
     */
    public function isUserLoggedIn() {
        return $this->auth->isLogged();
    }

    public function getUserData() {
        if ($this->auth->isLogged()) {
            $userId = $this->auth->getCurrentUID(); // Získá aktuální UID uživatele
            $user = $this->auth->getUser($userId);  // Získá informace o uživateli
    
            // Příklad: Vrátí jen některé informace o uživateli
            return [
                'uid' => $user['uid'],
                'email' => $user['email'],
                // Přidat další relevantní data, která chcete zahrnout
            ];
        } else {
            return null; // Nebo můžete vrátit prázdné pole nebo chybovou zprávu
        }
    }
        
    /**
     * logoutUser Bezpečné odhlášení uživatele
     *
     * @return void
     */
    public function logoutUser() {
        $currentSessionHash = $this->auth->getCurrentSessionHash();
        if (!$currentSessionHash) {
            $this->logger->info("Odhlášení uživatele se nezdařilo: žádná aktivní session");
            return false;
        }
    
        $result = $this->auth->logout($currentSessionHash);
    
        if ($result) {
            $this->logger->info("Uživatel byl úspěšně odhlášen.");
            // Zde můžete přidat další akce po úspěšném odhlášení, jako je přesměrování
            return true;
        } else {
            $this->logger->warning("Nastal problém při odhlášení uživatele.");
            // Zde můžete zpracovat chybu odhlášení
            return false;
        }
    }
        
    /**
     * registerUser Registrace uživatele
     *
     * @param  mixed $email
     * @param  mixed $password
     * @param  mixed $repeatpassword
     * @return void
     */
    public function registerUser($email, $password, $repeatpassword) {
        // Ověření platnosti e-mailu
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning("Neplatný formát e-mailu: $email");
            return false;
        }
    
        // Kontrola shody hesel
        if ($password !== $repeatpassword) {
            $this->logger->warning("Hesla se neshodují pro e-mail: $email");
            return false;
        }
    
        // Kontrola silného hesla (například minimální délka, obsahuje číslice, velká a malá písmena, speciální znaky)
        // Tuto logiku je třeba přizpůsobit vašim bezpečnostním požadavkům
        if (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[a-zA-Z]).{8,}$/', $password)) {
            $this->logger->warning("Heslo nesplňuje bezpečnostní požadavky pro e-mail: $email");
            return false;
        }
    
        // Další možná bezpečnostní opatření: Captcha pro prevenci automatických registrací
    
        // Pokus o registraci uživatele
        $result = $this->auth->register($email, $password, $repeatpassword);
    
        if ($result['error']) {
            $this->logger->warning("Registrace uživatele selhala: " . $result['message']);
            return false;
        } else {
            $this->logger->info("Uživatel byl úspěšně zaregistrován: $email");
            return true;
        }
    }
    
    /**
     * loginHandler Přihlašovací handler
     *
     * @return void
     */
    public function loginHandler() {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
    
        $loginResult = $this->loginUser($email, $password);
    
        if ($loginResult['success']) {
            // Přesměrujte na stránku po úspěšném přihlášení
            header('Location: /');
        } else {
            // Informujte uživatele o chybě a přesměrujte na přihlašovací stránku
            // Můžete použít session nebo GET parametry pro předání zprávy
            header('Location: /error?error=' . urlencode($loginResult['message']));
        }
        exit;
    }
    
}

?>