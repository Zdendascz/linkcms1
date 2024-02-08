<?php
namespace linkcms1\Models;

use PDO;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;
use linkcms1\Models\ArticleCategory;
use linkcms1\Models\Url;
use PHPAuth\Config as PHPAuthConfig;
use PHPAuth\Auth as PHPAuth;
use Monolog\Logger;
use Tracy\Debugger;

Debugger::enable(Debugger::DEVELOPMENT);
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
        'status'
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

    /**
     * Uloží nebo aktualizuje článek a jeho kategorie.
     * @param array $data Data článku.
     * @param int $userId ID uživatele provádějícího akci.
     * @return array Výsledek operace.
     */
    public static function saveOrUpdateArticle($data) {
        DB::beginTransaction();
    
        try {
            $article = self::updateOrCreate(
                ['id' => $data['id'] ?? null], // Klíče pro vyhledání
                [
                    'title' => $data['title'],
                    'subtitle' => $data['subtitle'] ?? null,
                    'short_text' => $data['short_text'] ?? null,
                    'snippet' => $data['snippet'] ?? null,
                    'body' => $data['body'],
                    'user_id' => $data['user_id'],
                    'meta' => json_encode([
                        'title' => $data['meta']['title'] ?? '',
                        'description' => $data['meta']['description'] ?? '',
                        'keywords' => $data['meta']['keywords'] ?? '',
                    ]),
                    'status' => $data['status']
                ]
            );
    
            // Zpracování kategorií
            if (isset($data['categories'])) {
                $article->categories()->sync($data['categories']);
            }
    
            // Zpracování URL
            $safeTitle = self::createSafeTitle($data['title']); // Implementujte podle vašich pravidel
            $urlPath = '/' . $safeTitle; // Příklad, jak by mohla URL vypadat
            $urlProcessResult = self::processUrlForArticle($article, $urlPath);
    
            if (is_array($urlProcessResult) && !$urlProcessResult['success']) {
                DB::rollBack();
                return $urlProcessResult; // Vrátí chybovou zprávu z processUrlForArticle
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
        $parsedUrl = parse_url($urlPath);
        $path = $parsedUrl['path'] ?? '';
    
        // Najde existující URL pro daný článek
        $existingUrl = Url::where('model', 'articles')
                          ->where('model_id', $article->id)
                          ->first();
    
        // Pokud existuje URL a je shodná s novou URL, nic se neděje
        if ($existingUrl && $existingUrl->url === $path) {
            return true;
        }
    
        // Kontrola existence jiného záznamu s novou URL
        $urlConflict = Url::where('url', $path)
                          ->where('model', '!=', 'articles')
                          ->orWhere(function($query) use ($path, $article) {
                              $query->where('url', $path)
                                    ->where('model', '=', 'articles')
                                    ->where('model_id', '!=', $article->id);
                          })
                          ->first();
    
        if ($urlConflict) {
            // Existuje konflikt URL, vrátí chybu s ID konfliktního záznamu
            return ['success' => false, 'message' => 'URL už existuje', 'conflict_id' => $urlConflict->id];
        }
    
        if ($existingUrl) {
            // Aktualizace stávajícího URL záznamu
            $existingUrl->url = $path;
            $existingUrl->save();
        } else {
            // Vytvoření nového URL záznamu, pokud neexistuje
            $domain = $_SERVER['HTTP_HOST'];
            $domain = preg_replace('/^www\./', '', $domain);
            $domain = explode(':', $domain)[0];
    
            $newUrl = new Url;
            $newUrl->domain = $domain;
            $newUrl->url = $path;
            $newUrl->handler = 'articleDetail';
            $newUrl->model = 'articles';
            $newUrl->model_id = $article->id;
            $newUrl->save();
        }
    
        return true;
    }

   /* public function articleDetail($id){
        return getArticleDetails($id)
    }*/

    public function handleSaveOrUpdateArticle() {
        // Kontrola, zda byla data odeslána metodou POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Zpracování dat formuláře
            $postData = $_POST;
    
            // Případná další validace dat zde
    
            // Volání metody pro uložení nebo aktualizaci článku
            $result = Article::saveOrUpdateArticle($postData);
    
            if ($result['status']) {
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
        // Použijeme metodu with() pro načtení relace 'categories' pro každý článek
        return self::with('categories')->get();
    }

    /**
     * Získá detail článku včetně autora, kategorií a URL.
     *
     * @param int $id ID článku
     * @return array Data článku
     */
    public static function getArticleDetails($id) {
        $article = self::with(['author', 'categories', 'url'])
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
            ],
            // Přidání dalších polí, pokud je potřebujete
            'subtitle' => $article->subtitle,
            'snippet' => $article->snippet,
            'status' => $article->status,
            'body' => $article->body,
        ];
    
        return $data;
    }
    
    // Předpokládá, že máte definovaný vztah 'url', který vrátí URL článku
    public function url() {
        return $this->hasOne(Url::class, 'model_id')->where('model', '=', 'articles');
    }

    function getActiveArticlesByCategoryWithUrlAndAuthor($categoryId) {
        $articles = Article::with(['url', 'user'])
                    ->whereHas('categories', function ($query) use ($categoryId) {
                        $query->where('id', $categoryId);
                    })
                    ->where('status', 'active')
                    ->get();
    
        return $articles->map(function ($article) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'url' => $article->url ? $article->url->url : null,
                'author_name' => $article->user ? $article->user->name : 'Unknown', // Předpokládejme, že uživatel má atribut 'name'
            ];
        });
    }
}

?>