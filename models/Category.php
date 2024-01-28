<?php
namespace linkcms1\Models;

use Illuminate\Database\Eloquent\Model;
use Tracy\Debugger;

class Category extends Model
{

    protected $table = 'categories'; // Název tabulky v databázi

    protected $fillable = [
        'title',
        'display_name',
        'top_text',
        'bottom_text',
        'meta',
        'parent_id',
        'is_active',
        'site_id',
        'order_cat',
        'url',
        'css'
    ];

    protected $casts = [
        'meta' => 'array'
    ];

    public function parentCategory() {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function childCategories() {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function site() {
        // Předpokládá, že existuje model Site
        return $this->belongsTo(Site::class, 'site_id');
    }

    /**
     * Generuje HTML navigaci pro kategorie
     *
     * @param int $siteId ID stránky
     * @param int|null $parentId ID nadřazené kategorie, nebo null pro kořenové kategorie
     * @return string HTML navigace
     */
    public static function generateNavigation($siteId, $parentId = null) {
        $html = '<ul>';
    
        $categories = self::where('parent_id', $parentId)
                           ->where('site_id', $siteId)
                           ->where('is_active', 1)
                           ->orderBy('order_cat', 'desc')
                           ->get();
    
        foreach ($categories as $category) {
            // Dekódování JSON a příprava atributů
            $css = json_decode($category->css_cat, true) ?? [];
            $aAttributes = self::prepareAttributes($css, 'a');
            $liAttributes = self::prepareAttributes($css, 'li');
    
            // Vytvoření elementů s případnými atributy
            $html .= '<li ' . $liAttributes . '>';
            $html .= !empty($category->url) ? '<a href="' . htmlspecialchars($category->url) . '" ' . $aAttributes . '>' . htmlspecialchars($category->title) . '</a>' : htmlspecialchars($category->title);
    
            if (self::where('parent_id', $category->id)->where('site_id', $siteId)->where('is_active', 1)->exists()) {
                $childHtml = self::generateNavigation($siteId, $category->id);
                $html .= $childHtml;
            }
    
            $html .= '</li>';
        }
    
        $html .= '</ul>';
        return $html;
    }
    
    private static function prepareAttributes($css, $tag) {
        $attributes = '';
        if (isset($css[$tag . '_class']) && $css[$tag . '_class'] != '') {
            $attributes .= 'class="' . htmlspecialchars($css[$tag . '_class']) . '" ';
        }
        if (isset($css[$tag . '_id']) && $css[$tag . '_id'] != '') {
            $attributes .= 'id="' . htmlspecialchars($css[$tag . '_id']) . '" ';
        }
        if (isset($css[$tag . '_style']) && $css[$tag . '_style'] != '') {
            $attributes .= 'style="' . htmlspecialchars($css[$tag . '_style']) . '" ';
        }
        return $attributes;
    }

    /**
     * Získá aktivní články v kategorii.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function articles($category_id)
    {
        //Debugger::barDump($category_id,'ID kategorie');
        return ArticleCategory::where('category_id', $category_id)
            ->join('articles', 'article_categories.article_id', '=', 'articles.id')
            ->join('urls', 'urls.model_id', '=', 'articles.id')
            ->where('articles.status', 'active')
            ->where('urls.model', 'articles')
            ->get();
            
    }

    /**
     * Získá detail konkrétního článku
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function articleDetail($article_id)
    {
        //Debugger::barDump($category_id,'ID kategorie');
        return ArticleCategory::where('articles.id', $article_id)
            ->join('articles', 'article_categories.article_id', '=', 'articles.id')
            ->join('urls', 'urls.model_id', '=', 'articles.id')
            ->where('articles.status', 'active')
            ->where('urls.model', 'articles')
            ->get();
            
    }


    /**
     * Vrací celou cestu kategorie jako pole kategorií.
     *
     * @return array
     */
    public function getPath()
    {
        $path = [];
        $category = $this;

        while ($category) {
            array_unshift($path, $category); // Přidá kategorii na začátek pole
            $category = $category->parentCategory; // Přechod k nadřazené kategorii
        }

        return $path;
    }

    /**
     * Vrací informace o kategorii a její cestu.
     *
     * @param int $id ID kategorie
     * @return array
     */
    public static function getCategoryInfo($id)
    {
        $category = self::find($id);
        if (!$category) {
            return null; // nebo vyvolání výjimky
        }

        $path = $category->getPath(); // Získání cesty kategorie
        $pathInfo = [];

        foreach ($path as $cat) {
            $pathInfo[] = [
                'id' => $cat->id,
                'title' => $cat->title,
                'display_name' => $cat->display_name,
                'top_text' => $cat->top_text,
                'bottom_text' => $cat->bottom_text,
                'url' => $cat->url,
                'meta' => $cat->meta

                
                // lze přidat další požadované atributy
            ];
        }

        return [
            'categoryInfo' => [
                'id' => $category->id,
                'title' => $category->title,
                'display_name' => $category->display_name,
                'top_text' => $category->top_text,
                'bottom_text' => $category->bottom_text,
                'url' => $category->url,
                'meta' => $category->meta
                // přidejte další požadované atributy
            ],
            'pathInfo' => $pathInfo
        ];
    }

    public function categories(){

    }

    /**
     * Vrací strukturované informace o všech kategoriích pro dané site_id.
     *
     * @param int|null $siteId ID webu (site) nebo null pro všechny webů.
     * @return array
     */
    public static function getAllCategoriesInfo($siteId = null) {
        $query = self::query();

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        $categories = $query->get();
        $categoriesInfo = [];

        foreach ($categories as $category) {
            $path = $category->getPath(); // Získání cesty kategorie
            $pathInfo = [];

            foreach ($path as $cat) {
                $pathInfo[] = [
                    'id' => $cat->id,
                    'title' => $cat->title,
                    'display_name' => $cat->display_name,
                    // přidat další požadované atributy
                ];
            }

            $meta = is_string($category->meta) ? json_decode($category->meta, true) : $category->meta; // Dekódování JSON pole 'meta' nebo přímé použití pole
            $css_cat = is_string($category->css_cat) ? json_decode($category->css_cat, true) : $category->css_cat; // Dekódování JSON pole 'css_cat' nebo přímé použití pole
    

            $categoriesInfo[] = [
                'categoryInfo' => [
                    'id' => $category->id,
                    'title' => $category->title,
                    'display_name' => $category->display_name,
                    'top_text' => $category->top_text,
                    'bottom_text' => $category->bottom_text,
                    'meta' => $meta,
                    'parent_id' => $category->parent_id,
                    'is_active' => $category->is_active,
                    'site_id' => $category->site_id,
                    'order_cat' => $category->order_cat,
                    'url' => $category->url,
                    'css_cat' => $css_cat,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                    'deleted_at' => $category->deleted_at
                    // přidat další požadované atributy
                ],
                'pathInfo' => $pathInfo
            ];
        }

        // Seřazení podle 'order_cat' a závislostí kategorií
        usort($categoriesInfo, function($a, $b) {
            return $a['categoryInfo']['order_cat'] <=> $b['categoryInfo']['order_cat'];
        });

        return $categoriesInfo;
    } 
    
    /**
     * Uloží nebo upraví kategorii na základě poskytnutých dat.
     *
     * @param array $data Data kategorie k uložení nebo aktualizaci.
     * @return array Vrací pole se status a zprávou o výsledku operace.
     */
    public static function saveOrUpdate($data) {
        // Kontrola, zda je nastaveno ID a není prázdné
        if (isset($data['id']) && !empty($data['id'])) {
            // Aktualizace existující kategorie
            $category = self::find($data['id']);
    
            if (!$category) {
                // Kategorie s daným ID neexistuje
                return ['status' => false, 'message' => 'Kategorie nebyla nalezena.'];
            }
        } else {
            // Vytvoření nové kategorie
            $category = new self();
        }
    
        // Nastavení hodnot kategorie
        $category->title = $data['title'];
        $category->display_name = $data['display_name'];
        $category->top_text = $data['top_text'] ?? null;
        $category->bottom_text = $data['bottom_text'] ?? null;
        $category->meta = json_encode([
            'title' => $data['meta_title'] ?? '',
            'description' => $data['meta_description'] ?? '',
            'keywords' => $data['meta_keywords'] ?? ''
        ]);
        $category->parent_id = $data['parent_id'] ?? null;
        $category->is_active = $data['is_active'] ?? 1;
        $category->site_id = $data['site_id'];
        $category->order_cat = $data['order_cat'] ?? 0;
        $category->url = $data['url'];
        $category->css_cat = json_encode([
            'a_class' => $data['a_class'] ?? '',
            'a_id' => $data['a_id'] ?? '',
            'a_style' => $data['a_style'] ?? '',
            'li_class' => $data['li_class'] ?? '',
            'li_id' => $data['li_id'] ?? '',
            'li_style' => $data['li_style'] ?? ''
        ]);
    
        // Uložení kategorie
        if ($category->save()) {
            return ['status' => true, 'message' => 'Kategorie byla úspěšně uložena.'];
        } else {
            return ['status' => false, 'message' => 'Nepodařilo se uložit kategorii.'];
        }
    }

    /**
     * Handler pro uložení nebo aktualizaci kategorie.
     */
    public function handleSaveOrUpdateCategory() {
        // Kontrola, zda byla data odeslána metodou POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Zpracování dat formuláře
            $postData = $_POST;

            // Případná další validace dat zde

            // Volání metody pro uložení nebo aktualizaci kategorie
            $result = Category::saveOrUpdate($postData);

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
    // Další metody a logika pro model mohou být zde
}
?>