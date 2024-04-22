<?php
namespace linkcms1\Models;

use PDO;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use linkcms1\Models\ArticleCategory;
use linkcms1\Models\Url;
use linkcms1\domainControl;
use PHPAuth\Config as PHPAuthConfig;
use PHPAuth\Auth as PHPAuth;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Tracy\Debugger;

class Site extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'created_at',
        'updated_at',
        'user_id',
        'active',
        'tarif_id',
        'template_dir',
        'language',
        'configurations',
        'analytics',
        'notes',
        'head_code',
        'post_body_code',
        'pre_end_body_code',
    ];

    use HasFactory;

    protected static $logger;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tariff()
    {
        return $this->belongsTo(Tariff::class);
    }

    /**
     * Vrátí pole všech unikátních handlerů z tabulky urls, seřazených abecedně.
     * @return array Pole unikátních handlerů.
     */
    public function getAllUniqueHandlers() {
        $handlers = Url::distinct()->orderBy('handler', 'asc')->pluck('handler')->toArray();
        return $handlers;
    }

    /**
     * Vrátí pole všech unikátních modelů z tabulky urls, seřazených abecedně.
     * @return array Pole unikátních modelů.
     */
    public function getAllUniqueModels() {
        $models = Url::distinct()->orderBy('model', 'asc')->pluck('model')->toArray();
        return $models;
    }
    
    
    /*public static function saveOrUpdate($data) {
        // Kontrola, zda je nastaveno ID a není prázdné
        if (isset($data['id']) && !empty($data['id'])) {
            // Aktualizace existujícího záznamu
            $site = self::find($data['id']);
            if (!$site) {
                return ['status' => false, 'message' => 'Stránka nebyla nalezena.'];
            }
        } else {
            // Vytvoření nového záznamu
      
            $site = new self();
        }

        // Příprava dat configurations a analytics pro uložení
        // Příprava dat pro configurations
        $configurations = [];
        if (isset($data['config_name']) && is_array($data['config_name'])) {
            foreach ($data['config_name'] as $index => $name) {
                if (isset($data['config_value'][$index])) {
                    $configurations[] = [
                        'name' => $data['config_name'][$index],
                        'value' => $data['config_value'][$index],
                        'description' => $data['config_description'][$index] ?? '',
                    ];
                }
            }
        }
        $data['configurations'] = json_encode($configurations);

        // Příprava dat pro analytics
        $analytics = [];
        if (isset($data['analytics_name']) && is_array($data['analytics_name'])) {
            foreach ($data['analytics_name'] as $index => $name) {
                if (isset($data['analytics_value'][$index])) {
                    $analytics[] = [
                        'name' => $data['analytics_name'][$index],
                        'value' => $data['analytics_value'][$index],
                    ];
                }
            }
        }
        $data['analytics'] = json_encode($analytics);

        // Nastavení hodnot (včetně configurations a analytics)
        $site->fill($data);

        // Uložení záznamu
        if ($site->save()) {
            return ['status' => true, 'message' => 'Stránka byla úspěšně uložena.'];
        } else {
            return ['status' => false, 'message' => 'Nepodařilo se uložit stránku.'];
        }
    }*/

    public static function saveOrUpdate($data) {
        // Příprava konfiguračních a analytických dat
        $configurations = [];
        if (isset($data['config_name']) && is_array($data['config_name'])) {
            foreach ($data['config_name'] as $index => $name) {
                if (isset($data['config_value'][$index])) {
                    $configurations[] = [
                        'name' => $name,
                        'value' => $data['config_value'][$index],
                        'description' => $data['config_description'][$index] ?? '',
                    ];
                }
            }
        }
        $data['configurations'] = json_encode($configurations);
    
        $analytics = [];
        if (isset($data['analytics_name']) && is_array($data['analytics_name'])) {
            foreach ($data['analytics_name'] as $index => $name) {
                if (isset($data['analytics_value'][$index])) {
                    $analytics[] = [
                        'name' => $name,
                        'value' => $data['analytics_value'][$index],
                    ];
                }
            }
        }
        $data['analytics'] = json_encode($analytics);
    
        // Kontrola, zda ID existuje a není prázdné
        if (isset($data['id']) && !empty($data['id'])) {
            // Hledání a aktualizace existujícího záznamu
            $site = self::find($data['id']);
            if (!$site) {
                return ['status' => false, 'message' => 'Stránka nebyla nalezena.'];
            }
        } else {
            // Vytvoření nového záznamu
            if (!isset($data['template_id']) || empty($data['template_id'])) {
                return ['status' => false, 'message' => 'Template ID je povinné pro vytvoření nové stránky.'];
            }
            $site = new self();
            $site->fill($data); // Předpřipravíme data
            if (!$site->save()) {
                return ['status' => false, 'message' => 'Nepodařilo se vytvořit novou stránku.'];
            }

            $data['id'] = $site->id; // Přiřadíme ID nově vytvořené stránky do dat
            return self::processNewSiteWithTemplate($data); // Zpracujeme šablonu s novým ID
        }
    
        // Nastavení a uložení dat
        $site->fill($data);
        if ($site->save()) {
            return ['status' => true, 'message' => 'Stránka byla úspěšně uložena.'];
        } else {
            return ['status' => false, 'message' => 'Nepodařilo se uložit stránku.'];
        }
    }

    public function handleSaveOrUpdateSite() {
        // Kontrola, zda byla data odeslána metodou POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Zpracování dat formuláře
            $postData = $_POST;
            // Případná další validace dat zde

            // Volání metody pro uložení nebo aktualizaci stránky
            $result = Site::saveOrUpdate($postData);
    
            if ($result['status']) {
                // Úspěch: Přesměrování s úspěšnou zprávou
                header('Location: ' . $_SERVER['HTTP_REFERER'] . '?status=success&message=' . urlencode($result['message']));
                exit;
            } else {
                // Neúspěch: Přesměrování s chybovou zprávou
                // Předpokládá se, že máte logger nastavený
                // $this->logger->warning($result['message']);
                header('Location: ' . $_SERVER['HTTP_REFERER'] . '?status=error&message=' . urlencode($result['message']));
                exit;
            }
        } else {
            // Pokud data nebyla odeslána metodou POST, přesměrování zpět s chybovou zprávou
            // Předpokládá se, že máte logger nastavený
            // $this->logger->warning('Neplatný požadavek při volání metody handleSaveOrUpdateSite');
            header('Location: ' . $_SERVER['HTTP_REFERER'] . '?status=error&message=' . urlencode('Neplatný požadavek'));
            exit;
        }
    }

    public static function setLogger(Logger $logger) {
        self::$logger = $logger;
    }
    
    /**
     * processNewSiteWithTemplate - základní funkce pro instalaci nové stránky
     *
     * @param  mixed $data
     * @return void
     */
    public static function processNewSiteWithTemplate($data) {
        self::$logger->info("[".$data["domain"]."] Zahajujeme vytváření nové stránky s šablonou.");

        // Načtení informací o šabloně
        $template = Template::getTemplateById($data['template_id']);
        if (!$template) {
            self::$logger->error("[".$data["domain"]."] Šablona s ID {$data['template_id']} nebyla nalezena.");
            return ['status' => false, 'message' => 'Šablona nebyla nalezena.'];
        }
        self::$logger->info("[".$data["domain"]."] Šablona ".$data['template_id']." načtena úspěšně.");

        // Kopírování souborů
        $sourceDir = "sablony/".$template['template_dir'];
        $targetDir = 'templates/'.$data["template_dir"].'/';

        if (!Template::copyFiles($sourceDir, $targetDir)) {
            self::$logger->error("[".$data["domain"]."] Kopírování souborů se nezdařilo.");
            return ['status' => false, 'message' => 'Kopírování souborů se nezdařilo.'];
        }
        self::$logger->info("[".$data["domain"]."] Soubory byly úspěšně zkopírovány [".$sourceDir."] >>> [".$targetDir."]");

        // Vytvoření adresářů
        $domain = parse_url($template['demo_url'], PHP_URL_HOST);
        Template::createDomainDirectories($data["domain"]);
        UploadedFile::createGoogleCloudDirectory($data["domain"]);
        self::$logger->info("[".$data["domain"]."] Adresáře byly vytvořeny.");

        // Vytvoření URL
        $urlData = ['url' => "/",
                    'handler' => "getArticleDetails",
                    'model' => "articles",
                    'model_id' => 1,
                    'domain' => $data["domain"]
                    ];
        $domainControl = new domainControl($capsule,self::$logger );
        $result = $domainControl->createUrlIfNotDuplicate($urlData);
                    
        if (!$result) {
            self::$logger->error("[".$data["domain"]."] Vytvoření unikátní URL se nezdařilo.");
            return ['status' => false, 'message' => 'Vytvoření unikátní URL se nezdařilo.'];
        }
        self::$logger->info("[".$data["domain"]."] Unikátní URL byla vytvořena.");

        // Převod proměnných šablony do konfigurace stránky
        $configurations = [];
        if (isset($data['config_name']) && is_array($data['config_name'])) {
            foreach ($data['config_name'] as $index => $name) {
                if (isset($data['config_value'][$index])) {
                    $configurations[] = [
                        'name' => $name,
                        'value' => $data['config_value'][$index],
                        'description' => $data['config_description'][$index] ?? '',
                    ];
                }
            }
        }
        $data['configurations'] = json_encode($configurations);
    
        $analytics = [];
        if (isset($data['analytics_name']) && is_array($data['analytics_name'])) {
            foreach ($data['analytics_name'] as $index => $name) {
                if (isset($data['analytics_value'][$index])) {
                    $analytics[] = [
                        'name' => $name,
                        'value' => $data['analytics_value'][$index],
                    ];
                }
            }
        }
        $data['analytics'] = json_encode($analytics);
        self::$logger->info("[".$data["domain"]."] Konfigurace byly nastaveny.");

        // Vytvoření nového záznamu stránky
        $site = new self();
        $site->fill($data);
        if ($site->save()) {
            self::$logger->info("[".$data["domain"]."] Stránka byla úspěšně uložena.");
            return ['status' => true, 'message' => 'Stránka byla úspěšně uložena.'];
        } else {
            self::$logger->error("[".$data["domain"]."] Uložení stránky se nezdařilo.");
            return ['status' => false, 'message' => 'Nepodařilo se uložit stránku.'];
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
Site::setLogger($logger);
?>