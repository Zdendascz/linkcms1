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

//Debugger::enable(Debugger::DEVELOPMENT);
$dbh = new PDO(
    'mysql:host=' . $_SERVER['DB_HOST'] . ';dbname=' . $_SERVER['DB_NAME'],
    $_SERVER['DB_USER'],
    $_SERVER['DB_PASSWORD'],
    array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'") // Nastavení kódování, pokud je potřeba
);

$config = new PHPAuthConfig($dbh);
$auth = new PHPAuth($dbh, $config);

class ConfigurationDefinition extends Model {
    protected $table = 'configurations_definitions';

    protected $fillable = ['key', 'type', 'default_value', 'editable_by_role', 'description'];

    /**
     * Vrátí všechny definice konfigurací.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getAllDefinitions()
    {
        return self::all();
    }

    /**
     * Uloží nebo aktualizuje definici konfigurace na základě předaných dat.
     *
     * @param array $data Data definice konfigurace
     * @return array Výsledek operace
     */
    public static function saveOrUpdate($data) {
        // Kontrola, zda je nastaveno ID a není prázdné
        if (isset($data['id']) && !empty($data['id'])) {
            // Aktualizace existující definice
            $definition = self::find($data['id']);
            if (!$definition) {
                // Zde logujte chybu nebo vraťte chybovou zprávu
                return ['status' => false, 'message' => 'Definice nebyla nalezena.'];
            }
        } else {
            // Vytvoření nové definice
            $definition = new self();
        }

        // Nastavení hodnot definice
        $definition->key = $data['key'];
        $definition->name = $data['name'];
        $definition->description = $data['description'];
        $definition->type = $data['type'];
        $definition->default_value = $data['default_value'];
        $definition->editable_by_role = $data['editable_by_role'];
        // případně další atributy podle vašeho schématu

        // Uložení definice
        if ($definition->save()) {
            return ['status' => true, 'message' => 'Definice byla úspěšně uložena.'];
        } else {
            // Zde logujte chybu nebo vraťte chybovou zprávu
            return ['status' => false, 'message' => 'Nepodařilo se uložit definici.'];
        }
    }

    public function handleSaveOrUpdateConfigurationDefinition() {
        // Kontrola, zda byla data odeslána metodou POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Zpracování dat formuláře
            $postData = $_POST;
    
            // Případná další validace dat zde
    
            // Volání metody pro uložení nebo aktualizaci definice konfigurace
            $result = ConfigurationDefinition::saveOrUpdate($postData);
    
            if ($result['status']) {
                // Úspěch: Přesměrování s úspěšnou zprávou
                header('Location: '.$_SERVER['HTTP_REFERER'].'?status=success&message=' . urlencode($result['message']));
                exit;
            } else {
                // Neúspěch: Přesměrování s chybovou zprávou
                // Předpokládá se, že máte logger nastavený
                // $this->logger->warning($result['message']);
                header('Location: '.$_SERVER['HTTP_REFERER'].'?status=error&message=' . urlencode($result['message']));
                exit;
            }
        } else {
            // Pokud data nebyla odeslána metodou POST, přesměrování zpět s chybovou zprávou
            // Předpokládá se, že máte logger nastavený
            // $this->logger->warning('Neplatný požadavek při volání metody handleSaveOrUpdateConfigurationDefinition');
            header('Location: '.$_SERVER['HTTP_REFERER'].'?status=error&message=' . urlencode('Neplatný požadavek'));
            exit;
        }
    }


    public static function saveSiteConfiguration($siteId, $configId, $value) {
        $config = DB::table('site_configurations')
                    ->where('site_id', $siteId)
                    ->where('config_id', $configId)
                    ->first();
    
        if ($config) {
            // Aktualizace existující konfigurace
            return DB::table('site_configurations')
                ->where('id', $config->id)
                ->update(['value' => $value]);
        } else {
            // Vytvoření nové konfigurace
            return DB::table('site_configurations')->insert([
                'site_id' => $siteId,
                'config_id' => $configId,
                'value' => $value
            ]);
        }
    }

    public function handleSaveSiteConfiguration() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $siteId = $_POST['site_id']; // Nebo získat z kontextu aplikace/session
            $configId = $_POST['id'];
            $value = $_POST['value'];
    
            // Uložení nebo aktualizace konfigurace
            $result = ConfigurationDefinition::saveSiteConfiguration($siteId, $configId, $value);
    
            if ($result) {
                // Úspěch
                echo json_encode(['success' => true, 'message' => 'Konfigurace byla úspěšně uložena.']);
            } else {
                // Chyba
                echo json_encode(['success' => false, 'message' => 'Nepodařilo se uložit konfiguraci.']);
            }
        } else {
            // Neplatný požadavek
            echo json_encode(['success' => false, 'message' => 'Neplatný požadavek.']);
        }
        exit;
    }
}

?>