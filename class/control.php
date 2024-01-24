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
        $siteDomain = $_SERVER['SITE_DOMAIN']; // Uložení hodnoty do lokální proměnné

        $url = Url::where(function ($query) use ($siteDomain, $path) {
            $query->where('domain', '=', $siteDomain)
                  ->orWhere('domain', '=', '*');
        })
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
    
    /**
     * checkForDuplicates Kontrola duplicit url na dané doméně a kontrola duplicit model_id na daném modelu a doméně
     * Funkce odstraňuje lomítko na konci url
     *
     * @param  mixed $url
     * @param  mixed $domain
     * @param  mixed $model
     * @param  mixed $modelId
     * @return void
     */
    public function checkForDuplicates($url, $domain, $model = null, $modelId = null) {
        // Odstranění koncového lomítka z URL, pokud existuje
        $url = rtrim($url, '/');
    
        // Kontrola, zda existuje záznam s identickou URL na stejné doméně
        $existingUrl = Url::where('url', '=', $url)
                          ->where('domain', '=', $domain)
                          ->first();
        
        if ($existingUrl) {
            $this->logger->error('Duplicitní URL nalezena: ' . $url . ' na doméně ' . $domain);
            return ['status' => false, 'error' => 'Duplicitní URL nalezena']; // Vrací status a popis chyby
        }
    
        // Kontrola, zda na doméně existuje více stejných modelů se stejným model_id
        if ($model !== null && $modelId !== null) {
            $existingModel = Url::where('domain', '=', $domain)
                                ->where('model', '=', $model)
                                ->where('model_id', '=', $modelId)
                                ->first();
    
            if ($existingModel) {
                $this->logger->error('Duplicitní model nalezen: ' . $model . ' s model_id ' . $modelId . ' na doméně ' . $domain);
                return ['status' => false, 'error' => 'Duplicitní model nalezen']; // Vrací status a popis chyby
            }
        }
    
        return ['status' => true, 'error' => '']; // Žádná duplicita nenalezena
    }
    
        
    /**
     * createUrlIfNotDuplicate
     *
     * @param  mixed $urlData
     * @return void
     */
    public function createUrlIfNotDuplicate($urlData) {
        // Rozbalení údajů z pole pro lepší čitelnost
        $url = $urlData['url'];
        $domain = $urlData['domain'];
        $handler = $urlData['handler'];
        $model = isset($urlData['model']) ? $urlData['model'] : null;
        $modelId = isset($urlData['model_id']) ? $urlData['model_id'] : null;
        $id = isset($urlData['id']) ? $urlData['id'] : null; // ID pro rozpoznání nového vs. existujícího záznamu
    
        // Přidání lomítka na začátek URL, pokud tam není
        if (substr($url, 0, 1) !== '/') {
            $url = '/' . $url;
        }
    
        // Odstranění lomítka z konce URL, pokud tam je
        $url = rtrim($url, '/');
    
        // Kontrola duplicity
        $duplicationCheck = $this->checkForDuplicates($url, $domain, $model, $modelId);
    
        if ($duplicationCheck['status'] === false) {
            // Byla nalezena duplicita
            return $duplicationCheck;
        }
    
        try {
            if ($id) {
                // Aktualizace existujícího záznamu
                $urlToUpdate = Url::find($id);
                if (!$urlToUpdate) {
                    return ['status' => false, 'error' => 'Záznam nenalezen pro aktualizaci'];
                }
            } else {
                // Vytvoření nového záznamu
                $urlToUpdate = new Url;
            }
    
            // Nastavení hodnot
            $urlToUpdate->url = $url;
            $urlToUpdate->domain = $domain;
            $urlToUpdate->model = $model;
            $urlToUpdate->model_id = $modelId;
            $urlToUpdate->handler = $handler;
            $urlToUpdate->save();
    
            return ['status' => true, 'error' => '', 'message' => $id ? 'URL úspěšně aktualizována' : 'URL úspěšně vytvořena'];
        } catch (\Exception $e) {
            // Chyba při vkládání nebo aktualizaci do databáze
            $this->logger->error('Chyba při vkládání/aktualizaci URL do databáze: ' . $e->getMessage());
            return ['status' => false, 'error' => 'Chyba při vkládání/aktualizaci do databáze'];
        }
    }

    public function handleCreateUrlRequest() {
        // Kontrola, zda byla data odeslána metodou POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Zpracování $_POST dat
            $postData = $_POST;

            // Zavolání metody createUrlIfNotDuplicate
            $result = $this->createUrlIfNotDuplicate($postData);

            if ($result['status']) {
                // Úspěch: URL byla vytvořena
                // Přesměrování a nastavení statusu
                header('Location: '.$_SERVER['HTTP_REFERER'].'?status=success&message=' . urlencode('URL úspěšně vytvořena'));
                exit();
            } else {
                // Neúspěch: Došlo k duplicitě nebo jiné chybě
                // Přesměrování a nastavení statusu
                header('Location: '.$_SERVER['HTTP_REFERER'].'?status=error&message=' . urlencode($result['error']));
                exit();
            }
        } else {
            // Pokud data nebyla odeslána metodou POST
            header('Location: '.$_SERVER['HTTP_REFERER'].'?status=error&message=' . urlencode('Neplatný požadavek'));
            exit();
        }
    }

    /**
     * Vrátí všechny URL pro zadanou doménu.
     * Pokud je doména '*', vrátí všechny URL.
     *
     * @param string $domain Doména pro vyhledání URL
     * @return array Seznam URL záznamů
     */
    public function getAllUrlsForDomain($domain) {
        if ($domain === '*') {
            // Vrátí všechny URL bez ohledu na doménu
            $urls = Url::all();
        } else {
            // Vrátí URL pouze pro zadanou doménu
            $urls = Url::where('domain', '=', $domain)->get();
        }

        // Vrátí výsledky jako pole
        return $urls->toArray();
    }

    /**
     * Načte všechny domény a jejich informace z databáze.
     * Pokud je zadáno user ID, vrátí pouze domény spojené s tímto uživatelem.
     * @param int|null $userId ID uživatele
     * @return array Pole domén a jejich informací.
     */
    public function getAllDomainsWithInfo($userId = null) {
        // Pokud je zadáno user ID, vrátí domény pro daného uživatele a seřadí je
        if ($userId) {
            $sites = Site::where('user_id', $userId)
                        ->orderBy('domain', 'asc')
                        ->get();
        } else {
            // Vrátí všechny domény seřazené abecedně
            $sites = Site::orderBy('domain', 'asc')
                        ->get();
        }

        $domainsInfo = [];

        foreach ($sites as $site) {
            $siteInfo = $site->toArray();
            // Převod potenciálních JSON hodnot na pole
            foreach ($siteInfo as $key => $value) {
                if (is_string($value) && is_array(json_decode($value, true)) && json_last_error() === JSON_ERROR_NONE) {
                    $siteInfo[$key] = json_decode($value, true);
                }
            }

            $domainsInfo[] = $siteInfo;
        }

        return $domainsInfo;
    }

}

?>