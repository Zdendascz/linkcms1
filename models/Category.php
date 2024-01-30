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
                           ->orderBy('order_cat', 'asc')
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

    public static function getAllCategoriesOrdered($siteId, $parentId = null, &$categoriesInfo = []) {
        $categories = self::where('site_id', $siteId)
                           ->where('parent_id', $parentId)
                           ->orderBy('order_cat', 'asc')
                           ->get();
        
        foreach ($categories as $category) {
            $categoryInfo = self::getCategoryInfo($category->id);
            if ($categoryInfo !== null) {
                $categoriesInfo[] = $categoryInfo;
                // Rekurzivní volání pro přidání potomků
                self::getAllCategoriesOrdered($siteId, $category->id, $categoriesInfo);
            }
        }
    
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
    
        // Kontrola a nastavení parent_id
        $category->parent_id = $data['parent_id'] == '0' ? null : $data['parent_id'];
    
        // Najděte nejvyšší hodnotu order_cat pro dané parent_id
        $highestOrderCat = self::where('parent_id', $category->parent_id)->max('order_cat');
    
        // Nastavení order_cat
        $category->order_cat = $highestOrderCat + 1;
    
        $category->is_active = $data['is_active'] ?? 1;
        $category->site_id = $data['site_id'];
        $category->url = $data['url'] ?? null;
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
    
    /**
     * getUrlsWithTitle
     * 
     * Tato metoda předpokládá, že existují modely Url, Article a Category 
     * s odpovídajícími metodami pro načtení dat z databáze. Dále se předpokládá, 
     * že každý model má atributy odpovídající sloupcům v jejich databázových 
     * tabulkách. Metoda getUrlsWithTitle načte URL, zjistí jejich model a podle 
     * toho vyhledá titulky článků nebo kategorií, které pak vrátí v seřazeném poli.
     *
     * @param  mixed $domain
     * @return void
     */
    public static function getUrlsWithTitle($domain) {
        // Načtení všech URL pro zadaný domain
        $urls = Url::where('domain', $domain)->get();
        $urlsWithTitle = [];
    
        foreach ($urls as $url) {
            $fullUrl = "https://".$url->domain . $url->url;
    
            if ($url->model == 'article') {
                $article = Article::find($url->model_id);
                $title = $article ? $article->title : $fullUrl;
            } elseif ($url->model == 'category') {
                $category = Category::find($url->model_id);
                $title = $category ? $category->title : $fullUrl;
            } else {
                $title = $fullUrl;  // Nastavíme výchozí hodnotu na plnou URL
            }
    
            $urlsWithTitle[] = [
                'url' => $fullUrl,
                'title' => $title
            ];
        }
    
        // Seřazení pole podle title
        usort($urlsWithTitle, function ($a, $b) {
            return strcmp($a['title'], $b['title']);
        });
    
        return $urlsWithTitle;
    }

    /**
     * Aktualizuje hierarchii a pořadí kategorií.
     *
     * @param array $categoriesData Pole s daty kategorií.
     * @return array Vrací pole se status a zprávou o výsledku operace.
     */
    public function updateCategoryHierarchy($categoriesData) {
        try {
            // Zámek transakce pro konzistenci dat
            DB::beginTransaction();
    
            foreach ($categoriesData as $categoryData) {
                $categoryId = $categoryData['id'];
                $newParentId = $categoryData['parent_id'] ?? null;
                $newOrderCat = $categoryData['order_cat'];
    
                $category = Category::find($categoryId);
                if (!$category) {
                    throw new Exception("Kategorie s ID $categoryId nebyla nalezena.");
                }
    
                // Zjištění, zda došlo ke změně parent_id nebo order_cat
                $parentChanged = $category->parent_id != $newParentId;
                $orderChanged = $category->order_cat != $newOrderCat;
    
                if ($parentChanged) {
                    // Aktualizace order_cat pro všechny sourozence ve staré i nové rodině
                    $this->updateSiblingOrderCat($category->parent_id);
                    $this->updateSiblingOrderCat($newParentId);
                } else if ($orderChanged) {
                    // Aktualizace order_cat pouze v rámci téže rodiny
                    $this->updateSiblingOrderCat($category->parent_id);
                }
    
                // Aktualizace aktuální kategorie
                $category->parent_id = $newParentId;
                $category->order_cat = $newOrderCat;
                $category->save();
            }
    
            DB::commit();
            return ['status' => true, 'message' => 'Kategorie byly úspěšně aktualizovány.'];
        } catch (Exception $e) {
            DB::rollback();
            return ['status' => false, 'message' => 'Chyba při aktualizaci kategorií: ' . $e->getMessage()];
        }
    }
    
    private function updateSiblingOrderCat($parentId) {
        $siblings = Category::where('parent_id', $parentId)->orderBy('order_cat')->get();
        foreach ($siblings as $index => $sibling) {
            $sibling->order_cat = $index + 1; // Nové pořadí
            $sibling->save();
        }
    }
}
?>