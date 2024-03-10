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
        $povoleneKlice = ['navigation_id']; // Příklad povolených klíčů
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
}
?>