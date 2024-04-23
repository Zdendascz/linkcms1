<?php
namespace linkcms1\Models;

use Illuminate\Database\Eloquent\Model;
use Tracy\Debugger; // Importování třídy Debugger z Tracy
use Monolog\Logger; // Importování třídy Logger z Monologu
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Illuminate\Database\Capsule\Manager as Capsule;

class Category extends Model
{

    protected $logger;

    public function __construct()
    {
        parent::__construct();

        //******************** Vytvoření loggeru
        $this->logger = new Logger('linkcms');
        // Nastavení rotačního handleru pro logování úrovní NOTICE a INFO
        $debugHandler = new RotatingFileHandler(__DIR__.'/../logs/info.log', 0, Logger::INFO);
        $this->logger->pushHandler($debugHandler);

        // nastavení rotačního handleru pro debug
        $debugHandler = new RotatingFileHandler(__DIR__.'/../logs/debug.log', 0, Logger::DEBUG);
        $this->logger->pushHandler($debugHandler);

        // Nastavení handleru pro logování úrovně WARNING do jednoho souboru
        $warningHandler = new StreamHandler(__DIR__.'/../logs/warning.log', Logger::WARNING);
        $warningHandler->setFormatter(new LineFormatter(null, null, true, true));
        $this->logger->pushHandler($warningHandler);

        // Nastavení handleru pro logování úrovně ERROR do nerotujícího souboru
        $errorHandler = new StreamHandler(__DIR__.'/../logs/error.log', Logger::ERROR);
        $this->logger->pushHandler($errorHandler);
    }

    protected $table = 'categories'; // Název tabulky v databázi

    protected $fillable = [
        'title',
        'display_name',
        'top_text',
        'bottom_text',
        'meta',
        'parent_id',
        'is_active',
        'site_id',
        'order_cat',
        'url',
        'css',
        'navigation_id'
    ];

    protected $casts = [
        'meta' => 'array'
    ];

    public function parentCategory() {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function childCategories() {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function site() {
        // Předpokládá, že existuje model Site
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function articles() {
        return $this->belongsToMany(Article::class, 'article_categories', 'category_id', 'article_id');
    }

        
    /**
     * generateNavigation
     *
     * @param  mixed $siteId
     * @param  mixed $navigation_id
     * @param  mixed $parentId
     * @param  mixed $navigationSpecifications obsahuje [
     *                  ul_class
     *                  ul_id
     *                  ul_style
     *                  sub_ul (true/dalse)
     *                  sub_ul_class
     * *                sub_ul_id
     *                  sub_ul_style
     *                  ]
     * @return void
     */
    public static function generateNavigation($siteId, $navigation_id = null, $parentId = null, $navigationSpecifications = false) {
        $insertUl = "";
        if (isset($navigationSpecifications)) {
            if (isset($navigationSpecifications["ul_class"])) $insertUl .= " class='" . $navigationSpecifications["ul_class"] . "'";
            if (isset($navigationSpecifications["ul_id"])) $insertUl .= " id='" . $navigationSpecifications["ul_id"] . "'";
            if (isset($navigationSpecifications["ul_style"])) $insertUl .= " style='" . $navigationSpecifications["ul_style"] . "'";
        }
    
        $html = '<ul' . $insertUl . '>';
        $categories = self::where('parent_id', $parentId)
                        ->where('site_id', $siteId)
                        ->where('navigation_id', $navigation_id)
                        ->where('is_active', 'active')
                        ->orderBy('order_cat', 'asc')
                        ->get();
    
        foreach ($categories as $category) {
            $css = json_decode($category->css_cat, true) ?? [];
            $aAttributes = self::prepareAttributes($css, 'a');
            $liAttributes = self::prepareAttributes($css, 'li');
    
            $html .= '<li ' . $liAttributes . '>';
            $html .= '<a href="' . htmlspecialchars($category->url) . '" ' . $aAttributes . '>' . htmlspecialchars($category->title) . '</a>';
    
            // Check if this category has children and sub_ul attribute is 1
            $childCategories = self::where('parent_id', $category->id)->where('site_id', $siteId)->where('is_active', 'active')->get();
            if (count($childCategories) > 0 && (isset($navigationSpecifications['sub_ul']) && $navigationSpecifications['sub_ul'] == 1)) {
                $childHtml = self::generateNavigation($siteId, $navigation_id, $category->id, $navigationSpecifications); // propagate specifications to children
                $html .= $childHtml; // childHtml should include <ul> only if children exist
            }
    
            $html .= '</li>';
        }
    
        $html .= '</ul>';
        return $html;
    }
    
    
    private static function prepareAttributes($css, $tag) {
        $attributes = '';
        if (isset($css[$tag . '_class']) && $css[$tag . '_class'] != '') {
            $attributes .= 'class="' . htmlspecialchars($css[$tag . '_class']) . '" ';
        }
        if (isset($css[$tag . '_id']) && $css[$tag . '_id'] != '') {
            $attributes .= 'id="' . htmlspecialchars($css[$tag . '_id']) . '" ';
        }
        if (isset($css[$tag . '_style']) && $css[$tag . '_style'] != '') {
            $attributes .= 'style="' . htmlspecialchars($css[$tag . '_style']) . '" ';
        }
        return $attributes;
    }

      /**
     * Vrací celou cestu kategorie jako pole kategorií.
     *
     * @return array
     */
    public function getPath()
    {
        $path = [];
        $category = $this;

        while ($category) {
            array_unshift($path, $category); // Přidá kategorii na začátek pole
            $category = $category->parentCategory; // Přechod k nadřazené kategorii
        }

        return $path;
    }

    /**
     * Vrací informace o kategorii a její cestu.
     *
     * @param int $id ID kategorie
     * @return array
     */
    public static function getCategoryInfo($id)
    {
        $category = self::find($id);
        if (!$category) {
            return null; // nebo vyvolání výjimky
        }

        $path = $category->getPath(); // Získání cesty kategorie
        $pathInfo = [];

        foreach ($path as $cat) {
            $pathInfo[] = [
                'id' => $cat->id,
                'title' => $cat->title,
                'display_name' => $cat->display_name,
                'top_text' => $cat->top_text,
                'bottom_text' => $cat->bottom_text,
                'url' => $cat->url,
                'meta' => $cat->meta

                
                // lze přidat další požadované atributy
            ];
        }

        return [
            'categoryInfo' => [
                'id' => $category->id,
                'title' => $category->title,
                'display_name' => $category->display_name,
                'top_text' => $category->top_text,
                'bottom_text' => $category->bottom_text,
                'url' => $category->url,
                'meta' => $category->meta
                // přidejte další požadované atributy
            ],
            'pathInfo' => $pathInfo
        ];
    }

    public function categories(){

    }

    public static function getAllCategoriesTree($siteId,$navigation_id=false) {
        if($navigation_id <> false){
            $categories = self::where('site_id', $siteId)
                               ->where('navigation_id', $navigation_id)
                               ->orderBy('parent_id', 'asc')
                               ->orderBy('order_cat', 'asc')
                               ->get();
        }
        else{
            $categories = self::where('site_id', $siteId)
                           ->orderBy('parent_id', 'asc')
                           ->orderBy('order_cat', 'asc')
                           ->get();
        }
        
        $categoriesById = [];
        foreach ($categories as $category) {
            $catArray = $category->toArray();
            
            // Dekódování JSON hodnot
            if (!empty($catArray['meta'])) {
                $catArray['meta'] = json_decode($catArray['meta'], true);
            } else {
                $catArray['meta'] = []; // Nastavte výchozí prázdné pole, pokud je meta prázdné
            }
    
            if (!empty($catArray['css_cat'])) {
                $catArray['css_cat'] = json_decode($catArray['css_cat'], true);
            } else {
                $catArray['css_cat'] = []; // Nastavte výchozí prázdné pole, pokud je css_cat prázdné
            }
    
            $categoriesById[$category->id] = $catArray;
            $categoriesById[$category->id]['children'] = [];
        }
    
        $tree = [];
        foreach ($categoriesById as $id => &$category) {
            if (is_null($category['parent_id'])) {
                $tree[] =& $category;
            } else {
                $categoriesById[$category['parent_id']]['children'][] =& $category;
            }
        }
    
        return $tree;
    }
    
    
    /**
     * Uloží nebo upraví kategorii na základě poskytnutých dat.
     *
     * @param array $data Data kategorie k uložení nebo aktualizaci.
     * @return array Vrací pole se status a zprávou o výsledku operace.
     */
    public static function saveOrUpdate($data) {
        // Kontrola, zda je nastaveno ID a není prázdné
        if (isset($data['id']) && !empty($data['id'])) {
            // Aktualizace existující kategorie
            $category = self::find($data['id']);
            $category->order_cat = $data['order_cat'] ?? null;
            if (!$category) {
                $this->logger->error('Kategorie nebyla nalezena:'.print_r($data));
                return ['status' => false, 'message' => 'Kategorie nebyla nalezena.'];
            }
        } else {
            // Vytvoření nové kategorie
            $category = new self();
            // Najděte nejvyšší hodnotu order_cat pro dané parent_id
            $highestOrderCat = self::where('parent_id', $category->parent_id)->max('order_cat');
        
            // Nastavení order_cat
            $category->order_cat = $highestOrderCat + 1;
        }
    
        // Nastavení hodnot kategorie
        $category->title = $data['title'];
        $category->display_name = $data['display_name'];
        $category->top_text = $data['top_text'] ?? null;
        $category->bottom_text = $data['bottom_text'] ?? null;
        $category->meta = json_encode([
            'title' => $data['meta_title'] ?? '',
            'description' => $data['meta_description'] ?? '',
            'keywords' => $data['meta_keywords'] ?? ''
        ]);
    
        // Kontrola a nastavení parent_id
        if (empty($data['parent_id']) || $data['parent_id'] === '0') {
            $category->parent_id = null;
        } else {
            $category->parent_id = $data['parent_id'];
        }
    
        
        $category->is_active = $data['is_active'] ?? 1;
        $category->site_id = $data['site_id'];
        $category->navigation_id = $data['navigation_id'];
        $category->url = $data['url_manual'] ?? null;
        $category->css_cat = json_encode([
            'a_class' => $data['a_class'] ?? '',
            'a_id' => $data['a_id'] ?? '',
            'a_style' => $data['a_style'] ?? '',
            'li_class' => $data['li_class'] ?? '',
            'li_id' => $data['li_id'] ?? '',
            'li_style' => $data['li_style'] ?? ''
        ]);

        // Uložení kategorie
        if ($category->save()) {
            $instance = new self(); // Vytvoření instance aktuální třídy
            $urlProcessingResult = $instance->processUrlForCategory($category);
            if ($urlProcessingResult['status'] === false) {
                // V případě chyby při zpracování URL
                return $urlProcessingResult;
            }
            return ['status' => true, 'message' => 'Kategorie byla úspěšně uložena.'];
        } else {
            $this->logger->error('Kategorii se nepodařilo vytvořit:'.print_r($data));
            return ['status' => false, 'message' => 'Nepodařilo se uložit kategorii.'];
        }
    }
    

    /**
     * Handler pro uložení nebo aktualizaci kategorie.
     */
    public function handleSaveOrUpdateCategory() {
        // Kontrola, zda byla data odeslána metodou POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Zpracování dat formuláře
            $postData = $_POST;

            // Případná další validace dat zde

            // Volání metody pro uložení nebo aktualizaci kategorie
            $result = Category::saveOrUpdate($postData);

            if ($result['status']) {
                // Úspěch: Přesměrování s úspěšnou zprávou
                header('Location: '.$_SERVER['HTTP_REFERER'].'?status=success&message=' . urlencode($result['message']));
                exit;
            } else {
                // Neúspěch: Přesměrování s chybovou zprávou
                $this->logger->warning($result['message']);
                header('Location: '.$_SERVER['HTTP_REFERER'].'?status=error&message=' . urlencode($result['message']));
                exit;
            }
        } else {
            // Pokud data nebyla odeslána metodou POST, přesměrování zpět s chybovou zprávou
            $this->logger->warning('Neplatný požadavek přo volání metody handleSaveOrUpdateCategory');
            header('Location: '.$_SERVER['HTTP_REFERER'].'?status=error&message=' . urlencode('Neplatný požadavek'));
            exit;
        }
    }
    // Další metody a logika pro model mohou být zde
    
    /**
     * getUrlsWithTitle
     * 
     * Tato metoda předpokládá, že existují modely Url, Article a Category 
     * s odpovídajícími metodami pro načtení dat z databáze. Dále se předpokládá, 
     * že každý model má atributy odpovídající sloupcům v jejich databázových 
     * tabulkách. Metoda getUrlsWithTitle načte URL, zjistí jejich model a podle 
     * toho vyhledá titulky článků nebo kategorií, které pak vrátí v seřazeném poli.
     *
     * @param  string $domain
     * @return array
     */
    public static function getUrlsWithTitle($domain) {
        // Načtení všech URL pro zadaný domain, kde model je null nebo 'article'
        $urls = Url::where('domain', $domain)
                    ->where(function ($query) {
                        $query->whereNull('model')
                            ->orWhere('model', 'articles');
                    })
                    ->get();

        $urlsWithTitle = [];

        foreach ($urls as $url) {
            $fullUrl = $_SERVER["REQUEST_SCHEME"]."://" . $url->domain .$_SERVER["BASE_PATH"]. $url->url;

            if ($url->model == 'articles') {
                // Načtení článku, pokud je model 'article'
                $article = Article::find($url->model_id);
                $title = $article ? $article->title : $fullUrl;
            } else {
                // Nastavení výchozí hodnoty na plnou URL, pokud je model null
                $title = $fullUrl;
            }

            $urlsWithTitle[] = [
                'url' => $fullUrl,
                'title' => $title
            ];
        }

        // Seřazení pole podle title
        usort($urlsWithTitle, function ($a, $b) {
            return strcmp($a['title'], $b['title']);
        });
        return $urlsWithTitle;
    }

    public static function updateCategoryHierarchy($categoryData) {
        try {
            // Zámek transakce pro konzistenci dat
            self::getConnectionResolver()->connection()->beginTransaction();
    
            $categoryId = $categoryData['id'];
            $newParentId = $categoryData['parent_id'];
            $newOrder = $categoryData['order_cat'];
    
            // Načtení původní kategorie
            $originalCategory = self::find($categoryId);
            if (!$originalCategory) {
                $this->logger->warning("Kategorie ".$categoryId." nebyla nalezena.");
                throw new \Exception("Kategorie nebyla nalezena.");
            }
    
            $originalParentId = $originalCategory->parent_id;
            $originalOrder = $originalCategory->order_cat;
    
            // Příprava na aktualizaci pořadí v původním parent_id, pokud je potřeba
            if ($originalParentId !== $newParentId) {
                // Zpracování kategorií s původním parent_id
                self::where('parent_id', $originalParentId)
                    ->where('order_cat', '>', $originalOrder)
                    ->decrement('order_cat');
                
                // Zpracování kategorií s novým parent_id
                self::where('parent_id', $newParentId)
                    ->where('order_cat', '>=', $newOrder)
                    ->increment('order_cat');
            } else {
                // Stejné parent_id, ale změna order_cat
                if ($newOrder < $originalOrder) {
                    self::where('parent_id', $newParentId)
                        ->whereBetween('order_cat', [$newOrder, $originalOrder])
                        ->increment('order_cat');
                } elseif ($newOrder > $originalOrder) {
                    self::where('parent_id', $newParentId)
                        ->whereBetween('order_cat', [$originalOrder, $newOrder])
                        ->decrement('order_cat');
                }
            }
    
            // Aktualizace přesunované kategorie
            $originalCategory->parent_id = $newParentId;
            $originalCategory->order_cat = $newOrder;
            $originalCategory->save();
    
            self::getConnectionResolver()->connection()->commit();
            return ['status' => true, 'message' => 'Kategorie byla úspěšně aktualizována.'];
        } catch (\Exception $e) {
            self::getConnectionResolver()->connection()->rollBack();
            $this->logger->warning($e->getMessage());
            return ['status' => false, 'message' => 'Chyba při aktualizaci kategorie: ' . $e->getMessage()];
        }
    }
    

    private static function updateOrderCat($parentId) {
        $siblings = self::where('parent_id', $parentId)->orderBy('order_cat', 'asc')->get();
        foreach ($siblings as $index => $sibling) {
            // Předpokládáme, že pořadí začíná od 0. Pokud chcete začít od 1, použijte ($index + 1)
            $sibling->order_cat = $index;
            $sibling->save();
        }
    }

    public function updateCategoryOrder() {
        // Kontrola, zda byla data odeslána metodou POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Zpracování dat odeslaných AJAXem
            $categoriesData = json_decode(file_get_contents('php://input'), true);
    
            // Případná další validace dat zde
    
            // Vytvoření instance modelu a volání metody pro aktualizaci
            $categoryModel = new \linkcms1\Models\Category(); // Ujistěte se, že používáte správný namespace
            $result = $categoryModel::updateCategoryHierarchy($categoriesData); // Metoda by měla být statická na základě předchozího kódu
    
            // Nastavení hlavičky pro JSON odpověď
            header('Content-Type: application/json');
            // Vrácení odpovědi
            echo json_encode($result);
        } else {
            // Pokud data nebyla odeslána metodou POST, vrací chybovou zprávu
            header('Content-Type: application/json');
            echo json_encode(['status' => false, 'message' => 'Neplatný požadavek']);
        }
        exit; // Doporučuji přidat exit pro ukončení skriptu po odeslání odpovědi
    }
    
    public static function processUrlForCategory($category) {
        $parsedUrl = parse_url($category->url);
        Debugger::barDump($parsedUrl, 'Parse url');
        $domain = preg_replace('/^(http:\/\/|https:\/\/)/', '', $parsedUrl['host']);
        $domain = preg_replace('/^www\./', '', $domain);
        $domain = rtrim($domain, '/');
        $path = $parsedUrl['path'] ?? '';
        $path = str_replace($_SERVER["BASE_PATH"],"",$path);
    
        // Kontrola existence URL s danou doménou a cestou
        $existingUrl = Url::where('domain', $domain)
                          ->where('url', $path)
                          ->first();
    
        // Pokud existuje URL se stejnou doménou a cestou
        if ($existingUrl) {
            // Situace 2.1
            if ($existingUrl->model === 'categories' && $existingUrl->model_id == $category->id) {
                // Záznam se edituje pouze pokud je potřeba aktualizace
                $existingUrl->url = $path; // V případě, že je třeba aktualizovat
                $existingUrl->save();
                return ['status' => true, 'message' => 'URL úspěšně aktualizována.'];
            }
            // V ostatních případech nemůžeme vytvořit duplicitní záznam
            return ['status' => false, 'message' => 'Existuje záznam s identickou URL a doménou.'];
        } else {
            // Situace 1 a 2.2: Vytvoření nového záznamu
            $newUrl = new Url;
            $newUrl->domain = $domain;
            $newUrl->url = $path;
            $newUrl->handler = 'getActiveArticlesByCategoryWithUrlAndAuthor';
            $newUrl->model = 'categories';
            $newUrl->model_id = $category->id;
            $newUrl->save();
            Debugger::barDump($newUrl, 'NEW url');
            return ['status' => true, 'message' => 'Nová URL úspěšně vytvořena.'];
        }
    }

    public function images()
    {
        return $this->morphToMany(UploadedFile::class, 'imageable', 'imageables', 'imageable_id', 'image_id')
                    ->withPivot('imageable_type');
    }
}
?>