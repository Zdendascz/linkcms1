<?php
namespace linkcms1\Models;

use Illuminate\Database\Eloquent\Model;

class Url extends Model
{
    // Specifikace názvu tabulky, pokud není standardní
    protected $table = 'urls';

    // Sloupce, do kterých je možné hromadně vkládat data
    protected $fillable = [
        'domain',
        'url',
        'handler',
        'model',
        'model_id'
    ];

    // Timestamps sloupce (created_at, updated_at, deleted_at) jsou již standardně v modelu

    public static function nactiBezpecneGetParametry() {
        $povoleneKlice = ['navigation_id','articlesPage']; // Příklad povolených klíčů
        $bezpecneParametry = [];
        foreach ($_GET as $klic => $hodnota) {
            // Zkontrolujte, zda je klíč povolený
            if (in_array($klic, $povoleneKlice)) {
                // Přidání základního odstraňování škodlivých skriptů/HTML tagů
                $bezpecneParametry[$klic] = htmlspecialchars($hodnota, ENT_QUOTES, 'UTF-8');
            }
        }
        return $bezpecneParametry;
    }

    /**
     * Metoda pro získání URL na základě modelu a model_id
     *
     * @param string $model Název modelu
     * @param int $modelId ID modelu
     * @return string|null Vrátí URL nebo null, pokud není nalezena
     */
    public static function getUrlByModelAndId($model, $modelId) {
        $urlRecord = self::where('model', $model)->where('model_id', $modelId)->first();
        
        // Kontrola, zda byl záznam nalezen
        if ($urlRecord) {
            // Sestavení úplné URL skládající se z domény a cesty (url)
            $fullUrl = "https://".$urlRecord->domain . $urlRecord->url;
            return $fullUrl;
        }

        return null; // Vrátí null, pokud nebyl záznam nalezen
    }

    public static function getCurrentUrl() {
        // Získání protokolu (http nebo https)
        $protocol = 'http';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $protocol = 'https';
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $protocol = $_SERVER['HTTP_X_FORWARDED_PROTO']; // Podpora pro proxy servery a load balancers
        }
    
        // Získání hostitele (domény)
        $host = $_SERVER['HTTP_HOST']; // Získá doménu, včetně portu, pokud není standardní
    
        // Získání cesty a query string
        $requestUri = $_SERVER['REQUEST_URI']; // Cesta a případné query parametry
    
        // Sestavení celé URL
        $url = $protocol . '://' . $host . $requestUri;
    
        return $url;
    }
    
}
?>