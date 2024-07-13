<?php
namespace linkcms1\Models;

use PDO;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;
use linkcms1\Models\ArticleCategory;
use linkcms1\Models\ArticleImage;
use linkcms1\Models\Url;
use linkcms1\Models\Category;
use PHPAuth\Config as PHPAuthConfig;
use PHPAuth\Auth as PHPAuth;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Tracy\Debugger;
use DateTime;

//Debugger::enable(Debugger::DEVELOPMENT);
$dbh = new PDO(
    'mysql:host=' . $_SERVER['DB_HOST'] . ';dbname=' . $_SERVER['DB_NAME'],
    $_SERVER['DB_USER'],
    $_SERVER['DB_PASSWORD'],
    array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'") // Nastavení kódování, pokud je potřeba
);

$config = new PHPAuthConfig($dbh);
$auth = new PHPAuth($dbh, $config);

class Article extends Model {

    protected $table = 'articles'; // Název tabulky v databázi

    protected $fillable = [
        'title',
        'subtitle',
        'short_text',
        'snippet',
        'body',
        'author_id',
        'meta', // Poznámka: Toto pole bude automaticky konvertováno na a z JSON.
        'status',
        'publish_at',
        'publish_end_at',
        'manual_update_at',
        'site_id'
    ];

    protected $casts = [
        'meta' => 'array'
    ];

    public function author() {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function categories() {
        return $this->belongsToMany(Category::class, 'article_categories', 'article_id', 'category_id');
    }

    // Accessor pro 'meta' atribut
    public function getMetaAttribute($value)
    {
        return json_decode($value, true);
    }

    // Příklad nastavení loggeru (toto by se obvykle dělo mimo tuto metodu)
    public static $logger = null;

    public static function getLogger()
    {
        if (self::$logger === null) {
            self::$logger = new Logger('linkcms');
            self::$logger->pushHandler(new StreamHandler(__DIR__.'/../logs/error.log', Logger::DEBUG));
        }
        return self::$logger;
    }

    /**
     * Uloží nebo aktualizuje článek a jeho kategorie.
     * @param array $data Data článku.
     * @param int $userId ID uživatele provádějícího akci.
     * @return array Výsledek operace.
     */
    public static function saveOrUpdateArticle($data) {
        DB::beginTransaction();
    
        try {
            $body = $data['body'];
            $body = str_replace("<pre>", "", $body);
            $body = str_replace("</pre>", "", $body);
            $body = preg_replace('/<form[^>]*>/', '', $body);
            $body = preg_replace('/<\/form>/', '', $body);
            $body = htmlspecialchars($body);

            $publishAtInput = $data['publish_at'] ?? null;
            $publishEndAtInput = $data['publish_end_at'] ?? null;
            $modifiedAtInput = $data['modified_at'] ?? null;

            // Přeformátování 'publish_at'
            $publishAt = null;
            if (!empty($publishAtInput)) {
                $publishAtDateTime = DateTime::createFromFormat('Y-m-d\TH:i', $publishAtInput);
                if ($publishAtDateTime) {
                    $publishAt = $publishAtDateTime->format('Y-m-d H:i:s');
                }
            }

            // Přeformátování 'publish_end_at'
            $publishEndAt = null;
            if (!empty($publishEndAtInput)) {
                $publishEndAtDateTime = DateTime::createFromFormat('Y-m-d\TH:i', $publishEndAtInput);
                if ($publishEndAtDateTime) {
                    $publishEndAt = $publishEndAtDateTime->format('Y-m-d H:i:s');
                }
            }

            $status = $data['status']; // Předpokládá se, že status je již načten z $data

            // Nastavení publish_at na aktuální čas, pokud je status 'active' a publish_at je prázdné
            if ($status === 'active' && empty($publishAtInput)) {
                $publishAt = date('Y-m-d H:i:s'); // Aktuální čas ve správném formátu
            } else {
                $publishAt = !empty($publishAtInput) ? DateTime::createFromFormat('Y-m-d\TH:i', $publishAtInput)->format('Y-m-d H:i:s') : null;
            }

            // Nastavení publish_end_at na aktuální čas, pokud je status 'suspend', publish_at je v minulosti a publish_end_at je prázdné
            if ($status === 'suspend' && !empty($publishAt) && new DateTime($publishAt) < new DateTime() && empty($publishEndAtInput)) {
                $publishEndAt = date('Y-m-d H:i:s'); // Aktuální čas ve správném formátu
            } else {
                $publishEndAt = !empty($publishEndAtInput) ? DateTime::createFromFormat('Y-m-d\TH:i', $publishEndAtInput)->format('Y-m-d H:i:s') : null;
            }

            // Přeformátování 'modified_at'
            $modifiedAt = null;
            if (!empty($modifiedAtInput)) {
                $modifiedAtDateTime = DateTime::createFromFormat('Y-m-d\TH:i', $modifiedAtInput);
                if ($modifiedAtDateTime) {
                    $modifiedAt = $modifiedAtDateTime->format('Y-m-d H:i:s');
                }
            }

            $article = Article::updateOrCreate(
                ['id' => $data['id'] ?? null], // Klíče pro vyhledání
                [
                    'title' => $data['title'],
                    'subtitle' => $data['subtitle'] ?? null,
                    'short_text' => $data['short_text'] ?? null,
                    'snippet' => $data['snippet'] ?? null,
                    'body' => $body,
                    'user_id' => $data['user_id'],
                    'meta' => json_encode([
                        'title' => $data['meta']['title'] ?? '',
                        'description' => $data['meta']['description'] ?? '',
                        'keywords' => $data['meta']['keywords'] ?? '',
                        'content_typ' => $data['meta']['content_typ'] ?? '',
                        'mainCatid' => $data['mainCatid'] ?? '',
                    ]),
                    'publish_at' => $publishAt,
                    'publish_end_at' => $publishEndAt,
                    'manual_update_at' => $modifiedAt,
                    'status' => $status,
                    'site_id' => $_SERVER["SITE_ID"]
                ]
            );
    
            // Zpracování kategorií
            if (isset($data['categories'])) {
                $article->categories()->sync($data['categories']);
            }
    
            // Zpracování obrázků
            if (!empty($data['selectedImageId1'])) {
                $article->assignImage($data['selectedImageId1'], 'articleDetail');
            }
            if (!empty($data['selectedImageId2'])) {
                $article->assignImage($data['selectedImageId2'], 'articles');
            }
            if (!empty($data['selectedImageIds3'])) {
                $galleryImageIds = explode(',', $data['selectedImageIds3']); // Předpokládáme, že ID jsou oddělená čárkou
                foreach ($galleryImageIds as $fileId) {
                    if (!empty(trim($fileId))) {
                        $article->assignImage(trim($fileId), 'gallery');
                    }
                }
            }
    
            // Zpracování URL pouze pokud se jedná o nový článek
            if (empty($data['id'])) {
                //$safeTitle = self::createSafeTitle($data['url']); // Implementujte podle vašich pravidel

                $urlProcessResult = self::processUrlForArticle($article, $data['url']);
        
                if (is_array($urlProcessResult) && !$urlProcessResult['success']) {
                    DB::rollBack();
                    return $urlProcessResult; // Vrátí chybovou zprávu z processUrlForArticle
                }
            }
    
            DB::commit();
            return ['status' => true, 'message' => 'Článek byl úspěšně uložen.'];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['status' => false, 'message' => 'Nastala chyba při ukládání článku: ' . $e->getMessage()];
        }
    }

    /**
     * Tato metoda by měla odstranit diakritiku a speciální znaky z názvu
     * @param string $title Název článku
     * @return string Bezpečný řetězec pro URL
     */
    protected static function createSafeTitle($title) {
        // Převod všech znaků na malá písmena
        $safeTitle = mb_strtolower($title, 'UTF-8');
    
        // Odstranění diakritiky
        $safeTitle = iconv('UTF-8', 'ASCII//TRANSLIT', $safeTitle);
    
        // Nahrazení všech znaků, které nejsou písmeny, čísly nebo pomlčkami, pomlčkou
        $safeTitle = preg_replace('/[^a-z0-9]/', '-', $safeTitle);
    
        // Odstranění vedoucích a závěrečných pomlček
        $safeTitle = trim($safeTitle, '-');
    
        // Nahrazení více po sobě jdoucích pomlček jednou pomlčkou
        $safeTitle = preg_replace('/-+/', '-', $safeTitle);
    
        return $safeTitle;
    }

    /**
     * Zpracuje URL pro článek.
     * @param Article $article Instance článku
     * @param string $urlPath Cesta URL
     */
    protected static function processUrlForArticle($article, $urlPath) {
        $logger = self::getLogger();
    
        try {
            $parsedUrl = parse_url($urlPath);
            $path = $parsedUrl['path'] ?? '';
    
            $logger->info("Processing URL for article", ['article_id' => $article->id, 'urlPath' => $urlPath]);
    
            // Doména z HTTP hlavičky
            $domain = preg_replace('/^(http:\/\/|https:\/\/)/', '', $_SERVER['HTTP_HOST']);
            $domain = preg_replace('/^www\./', '', $domain);
            $domain = rtrim($domain, '/');
    
            // Najde existující URL pro daný článek
            $existingUrl = Url::where('model', 'articles')
                              ->where('domain', '=', $domain)
                              ->where('model_id', $article->id)
                              ->first();
    
            // Pokud existuje URL a je shodná s novou URL, nic se neděje
            if ($existingUrl && $existingUrl->url === $path) {
                $logger->info("Existing URL matches new URL", ['url' => $path]);
                return true;
            }
    
            // Kontrola existence jiného záznamu s novou URL
            $urlConflict = Url::where('url', $path)
                              ->where('domain', '=', $domain)
                              ->where(function ($query) use ($article) {
                                  $query->where('model', '!=', 'articles')
                                        ->orWhere(function ($query) use ($article) {
                                            $query->where('model', 'articles')
                                                  ->where('model_id', '!=', $article->id);
                                        });
                              })
                              ->first();
    
            if ($urlConflict) {
                $logger->warning("URL conflict detected", ['url' => $path, 'conflict_id' => $urlConflict->id]);
                // Existuje konflikt URL, vrátí chybu s ID konfliktního záznamu
                return ['success' => false, 'message' => 'URL už existuje: url_id' . $urlConflict->id];
            }
    
            if ($existingUrl) {
                // Aktualizace stávajícího URL záznamu
                $existingUrl->url = $path;
                $existingUrl->save();
                $logger->info("Existing URL updated", ['url' => $path]);
            } else {
                // Vytvoření nového URL záznamu, pokud neexistuje
                $newUrl = new Url;
                $newUrl->domain = $domain;
                $newUrl->url = $path;
                $newUrl->handler = 'getArticleDetails';
                $newUrl->model = 'articles';
                $newUrl->model_id = $article->id;
                $newUrl->save();
                $logger->info("New URL created", ['url' => $path, 'article_id' => $article->id]);
            }
    
            return true;
        } catch (Exception $e) {
            $logger->error("Error processing URL for article", [
                'article_id' => $article->id,
                'urlPath' => $urlPath,
                'exception' => $e->getMessage()
            ]);
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }
    

    public function handleSaveOrUpdateArticle() {
        // Kontrola, zda byla data odeslána metodou POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Zpracování dat formuláře
            $postData = $_POST;
            // Případná další validace dat zde
    
            // Volání metody pro uložení nebo aktualizaci článku
            $result = Article::saveOrUpdateArticle($postData);
    
            if (isset($result['status']) and $result['status'] == true) {
                // Úspěch: Přesměrování s úspěšnou zprávou
                $redirectUrl = strpos($_SERVER['HTTP_REFERER'], '?') !== false ? $_SERVER['HTTP_REFERER'] . '&status=success&message=' . urlencode($result['message']) : $_SERVER['HTTP_REFERER'] . '?status=success&message=' . urlencode($result['message']);
                header('Location: ' . $redirectUrl);
                exit;
            } else {
                // Neúspěch: Přesměrování s chybovou zprávou
                $redirectUrl = strpos($_SERVER['HTTP_REFERER'], '?') !== false ? $_SERVER['HTTP_REFERER'] . '&status=error&message=' . urlencode($result['message']) : $_SERVER['HTTP_REFERER'] . '?status=error&message=' . urlencode($result['message']);
                header('Location: ' . $redirectUrl);
                exit;
            }
        } else {
            // Pokud data nebyla odeslána metodou POST, přesměrování zpět s chybovou zprávou
            $redirectUrl = strpos($_SERVER['HTTP_REFERER'], '?') !== false ? $_SERVER['HTTP_REFERER'] . '&status=error&message=' . urlencode('Neplatný požadavek') : $_SERVER['HTTP_REFERER'] . '?status=error&message=' . urlencode('Neplatný požadavek');
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    /**
     * Načte všechny články včetně kategorií, ke kterým patří.
     * @return Illuminate\Database\Eloquent\Collection Kolekce článků s kategoriemi
     */
    public static function getAllArticlesWithCategories() {
        // Přidání podmínky where do dotazu pro filtraci článků podle site_id
        $articles = self::with('categories')->where('site_id', $_SERVER["SITE_ID"])->orderBy('created_at', 'desc')->get();
    
        // Transformace 'meta' dat z JSON na PHP pole pro každý článek, pokud ještě nejsou pole
        $articles->transform(function ($article) {
            if (is_string($article->meta)) { // Kontrola, zda je 'meta' řetězec
                $article->meta = json_decode($article->meta, true);
            } elseif (is_null($article->meta)) {
                $article->meta = []; // Přiřaďte prázdné pole, pokud je 'meta' null
            }
            // Pokud 'meta' už je pole, nic se neděje
    
            // Získání dat o souborech a obrázcích pro aktuální článek
            $filesAndImages = self::getArticleFilesAndImages($article->id);
    
            // Přidání informací o souborech a obrázcích k článku
            $article->files = $filesAndImages['files'];
            $article->images = $filesAndImages['images'];

            // Pokud je v meta parametr 'mainCatid', načtení a přidání informací o kategorii
            if (!empty($article->meta['mainCatid'])) {
                $categoryInfo = Category::getCategoryInfo($article->meta['mainCatid']);
                $article->mainCategoryInfo = $categoryInfo;
            } else {
                $article->mainCategoryInfo = null;
            }
    
            return $article;
        });
    
        return $articles;
    }

    /**
     * Získá detail článku včetně autora, kategorií a URL.
     *
     * @param int $id ID článku
     * @return array Data článku
     */
    public static function getArticleDetails($id) {
        $article = self::with(['author', 'categories', 'url', 'images' => function($query) {
            // Předpokládáme, že je třeba explicitně načíst data obrázků
            $query->where('imageable_type', Article::class);
        }])
        ->where('id', $id)
        ->first();
        if (!$article) {
            return null; // Nebo vhodná reakce v případě, že článek není nalezen
        }
    
        // Dekódování JSON 'meta' pole do asociativního pole
        $metaData = json_decode($article->meta, true);
    
        // Získání informací o kategoriích včetně id a názvu
        $categories = $article->categories->map(function($category) {
            return ['id' => $category->id, 'name' => $category->title];
        })->toArray();

        // Dynamické sestavení celé URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER["HTTP_HOST"];
        $basePath = $_SERVER["BASE_PATH"] ?? ''; // Předpokládá, že BASE_PATH je definováno, jinak prázdný řetězec
        $articlePath = optional($article->url)->url;
        $fullUrl = $protocol . $host . $basePath . $articlePath;

        // Získání obrázků a souborů připojených k článku
        $filesAndImages = self::getArticleFilesAndImages($id);

        // Převod řetězců na objekty DateTime a formátování
        $publishAt = !empty($article->publish_at) ? (new DateTime($article->publish_at))->format('Y-m-d\TH:i') : '';
        $publishEndAt = !empty($article->publish_end_at) ? (new DateTime($article->publish_end_at))->format('Y-m-d\TH:i') : '';
        $lastModifiedAt = !empty($article->last_modified_at) ? (new DateTime($article->last_modified_at))->format('Y-m-d\TH:i') : '';

    
        $data = [
            'id' => $article->id,
            'title' => $article->title,
            'short_text' => $article->short_text,
            'author_name' => optional($article->author)->name,
            'categories' => $categories,
            'url' => $fullUrl,
            // Přidání 'meta' informací
            'meta' => [
                'title' => $metaData['title'] ?? '',
                'description' => $metaData['description'] ?? '',
                'keywords' => $metaData['keywords'] ?? '',
                'content_typ' => $metaData['content_typ'] ?? '',
                'mainCatid' => $metaData['mainCatid'] ?? '',
            ],
            // Přidání dalších polí, pokud je potřebujete
            'subtitle' => $article->subtitle,
            'snippet' => $article->snippet,
            'status' => $article->status,
            'body' => htmlspecialchars_decode($article->body),
            'files' => $filesAndImages['files'],
            'images' => $filesAndImages['images'],
            'publish_at' => $publishAt,
            'publish_end_at' => $publishEndAt,
            'last_modified_at' => $lastModifiedAt,
            
        ];
    
        return $data;
    }
    
    // Předpokládá, že máte definovaný vztah 'url', který vrátí URL článku
    public function url() {
        return $this->hasOne(Url::class, 'model_id')->where('model', '=', 'articles');
    }
    
    /**
     * getActiveArticlesByCategoryWithUrlAndAuthor
     *
     * @param  mixed $categoryId
     * @return void
     */
    function getActiveArticlesByCategoryWithUrlAndAuthor($categoryId) {
        $articles = Article::with(['url', 'user', 'images.file'])
                    ->whereHas('categories', function ($query) use ($categoryId) {
                        $query->where('id', $categoryId);
                    })
                    ->where('status', 'active')
                    ->get();
    
        return $articles->map(function ($article) {
            $filesAndImages = self::getArticleFilesAndImages($article->id);
            $articleData = [
                'id' => $article->id,
                'title' => $article->title,
                'url' => $article->url ? $article->url->url : null,
                'author_name' => $article->user ? $article->user->name : 'Unknown',
                'files' => $filesAndImages['files'], // Přidání dat o souborech
                'images' => $filesAndImages['images'], // Přidání dat o obrázcích
            ];
    
            // Zde doplňte logiku pro naplnění 'files', pokud je potřeba
            return $articleData;
        });
    }

    public static function getArticleFilesAndImages($articleId) {
        // Získání souborů a obrázků připojených k článku
        $articleImages = DB::table('article_images')
                        ->join('uploaded_files', 'article_images.file_id', '=', 'uploaded_files.id')
                        ->leftJoin('image_variants', function($join) {
                            $join->on('article_images.file_id', '=', 'image_variants.original_image_id')
                                 ->where('article_images.variant', '<>', 'file');
                        })
                        ->select('article_images.variant', 'article_images.order', 'uploaded_files.*', 'image_variants.*','uploaded_files.id as uploaded_file_id','image_variants.public_url as variant_public_url')
                        ->where('article_id', $articleId)
                        ->get();
    
        // Pole pro soubory
        $files = [];
    
        // Asociativní pole pro obrázky
        $images = ['images' => [], 'gallery' => []];
    
        foreach ($articleImages as $image) {
            // Pokud je variant 'files', přidej do pole souborů
            if ($image->variant === 'files') {
                $files[] = (array) $image;
            } else {
                // Jinak zpracuj obrázek nebo galerii
                if (!empty($image->variant_name)) {
                    $varianta[$image->variant_name] = [
                        'image_name' => $image->image_name,
                        'width' => $image->width,
                        'height' => $image->height,
                        'public_url' => $image->variant_public_url
                    ];
                }
                else
                    $varianta = [];

                $imageData = [
                    'id' => $image->uploaded_file_id,
                    'user_id' => $image->user_id,
                    'site_id' => $image->site_id,
                    'name' => $image->name,
                    'file_path' => $image->file_path,
                    'mime_type' => $image->mime_type,
                    'size' => $image->size,
                    'role' => $image->role,
                    'status' => $image->status,
                    'created_at' => $image->created_at,
                    'updated_at' => $image->updated_at,
                    'alt' => $image->alt,
                    'title' => $image->title,
                    'public_url' => $image->public_url,
                    'varianta' => $varianta,
                ];
    
                // Pokud je variant 'gallery', přidej do galerie podle pořadí
                if ($image->variant === 'gallery') {
                    $images['gallery'][$image->order] = $imageData;
                } else {
                    // Jinak přidej do pole obrázků podle varianty
                    $images['images'][$image->variant] = $imageData;
                }
            }
        }
    
        // Výstupní pole
        $output = ['files' => $files, 'images' => $images];
    
        return $output;
    }
    
    
    
    /**
     * Přiřadí obrázek k článku podle zadané varianty. Pro 'articleDetail' a 'articles'
     * smaže existující záznamy téže varianty a pro 'gallery' umožní více obrázků s automatickým
     * nastavením pořadí.
     *
     * @param int $fileId ID souboru (obrázku)
     * @param string $variant Typ varianty ('articleDetail', 'articles', 'gallery')
     */
    public function assignImage($fileId, $variant)
    {
        $logger = self::getLogger();
        $logger->info("Bude se přidávat obrázek '{$fileId}' ve variantě '{$variant}' pro článek {$this->id}.");
        $order = 1;
        // Pro varianty 'articleDetail' a 'articles', smažte stávající obrázky stejné varianty
        if (in_array($variant, ['articleDetail', 'articles'])) {
            ArticleImage::where('article_id', $this->id)
                        ->where('variant', $variant)
                        ->delete();
            $logger->info("Existující obrázky varianty '{$variant}' pro článek {$this->id} byly smazány.");
        }

        // Kontrola pro variantu "gallery" - zda už soubor s daným file_id není vložen
        if ($variant === 'gallery') {
            $existingImage = ArticleImage::where('article_id', $this->id)
                                        ->where('variant', 'gallery')
                                        ->where('file_id', $fileId)
                                        ->first();
            if ($existingImage) {
                $logger->info("Obrázek s file ID: '{$fileId}' už je vložen v galerii článku {$this->id}, přidávání se přeskakuje.");
                return; // Obrázek už existuje, ukončení funkce
            }

            // Pro 'gallery', zjistěte poslední pořadí a přidejte k němu jedničku
            
            $lastImage = ArticleImage::where('article_id', $this->id)
                                    ->where('variant', 'gallery')
                                    ->orderBy('order', 'desc')
                                    ->first();
            if ($lastImage) {
                $order = $lastImage->order + 1;
            }
            $logger->info("Přidán nový obrázek do galerie pro článek {$this->id}, file ID: {$fileId}.");
        }

        // Vytvoření nového záznamu pro obrázek
        ArticleImage::create([
            'article_id' => $this->id,
            'file_id' => $fileId,
            'variant' => $variant,
            'order' => $order,
        ]);
    }

    public function images()
    {
        return $this->morphToMany(UploadedFile::class, 'imageable', 'imageables', 'imageable_id', 'image_id')
                    ->withPivot('imageable_type');
    }

    public static function getHome(){
        // home_cat - v rámci šablony id kategorie, která slouží jako Home stránka
        if(isset($_GET["articlesPage"]))
            $page = $_GET["articlesPage"];
        else
            $page = 1;
        $return["articles"] =  self::getArticlesByCategoryId($_SERVER["domain"]["home_cat"], $page);
        $return["totalPages"] = self::getTotalPages($_SERVER["domain"]["home_cat"]);

        return $return;
    }

    public static function getArticlesByCategoryId($categoryId, $page = 1) {
        $siteId = $_SERVER["SITE_ID"];
        $pageSize = $_SERVER["domain"]["articles_count"];

        $offset = ($page - 1) * $pageSize; // Vypočet offsetu pro dotaz

        $category = Category::with(['articles' => function ($query) use ($siteId, $pageSize, $offset) {
            $query->where('site_id', $siteId)
                  ->where('status', 'active')
                  ->with('categories')
                  ->orderBy('created_at', 'desc')
                  ->skip($offset)
                  ->take($pageSize); // Omezení počtu článků na stránku
        }])->find($categoryId);
    
        if (!$category) {
            return null; // Kategorie nebyla nalezena
        }
    
        $articles = $category->articles->transform(function ($article) {
            if (is_string($article->meta)) {
                $article->meta = json_decode($article->meta, true);
            } elseif (is_null($article->meta)) {
                $article->meta = [];
            }
    
            // Získání dat o souborech a obrázcích pro aktuální článek
            $filesAndImages = self::getArticleFilesAndImages($article->id);
    
            // Přidání informací o souborech a obrázcích k článku
            $article->files = $filesAndImages['files'];
            $article->images = $filesAndImages['images'];
    
            // Volání metody z třídy Url pro získání URL
            $articleUrl = Url::getUrlByModelAndId('articles', $article->id);

            // Přidání URL do výstupu článku
            $article->url = $articleUrl;
    
            return $article;
        });
    
        return $articles;
    }

    public static function getTotalPages($categoryId) {
        $siteId = $_SERVER["SITE_ID"];
        // Přístup k článkům přes model Category, aby byl dotaz konzistentní s dotazem pro získání článků
        $category = Category::with(['articles' => function ($query) use ($siteId) {
            $query->where('site_id', $siteId)
                  ->where('status', 'active');
        }])->find($categoryId);
    
        if (!$category) {
            return 0; // V případě, že kategorie neexistuje, vrátí 0 stránek
        }
    
        $count = $category->articles->count(); // Získání počtu aktivních článků v kategorii pro dané site_id
        $articlesPerPage = $_SERVER["domain"]["articles_count"]; // Počet článků na stránku z konfigurace serveru
    
        return ceil($count / $articlesPerPage); // Výpočet celkového počtu stránek
    }

    public static function getLatestActiveArticles() {
        $siteId = $_SERVER["SITE_ID"]; // Zajištění, že filtrujeme články pro správný site_id
    
        // Načtení deseti nejnovějších článků se stavem 'active' pro daný site_id
        $articles = Article::where('site_id', $siteId)
                           ->where('status', 'active')
                           ->with(['categories', 'url', 'user', 'images.file']) // Načtení kategorií pro každý článek
                           ->orderBy('created_at', 'desc') // Řazení článků od nejnovějšího
                           ->limit(10) // Omezení výsledku na 10 článků
                           ->get();
    
        // Transformace výsledků pro načtení meta dat a souborů
        $articles->transform(function ($article) {
            if (is_string($article->meta)) {
                $article->meta = json_decode($article->meta, true);
            } elseif (is_null($article->meta)) {
                $article->meta = [];
            }
    
            // Získání dat o souborech a obrázcích pro aktuální článek
            $filesAndImages = self::getArticleFilesAndImages($article->id);
    
            // Přidání informací o souborech a obrázcích k článku
            $article->files = $filesAndImages['files'];
            $article->images = $filesAndImages['images'];
    
            // Získání URL pro aktuální článek
            $articleUrl = Url::getUrlByModelAndId('articles', $article->id);
    
            // Přidání URL do výstupu článku
            $article->url = $articleUrl;
    
            // Transformace kategorií
            $article->categories = $article->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'display_name' => $category->display_name,
                    'top_text' => $category->top_text,
                    'bottom_text' => $category->bottom_text,
                    'url' => $category->url,
                    'meta' => is_string($category->meta) ? json_decode($category->meta, true) : $category->meta,
                ];
            });
    
            return $article;
        });
    
        return $articles;
    }

    // Definice vztahu k modelu User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function getAdjacentArticles($currentArticleId) {
        $siteId = $_SERVER["SITE_ID"];
        
        // Načtení aktuálního článku pro získání jeho hlavní kategorie a datumu publikace
        $currentArticle = Article::with(['url', 'user', 'images.file', 'categories'])
                                 ->where('site_id', $siteId)
                                 ->where('id', $currentArticleId)
                                 ->first();
        
        if (!$currentArticle || is_null($currentArticle->meta)) {
            return null; // Pokud článek neexistuje nebo nemá meta data
        }
    
        // Dekódování meta dat a získání hlavní kategorie
        $currentArticleMeta = is_string($currentArticle->meta) ? json_decode($currentArticle->meta, true) : $currentArticle->meta;
        $mainCategoryId = $currentArticleMeta['mainCatid'] ?? null;
        
        if (is_null($mainCategoryId)) {
            return null; // Pokud článek nemá definovanou hlavní kategorii
        }
    
        $publishedDate = $currentArticle->created_at;
    
        // Načtení předchozího článku
        $previousArticle = Article::where('site_id', $siteId)
                                  ->where('status', 'active')
                                  ->where('created_at', '<', $publishedDate)
                                  ->whereHas('categories', function ($query) use ($mainCategoryId) {
                                      $query->where('id', $mainCategoryId);
                                  })
                                  ->with(['categories', 'url', 'user', 'images.file'])
                                  ->orderBy('created_at', 'desc')
                                  ->first();
        
        // Načtení následujícího článku
        $nextArticle = Article::where('site_id', $siteId)
                              ->where('status', 'active')
                              ->where('created_at', '>', $publishedDate)
                              ->whereHas('categories', function ($query) use ($mainCategoryId) {
                                  $query->where('id', $mainCategoryId);
                              })
                              ->with(['categories', 'url', 'user', 'images.file'])
                              ->orderBy('created_at', 'asc')
                              ->first();
    
        $adjacentArticles = collect([$previousArticle, $nextArticle]);
    
        // Transformace výsledků pro načtení meta dat a souborů
        $adjacentArticles->transform(function ($article) {
            if (is_null($article)) {
                return null;
            }
    
            if (is_string($article->meta)) {
                $article->meta = json_decode($article->meta, true);
            } elseif (is_null($article->meta)) {
                $article->meta = [];
            }
    
            // Získání dat o souborech a obrázcích pro aktuální článek
            $filesAndImages = self::getArticleFilesAndImages($article->id);
    
            // Přidání informací o souborech a obrázcích k článku
            $article->files = $filesAndImages['files'];
            $article->images = $filesAndImages['images'];
    
            // Získání URL pro aktuální článek
            $articleUrl = Url::getUrlByModelAndId('articles', $article->id);
    
            // Přidání URL do výstupu článku
            $article->url = $articleUrl;
    
            // Transformace kategorií
            $article->categories = $article->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'display_name' => $category->display_name,
                    'top_text' => $category->top_text,
                    'bottom_text' => $category->bottom_text,
                    'url' => $category->url,
                    'meta' => is_string($category->meta) ? json_decode($category->meta, true) : $category->meta,
                ];
            });
    
            return $article;
        });
    
        return $adjacentArticles;
    }
    
}

?>