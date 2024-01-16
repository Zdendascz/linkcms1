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
    Debugger::barDump($category_id,'ID kategorie');
    return ArticleCategory::where('category_id', $category_id)
        ->join('articles', 'article_categories.article_id', '=', 'articles.id')
        ->where('articles.status', 'active')
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
    // Další metody a logika pro model mohou být zde
}
?>