<?php
namespace linkcms1\Models;

use PDO;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use linkcms1\Models\ArticleCategory;
use linkcms1\Models\Url;
use PHPAuth\Config as PHPAuthConfig;
use PHPAuth\Auth as PHPAuth;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Tracy\Debugger;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

//Debugger::enable(Debugger::DEVELOPMENT);
$dbh = new PDO(
    'mysql:host=' . $_SERVER['DB_HOST'] . ';dbname=' . $_SERVER['DB_NAME'],
    $_SERVER['DB_USER'],
    $_SERVER['DB_PASSWORD'],
    array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'") // Nastavení kódování, pokud je potřeba
);

$config = new PHPAuthConfig($dbh);
$auth = new PHPAuth($dbh, $config);

class Template extends Model
{
    use HasFactory;

    protected static $logger;

    // Definuje, že model nemá timestamps - pokud máte v tabulce 'created_at' a 'updated_at', toto smažte
    public $timestamps = true;

    // Název tabulky, pokud není standardní 'templates'
    protected $table = 'templates';

    // Hromadné přiřazení všech atributů
    protected $guarded = [];

    // Přetypování pro správnou manipulaci s daty v PHP
    protected $casts = [
        'variables' => 'array', // JSON jako pole
        'preview_images' => 'array', // JSON jako pole
        'price' => 'float',  // Cena jako float
        'rating' => 'float',  // Hodnocení jako float
        'download_count' => 'integer', // Počet stažení jako integer
        'last_update' => 'date', // Datum poslední aktualizace jako Carbon date
    ];

    public static function getAllTemplates()
    {
        // Načtení všech šablon s jejich atributy
        $templates = self::all();

        // Příklad další transformace (pokud je potřeba)
        /*foreach ($templates as $template) {
            $template->variables = json_decode($template->variables, true);
            $template->preview_images = json_decode($template->preview_images, true);
        }*/

        return $templates->toArray();
    }

    public static function setLogger(Logger $logger) {
        self::$logger = $logger;
    }

    public static function saveOrUpdateTemplate(array $formData)
    {
        // Předpokládáme, že ID je součástí formData, pokud se jedná o aktualizaci
        if (isset($formData['id']) && !empty($formData['id'])) {
            // Pokus o nalezení existujícího záznamu
            $template = Template::find($formData['id']);
            if (!$template) {
                // Žádný existující záznam nenalezen, vytvoříme nový
                $template = new Template;
            }
        } else {
            // Vytvoření nové instance šablony
            $template = new Template;
        }

        // Přiřazení hodnot z formuláře k atributům modelu
        $template->name = $formData['name'];
        $template->description = $formData['description'];
        $template->category = $formData['category'];
        $template->thumbnail_url = $formData['thumbnail_url'];
        $template->template_dir = $formData['template_dir'];
        $template->variables = json_encode($formData['variables']);
        $template->status = $formData['status'];
        $template->version = $formData['version'];
        $template->author_id = $formData['author_id'];
        $template->license_type = $formData['license_type'];
        $template->price = $formData['price'];
        $template->currency = $formData['currency'];
        $template->tags = $formData['tags'];
        $template->layout_type = $formData['layout_type'];
        $template->color_scheme = $formData['color_scheme'];
        $template->framework = $formData['framework'];
        $template->language = $formData['language'];

        // Zpracování proměnné last_update
        if (empty($formData['last_update'])) {
            $template->last_update = date('Y-m-d');
        } else {
            $template->last_update = date('Y-m-d', strtotime($formData['last_update']));
        }

        $template->last_update = $formData['last_update'];
        $template->compatibility = $formData['compatibility'];
        $template->demo_url = $formData['demo_url'];
        $template->documentation_url = $formData['documentation_url'];
        $template->download_count = $formData['download_count'];
        $template->rating = $formData['rating'];
        $template->dependencies = $formData['dependencies'];
        $template->preview_images = json_encode($formData['preview_images']);


        // Spracování proměnných a obrázků pro náhled
        $variables = [];
        if (isset($formData['variable_name'])) {
            foreach ($formData['variable_name'] as $index => $name) {
                $variables[] = [
                    'name' => $name,
                    'value' => $formData['variable_value'][$index],
                    'description' => $formData['variable_description'][$index]
                ];
            }
        }
        $template->variables = json_encode($variables);

        $previewImages = [];
        if (isset($formData['preview_image_url'])) {
            foreach ($formData['preview_image_url'] as $index => $url) {
                $previewImages[] = [
                    'url' => $url,
                    'label' => $formData['preview_image_label'][$index]
                ];
            }
        }
        $template->preview_images = json_encode($previewImages);

        // Uložení nebo aktualizace záznamu
        return $template->save();
    }

    public function handleSaveOrUpdateTemplate() {
        // Kontrola, zda byla data odeslána metodou POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Zpracování dat formuláře
            $postData = $_POST;
    
            // Případná další validace dat zde
            // Příklad: ověření, zda jsou vyplněna všechna povinná pole
    
            // Převod JSON stringů proměnných a preview_images zpět na pole
            $postData['variables'] = isset($postData['variables']) ? json_decode($postData['variables'], true) : [];
            $postData['preview_images'] = isset($postData['preview_images']) ? json_decode($postData['preview_images'], true) : [];
    
            // Volání metody pro uložení nebo aktualizaci šablony
            $result = Template::saveOrUpdateTemplate($postData);
    
            if ($result) {
                // Úspěch: Přesměrování s úspěšnou zprávou
                header('Location: ' . $_SERVER['HTTP_REFERER'] . '?status=success&message=' . urlencode('Šablona byla úspěšně uložena.'));
                exit;
            } else {
                // Neúspěch: Přesměrování s chybovou zprávou
                header('Location: ' . $_SERVER['HTTP_REFERER'] . '?status=error&message=' . urlencode('Nepodařilo se uložit šablonu.'));
                exit;
            }
        } else {
            // Pokud data nebyla odeslána metodou POST, přesměrování zpět s chybovou zprávou
            header('Location: ' . $_SERVER['HTTP_REFERER'] . '?status=error&message=' . urlencode('Neplatný požadavek'));
            exit;
        }
    }

    /**
     * Vrátí šablonu na základě jejího ID.
     *
     * @param int $id ID šablony, kterou chceme získat
     * @return mixed Šablona jako pole, nebo null, pokud šablona není nalezena
     */
    public static function getTemplateById($id)
    {
        // Načtení šablony pomocí Eloquent ORM
        $template = self::find($id);

        // Ověření, zda šablona existuje
        if ($template) {
            // Přetypování proměnných a obrázků pro náhled z JSON stringu na pole, pokud jsou v modelu jako JSON stringy
            $template->variables = json_decode($template->variables, true);
            $template->preview_images = json_decode($template->preview_images, true);

            // Vrácení šablony jako pole
            return $template->toArray();
        } else {
            // Vrácení null, pokud šablona nebyla nalezena
            return null;
        }
    }

    /**
     * Kopíruje soubory z jednoho adresáře do druhého a případně vytvoří cílový adresář.
     *
     * @param string $sourceDir Zdrojový adresář
     * @param string $targetDir Cílový adresář
     * @return bool Vrací true pokud operace proběhla úspěšně, jinak false
     */
    public static function copyFiles($sourceDir, $targetDir)
    {
        // Zajistěte, že cesty končí lomítkem
        $sourceDir = rtrim($sourceDir, '/') . '/';
        $targetDir = rtrim($targetDir, '/') . '/';

        // Kontrola, zda zdrojový adresář existuje
        if (!is_dir($sourceDir)) {
            self::$logger->error("Zdrojový adresář {$sourceDir} neexistuje.");
            return false;
        }

        // Kontrola, zda cílový adresář existuje, pokud ne, vytvoří ho
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        } else {
            // Vyprázdnění cílového adresáře, pokud již existuje
            self::deleteDirectory($targetDir);
            mkdir($targetDir, 0777, true); // Znovu vytvoření čistého adresáře
        }

        $dir = new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            $targetPath = $targetDir . $iterator->getSubPathName();
            if ($item->isDir()) {
                // Vytvoření adresáře, pokud neexistuje
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }
            } else {
                // Kopírování souboru
                if (!copy($item, $targetPath)) {
                    self::$logger->error("Nepodařilo se kopírovat soubor z {$item} do {$targetPath}.");
                    return false;
                }
            }
        }

        self::$logger->info("Soubory z {$sourceDir} byly úspěšně zkopírovány do {$targetDir}.");
        return true;
    }

    /**
     * Rekurzivní smazání adresáře
     *
     * @param string $dirPath Cesta k adresáři
     */
    private static function deleteDirectory($dirPath) {
        if (!is_dir($dirPath)) {
            throw new InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($dirPath);
    }

    /**
     * Vytvoří adresářové struktury pro danou doménu.
     *
     * @param string $domain Název domény
     * @return void
     */
    public static function createDomainDirectories($domain)
    {
        // Příprava základní cesty
        $basePath = 'data/';

        // Definice cest pro jednotlivé adresáře
        $paths = [
            $basePath . "files/$domain/",
            $basePath . "images/$domain/",
            $basePath . "code/$domain/"
        ];

        // Iterace a vytvoření každého adresáře
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0777, true);  // Rekurzivní vytvoření adresářů, pokud neexistují
            }
        }
    }

    
}

// Nastavení loggeru
$logger = new Logger('linkcms');
// Nastavení rotačního handleru pro logování úrovní NOTICE a INFO
$debugHandler = new RotatingFileHandler(__DIR__.'/../logs/info.log', 0, Logger::INFO);
$logger->pushHandler($debugHandler);

// nastavení rotačního handleru pro debug
$debugHandler = new RotatingFileHandler(__DIR__.'/../logs/debug.log', 0, Logger::DEBUG);
$logger->pushHandler($debugHandler);

// Nastavení handleru pro logování úrovně WARNING do jednoho souboru
$warningHandler = new StreamHandler(__DIR__.'/../logs/warning.log', Logger::WARNING);
$warningHandler->setFormatter(new LineFormatter(null, null, true, true));
$logger->pushHandler($warningHandler);

// Nastavení handleru pro logování úrovně ERROR do nerotujícího souboru
$errorHandler = new StreamHandler(__DIR__.'/../logs/error.log', Logger::ERROR);
$logger->pushHandler($errorHandler);
Template::setLogger($logger);
?>