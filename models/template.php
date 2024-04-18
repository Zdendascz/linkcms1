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

class Template extends Model
{
    use HasFactory;

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
                    'key' => $formData['variable_key'][$index],
                    'label' => $formData['variable_label'][$index],
                    'default' => $formData['variable_default'][$index],
                    'type' => $formData['variable_type'][$index],
                    'role' => $formData['variable_role'][$index]
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
}
?>