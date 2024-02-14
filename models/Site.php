<?php
namespace linkcms1\Models;

use Illuminate\Database\Eloquent\Model;

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
    
    
    public static function saveOrUpdate($data) {
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
}
?>