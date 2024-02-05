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
    public static function saveOrUpdateArticle($data,$uid) {
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
                    'author_id' => $uid,
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
            self::processUrlForArticle($article, $urlPath);

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
    
        // Kontrola existence URL s danou cestou
        $existingUrl = Url::where('url', $path)
                          ->where('handler', 'articleDetail')
                          ->where('model', 'articles')
                          ->where('model_id', $article->id)
                          ->first();
    
        // Pokud existuje URL se stejnou cestou
        if ($existingUrl) {
            // Aktualizace URL
            $existingUrl->url = $path;
            $existingUrl->save();
        } else {
            // Vytvoření nového URL záznamu
            $newUrl = new Url;
            $newUrl->domain = ''; // Prázdná doména, protože nevyžadujeme specifickou doménu
            $newUrl->url = $path;
            $newUrl->handler = 'articleDetail';
            $newUrl->model = 'articles';
            $newUrl->model_id = $article->id;
            $newUrl->save();
        }
    }

    public function articleDetail(){

    }

    public function handleSaveOrUpdateArticle($uid) {
        // Kontrola, zda byla data odeslána metodou POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Zpracování dat formuláře
            $postData = $_POST;

            // Případná další validace dat zde

            // Volání metody pro uložení nebo aktualizaci kategorie
            $result = Article::saveOrUpdateArticle($postData,$uid);

            if ($result['status']) {
                // Úspěch: Přesměrování s úspěšnou zprávou
                header('Location: '.$_SERVER['HTTP_REFERER'].'?status=success&message=' . urlencode($result['message']));
                exit;
            } else {
                // Neúspěch: Přesměrování s chybovou zprávou
                header('Location: '.$_SERVER['HTTP_REFERER'].'?status=error&message=' . urlencode($result['message']));
                exit;
            }
        } else {
            // Pokud data nebyla odeslána metodou POST, přesměrování zpět s chybovou zprávou
            header('Location: '.$_SERVER['HTTP_REFERER'].'?status=error&message=' . urlencode('Neplatný požadavek'));
            exit;
        }
    }
}

?>