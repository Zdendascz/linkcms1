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

class SiteConfiguration extends Model
{
    protected $table = 'site_configurations';

    protected $fillable = ['site_id', 'config_id', 'value'];

    public function definition()
    {
        return $this->belongsTo(ConfigurationDefinition::class, 'config_id');
    }

    public static function getAllConfigurationsBySiteId($siteId)
    {
        $configurations = self::where('site_id', $siteId)
            ->with('definition') // Načtení související definice konfigurace
            ->get();

        $result = [];
        foreach ($configurations as $configuration) {
            $key = $configuration->definition->key;
            $value = $configuration->value;
            $result[$key] = $value;
        }

        return $result;
    }

    
}

?>