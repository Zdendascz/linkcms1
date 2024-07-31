<?php
namespace linkcms1;
use Illuminate\Database\Capsule\Manager as Capsule;
use linkcms1\Models\User as EloquentUser;
use linkcms1\Models\Site;
use linkcms1\Models\Url;
use linkcms1\Models\Category;
use SimpleXMLElement;
use linkcms1\Models\Article;
use linkcms1\Models\UploadedFile;
use Monolog\Logger;
use Tracy\Debugger;
use Illuminate\Database\Eloquent\Model;

//Debugger::enable(Debugger::DEVELOPMENT);

/**
 * Třída reprezentující web, zpracovává kontrolu domény a informace o ní.
 * také zpracovává informace o konkrétní stránce
 */
class domainControl extends Model {

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
    public function loadDomain() {
        $domain = Site::where('domain', '=', str_replace("www.", "", $_SERVER['HTTP_HOST']))->first();
        $domainInfo = "";
    
        if ($domain) {
            foreach ($domain->getAttributes() as $key => $value) {
                // Kontrola, zda hodnota je JSON a dekódování
                if (is_string($value) && is_array(json_decode($value, true)) && json_last_error() === JSON_ERROR_NONE) {
                    $value = json_decode($value, true);
                }
                
                // Přidání hodnoty do $_SERVER s prefixem 'SITE_'
                if ($key !== 'configurations') {
                    $_SERVER['SITE_' . strtoupper($key)] = $value;
                    // Převod pole na řetězec pro logování, pokud je hodnota pole
                    $value = is_array($value) ? json_encode($value) : $value;
                } else {
                    // Speciální zpracování pro SITE_CONFIGURATIONS
                    if (is_array($value)) {
                        $configs = [];
                        foreach ($value as $config) {
                            // Uložení hodnoty a popisku do pole
                            $configs[$config['name']] = ['value' => $config['value'], 'description' => $config['description']];
                        }
                        $_SERVER['SITE_CONFIGURATIONS'] = $configs;
                        // Převod pole konfigurací na řetězec JSON pro logování
                        $value = json_encode($configs);
                    }
                }
    
                // Zde je již $value vždy řetězec, ať už původní nebo JSON reprezentace pole
                $domainInfo .= $key."=".$value;
            }
            $this->logger->debug('Načtení informací o doméně '.$_SERVER['HTTP_HOST']." >>> ".$domainInfo);
        }
        else{
            \Tracy\Debugger::barDump(str_replace("www.","",$_SERVER['HTTP_HOST']), 'Doména nebyla nalezena');
            $this->logger->error('Nepodařilo se načíst data domény '.$_SERVER['HTTP_HOST']);
            // Nastavení výchozích hodnot, pokud doména není nalezena
            $this->setDefaultDomainValues();
        }
    }

    private function setDefaultDomainValues() {
        $_SERVER['SITE_ID'] = 0;
        $_SERVER['SITE_NAME'] = 'Doména je hostována u nás';
        $_SERVER['SITE_DOMAIN'] = 'mini-web.cz';
        $_SERVER['SITE_NODOMAIN'] = str_replace("www.","",$_SERVER['HTTP_HOST']);
        $_SERVER['SITE_CREATED_AT'] = '2024-01-10 09:19:48';
        $_SERVER['SITE_UPDATED_AT'] = '2024-04-11 22:04:00';
        $_SERVER['SITE_USER_ID'] = 1;
        $_SERVER['SITE_ACTIVE'] = 'development';
        $_SERVER['SITE_TARIF_ID'] = 1;
        $_SERVER['SITE_TEMPLATE_DIR'] = 'templates/mini-web/';
        $_SERVER['SITE_LANGUAGE'] = 'cs';
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
        //echo $path;

        // Vyhledání URL v databázi, která odpovídá jak doméně, tak cestě
        $siteDomain = $_SERVER['SITE_DOMAIN']; // Uložení hodnoty do lokální proměnné

        $url = Url::where(function ($query) use ($siteDomain, $path) {
            $query->where('domain', '=', $siteDomain)
                  ->orWhere('domain', '=', '*');
        })
        ->where('url', '=', $path)
        ->first();
    
        // Kontrola, zda byl záznam nalezen
        if ($url == null) {
            // Zde můžete zpracovat situaci, kdy záznam nebyl nalezen
            \Tracy\Debugger::barDump($_SERVER['SITE_DOMAIN'].$path, 'Problém s nalezením url v db');
            \Tracy\Debugger::barDump($siteDomain, '-> doména');
            \Tracy\Debugger::barDump($path, '-> path');
            $this->logger->warning('Adresa '.$_SERVER['SITE_DOMAIN'].$path." nenalezena");

            // Můžete vrátit null nebo jinou výchozí hodnotu
            return [
            0,                           // Default ID
            $siteDomain,           // Domain
            '/404',                          // URL Path again
            'fnc404',                    // Handler Function
            '404',                       // Model
            1
            ];
        }
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

    public function noweb() {}
    public function fnc404() {}

    public function favicon(){
        $customFaviconUrl = $_SERVER["SITE_WEB"]."/data/images/".$_SERVER["SITE_DOMAIN"]."/".$_SERVER["domain"]["fav_ico"];
        $defaultFavicon = "https://mini-web.cz/templates/mini-web/images/favicon.png";  // Cesta k vaší defaultní favicon

        // Zkuste získat hlavičky pro specifickou favicon
        $headers = @get_headers($customFaviconUrl);
        if($headers && strpos( $headers[0], '200')) {
            header('Location: ' . $customFaviconUrl);
            exit;
        } else {
            header('Content-Type: image/x-icon');
            readfile($_SERVER['DOCUMENT_ROOT'] . $defaultFavicon);
            exit;
        }
    }

    public function robotsTxt() {
        $customRobotsTxtUrl = $_SERVER["SITE_WEB"]."/data/".$_SERVER["SITE_DOMAIN"]."/robots.txt";
        $defaultRobotsTxtPath = $_SERVER['DOCUMENT_ROOT'] . "/templates/mini-web/robots.txt"; // Cesta k vaší defaultní robots.txt
    
        // Zkuste získat hlavičky pro specifický robots.txt
        $headers = @get_headers($customRobotsTxtUrl);
        if ($headers && strpos($headers[0], '200')) {
            header('Location: ' . $customRobotsTxtUrl);
            exit;
        } else {
            header('Content-Type: text/plain');
            readfile($defaultRobotsTxtPath);
            exit;
        }
    }

    public function generateCategorySitemap() {
        $site_id = $_SERVER["SITE_ID"];
        
        // Získání všech aktivních kategorií pro dané site_id pomocí Eloquentu
        $categories = Category::where('site_id', $site_id)
                            ->where('is_active', 'active')
                            ->get();
        
        // Inicializace XML
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>');
        $xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        
        // Přidání kategorií do XML
        foreach ($categories as $category) {
            if (!empty($category->url)) {
                $url = $xml->addChild('url');
                $url->addChild('loc', htmlspecialchars($category->url));
                $url->addChild('lastmod', date('Y-m-d', strtotime($category->updated_at)));
                $url->addChild('changefreq', 'weekly');
                $url->addChild('priority', '0.8');
            }
        }
        
        // Nastavení hlaviček pro XML výstup
        header('Content-Type: application/xml');
        echo $xml->asXML();
        exit;
    }

    public function generateArticleSitemap() {
        $site_id = $_SERVER["SITE_ID"];
        $domain = $_SERVER["SITE_DOMAIN"];
        // Získání všech aktivních článků pro dané site_id
        $articles = Article::where('site_id', $site_id)
                    ->where('status', 'active')
                    ->get();
    
        // Inicializace XML
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>');
        $xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    
        // Přidání článků do XML
        foreach ($articles as $article) {
            // Získání URL pro daný článek z tabulky 'urls'
            $urlRecord = Url::where('model', 'articles')
                            ->where('model_id', $article->id)
                            ->first();
    
            if ($urlRecord) {
                $url = $xml->addChild('url');
                $fullUrl = 'https://' . $domain . $urlRecord->url;
                $url->addChild('loc', htmlspecialchars($fullUrl));
                $url->addChild('lastmod', date('Y-m-d', strtotime($article->updated_at)));
                $url->addChild('changefreq', 'monthly');
                $url->addChild('priority', '0.8');
            }
        }
    
        // Nastavení hlaviček pro XML výstup
        header('Content-Type: application/xml');
        echo $xml->asXML();
        exit;
    }
    
    public function generateImageSitemap()
    {
        $siteId = $_SERVER['SITE_ID'];
        $domain = $_SERVER['SITE_DOMAIN'];

        // Získání všech aktivních obrázků pro dané site_id
        $images = UploadedFile::where('site_id', $siteId)
                            ->where('status', 'active')
                            ->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                            ->with('variants')
                            ->get();

        // Inicializace XML
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>');
        $xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->addAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');

        // Přidání obrázků do XML
        foreach ($images as $image) {
            $url = $xml->addChild('url');
            $fullUrl = 'https://' . $domain . $image->file_path;
            $url->addChild('loc', htmlspecialchars($fullUrl));
            $url->addChild('lastmod', date('Y-m-d', strtotime($image->updated_at)));
            $url->addChild('changefreq', 'monthly');
            $url->addChild('priority', '0.5');

            // Přidání hlavního obrázku
            $imageTag = $url->addChild('image:image', null, 'http://www.google.com/schemas/sitemap-image/1.1');
            $imageTag->addChild('image:loc', htmlspecialchars($image->public_url), 'http://www.google.com/schemas/sitemap-image/1.1');

            // Přidání variant obrázku
            foreach ($image->variants as $variant) {
                $variantTag = $url->addChild('image:image', null, 'http://www.google.com/schemas/sitemap-image/1.1');
                $variantTag->addChild('image:loc', htmlspecialchars($variant->public_url), 'http://www.google.com/schemas/sitemap-image/1.1');
            }
        }

        // Nastavení hlaviček pro XML výstup
        header('Content-Type: application/xml');
        echo $xml->asXML();
        exit;
    }

    public function generateSitemapIndex() {
        $domain = $_SERVER['SITE_DOMAIN'];
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    
        // Inicializace XML
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><sitemapindex></sitemapindex>');
        $xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    
        // Přidání cesty k sitemap kategorií
        $sitemap = $xml->addChild('sitemap');
        $sitemap->addChild('loc', $protocol . $domain . '/sitemap-categories.xml');
        $sitemap->addChild('lastmod', date('Y-m-d'));
    
        // Přidání cesty k sitemap článků
        $sitemap = $xml->addChild('sitemap');
        $sitemap->addChild('loc', $protocol . $domain . '/sitemap-articles.xml');
        $sitemap->addChild('lastmod', date('Y-m-d'));
    
        // Přidání cesty k sitemap obrázků
        $sitemap = $xml->addChild('sitemap');
        $sitemap->addChild('loc', $protocol . $domain . '/sitemap-images.xml');
        $sitemap->addChild('lastmod', date('Y-m-d'));
    
        // Nastavení hlaviček pro XML výstup
        header('Content-Type: application/xml');
        echo $xml->asXML();
        exit;
    }

    public function endpointOk() {
        http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'No data stored']);
    }
    
}

?>