<?php
namespace linkcms1;
use Illuminate\Database\Capsule\Manager as Capsule;
use linkcms1\Models\User as EloquentUser;
use linkcms1\Models\Site;
use linkcms1\Models\Url;
use linkcms1\Models\Category;
use Monolog\Logger;
use Tracy\Debugger;

Debugger::enable(Debugger::DEVELOPMENT);
/**
 * Třída reprezentující web, zpracovává kontrolu domény a informace o ní.
 * také zpracovává informace o konkrétní stránce
 */
class domainControl {

    protected $capsule;
    protected $logger;

    public function __construct($capsule, Logger $logger) {
        $this->capsule = $capsule;
        $this->logger = $logger;
        

    }
    
    /**
     * loadDomain
     * funkce načítá infomace o doméně a ukládá je k dalšímu použití
     * @return data v poli $_SERVER[] s prefixem SITE_
     */
    public function loadDomain (){
        $domain = Site::where('domain', '=', str_replace("www.","",$_SERVER['HTTP_HOST']))->first();
        $domainInfo = "";
        if ($domain) {
            foreach ($domain->getAttributes() as $key => $value) {
                // Kontrola, zda hodnota je JSON a dekódování
                if (is_string($value) && is_array(json_decode($value, true)) && json_last_error() === JSON_ERROR_NONE) {
                    $value = json_decode($value, true);
                }
        
                // Přidání hodnoty do $_SERVER s prefixem 'SITE_'
                $_SERVER['SITE_' . strtoupper($key)] = $value;
                
                // Kontrola, zda je hodnota pole a převod na řetězec
                if (is_array($value)) {
                    $value = json_encode($value);
                }

                $domainInfo .= $key."=".$value;
            }
            $this->logger->debug('Načtení informací o doméně '.$_SERVER['HTTP_HOST']." >>> ".$domainInfo); 
        }
        else{
            $this->logger->error('Nepodařilo se načíst data domény '.$_SERVER['HTTP_HOST']);
        }

    }
    
    /**
     * loadSite
     * Funkce načítá informace o aktuální URL pro potřeby routování
     * @return array Kompletní informace o aktuální url adrese
     */
    public function loadSite() {
        // Rozdělení URL na komponenty a získání pouze cesty
        $parsedUrl = parse_url($_SERVER['REQUEST_URI']);
        $path = $parsedUrl['path'];

        // Odstranění domény, pokud je součástí cesty
        $path = str_replace('http://' . $_SERVER['SITE_DOMAIN'], '', $path);
        $path = str_replace('https://' . $_SERVER['SITE_DOMAIN'], '', $path);

        // Vyhledání URL v databázi, která odpovídá jak doméně, tak cestě
        $url = Url::where('domain', '=', $_SERVER['SITE_DOMAIN'])
                ->where('url', '=', $path)
                ->first();                
        $this->logger->debug('Načtení informací o url ze SQL [domain:'.$_SERVER['SITE_DOMAIN']." path:".$path."]");         
    
        // Kontrola, zda byl záznam nalezen
        if ($url === null) {
            // Zde můžete zpracovat situaci, kdy záznam nebyl nalezen
            \Tracy\Debugger::barDump($_SERVER['SITE_DOMAIN'].$path, 'Problém s nalezením url v db');
            $this->logger->warning('Adresa '.$_SERVER['SITE_DOMAIN'].$path." nenalezena");

            // Můžete vrátit null nebo jinou výchozí hodnotu
            return null;
        }
        return $url->toArray();
    }

}

?>