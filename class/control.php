<?php
namespace linkcms1;
use Illuminate\Database\Capsule\Manager as Capsule;
use linkcms1\Models\User as EloquentUser;
use linkcms1\Models\Site;
use linkcms1\Models\Url;
use linkcms1\Models\Category;
use linkcms1\Models\Article;
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
        $path = str_replace($_SERVER["BASE_PATH"], '', $path);

        // Vyhledání URL v databázi, která odpovídá jak doméně, tak cestě
        $siteDomain = $_SERVER['SITE_DOMAIN']; // Uložení hodnoty do lokální proměnné

        $url = Url::where(function ($query) use ($siteDomain, $path) {
            $query->where('domain', '=', $siteDomain)
                  ->orWhere('domain', '=', '*');
        })
        ->where('url', '=', $path)
        ->first();
    
        // Kontrola, zda byl záznam nalezen
        if ($url === null) {
            // Zde můžete zpracovat situaci, kdy záznam nebyl nalezen
            \Tracy\Debugger::barDump($_SERVER['SITE_DOMAIN'].$path, 'Problém s nalezením url v db');
            \Tracy\Debugger::barDump($siteDomain, '-> doména');
            \Tracy\Debugger::barDump($path, '-> path');
            $this->logger->warning('Adresa '.$_SERVER['SITE_DOMAIN'].$path." nenalezena");

            // Můžete vrátit null nebo jinou výchozí hodnotu
            return null;
        }
        \Tracy\Debugger::barDump($url->toArray(), 'aktuální url');
        return $url->toArray();
    }
        
    /**
     * checkForDuplicates
     * Funkce provádí kontrolu diuplicit url. Nesmí být jedna url víckrát pod jednou doménou.
     * a pokud není model_id 0/null/empty, tak nesmí být stejné model_id ve stejném model
     *
     * @param  mixed $url
     * @param  mixed $domain
     * @param  mixed $model
     * @param  mixed $modelId
     * @param  mixed $excludeId
     * @return void
     */
    public function checkForDuplicates($url, $domain, $model = null, $modelId = null, $excludeId = null) {
        $urlQuery = Url::where('url', '=', $url)->where('domain', '=', $domain);
        
        // Vyloučení záznamu s ID, pokud je poskytnuto
        if ($excludeId) {
            $urlQuery->where('id', '!=', $excludeId);
        }
    
        $existingUrl = $urlQuery->first();
    
        if ($existingUrl) {
            return ['status' => false, 'error' => 'Vámi zadaná adresa už existuje'];
        }
    
        // Kontrola modelu a model_id pouze pokud model_id není prázdné, null nebo 0
        // a model je 'article' nebo 'category'
        if (($model === 'article' || $model === 'category') && $modelId !== null && $modelId != 0) {
            $modelQuery = Url::where('domain', '=', $domain)->where('model', '=', $model)->where('model_id', '=', $modelId);
    
            if ($excludeId) {
                $modelQuery->where('id', '!=', $excludeId);
            }
    
            $existingModel = $modelQuery->first();
    
            if ($existingModel) {
                return ['status' => false, 'error' => 'V zadaném modelu '.$model.' je už id '.$modelId.' použito.'];
            }
        }
    
        return ['status' => true, 'error' => ''];
    }
    
        
    public function createUrlIfNotDuplicate($urlData) {
        // Rozbalení údajů z pole pro lepší čitelnost
        $url = '/' . ltrim($urlData['url'], '/'); // Přidání lomítka na začátek URL a odstranění případného duplicitního lomítka
        $domain = $urlData['domain'];
        $handler = $urlData['handler'];
        $model = isset($urlData['model']) ? $urlData['model'] : null;
        $modelId = isset($urlData['model_id']) ? $urlData['model_id'] : null;
        $id = isset($urlData['id']) && $urlData['id'] ? $urlData['id'] : null;
    
        // Kontrola duplicity s vyloučením aktuálního záznamu při editaci
        $duplicationCheck = $this->checkForDuplicates($url, $domain, $model, $modelId, $id);
    
        if ($duplicationCheck['status'] === false) {
            return $duplicationCheck;
        }
    
        try {
            $urlToUpdate = $id ? Url::find($id) : new Url;
            if ($id && !$urlToUpdate) {
                return ['status' => false, 'error' => 'Záznam nenalezen pro aktualizaci'];
            }
    
            $urlToUpdate->url = $url;
            $urlToUpdate->domain = $domain;
            $urlToUpdate->model = $model;
            $urlToUpdate->model_id = $modelId;
            $urlToUpdate->handler = $handler;
            $urlToUpdate->save();
    
            return ['status' => true, 'error' => '', 'message' => $id ? 'URL úspěšně aktualizována' : 'URL úspěšně vytvořena'];
        } catch (\Exception $e) {
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
        $urls = $domain === '*' ? Url::all() : Url::where('domain', '=', $domain)->get();
        
        $urls = $urls->map(function ($url) {
            if ($url->model === 'categories') {
                $content = Category::find($url->model_id);
            } elseif ($url->model === 'articles') {
                $content = Article::find($url->model_id);
            } else {
                $content = null;
            }
    
            if ($content) {
                $url->content_title = $content->title;
            } else {
                $url->content_title = null;
            }
    
            return $url;
        });
    
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

    public static function prepareArguments($methodName, $vars) {
        switch ($methodName) {
            case 'updateCategoryOrder':
                return [$_REQUEST];
            // Zde můžete přidat další případy pro jiné metody
            default:
                return $vars;
        }
    }

}

?>