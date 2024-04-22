<?php
namespace linkcms1\Models;

use Illuminate\Database\Eloquent\Model;
use Tracy\Debugger; // Importování třídy Debugger z Tracy
use Monolog\Logger; // Importování třídy Logger z Monologu
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Illuminate\Database\Capsule\Manager as Capsule;

class Navigation extends Model
{
    protected $table = 'navigations';

    // Nastavení, které sloupce mohou být hromadně přiřazeny
    protected $fillable = ['name', 'description'];

    // Ve výchozím nastavení Eloquent očekává sloupce timestamps (created_at a updated_at).
    // Pokud vaše tabulka tato pole nemá, měli byste zakázat jejich automatické spravování nastavením na false.
    public $timestamps = false;

    // Definice vztahu k kategoriím
    public function categories()
    {
        return $this->hasMany(Category::class, 'navigation_id');
    }

    public static function saveOrUpdateNavigation($data) {
        // Kontrola, zda je nastaveno ID a není prázdné
        if (isset($data['id']) && !empty($data['id'])) {
            // Aktualizace existující navigace
            $navigation = Navigation::find($data['id']);
            if (!$navigation) {
                // Případ, kdy navigace s daným ID nebyla nalezena
                return ['status' => false, 'message' => 'Navigace nebyla nalezena.'];
            }
        } else {
            // Vytvoření nové navigace
            $navigation = new Navigation();
        }
    
        // Nastavení hodnot pro navigaci
        $navigation->name = $data['name'];
        $navigation->description = $data['description'] ?? null;
        $navigation->site_id = $_SERVER["SITE_ID"];
        $navigation->ul_class = $data["ul_class"];
        $navigation->ul_id = $data["ul_id"];
        $navigation->ul_style = $data["ul_style"];
    
        // Uložení navigace
        if ($navigation->save()) {
            return ['status' => true, 'message' => 'Navigace byla úspěšně uložena.'];
        } else {
            // Zde můžete přidat logování chyby
            return ['status' => false, 'message' => 'Uložení navigace se nezdařilo.'];
        }
    }

    public function handleSaveOrUpdateNavigation() {
        // Kontrola, zda byla data odeslána metodou POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Zpracování dat formuláře
            $postData = $_POST;
    
            // Případná další validace dat zde
    
            // Volání metody pro uložení nebo aktualizaci navigace
            $result = Navigation::saveOrUpdateNavigation($postData);
    
            if ($result['status']) {
                // Úspěch: Přesměrování s úspěšnou zprávou
                header('Location: ' . $_SERVER['HTTP_REFERER'] . '?status=success&message=' . urlencode($result['message']));
                exit;
            } else {
                // Neúspěch: Přesměrování s chybovou zprávou
                // Zde předpokládáme, že máte instanci loggeru k dispozici
                $this->logger->warning('Ukládání navigace selhalo: ' . $result['message']);
                header('Location: ' . $_SERVER['HTTP_REFERER'] . '?status=error&message=' . urlencode($result['message']));
                exit;
            }
        } else {
            // Pokud data nebyla odeslána metodou POST, přesměrování zpět s chybovou zprávou
            // Zde předpokládáme, že máte instanci loggeru k dispozici
            $this->logger->warning('Neplatný požadavek pro volání metody handleSaveOrUpdateNavigation');
            header('Location: ' . $_SERVER['HTTP_REFERER'] . '?status=error&message=' . urlencode('Neplatný požadavek'));
            exit;
        }
    }

    /**
     * Vrátí všechny navigace odpovídající dané site_id.
     *
     * @param int $siteId ID webu, pro který se mají navigace načíst
     * @return Collection|static[] Seznam navigací pro dané site_id
     */
    public static function getNavigationsBySiteId($siteId)
    {
        return self::where('site_id', $siteId)->get()->toArray();
    }
}


?>